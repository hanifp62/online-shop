<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MysqlCheckoutRetryTest extends TestCase
{
    use RefreshDatabase;

    private function prepareCheckout(): array
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/MariaDB fault injection requires the guarded InnoDB test schema.');
        }
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
        $category = Category::create(['cat_title' => 'Atelier']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'sutra', 'product_price' => 125000,
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 3]);
        $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 5, 'per_user_limit' => 1]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();

        return [$product, $voucher, ['address_id' => $address->id, 'idempotency_key' => 'mysql-retry', 'voucher_code' => 'SUTRA']];
    }

    private function failOrderInserts(Product $product, Voucher $voucher, int $failures, int $driverCode = 1213): object
    {
        $state = (object) ['remaining' => $failures, 'seen' => 0, 'snapshots' => []];
        DB::connection()->beforeExecuting(function (string $sql) use ($state, $product, $voucher, $driverCode) {
            if ($state->remaining > 0 && str_starts_with($sql, 'insert into `orders` ')) {
                $state->remaining--;
                $state->seen++;
                $state->snapshots[] = [
                    (int) $product->fresh()->stock,
                    (int) $voucher->fresh()->used,
                    DB::table('orders')->count(),
                    DB::table('order_items')->count(),
                ];
                // Synthetic conflict after both writes; not evidence of real concurrent deadlock handling.
                $pdo = new \PDOException('Injected order insert conflict');
                $pdo->errorInfo = [$driverCode === 1213 ? '40001' : 'HY000', $driverCode, 'Injected order insert conflict'];
                throw new QueryException('mysql', $sql, [], $pdo);
            }
        });

        return $state;
    }

    public function test_deadlock_retry_succeeds_replays_and_cancel_restores_stock_only_once(): void
    {
        [$product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($product, $voucher, 1);
        $id = $this->postJson('/checkout', $payload)->assertCreated()->json('order.id');
        $this->assertSame(1, $state->seen);
        $this->assertSame([[2, 1, 0, 0]], $state->snapshots);
        $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $voucher->fresh()->used);
        // Stale copy simulates a concurrent cancel that loaded the order before the first committed.
        $stale = Order::with('items')->findOrFail($id);
        $this->postJson('/orders/'.$id.'/cancel')->assertOk();
        try {
            app(CheckoutService::class)->transition($stale, 'cancelled');
            $this->fail('Expected stale concurrent cancel to conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->postJson('/orders/'.$id.'/cancel')->assertOk(); // sequential retry stays idempotent
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
    }

    public function test_deadlock_exhaustion_returns_503_without_partial_writes_and_key_is_reusable(): void
    {
        [$product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($product, $voucher, PHP_INT_MAX);
        $this->postJson('/checkout', $payload)->assertStatus(503);
        $this->assertSame(4, $state->seen);
        $this->assertSame([[2, 1, 0, 0], [2, 1, 0, 0], [2, 1, 0, 0], [2, 1, 0, 0]], $state->snapshots);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
        $this->assertSame([$product->product_id => 1], session('cart'));
        $state->remaining = 0;
        $id = $this->postJson('/checkout', $payload)->assertCreated()->json('order.id');
        $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $voucher->fresh()->used);
    }

    public function test_lock_wait_timeout_is_not_classified_as_deadlock(): void
    {
        [$product, $voucher, $payload] = $this->prepareCheckout();
        $state = $this->failOrderInserts($product, $voucher, PHP_INT_MAX, 1205);
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/checkout', $payload);
            $this->fail('Expected timeout to propagate without retry.');
        } catch (QueryException $exception) {
            $this->assertSame(1205, $exception->errorInfo[1]);
        }
        $this->assertSame(1, $state->seen);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $voucher->fresh()->used);
    }
}
