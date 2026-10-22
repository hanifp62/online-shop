<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartStore;
use App\Services\CheckoutService;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MysqlCheckoutRetryTest extends TestCase
{
    private ?object $faultState = null;

    private function prepareCheckout(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/MariaDB fault injection requires the guarded InnoDB test schema.');
        }
        // RefreshDatabase would make CartStore a nested caller and suppress its retries.
        $connection = DB::connection();
        $this->assertSame(0, $connection->transactionLevel());
        $suffix = bin2hex(random_bytes(12));
        $user = User::factory()->make(['email' => 'mysql-retry-'.$suffix.'@example.test']);
        $category = new Category(['cat_title' => 'Atelier '.$suffix]);
        $product = new Product([
            'product_brand' => 'ZAFIRA', 'product_title' => 'Sutra',
            'product_slug' => 'sutra-'.$suffix, 'product_price' => 125000, 'stock' => 3,
        ]);
        $voucher = new Voucher([
            'code' => 'MYSQL-'.strtoupper($suffix), 'discount_amount' => 10000, 'quota' => 5, 'per_user_limit' => 1,
        ]);

        // Register before any insert; retained models also expose IDs after a failing created observer.
        $this->beforeApplicationDestroyed(function () use ($connection, $user, $category, $product, $voucher) {
            if ($this->faultState !== null) {
                $this->faultState->remaining = 0;
            }
            try {
                $connection->rollBack(0);
                // User deletion only nulls orders.user_id; delete owned orders first to cascade their children.
                if ($user->getKey() !== null) {
                    Order::where('user_id', $user->getKey())->delete();
                }
                // User cascades addresses/carts/profile backups; products must precede their category.
                foreach ([$user, $product, $category, $voucher] as $fixture) {
                    if ($fixture->getKey() !== null) {
                        $fixture->delete();
                    }
                }
            } finally {
                $connection->disconnect();
            }
        });

        $user->save();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
        $category->save();
        $product->product_cat = $category->cat_id;
        $product->save();
        $voucher->save();
        $this->actingAs($user);
        $this->postAtTopLevel('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();

        return [$user, $product, $voucher, [
            'address_id' => $address->id, 'idempotency_key' => 'mysql-retry-'.$suffix, 'voucher_code' => $voucher->code,
        ]];
    }

    private function postAtTopLevel(string $uri, array $data = []): TestResponse
    {
        $this->assertSame(0, DB::transactionLevel(), 'HTTP retry tests must start outside a transaction.');
        try {
            return $this->postJson($uri, $data);
        } finally {
            $this->assertSame(0, DB::transactionLevel(), 'HTTP requests must close their transaction, including on failure.');
        }
    }

    private function assertPersistentCart(User $user, array $items): void
    {
        $id = DB::table('carts')->where('user_id', $user->id)->value('id');
        $this->assertNotNull($id);
        $this->assertSame($items, CartStore::items((int) $id));
    }

    private function failOrderInserts(User $user, Product $product, Voucher $voucher, int $failures, int $driverCode = 1213): object
    {
        $state = (object) ['remaining' => $failures, 'seen' => 0, 'snapshots' => []];
        $this->faultState = $state;
        DB::connection()->beforeExecuting(function (string $sql) use ($state, $user, $product, $voucher, $driverCode) {
            if ($state->remaining > 0 && str_starts_with($sql, 'insert into `orders` ')) {
                $state->remaining--;
                $state->seen++;
                $state->snapshots[] = [
                    (int) $product->fresh()->stock,
                    (int) $voucher->fresh()->used,
                    Order::where('user_id', $user->id)->count(),
                    DB::table('order_items')->where('product_id', $product->product_id)->count(),
                ];
                // Realistic SQLSTATE/message exercises Laravel's wrapper, not a physical concurrent deadlock.
                $pdo = new class($driverCode) extends \PDOException
                {
                    public function __construct(int $driverCode)
                    {
                        $state = $driverCode === 1213 ? '40001' : 'HY000';
                        $message = $driverCode === 1213 ? 'Deadlock found when trying to get lock; try restarting transaction'
                            : 'Lock wait timeout exceeded; try restarting transaction';
                        parent::__construct('SQLSTATE['.$state.']: General error: '.$driverCode.' '.$message);
                        $this->code = $state;
                        $this->errorInfo = [$state, $driverCode, $message];
                    }
                };
                throw new QueryException('mysql', $sql, [], $pdo);
            }
        });

        return $state;
    }

    public function test_deadlock_retry_succeeds_replays_and_cancel_restores_stock_only_once(): void
    {
        [$user, $product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($user, $product, $voucher, 1);
        $id = $this->postAtTopLevel('/checkout', $payload)->assertCreated()->json('order.id');
        $this->assertSame(1, $state->seen);
        $this->assertSame([[2, 1, 0, 0]], $state->snapshots);
        $this->postAtTopLevel('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertSame(1, Order::where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('order_items')->where('product_id', $product->product_id)->count());
        $this->assertPersistentCart($user, []);
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $voucher->fresh()->used);
        // Stale copy simulates a concurrent cancel that loaded the order before the first committed.
        $stale = Order::where('user_id', $user->id)->with('items')->findOrFail($id);
        $this->postAtTopLevel('/orders/'.$id.'/cancel')->assertOk();
        try {
            app(CheckoutService::class)->transition($stale, 'cancelled');
            $this->fail('Expected stale concurrent cancel to conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->postAtTopLevel('/orders/'.$id.'/cancel')->assertOk(); // sequential retry stays idempotent
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
    }

    public function test_deadlock_exhaustion_returns_503_without_partial_writes_and_key_is_reusable(): void
    {
        [$user, $product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($user, $product, $voucher, PHP_INT_MAX);
        $this->postAtTopLevel('/checkout', $payload)->assertStatus(503);
        // CartStore owns the HTTP transaction: three total attempts, not CheckoutService's four.
        $this->assertSame(3, $state->seen);
        $this->assertSame([[2, 1, 0, 0], [2, 1, 0, 0], [2, 1, 0, 0]], $state->snapshots);
        $this->assertSame(0, Order::where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('order_items')->where('product_id', $product->product_id)->count());
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
        $this->assertSame([$product->product_id => 1], session('cart'));
        $this->assertPersistentCart($user, [$product->product_id => 1]);
        $state->remaining = 0;
        $id = $this->postAtTopLevel('/checkout', $payload)->assertCreated()->json('order.id');
        $this->postAtTopLevel('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertSame(1, Order::where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('order_items')->where('product_id', $product->product_id)->count());
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $voucher->fresh()->used);
        $this->assertPersistentCart($user, []);
    }

    public function test_lock_wait_timeout_is_not_classified_as_deadlock(): void
    {
        [$user, $product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($user, $product, $voucher, PHP_INT_MAX, 1205);
        $this->withoutExceptionHandling();
        try {
            $this->postAtTopLevel('/checkout', $payload);
            $this->fail('Expected timeout to propagate without retry.');
        } catch (QueryException|DeadlockException $exception) {
            $this->assertInstanceOf(DeadlockException::class, $exception, 'The nested checkout must exercise Laravel\'s wrapper.');
            $query = $exception instanceof DeadlockException ? $exception->getPrevious() : $exception;
            $this->assertInstanceOf(QueryException::class, $query);
            $this->assertSame('HY000', $query->getCode());
            $this->assertSame('HY000', $query->errorInfo[0]);
            $this->assertSame(1205, $query->errorInfo[1]);
        }
        $this->assertSame(1, $state->seen);
        $this->assertSame([[2, 1, 0, 0]], $state->snapshots);
        $this->assertSame(0, Order::where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('order_items')->where('product_id', $product->product_id)->count());
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
        $this->assertSame([$product->product_id => 1], session('cart'));
        $this->assertPersistentCart($user, [$product->product_id => 1]);
    }
}
