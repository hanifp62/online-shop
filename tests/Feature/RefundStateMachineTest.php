<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function createCompletedReturn(): array
    {
        $category = Category::create(['cat_title' => 'Parfum']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Attar', 'product_slug' => 'attar-'.uniqid(), 'product_price' => '500000.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 5, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Atelier', 'recipient_name' => 'Siti',
            'phone' => '08123456789', 'line1' => 'Jl. Atelier 1', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '12190',
        ]);
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => 1])->assertOk();
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'refund-key-'.uniqid()])
            ->assertCreated()->json('order.id');
        $order = Order::with('items')->findOrFail($id);

        $checkout = app(CheckoutService::class);
        DB::transaction(fn () => $checkout->markPaid($order));
        Payment::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'provider_transaction_id' => 'tx-'.uniqid(),
            'provider_order_id' => (string) $order->id, 'event_status' => 'settlement', 'amount' => 500000,
            'outcome' => 'paid',
        ]);
        foreach (['processing', 'shipped', 'completed'] as $status) {
            $order = $checkout->transition($order->fresh(), $status);
        }

        $return = OrderReturn::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'return_number' => OrderReturn::generateReturnNumber($order->id),
            'status' => OrderReturn::STATUS_APPROVED, 'reason' => 'damaged', 'requested_refund_amount' => 500000,
        ]);

        return [$user, $order, $return];
    }

    public function test_customer_can_request_refund_for_approved_return(): void
    {
        [$user, , $return] = $this->createCompletedReturn();

        $body = $this->actingAs($user)
            ->postJson('/returns/'.$return->id.'/refunds', ['reason' => 'Barang cacat dan sudah disetujui retur.'])
            ->assertCreated()->json('refund');

        $this->assertSame(Refund::STATUS_DIAJUKAN, $body['status']);
        $this->assertSame(500000, $body['amount']);
        $this->assertDatabaseHas('refunds', ['return_id' => $return->id, 'status' => Refund::STATUS_DIAJUKAN]);
    }

    public function test_admin_can_approve_or_reject_refund(): void
    {
        [$user, , $return] = $this->createCompletedReturn();
        $refund = app(RefundService::class)->request($return, 'Barang cacat.');

        $approved = app(RefundService::class)->transition($refund, Refund::STATUS_DISETUJUI);
        $this->assertSame(Refund::STATUS_DISETUJUI, $approved->status);
        $this->assertNotNull($approved->reviewed_at);

        // Transition from disetujui to ditolak is illegal according to the state machine.
        $this->expectException(ValidationException::class);
        app(RefundService::class)->transition($approved, Refund::STATUS_DITOLAK, ['rejection_reason' => 'X']);
    }

    public function test_rejection_requires_reason(): void
    {
        [, , $return] = $this->createCompletedReturn();
        $refund = app(RefundService::class)->request($return, 'Barang cacat.');

        $this->expectException(ValidationException::class);
        app(RefundService::class)->transition($refund, Refund::STATUS_DITOLAK, ['rejection_reason' => '']);
    }

    public function test_dispatched_and_settled_are_blocked_until_provider_verification_exists(): void
    {
        [, , $return] = $this->createCompletedReturn();
        $service = app(RefundService::class);
        $refund = $service->transition($service->request($return, 'Cacat.'), Refund::STATUS_DISETUJUI);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Status pencairan belum tersedia');
        $service->transition($refund, Refund::STATUS_DANA_DIKIRIM, ['provider_refund_id' => 'ref-999']);
    }

    public function test_refund_cannot_exceed_settled_payment_total(): void
    {
        [, $order, $return] = $this->createCompletedReturn();
        $return->update(['requested_refund_amount' => 9999999]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('melebihi pembayaran yang tercatat');
        app(RefundService::class)->request($return, 'Kelebihan.');
    }
}
