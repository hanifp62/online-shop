<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderTaxShippingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $price, int $stock = 5): Product
    {
        $category = Category::firstOrCreate(['cat_title' => 'Snapshot']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Sutra', 'product_slug' => 'snap-'.uniqid(), 'product_price' => $price,
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => $stock]);

        return $product->fresh();
    }

    private function address(User $user, string $line1 = 'Jalan Sutra 1'): Address
    {
        return Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Klien',
            'phone' => '081234567890', 'line1' => $line1, 'city' => 'Jakarta',
            'province' => 'DKI Jakarta', 'postal_code' => '12345',
        ]);
    }

    private function checkout(User $user, Product $product, int $qty, array $payload)
    {
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => $qty])->assertOk();

        return $this->postJson('/checkout', $payload);
    }

    public function test_defaults_keep_zero_shipping_and_tax(): void
    {
        $user = User::factory()->create();
        $product = $this->product('100000.00');
        $order = $this->checkout($user, $product, 1, ['address_id' => $this->address($user)->id, 'idempotency_key' => 'default-zero'])
            ->assertCreated()->json('order');

        $this->assertSame(0, $order['shipping_fee']);
        $this->assertSame(0, $order['tax']);
        $this->assertSame(100000, $order['total']);
    }

    public function test_shipping_and_tax_snapshot_persisted_and_client_totals_ignored(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1100]);
        $user = User::factory()->create();
        $product = $this->product('100000.00');
        $payload = [
            'address_id' => $this->address($user)->id, 'idempotency_key' => 'snapshot-1',
            'total' => 1, 'tax' => 0, 'shipping_fee' => 0,
        ];
        $order = $this->checkout($user, $product, 2, $payload)->assertCreated()->json('order');

        $this->assertSame(200000, $order['subtotal']);
        $this->assertSame(25000, $order['shipping_fee']);
        $this->assertSame(22000, $order['tax']);
        $this->assertSame(247000, $order['total']);
        $this->assertDatabaseHas('orders', ['id' => $order['id'], 'shipping_fee' => 25000, 'tax' => 22000, 'total' => 247000]);
    }

    public function test_replay_returns_original_snapshot_even_after_rates_change(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1100]);
        $user = User::factory()->create();
        $product = $this->product('100000.00');
        $payload = ['address_id' => $this->address($user)->id, 'idempotency_key' => 'snapshot-replay'];
        $first = $this->checkout($user, $product, 1, $payload)->assertCreated()->json('order');

        config(['checkout.shipping_fee' => 99000, 'checkout.tax_rate_bps' => 5000]);
        $replay = $this->postJson('/checkout', $payload)->assertOk()->json('order');

        foreach (['id', 'subtotal', 'discount', 'shipping_fee', 'tax', 'total'] as $field) {
            $this->assertSame($first[$field], $replay[$field], $field);
        }
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, (int) $product->fresh()->stock);
    }

    public function test_replay_with_changed_address_is_rejected(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1100]);
        $user = User::factory()->create();
        $product = $this->product('100000.00');
        $payload = ['address_id' => $this->address($user)->id, 'idempotency_key' => 'snapshot-addr'];
        $this->checkout($user, $product, 1, $payload)->assertCreated();

        $other = $this->address($user, 'Jalan Linen 2');
        $this->postJson('/checkout', ['address_id' => $other->id] + $payload)->assertStatus(409);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_tax_rounds_half_up_to_whole_rupiah(): void
    {
        // 12345 * 11% = 1357.95 -> 1358; 12345 * 2.5% = 308.625 -> 309; 10 * 5% = 0.5 -> 1.
        foreach ([['12345.00', 1100, 1358], ['12345.00', 250, 309], ['10.00', 500, 1]] as $i => [$price, $bps, $tax]) {
            config(['checkout.shipping_fee' => 0, 'checkout.tax_rate_bps' => $bps]);
            $user = User::factory()->create();
            $order = $this->checkout($user, $this->product($price), 1, ['address_id' => $this->address($user)->id, 'idempotency_key' => "round-key-$i"])
                ->assertCreated()->json('order');
            $this->assertSame($tax, $order['tax'], "bps $bps");
            $this->assertSame((int) $price + $tax, $order['total']);
        }
    }

    public function test_tax_applies_after_voucher_and_shipping_is_not_discounted(): void
    {
        config(['checkout.shipping_fee' => 25000, 'checkout.tax_rate_bps' => 1000]);
        Voucher::create(['code' => 'SUTRA', 'discount_amount' => 30000, 'quota' => 5]);
        Voucher::create(['code' => 'BESAR', 'discount_amount' => 500000, 'quota' => 5]);
        $user = User::factory()->create();
        $address = $this->address($user);

        $order = $this->checkout($user, $this->product('100000.00'), 1, ['address_id' => $address->id, 'idempotency_key' => 'voucher-part', 'voucher_code' => 'SUTRA'])
            ->assertCreated()->json('order');
        $this->assertSame(30000, $order['discount']);
        $this->assertSame(7000, $order['tax']);
        $this->assertSame(100000 - 30000 + 7000 + 25000, $order['total']);

        // Diskon dibatasi subtotal: pajak 0, ongkir tetap dibayar.
        $order = $this->checkout($user, $this->product('100000.00'), 1, ['address_id' => $address->id, 'idempotency_key' => 'voucher-full', 'voucher_code' => 'BESAR'])
            ->assertCreated()->json('order');
        $this->assertSame(100000, $order['discount']);
        $this->assertSame(0, $order['tax']);
        $this->assertSame(25000, $order['total']);
        $this->assertSame(2, Order::count());
    }

    public function test_guest_checkout_snapshots_shipping_and_tax(): void
    {
        config(['checkout.shipping_fee' => 15000, 'checkout.tax_rate_bps' => 1100]);
        $product = $this->product('100000.00');
        $contact = ['customer_name' => 'Tamu', 'customer_email' => 'tamu@example.test', 'customer_phone' => '081234567890'];
        $address = ['recipient_name' => 'Tamu', 'line1' => 'Jalan Sutra 1', 'city' => 'Jakarta', 'province' => 'DKI Jakarta', 'postal_code' => '12345'];
        $cart = [$product->product_id => 1];
        $scope = str_repeat('b', 64);

        [$order] = app(CheckoutService::class)->checkoutGuest(
            $contact, $address, $cart, 'guest-snapshot', $scope, 'token-guest'
        );

        $this->assertSame(15000, $order->shipping_fee);
        $this->assertSame(11000, $order->tax);
        $this->assertSame(126000, $order->total);

        // Replay dengan tarif baru tetap mengembalikan snapshot awal
        config(['checkout.shipping_fee' => 90000, 'checkout.tax_rate_bps' => 5000]);
        [$replay] = app(CheckoutService::class)->checkoutGuest(
            $contact, $address, $cart, 'guest-snapshot', $scope, 'token-guest'
        );
        $this->assertSame(15000, $replay->shipping_fee);
        $this->assertSame(11000, $replay->tax);
        $this->assertSame(126000, $replay->total);

        // Replay dengan alamat diubah ditolak (409)
        $diffAddress = ['line1' => 'Jalan Lain'] + $address;
        $this->expectException(HttpException::class);
        app(CheckoutService::class)->checkoutGuest(
            $contact, $diffAddress, $cart, 'guest-snapshot', $scope, 'token-guest'
        );
    }
}
