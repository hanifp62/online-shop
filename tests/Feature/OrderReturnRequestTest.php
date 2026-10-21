<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(int $quantity = 2): Order
    {
        $category = Category::create(['cat_title' => 'Syal']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Syal Sutra', 'product_slug' => 'syal-'.uniqid(), 'product_price' => '400000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 9, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Rumah', 'recipient_name' => 'Dewi',
            'phone' => '08123456789', 'line1' => 'Jl. Melati', 'city' => 'Bandung',
            'province' => 'Jabar', 'postal_code' => '40111',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => $quantity])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'return-key-'.uniqid()])
            ->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    private function complete(Order $order): Order
    {
        $order->payments()->create([
            'provider' => 'midtrans',
            'provider_transaction_id' => 'return-payment-'.$order->id,
            'provider_order_id' => (string) $order->id,
            'event_status' => 'settlement',
            'amount' => $order->total,
            'outcome' => 'paid',
            'payload' => [],
        ]);
        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        foreach (['processing', 'shipped', 'completed'] as $status) {
            $order = $checkout->transition($order->fresh(), $status);
        }

        return $order->fresh()->load('items');
    }

    public function test_eligibility_rejects_order_that_is_not_completed(): void
    {
        $order = $this->createOrder();

        $body = $this->getJson('/orders/'.$order->id.'/return-eligibility')->assertOk()->json();

        $this->assertFalse($body['eligible']);
        $this->assertStringContainsString('setelah pesanan diterima', $body['reason']);
        $this->assertSame([], $body['items']);
    }

    public function test_completed_order_without_settled_payment_cannot_request_return(): void
    {
        $order = $this->createOrder();
        $order->update(['status' => 'completed']);
        $this->assertFalse($order->hasSettledPayment());

        $this->getJson('/orders/'.$order->id.'/return-eligibility')->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('reason', 'Retur belum dapat diajukan karena pembayaran belum terverifikasi. Hubungi layanan pelanggan.')
            ->assertJsonPath('items', []);

        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged',
            'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('order')
            ->assertJsonPath('errors.order.0', 'Retur belum dapat diajukan karena pembayaran belum terverifikasi. Hubungi layanan pelanggan.');

        $this->assertDatabaseCount('returns', 0);
        $this->assertDatabaseCount('return_items', 0);
    }

    public function test_eligibility_lists_returnable_items_reasons_and_deadline(): void
    {
        $order = $this->complete($this->createOrder());

        $body = $this->getJson('/orders/'.$order->id.'/return-eligibility')->assertOk()->json();

        $this->assertTrue($body['eligible']);
        $this->assertSame(OrderReturn::WINDOW_DAYS, $body['window_days']);
        $this->assertNotNull($body['deadline']);
        $this->assertArrayHasKey('damaged', $body['reasons']);
        $this->assertSame(2, $body['items'][0]['returnable_quantity']);
    }

    public function test_request_records_return_with_summary_and_reference_number(): void
    {
        $order = $this->complete($this->createOrder());
        $itemId = $order->items->first()->id;

        $return = $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1, 'condition_note' => 'Jahitan terbuka']],
        ])->assertCreated()->json('return');

        $this->assertMatchesRegularExpression('/^RET-\d{8}-\d{4}-[0-9A-F]{4}$/', $return['return_number']);
        $this->assertSame(OrderReturn::STATUS_PENDING, $return['status']);
        $this->assertSame(400000, $return['requested_refund_amount']);
        $this->assertSame(1, $return['items'][0]['quantity']);
        $this->assertNotEmpty($return['next_step']);
        $this->assertDatabaseCount('return_items', 1);
    }

    public function test_quantity_cannot_exceed_remaining_and_other_reason_requires_notes(): void
    {
        $order = $this->complete($this->createOrder());
        $itemId = $order->items->first()->id;

        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged',
            'items' => [['order_item_id' => $itemId, 'quantity' => 3]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');

        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'other',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged',
            'items' => [['order_item_id' => $itemId, 'quantity' => 2]],
        ])->assertCreated();

        $this->getJson('/orders/'.$order->id.'/return-eligibility')
            ->assertOk()
            ->assertJsonPath('eligible', false);
    }

    public function test_return_window_expires_and_other_users_cannot_see_or_submit(): void
    {
        $order = $this->complete($this->createOrder());
        $itemId = $order->items->first()->id;
        DB::table('order_status_history')->where('order_id', $order->id)->where('to_status', 'completed')
            ->update(['created_at' => now()->subDays(OrderReturn::WINDOW_DAYS + 1)]);

        $this->getJson('/orders/'.$order->id.'/return-eligibility')->assertOk()->assertJsonPath('eligible', false);
        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged',
            'items' => [['order_item_id' => $itemId, 'quantity' => 1]],
        ])->assertStatus(422);

        $this->actingAs(User::factory()->create());
        $this->getJson('/orders/'.$order->id.'/return-eligibility')->assertNotFound();
        $this->getJson('/returns')->assertOk()->assertJsonPath('returns', []);
    }
}
