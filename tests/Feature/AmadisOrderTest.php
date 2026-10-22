<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AmadisOrderTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 3, string $price = '125000.00'): Product
    {
        $category = Category::create(['cat_title' => 'Amadis']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Amadis', 'product_slug' => 'amadis-'.uniqid(), 'product_price' => $price,
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock]);

        return $product->fresh();
    }

    private function address(User $user): Address
    {
        return Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
    }

    public function test_checkout_reprices_and_snapshots_then_replays_without_double_stock(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2, 'price' => 1])->assertOk();
        DB::table('products')->where('product_id', $product->product_id)->update(['product_price' => 150000]);
        $payload = ['address_id' => $address->id, 'idempotency_key' => 'checkout-1'];
        $first = $this->postJson('/checkout', $payload)->assertCreated()->json('order');
        $this->assertSame(300000, $first['total']);
        $this->assertSame(150000, $first['items'][0]['unit_price']);
        $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $first['id']);
        $this->assertSame(1, (int) $product->fresh()->stock);
        $address->update(['line1' => 'Alamat baru']);
        $this->assertSame('Jalan Sutra 1', Order::first()->shipping_address['line1']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_late_duplicate_lookup_replays_before_stock_or_voucher_validation(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product(1);
        $cart = [$product->product_id => 1];
        [$first] = app(CheckoutService::class)->checkout($user, $address, $cart, 'late-duplicate', null);

        // Simulasi worker kedua: lookup awal sudah lewat saat worker pertama commit.
        $place = new \ReflectionMethod(CheckoutService::class, 'place');
        $replayed = DB::transaction(fn () => $place->invoke(
            app(CheckoutService::class), $user, $address, $cart,
            'late-duplicate', $first->payload_hash, null
        ));

        $this->assertSame($first->id, $replayed->id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(0, (int) $product->fresh()->stock);
    }

    public function test_late_duplicate_with_changed_payload_is_rejected_before_mutation(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $otherAddress = $this->address($user);
        $product = $this->product(2);
        $service = app(CheckoutService::class);
        [$first] = $service->checkout($user, $address, [$product->product_id => 1], 'late-conflict', null);

        $place = new \ReflectionMethod($service, 'place');
        try {
            DB::transaction(fn () => $place->invoke(
                $service, $user, $otherAddress, [$product->product_id => 1],
                'late-conflict', $first->payload_hash, null
            ));
            $this->fail('Expected changed payload conflict.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_changed_payload_cannot_reuse_key(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'same-key'])->assertCreated();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();
        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'same-key'])->assertStatus(409);
        $this->assertSame(2, (int) $product->fresh()->stock);
    }

    public function test_empty_cart_replay_rejects_changed_address_or_voucher(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $otherAddress = $this->address($user);
        $product = $this->product();
        Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 5]);
        Voucher::create(['code' => 'LINEN', 'discount_amount' => 5000, 'quota' => 5]);
        $payload = ['address_id' => $address->id, 'idempotency_key' => 'empty-replay', 'voucher_code' => 'SUTRA'];
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', $payload)->assertCreated()->json('order.id');
        $this->assertSame([], session('cart', []));

        $this->postJson('/checkout', ['address_id' => $otherAddress->id] + $payload)->assertStatus(409);
        $this->postJson('/checkout', ['voucher_code' => 'LINEN'] + $payload)->assertStatus(409);
        $this->postJson('/checkout', ['voucher_code' => null] + $payload)->assertStatus(409);
        $this->postJson('/checkout', ['voucher_code' => ' sutra '] + $payload)->assertOk()->assertJsonPath('order.id', $id);

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, (int) $product->fresh()->stock);
    }

    /** Inject before stock writes, or after stock/voucher claims to prove whole-checkout rollback. */
    private function failStockWrites(int $failures, int $code = 5, bool $afterClaims = false): object
    {
        $state = (object) ['remaining' => $failures, 'seen' => 0, 'attempts' => []];
        $connection = DB::connection();
        $target = $afterClaims
            ? 'insert into '.$connection->getQueryGrammar()->wrapTable('orders').' '
            : 'update '.$connection->getQueryGrammar()->wrapTable('products').' ';
        $connection->beforeExecuting(function (string $sql) use ($state, $code, $afterClaims, $connection, $target) {
            if (! str_starts_with($sql, $target)) {
                return;
            }
            if ($afterClaims) {
                $state->attempts[] = [
                    'level' => $connection->transactionLevel(),
                    'physical' => $connection->getPdo()->inTransaction(),
                    'stock' => $connection->table('products')->orderBy('product_id')->pluck('stock')->all(),
                    'voucher_used' => $connection->table('vouchers')->orderBy('id')->pluck('used')->all(),
                    'orders' => $connection->table('orders')->count(),
                    'items' => $connection->table('order_items')->count(),
                ];
            }
            if ($state->remaining > 0) {
                $state->remaining--;
                $state->seen++;
                $msg = $code === 5 ? 'database is locked' : 'general error';
                $pdo = new \PDOException($msg);
                $pdo->errorInfo = ['HY000', $code, $msg];
                throw new QueryException('sqlite', $sql, [], $pdo);
            }
        });

        return $state;
    }

    /** Separate physical SQLite owner; never commit/rollback the suite's RefreshDatabase transaction. */
    private function withPhysicalSqliteCheckout(callable $scenario): void
    {
        $this->requireSqliteLockSimulation();
        $name = 'amadis_sqlite_lock_test';
        $originalName = DB::getDefaultConnection();
        $originalConnection = DB::connection($originalName);
        $originalPdo = $originalConnection->getPdo();
        $originalLevel = $originalConnection->transactionLevel();
        $originalSchema = Schema::getFacadeRoot();
        $connections = config('database.connections');
        $this->assertFalse(array_key_exists($name, $connections), 'Reserved SQLite fixture connection must not already exist.');
        $this->assertSame(1, $originalLevel);
        $this->assertTrue($originalPdo->inTransaction());
        config(['database.connections.'.$name => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        try {
            DB::setDefaultConnection($name);
            $isolated = DB::connection($name);
            $this->assertSame('sqlite', $isolated->getDriverName());
            $this->assertSame(':memory:', $isolated->getDatabaseName());
            $this->assertNotSame($originalPdo, $isolated->getPdo());
            // Schema's cached facade must follow the real isolated connection during migrations.
            Schema::swap($isolated->getSchemaBuilder());
            $this->artisan('migrate', ['--database' => $name, '--force' => true])->assertExitCode(0);
            $this->assertSame($name, DB::getDefaultConnection());
            $this->assertSame(0, $isolated->transactionLevel());
            $this->assertFalse($isolated->getPdo()->inTransaction());
            Mail::fake(); // Real commits may dispatch outbox callbacks; never send mail from this fixture.
            $scenario();
            $this->assertSame(0, $isolated->transactionLevel());
            $this->assertFalse($isolated->getPdo()->inTransaction());
        } finally {
            DB::setDefaultConnection($originalName);
            Schema::swap($originalSchema);
            DB::purge($name);
            config(['database.connections' => $connections]);
        }
        $this->assertSame($originalPdo, $originalConnection->getPdo());
        $this->assertSame($originalLevel, $originalConnection->transactionLevel());
        $this->assertTrue($originalPdo->inTransaction());
    }

    private function requireSqliteLockSimulation(): void
    {
        // Simulates SQLite error code 5; MySQL lock semantics differ, so these are engine-specific, not all-engine proof.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Engine-specific SQLite lock retry simulation; not applicable to '.DB::connection()->getDriverName().'.');
        }
    }

    public function test_transient_sqlite_lock_is_retried_once_and_creates_single_order(): void
    {
        $this->withPhysicalSqliteCheckout(function (): void {
            $user = User::factory()->create();
            $address = $this->address($user);
            $product = $this->product();
            $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 5, 'per_user_limit' => 1]);
            $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
            $state = $this->failStockWrites(1, afterClaims: true);
            $payload = ['address_id' => $address->id, 'idempotency_key' => 'busy-once', 'voucher_code' => 'SUTRA'];
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());

            $id = $this->postJson('/checkout', $payload)->assertCreated()->json('order.id');
            $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);

            $this->assertSame(1, $state->seen);
            $attempt = ['level' => 2, 'physical' => true, 'stock' => [2], 'voucher_used' => [1], 'orders' => 0, 'items' => 0];
            $this->assertSame([$attempt, $attempt], $state->attempts, 'Retry must roll back both claims and rerun the whole HTTP owner.');
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('order_items', 1);
            $this->assertDatabaseCount('inventory_reservations', 1);
            $this->assertDatabaseCount('cart_items', 0);
            $this->assertSame(2, (int) $product->fresh()->stock);
            $this->assertSame(1, (int) $voucher->fresh()->used);
        });
    }

    public function test_exhausted_sqlite_lock_returns_503_without_partial_state_and_key_stays_usable(): void
    {
        $this->withPhysicalSqliteCheckout(function (): void {
            $user = User::factory()->create();
            $address = $this->address($user);
            $product = $this->product();
            $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 5, 'per_user_limit' => 1]);
            $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
            $payload = ['address_id' => $address->id, 'idempotency_key' => 'busy-always', 'voucher_code' => 'SUTRA'];
            $state = $this->failStockWrites(PHP_INT_MAX, afterClaims: true);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());

            $this->postJson('/checkout', $payload)->assertStatus(503);

            $this->assertSame(3, $state->seen); // HTTP owner: three total attempts, not four inner-service attempts.
            $attempt = ['level' => 2, 'physical' => true, 'stock' => [2], 'voucher_used' => [1], 'orders' => 0, 'items' => 0];
            $this->assertSame([$attempt, $attempt, $attempt], $state->attempts);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_items', 0);
            $this->assertDatabaseCount('inventory_reservations', 0);
            $this->assertSame(3, (int) $product->fresh()->stock);
            $this->assertSame(0, (int) $voucher->fresh()->used);
            $this->assertSame([$product->product_id => 1], session('cart'));
            $cartId = DB::table('carts')->where('user_id', $user->id)->value('id');
            $this->assertNotNull($cartId);
            $this->assertSame([$product->product_id => 1], DB::table('cart_items')->where('cart_id', $cartId)->pluck('quantity', 'product_id')->all());

            $state->remaining = 0;
            $id = $this->postJson('/checkout', $payload)->assertCreated()->json('order.id');
            $this->postJson('/checkout', $payload)->assertOk()->assertJsonPath('order.id', $id);
            $this->assertSame(3, $state->seen);
            $this->assertSame([$attempt, $attempt, $attempt, $attempt], $state->attempts);
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('order_items', 1);
            $this->assertDatabaseCount('inventory_reservations', 1);
            $this->assertDatabaseCount('cart_items', 0);
            $this->assertSame(2, (int) $product->fresh()->stock);
            $this->assertSame(1, (int) $voucher->fresh()->used);
        });
    }

    public function test_non_transient_query_error_is_not_retried(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $state = $this->failStockWrites(PHP_INT_MAX, 1);

        $this->withoutExceptionHandling();
        try {
            $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'hard-error']);
            $this->fail('Expected non-transient QueryException.');
        } catch (QueryException|DeadlockException) {
        }

        $this->assertSame(1, $state->seen);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public static function invalidCartItems(): array
    {
        return [
            'negative' => [-1, false],
            'zero' => [0, false],
            'fractional' => [1.5, false],
            'excessive' => [100, false],
            'array' => [[1], false],
            'boolean' => [true, false],
            'malformed id' => [1, true],
        ];
    }

    #[DataProvider('invalidCartItems')]
    public function test_invalid_session_cart_cannot_place_or_replay_order(mixed $quantity, bool $malformedId): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product(5);
        $validCart = [$product->product_id => 1];
        $this->actingAs($user)->withSession(['cart' => $validCart])
            ->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'valid-key'])
            ->assertCreated();

        $invalidCart = [$malformedId ? 'bad-id' : $product->product_id => $quantity];
        foreach (['new-key-1', 'valid-key'] as $key) {
            $this->withSession(['cart' => $invalidCart])
                ->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => $key])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('cart');
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(4, (int) $product->fresh()->stock);
        }
    }

    public function test_stock_failure_rolls_back_order_and_voucher(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 1]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertOk();
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 1]);
        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'stock-fail', 'voucher_code' => 'SUTRA'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, (int) $voucher->fresh()->used);
        $this->assertSame(1, (int) $product->fresh()->stock);
    }

    public function test_voucher_global_and_per_user_quotas(): void
    {
        $user = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product(5);
        $voucher = Voucher::create(['code' => 'SUTRA', 'discount_amount' => 10000, 'quota' => 2, 'per_user_limit' => 1]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'voucher-1', 'voucher_code' => 'SUTRA'])->assertCreated()->assertJsonPath('order.total', 115000);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'voucher-2', 'voucher_code' => 'SUTRA'])->assertUnprocessable();
        $other = User::factory()->create();
        $otherAddress = $this->address($other);
        $this->actingAs($other)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $this->postJson('/checkout', ['address_id' => $otherAddress->id, 'idempotency_key' => 'voucher-3', 'voucher_code' => 'SUTRA'])->assertCreated();
        $third = User::factory()->create();
        $thirdAddress = $this->address($third);
        $this->actingAs($third)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $this->postJson('/checkout', ['address_id' => $thirdAddress->id, 'idempotency_key' => 'voucher-4', 'voucher_code' => 'SUTRA'])->assertUnprocessable();
        $this->assertSame(2, (int) $voucher->fresh()->used);
    }

    public function test_owner_boundaries_and_cancel_restores_stock_once(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $address = $this->address($user);
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $this->postJson('/checkout', ['address_id' => $this->address($other)->id, 'idempotency_key' => 'foreign-address'])->assertNotFound();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'owned-order'])->assertCreated()->json('order.id');
        $this->actingAs($other)->getJson('/orders/'.$id)->assertNotFound();
        $this->postJson('/orders/'.$id.'/cancel')->assertNotFound();
        $this->patchJson('/addresses/'.$address->id, ['label' => 'Stolen'])->assertNotFound();
        $this->deleteJson('/addresses/'.$address->id)->assertNotFound();
        $this->actingAs($user)->postJson('/orders/'.$id.'/cancel')->assertOk();
        $this->postJson('/orders/'.$id.'/cancel')->assertOk();
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_only_admin_can_transition_and_completed_owner_can_review_once(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1]);
        $id = $this->postJson('/checkout', ['address_id' => $this->address($user)->id, 'idempotency_key' => 'review-order'])->json('order.id');
        $review = ['order_id' => $id, 'product_id' => $product->product_id, 'rating' => 5, 'body' => 'Sangat baik.'];
        $this->postJson('/reviews', $review)->assertUnprocessable();
        $this->patchJson('/admin/orders/'.$id, ['status' => 'completed'])->assertUnauthorized();
        $admin = Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
        $this->actingAs($admin, 'admin')->patchJson('/admin/orders/'.$id, ['status' => 'completed'])->assertUnprocessable();
        $this->patchJson('/admin/orders/'.$id, ['status' => 'paid'])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $id, 'status' => 'pending']);
        $this->actingAs($user)->postJson('/reviews', $review)->assertUnprocessable();
        // Historical completed fixture tests reviews without pretending a payment was verified.
        Order::findOrFail($id)->update(['status' => 'completed']);
        $this->postJson('/reviews', $review)->assertCreated();
        $this->postJson('/reviews', $review)->assertUnprocessable();
        $this->postJson('/orders/'.$id.'/cancel')->assertUnprocessable();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson('/reviews', $review)->assertNotFound();
    }

    public function test_favorites_are_owner_scoped_and_idempotent(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())->postJson('/favorites', ['product_id' => $product->product_id])->assertOk();
        $this->postJson('/favorites', ['product_id' => $product->product_id])->assertOk();
        $this->assertDatabaseCount('favorites', 1);
        $this->actingAs(User::factory()->create())->getJson('/favorites')->assertJsonCount(0, 'favorites');
        $this->deleteJson('/favorites/'.$product->product_id)->assertOk();
        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_cart_validation_and_guest_checkout(): void
    {
        $product = $this->product(1);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 0])->assertUnprocessable();
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 2])->assertUnprocessable();
        $this->postJson('/checkout', ['address_id' => 1, 'idempotency_key' => 'guest'])->assertUnprocessable();
        DB::table('products')->where('product_id', $product->product_id)->update(['is_active' => false]);
        $this->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertUnprocessable();
    }
}
