<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private function createOrder(string $key = 'reservation-key-1'): Order
    {
        $category = Category::create(['cat_title' => 'Tas']);
        $this->product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Clutch', 'product_slug' => 'clutch-'.uniqid(), 'product_price' => '250000.00',
        ]);
        DB::table('products')->where('product_id', $this->product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Kantor', 'recipient_name' => 'Budi',
            'phone' => '08123456789', 'line1' => 'Jl. Sudirman', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $this->product->product_id, 'quantity' => 2])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => $key])
            ->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    public function test_checkout_reserves_stock_with_expiry(): void
    {
        $order = $this->createOrder();

        $reservation = $order->inventoryReservations()->sole();
        $this->assertSame('reserved', $reservation->status);
        $this->assertSame(2, $reservation->quantity);
        $this->assertSame($this->product->product_id, $reservation->product_id);
        $this->assertTrue($reservation->expires_at->greaterThan(now()));
    }

    public function test_expired_reservation_releases_stock_and_cancels_pending_order(): void
    {
        $order = $this->createOrder();
        $this->assertSame(3, (int) DB::table('products')->where('product_id', $this->product->product_id)->value('stock'));

        $this->travel(61)->minutes();
        $this->artisan('reservations:release')->assertExitCode(0);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('released', $order->inventoryReservations()->sole()->status);
        $this->assertSame(5, (int) DB::table('products')->where('product_id', $this->product->product_id)->value('stock'));
    }

    public function test_release_leaves_processed_orders_intact_and_commits_reservation(): void
    {
        $order = $this->createOrder();
        // pending→processing tidak lewat transisi manual; simulasikan order yang sudah diproses.
        DB::table('orders')->where('id', $order->id)->update(['status' => 'processing']);

        $this->travel(61)->minutes();
        $this->artisan('reservations:release')->assertExitCode(0);

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('committed', $order->inventoryReservations()->sole()->status);
        $this->assertSame(3, (int) DB::table('products')->where('product_id', $this->product->product_id)->value('stock'));
    }

    public function test_manual_cancel_releases_reservation_without_double_restock(): void
    {
        $order = $this->createOrder();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'op2@example.test', 'password' => 'Password123!']);
        $this->actingAs($admin, 'admin');
        app(CheckoutService::class)->transition($order, 'cancelled');

        $this->travel(61)->minutes();
        $this->artisan('reservations:release')->assertExitCode(0);

        $this->assertSame('released', $order->inventoryReservations()->sole()->status);
        $this->assertSame(5, (int) DB::table('products')->where('product_id', $this->product->product_id)->value('stock'));
    }
}
