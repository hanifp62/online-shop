<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentDeadLetter;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-server-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY, 'services.midtrans.is_production' => false]);
    }

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
        $id = $this->postJson('/checkout', ['address_id' => $address->id, 'idempotency_key' => 'pay-key-'.uniqid()])
            ->assertCreated()->json('order.id');
        auth()->logout();

        return Order::findOrFail($id);
    }

    private function notification(Order|int $order, array $overrides = []): array
    {
        $orderId = (string) ($order instanceof Order ? $order->id : $order);
        $gross = $order instanceof Order ? $order->total.'.00' : '1000.00';
        $body = array_merge([
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_id' => 'tx-'.$orderId,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'currency' => 'IDR',
        ], $overrides);
        $body['signature_key'] ??= hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].self::KEY);

        return $body;
    }

    private function fakeStatus(array $notification, array $overrides = [], int $code = 200): void
    {
        $response = array_merge(array_diff_key($notification, ['signature_key' => true]), $overrides);
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response($response, $code)]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['signature_key' => str_repeat('a', 128)]);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertForbidden();

        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $order->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_settlement_marks_order_paid(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $payment = Payment::sole();
        $this->assertSame('paid', $payment->outcome);
        $this->assertSame($order->total, $payment->amount);
        $this->assertArrayNotHasKey('signature_key', $payment->payload);
        $history = $order->statusHistory()->get()->last();
        $this->assertSame(['pending', 'paid', null], [$history->from_status, $history->to_status, $history->admin_id]);
        $this->assertSame(['committed'], $order->inventoryReservations()->pluck('status')->unique()->values()->all());
    }

    public function test_duplicate_notification_is_idempotent(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();
        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'paid')->count());
    }

    public function test_status_api_overrides_notification_body(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, ['transaction_status' => 'pending', 'status_code' => '201']);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('recorded', Payment::sole()->outcome);
    }

    public function test_capture_requires_fraud_accept(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['transaction_status' => 'capture']);
        $this->fakeStatus($body, ['fraud_status' => 'challenge']);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('recorded', Payment::sole()->outcome);
    }

    public function test_status_api_mismatched_transaction_is_rejected(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, ['transaction_id' => 'tx-other']);

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(422);

        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_status_api_non_idr_currency_is_rejected(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, ['currency' => 'USD']);

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    public function test_amount_mismatch_does_not_mark_paid(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, ['gross_amount' => '1.00']);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('amount_mismatch', Payment::sole()->outcome);
    }

    public function test_fractional_gross_amount_does_not_match(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, ['gross_amount' => $order->total.'.50']);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('amount_mismatch', Payment::sole()->outcome);
    }

    public function test_integer_gross_amount_matches(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['gross_amount' => (string) $order->total]);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_status_api_failure_returns_503(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, [], 500);

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);

        $this->assertSame(0, Payment::count());
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_cancelled_order_records_not_payable(): void
    {
        $order = $this->createOrder();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);
        $body = $this->notification($order);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('order_not_payable', Payment::sole()->outcome);
    }

    public function test_unknown_order_is_recorded(): void
    {
        $body = $this->notification(999999);
        $this->fakeStatus($body);

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $payment = Payment::sole();
        $this->assertSame('unknown_order', $payment->outcome);
        $this->assertNull($payment->order_id);
    }

    public function test_missing_fields_return_422(): void
    {
        $this->postJson('/webhooks/midtrans', ['order_id' => '1'])->assertStatus(422);
        $this->assertSame(0, Payment::count());
    }

    public function test_missing_server_key_returns_503(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        config(['services.midtrans.server_key' => '']);
        Http::fake();

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);

        $this->assertSame(0, Payment::count());
        Http::assertNothingSent();
    }

    public function test_admin_still_cannot_set_paid_manually(): void
    {
        $order = $this->createOrder();

        $this->expectException(ValidationException::class);
        app(CheckoutService::class)->transition($order, 'paid');
    }

    public function test_numeric_json_gross_amount_keeps_raw_decimals_for_signature(): void
    {
        $order = $this->createOrder();
        $gross = $order->total.'.00';
        $sig = hash('sha512', $order->id.'200'.$gross.self::KEY);
        // gross_amount sebagai angka JSON (bukan string): json_decode akan membuang ".00".
        $rawJson = '{"order_id":"'.$order->id.'","status_code":"200","gross_amount":'.$gross
            .',"transaction_id":"tx-'.$order->id.'","transaction_status":"settlement","fraud_status":"accept"'
            .',"currency":"IDR","signature_key":"'.$sig.'"}';
        $status = $this->notification($order) + ['customer_details' => ['email' => 'pii@example.com']];
        $this->fakeStatus($status);

        $this->call('POST', '/webhooks/midtrans', [], [], [], ['CONTENT_TYPE' => 'application/json'], $rawJson)
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertArrayNotHasKey('customer_details', Payment::sole()->payload);
    }

    public function test_status_api_failure_is_dead_lettered_and_replayed_later(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, [], 500);

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);
        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);

        $letter = PaymentDeadLetter::sole();
        $this->assertSame('status_api_unavailable', $letter->reason);
        $this->assertSame(2, $letter->attempts);
        $this->assertSame($order->id, $letter->order_id);
        $this->assertNull($letter->resolved_at);

        Http::swap(new Factory);
        $this->fakeStatus($body);
        $this->artisan('payments:replay-dead-letters')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNotNull($letter->fresh()->resolved_at);
        $this->assertSame('paid', Payment::sole()->outcome);
    }

    public function test_connection_errors_are_retried_before_failing(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $calls = 0;
        Http::fake(function () use (&$calls, $body) {
            $calls++;
            if ($calls < 2) {
                throw new ConnectionException('timeout');
            }

            return Http::response(array_diff_key($body, ['signature_key' => true]), 200);
        });

        $this->postJson('/webhooks/midtrans', $body)->assertOk();

        $this->assertSame(2, $calls);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, PaymentDeadLetter::count());
    }

    public function test_circuit_opens_after_consecutive_failures_and_skips_upstream(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, [], 500);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);
        }
        $this->assertGreaterThan(now()->timestamp, Cache::get('midtrans:circuit_open_until'));

        Http::swap(new Factory);
        Http::fake();
        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);
        Http::assertNothingSent();
        $this->assertSame('circuit_open', PaymentDeadLetter::sole()->reason);

        $this->travel(2)->minutes();
        Http::swap(new Factory);
        $this->fakeStatus($body);
        $this->postJson('/webhooks/midtrans', $body)->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNull(Cache::get('midtrans:consecutive_failures'));
        $this->assertNotNull(PaymentDeadLetter::sole()->resolved_at);
    }

    public function test_invalid_signature_is_never_dead_lettered(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['signature_key' => str_repeat('0', 128)]);
        Http::fake();

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(403);

        $this->assertSame(0, PaymentDeadLetter::count());
    }

    public function test_replay_stops_at_attempt_cap(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
            'transaction_id' => $body['transaction_id'], 'attempts' => 10, 'raw_body' => json_encode($body),
        ]);
        Http::fake();

        $this->artisan('payments:replay-dead-letters')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_oversized_body_is_rejected_before_signature_work(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['padding' => str_repeat('a', 20_000)]);
        Http::fake();

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(413);

        $this->assertSame(0, PaymentDeadLetter::count());
        Http::assertNothingSent();
    }

    public function test_overlong_transaction_id_is_rejected(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order, ['transaction_id' => str_repeat('t', 65)]);
        Http::fake();

        $this->postJson('/webhooks/midtrans', $body)->assertStatus(422);

        $this->assertSame(0, PaymentDeadLetter::count());
    }

    public function test_order_with_pending_dead_letter_is_not_cancelled_by_reservation_release(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, [], 500);
        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);

        DB::table('inventory_reservations')->where('order_id', $order->id)
            ->update(['expires_at' => now()->subHour()]);

        $this->artisan('reservations:release')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_resolved_dead_letters_are_pruned_after_retention(): void
    {
        $letter = PaymentDeadLetter::create([
            'order_id' => null, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
            'transaction_id' => 'tx-old', 'attempts' => 1, 'raw_body' => '{}',
            'resolved_at' => now()->subDays(31),
        ]);
        $fresh = PaymentDeadLetter::create([
            'order_id' => null, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
            'transaction_id' => 'tx-new', 'attempts' => 1, 'raw_body' => '{}',
            'resolved_at' => now(),
        ]);

        $this->artisan('payments:prune-dead-letters')->assertSuccessful();

        $this->assertNull($letter->fresh());
        $this->assertNotNull($fresh->fresh());
    }

    public function test_raw_body_is_stored_encrypted_at_rest(): void
    {
        $order = $this->createOrder();
        $body = $this->notification($order);
        $this->fakeStatus($body, [], 500);
        $this->postJson('/webhooks/midtrans', $body)->assertStatus(503);

        $stored = DB::table('payment_dead_letters')->value('raw_body');
        $this->assertStringNotContainsString($body['signature_key'], $stored);
        $this->assertSame(json_encode($body), PaymentDeadLetter::sole()->raw_body);
    }
}
