<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class PaymentLedgerSourceOfTruthTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $status = 'pending', int $total = 250000): Order
    {
        return Order::create([
            'status' => $status,
            'subtotal' => $total,
            'discount' => 0,
            'shipping_fee' => 0,
            'total' => $total,
            'idempotency_key' => 'ledger-'.uniqid(),
            'payload_hash' => str_repeat('b', 64),
            'shipping_address' => ['line1' => 'Jalan Sutra 1', 'city' => 'Jakarta'],
        ]);
    }

    private function ledger(Order $order, string $outcome, string $event = 'settlement', ?int $amount = null): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'provider' => 'midtrans',
            'provider_transaction_id' => 'tx-'.uniqid(),
            'provider_order_id' => (string) $order->id,
            'event_status' => $event,
            'amount' => $amount ?? $order->total,
            'outcome' => $outcome,
            'payload' => ['payment_type' => 'bank_transfer', 'signature_key' => 'tidak-boleh-bocor'],
        ]);
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
    }

    public function test_settled_only_when_ledger_has_paid_entry_matching_total(): void
    {
        $order = $this->order();
        $this->assertFalse($order->hasSettledPayment());

        $this->ledger($order, 'amount_mismatch', amount: 1000);
        $this->ledger($order, 'recorded', 'pending');
        $this->assertFalse($order->hasSettledPayment());

        $this->ledger($order, 'paid');
        $this->assertTrue($order->hasSettledPayment());
    }

    public function test_paid_status_without_ledger_is_flagged_as_anomaly(): void
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid']);
        $order->refresh();

        $this->assertTrue($order->hasPaymentAnomaly());

        $this->ledger($order, 'paid');
        $this->assertFalse($order->fresh()->hasPaymentAnomaly());
    }

    public function test_downstream_statuses_also_require_ledger_but_unpaid_statuses_do_not(): void
    {
        foreach (['processing', 'shipped', 'completed'] as $status) {
            $this->assertTrue($this->order($status)->hasPaymentAnomaly(), $status);
        }
        foreach (['pending', 'cancelled'] as $status) {
            $this->assertFalse($this->order($status)->hasPaymentAnomaly(), $status);
        }
    }

    public function test_ledger_entries_are_immutable(): void
    {
        $payment = $this->ledger($this->order(), 'paid');

        $this->expectException(LogicException::class);
        $payment->update(['outcome' => 'recorded']);
    }

    public function test_admin_order_detail_exposes_ledger_without_raw_payload(): void
    {
        $order = $this->order('paid');
        $this->ledger($order, 'paid');

        $response = $this->actingAs($this->admin(), 'admin')->getJson("/admin/orders/{$order->id}")->assertOk();

        $response->assertJsonPath('order.payment.settled', true)
            ->assertJsonPath('order.payment.anomaly', false)
            ->assertJsonPath('order.payment.ledger.0.outcome', 'paid')
            ->assertJsonPath('order.payment.ledger.0.payment_type', 'bank_transfer')
            ->assertJsonPath('order.payment.ledger.0.amount', 250000);
        $this->assertStringNotContainsString('tidak-boleh-bocor', $response->getContent());
        $this->assertArrayNotHasKey('payload', $response->json('order.payment.ledger.0'));
    }

    public function test_admin_order_detail_tolerates_null_payload_without_warning(): void
    {
        $order = $this->order('paid');
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'midtrans',
            'provider_transaction_id' => 'legacy-tx',
            'provider_order_id' => (string) $order->id,
            'event_status' => 'settlement',
            'amount' => $order->total,
            'outcome' => 'paid',
            'payload' => null,
        ]);

        $this->actingAs($this->admin(), 'admin')->getJson("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.payment.ledger.0.payment_type', null);
    }

    public function test_admin_order_detail_flags_paid_order_without_ledger(): void
    {
        $order = $this->order('paid');

        $this->actingAs($this->admin(), 'admin')->getJson("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('order.payment.settled', false)
            ->assertJsonPath('order.payment.anomaly', true)
            ->assertJsonPath('order.payment.ledger', []);
    }
}
