<?php

namespace Tests\Feature;

use App\Mail\OrderStatusChanged;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(): Order
    {
        $category = Category::create(['cat_title' => 'Tas']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Clutch', 'product_slug' => 'clutch-'.uniqid(), 'product_price' => '250000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Kantor', 'recipient_name' => 'Budi',
            'phone' => '08123456789', 'line1' => 'Jl. Sudirman', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'notif-key-'.uniqid()])
            ->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    public function test_paid_and_shipped_and_completed_transitions_each_queue_exactly_one_mail(): void
    {
        Mail::fake();
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        $order = $checkout->transition($order->fresh(), 'processing');
        $order = $checkout->transition($order->fresh(), 'shipped');
        $checkout->transition($order->fresh(), 'completed');

        Mail::assertQueued(OrderStatusChanged::class, 3);
        $this->assertDatabaseCount('order_notifications', 3);
    }

    public function test_processing_transition_does_not_notify(): void
    {
        Mail::fake();
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        $checkout->transition($order->fresh(), 'processing');

        Mail::assertQueued(OrderStatusChanged::class, 1);
    }

    public function test_replaying_the_same_status_is_idempotent(): void
    {
        Mail::fake();
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));

        // Second call returns false (CAS), so no second slot is claimed and no mail is queued.
        DB::transaction(fn () => $checkout->markPaid($order));

        Mail::assertQueued(OrderStatusChanged::class, 1);
        $this->assertDatabaseCount('order_notifications', 1);
    }
}
