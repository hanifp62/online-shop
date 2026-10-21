<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function product(int $stock = 5): Product
    {
        $category = Category::create(['cat_title' => 'Guest']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'sutra-'.uniqid(), 'product_price' => '125000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock]);

        return $product->fresh();
    }

    protected function payload(): array
    {
        return [
            'idempotency_key' => 'guest-key-123', 'customer_name' => 'Élodie',
            'customer_email' => 'ELODIE@example.test', 'customer_phone' => '+62 812-3456',
            'recipient_name' => 'Élodie', 'phone' => '+62 812-3456', 'label' => 'Rumah',
            'line1' => 'Jalan Sutra 1', 'line2' => null, 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ];
    }

    public function test_guest_snapshot_server_price_and_empty_cart_replay(): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->product_id => 2]]);
        $first = $this->postJson('/checkout', $this->payload())->assertCreated();
        $order = Order::firstOrFail();
        $this->assertNull($order->user_id);
        $this->assertSame('elodie@example.test', $order->customer_email);
        $this->assertSame('+628123456', $order->customer_phone);
        $this->assertSame('Jalan Sutra 1', $order->shipping_address['line1']);
        $this->assertSame(250000, $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertSame([], session('cart', []));
        $this->assertSame(hash('sha256', session('guest_checkout_scope')), $order->guest_scope_hash);
        $this->postJson('/checkout', $this->payload())->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('tracking_token', $first->json('tracking_token'));
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tracking_token_is_encrypted_in_session_and_recovered_on_retry(): void
    {
        $product = $this->product();
        $first = $this->withSession(['cart' => [$product->product_id => 1]])
            ->postJson('/checkout', $this->payload())->assertCreated();
        $token = $first->json('tracking_token');
        $session = session()->all();
        $this->assertNotContains($token, $session);
        $this->assertStringNotContainsString($token, json_encode($session));
        $this->postJson('/checkout', $this->payload())->assertOk()
            ->assertJsonPath('tracking_token', $token);
    }

    public function test_guest_checkout_route_serializes_same_session_requests(): void
    {
        $route = app('router')->getRoutes()->getByName('checkout.store');
        $this->assertSame(10, $route->locksFor());
        $this->assertSame(10, $route->waitsFor());
    }

    public function test_replay_token_expires_without_rotating_persisted_hash(): void
    {
        $product = $this->product();
        $first = $this->withSession(['cart' => [$product->product_id => 1]])
            ->postJson('/checkout', $this->payload())->assertCreated();
        $hash = Order::firstOrFail()->tracking_token_hash;
        // Ignore the first response as a client would after a network timeout.
        $this->postJson('/checkout', $this->payload())->assertOk()
            ->assertJsonPath('tracking_token', $first->json('tracking_token'));
        $this->travel(30)->minutes();
        $this->postJson('/checkout', $this->payload())->assertOk()
            ->assertJsonPath('tracking_token', null)
            ->assertJsonPath('tracking_recovery', 'unavailable')
            ->assertJsonPath('message', 'Kode pelacakan tidak dapat dipulihkan. Hubungi concierge dengan nomor pesanan #1.');
        $this->assertSame($hash, Order::firstOrFail()->tracking_token_hash);
        $this->assertDatabaseCount('orders', 1);
        $this->travelBack();
    }

    public function test_guest_migration_down_refuses_without_changing_guest_data(): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', $this->payload())->assertCreated();
        $migration = require database_path('migrations/2026_10_19_000001_add_guest_checkout_to_orders_table.php');
        try {
            $migration->down();
            $this->fail('Expected rollback refusal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot roll back guest checkout while orders with null user_id exist.', $exception->getMessage());
        }
        $this->assertNotNull(Order::firstOrFail()->tracking_token_hash);
    }

    public function test_guest_migration_down_and_up_without_guest_rows(): void
    {
        $migration = require database_path('migrations/2026_10_19_000001_add_guest_checkout_to_orders_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'guest_scope_hash'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'guest_scope_hash'));
    }

    public static function invalidInput(): array
    {
        return [
            'name missing' => ['customer_name', null], 'email malformed' => ['customer_email', 'bad'],
            'phone array' => ['customer_phone', []], 'address empty' => ['line1', ''],
            'postal too long' => ['postal_code', str_repeat('1', 21)],
            'voucher' => ['voucher_code', 'FREE'], 'address id' => ['address_id', 1],
            'user id' => ['user_id', 1], 'price' => ['price', 1], 'total' => ['total', 1],
            'status' => ['status', 'paid'], 'phone invalid' => ['phone', 'abc'],
        ];
    }

    #[DataProvider('invalidInput')]
    public function test_invalid_guest_input_never_mutates_stock(string $field, mixed $value): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->product_id => 1]])
            ->postJson('/checkout', [$field => $value] + $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(5, (int) $product->fresh()->stock);
    }

    public function test_changed_contact_address_or_cart_conflicts_and_voucher_rejected_on_replay(): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', $this->payload())->assertCreated();
        foreach (['customer_email' => 'other@example.test', 'line1' => 'Other road'] as $field => $value) {
            $this->postJson('/checkout', [$field => $value] + $this->payload())->assertConflict();
        }
        $this->postJson('/checkout', ['voucher_code' => 'FREE'] + $this->payload())->assertUnprocessable();
        $this->withSession(['cart' => [$product->product_id => 2]])->postJson('/checkout', $this->payload())->assertConflict();
        $this->assertSame(4, (int) $product->fresh()->stock);
    }

    public function test_independent_scopes_can_use_same_key_but_cannot_recover_other_token(): void
    {
        $product = $this->product();
        $first = $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', $this->payload())->assertCreated();
        session()->flush();
        $this->postJson('/checkout', $this->payload())->assertUnprocessable();
        $second = $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', $this->payload())->assertCreated();
        $this->assertNotSame($first->json('tracking_token'), $second->json('tracking_token'));
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame(3, (int) $product->fresh()->stock);
    }

    public function test_empty_inactive_and_short_stock_carts_fail_atomically(): void
    {
        $first = $this->product();
        $second = $this->product(0);
        $this->postJson('/checkout', $this->payload())->assertUnprocessable();
        $this->withSession(['cart' => [$first->product_id => 1, $second->product_id => 1]])
            ->postJson('/checkout', $this->payload())->assertUnprocessable();
        $this->assertSame(5, (int) $first->fresh()->stock);
        DB::table('products')->where('product_id', $first->product_id)->update(['is_active' => false]);
        $this->withSession(['cart' => [$first->product_id => 1]])->postJson('/checkout', $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_account_endpoints_cannot_read_or_cancel_guest_order(): void
    {
        $product = $this->product();
        $id = $this->withSession(['cart' => [$product->product_id => 1]])->postJson('/checkout', $this->payload())->assertCreated()->json('order.id');
        $this->getJson('/orders/'.$id)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/orders/'.$id)->assertNotFound();
        $this->postJson('/orders/'.$id.'/cancel')->assertNotFound();
        $this->getJson('/orders')->assertJsonPath('total', 0);
    }
}
