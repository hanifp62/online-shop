<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminSalesSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Atelier', 'email' => 'atelier@example.test', 'password' => 'password']);
    }

    private function order(string $createdAt, int $total, string $status, bool $paid): Order
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'idempotency_key' => 'sales-'.uniqid(), 'status' => $status,
            'payload_hash' => hash('sha256', 'payload-'.uniqid()),
            'subtotal' => $total, 'shipping_cost' => 0, 'discount' => 0, 'total' => $total,
        ]);
        // created_at bukan fillable, jadi tanggal uji ditulis langsung.
        DB::table('orders')->where('id', $order->id)
            ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        if ($paid) {
            Payment::create([
                'order_id' => $order->id, 'provider' => 'midtrans', 'provider_transaction_id' => 'tx-'.uniqid(),
                'provider_order_id' => (string) $order->id, 'event_status' => 'settlement', 'amount' => $total,
                'outcome' => 'paid',
            ]);
        }

        return $order;
    }

    public function test_summary_compares_paid_ledger_value_and_success_rate_to_previous_period(): void
    {
        $today = now('UTC')->toDateString();
        $previous = now('UTC')->subDay()->toDateString();
        $this->order($today.' 10:00:00', 300000, 'completed', true);
        $this->order($today.' 11:00:00', 200000, 'pending', false);
        $this->order($previous.' 10:00:00', 100000, 'completed', true);
        $this->order($previous.' 11:00:00', 300000, 'cancelled', false);

        $body = $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/reports/sales-summary?period=custom&from='.$today.'&to='.$today)
            ->assertOk()->json();

        $this->assertSame(2, $body['current']['orders']);
        $this->assertSame(1, $body['current']['paid_orders']);
        $this->assertSame(300000, $body['current']['paid_value']);
        $this->assertEquals(50.0, $body['current']['payment_success_rate']);
        $this->assertSame(2, $body['previous']['orders']);
        $this->assertSame(100000, $body['previous']['paid_value']);
        $this->assertEquals(200.0, $body['change_percent']['paid_value']);
    }

    public function test_summary_requires_valid_bounded_period(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->getJson('/admin/reports/sales-summary?period=custom&from=2024-01-01&to=2026-12-31')
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }
}
