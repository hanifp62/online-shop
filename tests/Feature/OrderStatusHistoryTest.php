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
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
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
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'history-key-1'])
            ->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    public function test_new_order_records_initial_pending_history(): void
    {
        $order = $this->createOrder();

        $history = $order->statusHistory()->get();
        $this->assertCount(1, $history);
        $this->assertNull($history[0]->from_status);
        $this->assertSame('pending', $history[0]->to_status);
        $this->assertNull($history[0]->admin_id);
    }

    public function test_transition_appends_history_with_actor_and_order_flow(): void
    {
        $order = $this->createOrder();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'op@example.test', 'password' => 'Password123!']);

        $this->actingAs($admin, 'admin');
        app(CheckoutService::class)->transition($order, 'cancelled');

        $history = $order->fresh()->statusHistory()->get();
        $this->assertCount(2, $history);
        $this->assertSame('pending', $history[1]->from_status);
        $this->assertSame('cancelled', $history[1]->to_status);
        $this->assertSame($admin->id, $history[1]->admin_id);
    }

    public function test_full_lifecycle_records_every_transition_in_order(): void
    {
        $order = $this->createOrder();
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        foreach (['processing', 'shipped', 'completed'] as $status) {
            $order = $checkout->transition($order->fresh(), $status);
        }

        $this->assertSame(
            [[null, 'pending'], ['pending', 'paid'], ['paid', 'processing'], ['processing', 'shipped'], ['shipped', 'completed']],
            $order->statusHistory()->get()->map(fn ($h) => [$h->from_status, $h->to_status])->all(),
        );
    }

    public function test_illegal_transition_is_rejected_without_history_or_status_change(): void
    {
        $order = $this->createOrder();

        foreach (['completed', 'shipped', 'paid', 'unknown'] as $status) {
            try {
                app(CheckoutService::class)->transition($order->fresh(), $status);
                $this->fail("pending → {$status} must be rejected");
            } catch (ValidationException) {
            }
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_customer_order_detail_exposes_timeline_without_operator_identity(): void
    {
        $order = $this->createOrder();
        app(CheckoutService::class)->transition($order, 'cancelled');

        $history = $this->getJson('/orders/'.$order->id)->assertOk()->json('order.status_history');

        $this->assertSame(['pending', 'cancelled'], array_column($history, 'to_status'));
        $this->assertSame(['from_status', 'to_status', 'created_at'], array_keys($history[1]));
    }

    public function test_status_history_is_append_only_and_immutable(): void
    {
        $order = $this->createOrder();
        $entry = $order->statusHistory()->first();

        $this->expectException(LogicException::class);
        $entry->update(['to_status' => 'paid']);
    }

    public function test_status_history_cannot_be_deleted_via_model(): void
    {
        $order = $this->createOrder();
        $entry = $order->statusHistory()->first();

        $this->expectException(LogicException::class);
        $entry->delete();
    }
}
