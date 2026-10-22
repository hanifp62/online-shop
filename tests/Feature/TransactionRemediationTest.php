<?php

namespace Tests\Feature;

use App\Jobs\ReleaseExpiredReservations;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\PaymentDeadLetter;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CheckoutService;
use App\Services\PaymentWebhookService;
use App\Services\RefundService;
use App\Services\ReturnAllocation;
use App\Services\TransactionLockRetry;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * FR-REM-002 / NFR-REM-003: acceptance, bukan characterization cacat audit.
 * Integrator menjalankan serial lewat launcher terisolasi. HTTP provider selalu fake.
 * Fault injection/recheck satu koneksi TIDAK membuktikan deadlock/lock InnoDB nyata.
 * TXN-06 (kapasitas monetary/migrasi) dimiliki lane DATA, bukan suite ini.
 */
class TransactionRemediationTest extends TestCase
{
    use RefreshDatabase;

    private string $providerKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->providerKey = bin2hex(random_bytes(32));
        config(['services.midtrans.server_key' => $this->providerKey, 'services.midtrans.is_production' => false]);
        Http::preventStrayRequests();
        Notification::fake();
    }

    private function order(int $price = 250000, int $quantity = 1, int $discount = 0, ?int $secondPrice = null): Order
    {
        $category = Category::create(['cat_title' => 'Remediation synthetic']);
        $product = Product::create([
            'product_cat' => $category->cat_id, 'product_brand' => 'ZAFIRA',
            'product_title' => 'Synthetic textile', 'product_slug' => 'rem-'.bin2hex(random_bytes(6)),
            'product_price' => $price.'.00',
        ]);
        DB::table('products')->where('product_id', $product->product_id)->update(['stock' => 10, 'is_active' => true]);
        $user = User::factory()->create();
        $address = Address::create([
            'user_id' => $user->id, 'label' => 'Synthetic', 'recipient_name' => 'Synthetic Client',
            'phone' => '081200000000', 'line1' => 'Jalan Uji', 'city' => 'Jakarta',
            'province' => 'DKI', 'postal_code' => '10110',
        ]);
        $voucher = $discount > 0 ? Voucher::create([
            'code' => 'REM'.strtoupper(bin2hex(random_bytes(4))), 'discount_amount' => $discount,
            'quota' => 10, 'used' => 0, 'per_user_limit' => 1, 'is_active' => true,
        ]) : null;
        $this->actingAs($user)->postJson('/cart/items', ['product_id' => $product->product_id, 'quantity' => $quantity])->assertOk();
        if ($secondPrice !== null) {
            $second = $product->replicate();
            $second->product_slug = 'rem-second-'.bin2hex(random_bytes(6));
            $second->product_price = $secondPrice.'.00';
            $second->save();
            DB::table('products')->where('product_id', $second->product_id)->update(['stock' => 10, 'is_active' => true]);
            $this->postJson('/cart/items', ['product_id' => $second->product_id, 'quantity' => 2])->assertOk();
        }
        $id = $this->postJson('/checkout', [
            'address_id' => $address->id, 'idempotency_key' => 'rem-'.bin2hex(random_bytes(8)),
            'voucher_code' => $voucher?->code,
        ])->assertCreated()->json('order.id');

        return Order::with('items')->findOrFail($id);
    }

    private function event(Order $order, string $status = 'settlement', string $fraud = 'accept', string $suffix = 'first'): array
    {
        $body = [
            'order_id' => (string) $order->id, 'status_code' => '200',
            'gross_amount' => $order->total.'.00', 'transaction_id' => 'rem-'.$order->id.'-'.$suffix,
            'transaction_status' => $status, 'fraud_status' => $fraud, 'currency' => 'IDR',
        ];
        $body['signature_key'] = hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].$this->providerKey);

        return $body;
    }

    private function deliver(array ...$events): void
    {
        $sequence = Http::sequence();
        foreach ($events as $event) {
            $sequence->push($event);
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.sandbox.midtrans.com/v2/*' => $sequence]);
        foreach ($events as $event) {
            $this->postJson('/webhooks/midtrans', $event)->assertOk();
        }
    }

    private function assertPaidOnce(Order $order): void
    {
        $this->assertSame(1, $order->payments()->where('outcome', 'paid')->count());
        $this->assertSame($order->total, (int) $order->payments()->where('outcome', 'paid')->sum('amount'));
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'paid')->count());
        $this->assertDatabaseHas('order_notifications', ['order_id' => $order->id, 'status' => 'paid']);
        $this->assertSame(1, DB::table('order_notifications')->where('order_id', $order->id)->where('status', 'paid')->count());
    }

    public function test_txn02_capture_challenge_then_accept_recognizes_payment_once(): void
    {
        $order = $this->order();
        $challenge = $this->event($order, 'capture', 'challenge');
        $accept = $this->event($order, 'capture');
        $this->deliver($challenge);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertFalse($order->fresh()->hasSettledPayment());
        $this->deliver($accept, $accept, $challenge);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertTrue($order->fresh()->hasSettledPayment());
        $this->assertPaidOnce($order);
        $this->assertSame('committed', $order->inventoryReservations()->sole()->status);
        $this->assertSame(9, (int) Product::findOrFail($order->items->first()->product_id)->stock);
        Http::assertSentCount(3); // Factory HTTP baru untuk setiap batch.
    }

    public function test_txn05_same_transaction_settlement_reconciles_without_false_refund_alarm(): void
    {
        $order = $this->order();
        Log::spy();
        $settlement = $this->event($order);
        $this->deliver($this->event($order, 'capture'), $settlement, $settlement);

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertPaidOnce($order);
        $this->assertFalse($order->payments()->where('outcome', 'order_not_payable')->exists());
        $this->assertSame('reconciled', $order->payments()->where('event_status', 'settlement')->sole()->outcome);
        $this->assertSame(2, $order->payments()->count());
        Log::shouldNotHaveReceived('alert');
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_txn05_distinct_second_payment_is_not_mistaken_for_reconciliation(): void
    {
        $order = $this->order();
        $this->deliver($this->event($order), $this->event($order, suffix: 'second'));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertPaidOnce($order);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id, 'provider_transaction_id' => 'rem-'.$order->id.'-second',
            'outcome' => 'order_not_payable', 'amount' => $order->total,
        ]);
        $this->assertDatabaseCount('refunds', 0); // Tidak mengarang refund/provider evidence.
    }

    public function test_txn03_customer_and_domain_cancel_paid_or_processing_fail_closed(): void
    {
        foreach (['paid', 'processing'] as $status) {
            $order = $this->order(discount: 50000);
            $this->deliver($this->event($order));
            if ($status === 'processing') {
                app(CheckoutService::class)->transition($order->fresh(), $status);
            }
            $history = $order->statusHistory()->count();
            $this->postJson('/orders/'.$order->id.'/cancel')->assertUnprocessable();
            try {
                app(CheckoutService::class)->transition($order->fresh(), 'cancelled');
                $this->fail('Transisi domain tidak boleh melewati guard pembatalan berbayar.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
            $this->assertSame($status, $order->fresh()->status);
            $this->assertFalse($order->fresh()->isCancellable());
            $this->assertSame($history, $order->statusHistory()->count());
            $this->assertSame(9, (int) Product::findOrFail($order->items->first()->product_id)->stock);
            $this->assertSame(1, (int) $order->voucher->fresh()->used);
            $this->assertSame('committed', $order->inventoryReservations()->sole()->status);
            $this->assertPaidOnce($order);
        }
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_txn03_pending_cancellation_still_restores_stock_and_voucher_once(): void
    {
        $order = $this->order(discount: 50000);
        $this->postJson('/orders/'.$order->id.'/cancel')->assertOk();
        $this->postJson('/orders/'.$order->id.'/cancel')->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, (int) Product::findOrFail($order->items->first()->product_id)->stock);
        $this->assertSame(0, (int) $order->voucher->fresh()->used);
        $this->assertSame('released', $order->inventoryReservations()->sole()->status);
        $this->assertSame(1, $order->statusHistory()->where('to_status', 'cancelled')->count());
    }

    private function complete(Order $order): void
    {
        $this->deliver($this->event($order));
        foreach (['processing', 'shipped', 'completed'] as $status) {
            app(CheckoutService::class)->transition($order->fresh(), $status);
        }
    }

    public function test_txn04_discounted_full_return_can_request_local_refund_without_moving_money(): void
    {
        $order = $this->order(discount: 50000);
        $this->complete($order);
        $return = $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('return.requested_refund_amount', 200000)->json('return');
        $this->postJson('/returns/'.$return['id'].'/refunds', ['reason' => 'Synthetic return'])
            ->assertCreated();
        $this->assertDatabaseHas('refunds', ['return_id' => $return['id'], 'amount' => 200000, 'provider_refund_id' => null]);
        $this->assertFalse($order->payments()->whereIn('outcome', ['refund', 'refund_settlement'])->exists());
    }

    public function test_txn04_partial_quantity_rounding_conserves_net_value_across_requests(): void
    {
        // Tanpa rejection, saldo kuantitas + nominal aktif mempertahankan rounding kumulatif.
        // 3 x 100001 - 10000 = 290003; split 1 lalu 2 tidak boleh kehilangan/mencipta rupiah.
        $order = $this->order(price: 100001, quantity: 3, discount: 10000);
        $this->assertSame(290003, $order->total);
        $this->complete($order);
        $amounts = [];
        foreach ([1, 2] as $quantity) {
            $return = $this->postJson('/orders/'.$order->id.'/returns', [
                'reason' => 'damaged', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => $quantity]],
            ])->assertCreated()->json('return');
            $amounts[] = $return['requested_refund_amount'];
            $this->postJson('/returns/'.$return['id'].'/refunds', ['reason' => 'Synthetic partial return'])->assertCreated();
        }
        $this->assertSame([96667, 193336], $amounts);
        $this->assertSame($order->total, array_sum($amounts));
        $this->assertSame($order->total, (int) DB::table('refunds')->where('order_id', $order->id)->sum('amount'));
        $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
        ])->assertUnprocessable();
    }

    public function test_txn04_allocation_avoids_intermediate_integer_overflow(): void
    {
        $this->assertSame(3000000000, ReturnAllocation::portion(5000000000, 3000000000, 5000000000));
        $this->assertSame(intdiv(PHP_INT_MAX, 3), ReturnAllocation::portion(PHP_INT_MAX, 1, 3));
        $this->assertSame(PHP_INT_MAX, ReturnAllocation::portion(PHP_INT_MAX, 3, 3));
    }

    public function test_txn04_multiple_lines_and_split_quantities_conserve_discounted_total(): void
    {
        $order = $this->order(price: 100001, quantity: 3, discount: 10000, secondPrice: 70001);
        $this->assertSame(430005, $order->total);
        $this->complete($order);
        $items = $order->items()->orderBy('id')->get();
        $firstNet = intdiv(430005 * 300003, 440005);
        $expected = [intdiv($firstNet, 3), $firstNet - intdiv($firstNet, 3), 430005 - $firstNet];
        $actual = [];
        foreach ([[$items[0], 1], [$items[0], 2], [$items[1], 2]] as [$item, $quantity]) {
            $return = $this->postJson('/orders/'.$order->id.'/returns', [
                'reason' => 'damaged', 'items' => [['order_item_id' => $item->id, 'quantity' => $quantity]],
            ])->assertCreated()->json('return');
            $actual[] = $return['requested_refund_amount'];
            $this->postJson('/returns/'.$return['id'].'/refunds', ['reason' => 'Synthetic multiline return'])->assertCreated();
        }
        $this->assertSame($expected, $actual);
        $this->assertSame($order->total, array_sum($actual));
        $this->assertSame($order->total, (int) DB::table('refunds')->where('order_id', $order->id)->sum('amount'));
    }

    public function test_txn01_nested_deadlock_reaches_outer_owner_without_lookup_or_inner_retry(): void
    {
        // Uji kontrak private boundary secara sintetis; bukan simulasi rollback fisik InnoDB.
        $pdo = new \PDOException('Synthetic nested deadlock');
        $pdo->errorInfo = ['40001', 1213, 'Synthetic nested deadlock'];
        $query = new QueryException('mysql', 'synthetic statement', [], $pdo);
        $deadlock = new DeadlockException('Synthetic nested deadlock', 0, $query);
        $lookups = 0;
        $transactions = 0;
        $original = DB::getFacadeRoot();
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(2);
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('transaction')->andReturnUsing(function () use (&$transactions, $deadlock) {
            $transactions++;
            throw $deadlock;
        });
        $method = new \ReflectionMethod(CheckoutService::class, 'attempt');
        try {
            try {
                $method->invoke(app(CheckoutService::class), fn () => null, function () use (&$lookups) {
                    $lookups++;

                    return null;
                }, fn ($order) => $order);
                $this->fail('Deadlock bersarang wajib mencapai pemilik transaksi outer.');
            } catch (DeadlockException $exception) {
                $this->assertSame($deadlock, $exception);
            }
            $this->assertSame(1, $transactions);
            $this->assertSame(0, $lookups);
        } finally {
            DB::swap($original);
        }
    }

    public function test_txn07_expiry_rechecks_hold_created_after_candidate_snapshot(): void
    {
        $order = $this->order();
        $order->inventoryReservations()->update(['expires_at' => now()->subMinute()]);
        $injected = false;
        // Interleaving logis: job sudah memilih kandidat saat model diambil; bukan koneksi kedua.
        Order::retrieved(function (Order $candidate) use ($order, &$injected) {
            if (! $injected && $candidate->id === $order->id) {
                $injected = true;
                PaymentDeadLetter::create([
                    'order_id' => $order->id, 'provider' => 'midtrans', 'transaction_id' => 'rem-hold-'.$order->id,
                    'reason' => 'status_api_unavailable', 'attempts' => 1,
                    'raw_body' => '{}', 'last_attempted_at' => now(),
                ]);
            }
        });
        $released = (new ReleaseExpiredReservations)->handle(app(CheckoutService::class));
        $this->assertTrue($injected, 'Fixture harus menyisipkan hold setelah pemilihan kandidat.');
        $this->assertSame(0, $released);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('reserved', $order->inventoryReservations()->sole()->status);
        $this->assertSame(9, (int) Product::findOrFail($order->items->first()->product_id)->stock);
        PaymentDeadLetter::where('order_id', $order->id)->firstOrFail()->markResolved();
        $this->assertSame(1, (new ReleaseExpiredReservations)->handle(app(CheckoutService::class)));
        $this->assertSame(0, (new ReleaseExpiredReservations)->handle(app(CheckoutService::class)));
        $this->assertSame(10, (int) Product::findOrFail($order->items->first()->product_id)->stock);
    }

    public function test_txn07_dead_letter_write_has_transaction_boundary_for_order_lock(): void
    {
        $order = $this->order();
        $baseline = DB::transactionLevel(); // RefreshDatabase sudah memiliki transaksi test.
        $levels = [];
        PaymentDeadLetter::creating(function () use (&$levels) {
            $levels[] = DB::transactionLevel();
        });
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response([], 503)]);
        $this->postJson('/webhooks/midtrans', $this->event($order))->assertStatus(503);
        $this->assertCount(1, $levels);
        $this->assertGreaterThan($baseline, $levels[0], 'Hold writer harus ikut transaksi yang mengunci order; kedalaman saja bukan bukti row lock.');
        $this->assertDatabaseHas('payment_dead_letters', ['order_id' => $order->id, 'resolved_at' => null]);
    }

    public function test_txn02_legacy_recorded_capture_accept_is_append_only(): void
    {
        $order = $this->order();
        $event = $this->event($order, 'capture');
        $legacy = Payment::create([
            'order_id' => $order->id, 'provider' => 'midtrans',
            'provider_transaction_id' => $event['transaction_id'], 'provider_order_id' => (string) $order->id,
            'event_status' => 'capture', 'outcome' => 'recorded', 'amount' => $order->total,
            'payload' => ['transaction_status' => 'capture', 'fraud_status' => 'challenge'],
        ]);
        $snapshot = $legacy->fresh()->getRawOriginal();
        $this->deliver($event, $event);
        $this->assertSame($snapshot, $legacy->fresh()->getRawOriginal());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertPaidOnce($order);
        $this->assertSame('paid', $order->payments()->where('event_status', 'capture_accept')->sole()->outcome);
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_txn07_newer_failure_during_fetch_survives_success_and_requires_new_replay(): void
    {
        foreach ([false, true] as $existingHold) {
            $order = $this->order();
            $event = $this->event($order);
            $service = app(PaymentWebhookService::class);
            $writer = new \ReflectionMethod(PaymentWebhookService::class, 'deadLetter');
            if ($existingHold) {
                $writer->invoke($service, (string) $order->id, $event['transaction_id'], json_encode($event), 'status_api_unavailable');
            }
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['api.sandbox.midtrans.com/v2/*' => function () use ($writer, $service, $order, $event) {
                // Logical interleaving through the real writer, not proof of two-connection isolation.
                $writer->invoke($service, (string) $order->id, $event['transaction_id'], json_encode($event), 'status_api_unavailable');

                return Http::response($event);
            }]);
            $this->postJson('/webhooks/midtrans', $event)->assertOk();
            $letter = PaymentDeadLetter::where('order_id', $order->id)->sole();
            $this->assertNull($letter->resolved_at);
            $this->assertSame($existingHold ? 2 : 1, $letter->attempts);
            $this->assertPaidOnce($order);
            $baseline = DB::transactionLevel();
            $levels = [];
            DB::connection()->beforeExecuting(function (string $sql) use (&$levels) {
                if (str_starts_with(strtolower($sql), 'update') && str_contains($sql, 'payment_dead_letters') && str_contains($sql, 'resolved_at')) {
                    $levels[] = DB::transactionLevel();
                }
            });
            $this->deliver($event);
            $this->assertNotNull($letter->fresh()->resolved_at);
            $this->assertNotEmpty($levels);
            $this->assertGreaterThan($baseline, min($levels));
            $this->assertPaidOnce($order);
        }
    }

    public function test_txn_refund_number_preserves_full_uuid_and_fits_schema_for_max_order_id(): void
    {
        $first = Uuid::fromString('abcd1234-0000-4000-8000-000000000001');
        $second = Uuid::fromString('abcd1234-0000-4000-8000-000000000002');
        Str::createUuidsUsingSequence([$first, $second, $first], fn () => throw new \LogicException('Unexpected UUID request.'));
        try {
            $a = Refund::generateRefundNumber(PHP_INT_MAX);
            $b = Refund::generateRefundNumber(PHP_INT_MAX);
            $otherOrder = Refund::generateRefundNumber(PHP_INT_MAX - 1);
            $this->assertNotSame($a, $b, 'UUIDs sharing the same prefix must remain distinct.');
            $this->assertNotSame($a, $otherOrder, 'Different orders must have disjoint number namespaces.');
            foreach ([[$a, $first], [$b, $second]] as [$number, $uuid]) {
                $this->assertLessThanOrEqual(40, strlen($number));
                $parts = explode('-', $number, 3);
                $this->assertSame('REF', $parts[0]);
                $this->assertSame(22, strlen($parts[2]));
                $bytes = base64_decode(strtr($parts[2], '-_', '+/').'==', true);
                $this->assertSame(str_replace('-', '', $uuid->toString()), bin2hex($bytes));
                if (PHP_INT_SIZE === 8) {
                    $this->assertSame('1y2p0ij32e8e7', $parts[1], 'Order namespace must not lose precision through floating point.');
                    $this->assertSame(40, strlen($number));
                }
            }
        } finally {
            Str::createUuidsNormally();
        }
    }

    public function test_txn_refund_number_forced_collision_uses_next_candidate_without_mutating_existing_refund(): void
    {
        $order = $this->order(quantity: 2);
        $this->complete($order);
        $a = $this->claim($order, 1);
        $b = $this->claim($order, 1);
        $uuids = [
            Uuid::fromString('abcd1234-0000-4000-8000-000000000001'),
            Uuid::fromString('abcd1234-0000-4000-8000-000000000001'),
            Uuid::fromString('abcd1234-0000-4000-8000-000000000002'),
        ];
        $calls = 0;
        Str::createUuidsUsing(function () use ($uuids, &$calls) {
            return $uuids[$calls++] ?? throw new \LogicException('Candidate bound exceeded.');
        });
        try {
            $service = app(RefundService::class);
            $existing = $service->request(OrderReturn::findOrFail($a['id']), 'Synthetic first claim');
            $snapshot = $existing->fresh()->getRawOriginal();
            $created = $service->request(OrderReturn::findOrFail($b['id']), 'Synthetic second claim');
            $this->assertSame(3, $calls, 'One existing UUID, one actual collision, then one fresh candidate.');
            $this->assertNotSame($existing->refund_number, $created->refund_number);
            $this->assertSame($snapshot, $existing->fresh()->getRawOriginal());
            $this->assertSame(Refund::STATUS_DIAJUKAN, $created->status);
            $this->assertNull($created->provider_refund_id);
            $this->assertSame(2, Refund::where('order_id', $order->id)->count());
            $this->assertSame($order->total, (int) Refund::where('order_id', $order->id)->sum('amount'));
            $this->assertPaidOnce($order);
            $this->assertFalse($order->payments()->whereIn('outcome', ['refund', 'refund_settlement'])->exists());
        } finally {
            Str::createUuidsNormally();
        }
    }

    public function test_txn_refund_number_exhausted_candidates_fail_closed_without_mutation(): void
    {
        $order = $this->order(quantity: 2);
        $this->complete($order);
        $a = $this->claim($order, 1);
        $b = $this->claim($order, 1);
        $uuid = Uuid::fromString('abcd1234-0000-4000-8000-000000000001');
        $calls = 0;
        Str::createUuidsUsing(function () use ($uuid, &$calls) {
            $calls++;
            if ($calls > 4) {
                throw new \LogicException('Candidate bound exceeded.');
            }

            return $uuid;
        });
        try {
            $service = app(RefundService::class);
            $service->request(OrderReturn::findOrFail($a['id']), 'Synthetic first claim');
            $state = function () use ($order, $a, $b): array {
                $rows = fn ($query) => $query->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

                return [
                    'order' => $order->fresh()->getRawOriginal(),
                    'refunds' => $rows(DB::table('refunds')->where('order_id', $order->id)),
                    'returns' => $rows(DB::table('returns')->where('order_id', $order->id)),
                    'return_items' => $rows(DB::table('return_items')->whereIn('return_id', [$a['id'], $b['id']])),
                    'payments' => $rows(DB::table('payments')->where('order_id', $order->id)),
                ];
            };
            $before = $state();
            $originalLocale = app()->getLocale();
            try {
                app()->setLocale('en');
                $service->request(OrderReturn::findOrFail($b['id']), 'Synthetic exhausted claim');
                $this->fail('Exhausted candidate collisions must never report refund creation success.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('refund_number', $exception->errors());
                $this->assertSame(['A refund reference cannot be issued yet. Please try again.'], $exception->errors()['refund_number']);
            } finally {
                app()->setLocale($originalLocale);
            }
            $this->assertSame(4, $calls, 'One initial creation plus exactly three rejected candidates.');
            $this->assertSame($before, $state());
        } finally {
            Str::createUuidsNormally();
        }
    }

    private function claim(Order $order, int $quantity): array
    {
        return $this->postJson('/orders/'.$order->id.'/returns', [
            'reason' => 'damaged', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => $quantity]],
        ])->assertCreated()->json('return');
    }

    public function test_txn04_rejected_first_or_middle_claim_reclaims_actual_stored_balance(): void
    {
        foreach ([0, 1] as $rejectedIndex) {
            $order = $this->order(price: 100001, quantity: 3, discount: 10000);
            $this->complete($order);
            $claims = $rejectedIndex === 0
                ? [$this->claim($order, 1), $this->claim($order, 2)]
                : [$this->claim($order, 1), $this->claim($order, 1), $this->claim($order, 1)];
            OrderReturn::findOrFail($claims[$rejectedIndex]['id'])->update(['status' => OrderReturn::STATUS_REJECTED]);
            $active = array_values(array_filter($claims, fn ($claim, $index) => $index !== $rejectedIndex, ARRAY_FILTER_USE_BOTH));
            $replacement = $this->claim($order, 1);
            $this->assertSame(290003 - array_sum(array_column($active, 'requested_refund_amount')), $replacement['requested_refund_amount']);
            foreach ([...$active, $replacement] as $claim) {
                $this->postJson('/returns/'.$claim['id'].'/refunds', ['reason' => 'Synthetic reclaim'])->assertCreated();
            }
            $this->assertSame(290003, (int) Refund::where('order_id', $order->id)->sum('amount'));
        }
    }

    public function test_txn04_r2_two_rejections_reclaim_released_balance_split_or_whole(): void
    {
        foreach ([false, true] as $whole) {
            $order = $this->order(price: 100001, quantity: 4, discount: 2);
            $this->complete($order);
            $this->assertSame(400002, $order->total);
            $history = [];
            foreach ([100000, 100001, 100000, 100001] as $index => $amount) {
                // Valid pre-v2 history, explicitly not backfilled with invented provenance.
                $return = OrderReturn::create([
                    'order_id' => $order->id, 'user_id' => $order->user_id,
                    'return_number' => OrderReturn::generateReturnNumber($order->id),
                    'status' => in_array($index, [0, 2], true) ? OrderReturn::STATUS_REJECTED : OrderReturn::STATUS_PENDING,
                    'reason' => 'damaged', 'requested_refund_amount' => $amount,
                ]);
                $line = $return->items()->create([
                    'order_item_id' => $order->items->first()->id, 'product_id' => $order->items->first()->product_id,
                    'quantity' => 1, 'unit_price' => 100001, 'subtotal' => $amount,
                ]);
                $history[] = [$line, $line->fresh()->getRawOriginal()];
            }
            $activeIds = OrderReturn::where('order_id', $order->id)->where('status', OrderReturn::STATUS_PENDING)->pluck('id')->all();
            $newIds = [];
            $quantityBefore = 2;
            $amountBefore = 200000;
            foreach ($whole ? [2] : [1, 1] as $quantity) {
                $claim = $this->claim($order, $quantity);
                $this->assertSame(100000 * $quantity, $claim['requested_refund_amount']);
                $newIds[] = $claim['id'];
                $line = OrderReturn::findOrFail($claim['id'])->items()->sole();
                $this->assertSame(ReturnAllocation::VERSION, $line->allocation_version);
                $this->assertSame($quantityBefore, $line->allocation_quantity_before);
                $this->assertSame($amountBefore, $line->allocation_amount_before);
                $this->assertSame(ReturnAllocation::portion($line->allocation_amount_before, $quantity, $line->allocation_quantity_before), $line->subtotal);
                $quantityBefore -= $quantity;
                $amountBefore -= $line->subtotal;
            }
            foreach ([...$activeIds, ...$newIds] as $id) {
                $this->postJson('/returns/'.$id.'/refunds', ['reason' => 'Synthetic R2 reclaim'])->assertCreated();
            }
            $this->assertSame(400002, (int) Refund::where('order_id', $order->id)->sum('amount'));
            foreach ($history as [$line, $snapshot]) {
                $this->assertSame($snapshot, $line->fresh()->getRawOriginal());
            }
            $this->assertPaidOnce($order);
        }
    }

    public function test_txn04_r2_rollback_refuses_to_discard_allocation_provenance(): void
    {
        $order = $this->order();
        $this->complete($order);
        $claim = $this->claim($order, 1);
        $line = OrderReturn::findOrFail($claim['id'])->items()->sole();
        $this->assertSame(ReturnAllocation::VERSION, $line->allocation_version);
        $migration = require database_path('migrations/2026_10_21_000060_add_return_allocation_provenance.php');
        try {
            $migration->down();
            $this->fail('Rollback must not erase provenance required to interpret stored money.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('discard return allocation provenance', $exception->getMessage());
        }
        $this->assertSame(ReturnAllocation::VERSION, $line->fresh()->allocation_version);
        $this->assertTrue(Schema::hasColumn('return_items', 'allocation_amount_before'));
    }

    public function test_txn07_r2_fifty_invalid_letters_cannot_starve_valid_letter_fifty_one(): void
    {
        $this->freezeTime();
        $order = $this->order();
        $invalidIds = [];
        for ($index = 0; $index < PaymentDeadLetter::REPLAY_BATCH_SIZE; $index++) {
            $invalidIds[] = PaymentDeadLetter::create([
                'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
                'transaction_id' => 'invalid-'.$index, 'attempts' => 1, 'raw_body' => '{}',
                'last_attempted_at' => now()->subMinute(),
            ])->id;
        }
        $event = $this->event($order);
        $valid = PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
            'transaction_id' => $event['transaction_id'], 'attempts' => 1,
            'raw_body' => json_encode($event), 'last_attempted_at' => now(),
        ]);
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response($event)]);
        $runs = 0;
        do {
            $this->artisan('payments:replay-dead-letters')->assertSuccessful();
            $runs++;
        } while ($valid->fresh()->resolved_at === null && $runs < PaymentDeadLetter::MAX_REPLAY_ATTEMPTS);
        $this->assertSame(PaymentDeadLetter::MAX_REPLAY_ATTEMPTS, $runs, 'Frozen ties exercise the worst-case bounded scan.');
        $this->assertNotNull($valid->fresh()->resolved_at);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertPaidOnce($order);
        Http::assertSentCount(1);
        $this->assertSame(50, PaymentDeadLetter::whereIn('id', $invalidIds)->whereNull('resolved_at')
            ->where('attempts', PaymentDeadLetter::MAX_REPLAY_ATTEMPTS)->where('reason', 'quarantined:replay_http_422')->count());
        $this->assertSame(0, PaymentDeadLetter::eligibleForReplay()->whereIn('id', $invalidIds)->count());
        $this->travelBack();
    }

    public function test_txn07_r2_replay_failure_cas_preserves_newer_failure_body_and_version(): void
    {
        $order = $this->order();
        $event = $this->event($order);
        $letter = PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'old_failure',
            'transaction_id' => $event['transaction_id'], 'attempts' => 1,
            'raw_body' => json_encode($event), 'last_attempted_at' => now()->subMinute(),
        ]);
        $service = app(PaymentWebhookService::class);
        $writer = new \ReflectionMethod(PaymentWebhookService::class, 'deadLetter');
        $newBody = json_encode($this->event($order, 'capture', 'challenge'));
        Http::fake(['api.sandbox.midtrans.com/v2/*' => function () use ($writer, $service, $order, $event, $newBody) {
            $writer->invoke($service, (string) $order->id, $event['transaction_id'], $newBody, 'newer_failure');

            return Http::response([...$event, 'currency' => 'USD']);
        }]);
        $this->assertSame(422, $service->replay($letter));
        $current = $letter->fresh();
        $this->assertSame(2, $current->attempts);
        $this->assertSame('newer_failure', $current->reason);
        $this->assertSame($newBody, $current->raw_body);
        $this->assertNull($current->resolved_at);
        $this->assertNull($service->replay($letter), 'A stale selected version must be skipped.');
        Http::assertSentCount(1);
    }

    public function test_txn07_replay_503_preserves_newer_live_failure_before_final_cas(): void
    {
        $order = $this->order();
        $event = $this->event($order);
        $letter = PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'failure_A',
            'transaction_id' => $event['transaction_id'], 'attempts' => 1,
            'raw_body' => json_encode($event), 'last_attempted_at' => now()->subMinute(),
        ]);
        $service = app(PaymentWebhookService::class);
        $writer = new \ReflectionMethod(PaymentWebhookService::class, 'deadLetter');
        $bodyB = json_encode($this->event($order, 'capture', 'challenge'));
        $snapshotB = null;
        Http::fake(['api.sandbox.midtrans.com/v2/*' => function () use ($writer, $service, $order, $event, $bodyB, $letter, &$snapshotB) {
            $writer->invoke($service, (string) $order->id, $event['transaction_id'], $bodyB, 'failure_B');
            $snapshotB = $letter->fresh()->getRawOriginal();

            return Http::response([], 503);
        }]);
        $this->assertSame(503, $service->replay($letter));
        $this->assertNotNull($snapshotB, 'Writer B must run during fetch A.');
        $current = $letter->fresh();
        $this->assertSame($snapshotB, $current->getRawOriginal());
        $this->assertSame(2, $current->attempts);
        $this->assertSame('failure_B', $current->reason);
        $this->assertSame($bodyB, $current->raw_body);
        $this->assertNull($current->resolved_at);
        $this->assertSame('pending', $order->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_txn07_replay_pins_selected_version_before_success_observation_or_circuit_failure(): void
    {
        foreach ([200, 503] as $status) {
            $order = $this->order();
            $event = $this->event($order);
            $letter = PaymentDeadLetter::create([
                'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'failure_A',
                'transaction_id' => $event['transaction_id'], 'attempts' => 1,
                'raw_body' => json_encode($event), 'last_attempted_at' => now()->subMinute(),
            ]);
            $service = app(PaymentWebhookService::class);
            $writer = new \ReflectionMethod(PaymentWebhookService::class, 'deadLetter');
            $bodyB = json_encode($this->event($order, 'capture', 'challenge'));
            $snapshotB = null;
            $originalCache = Cache::getFacadeRoot();
            // This gate runs AFTER replay eligibility precheck, BEFORE live handle would observe a hold.
            $cache = Cache::partialMock();
            $cache->setApplication($this->app);
            $cache->shouldReceive('get')->with('midtrans:circuit_open_until', 0)->once()
                ->andReturnUsing(function () use ($writer, $service, $order, $event, $bodyB, $letter, &$snapshotB, $status) {
                    $writer->invoke($service, (string) $order->id, $event['transaction_id'], $bodyB, 'failure_B');
                    $snapshotB = $letter->fresh()->getRawOriginal();

                    return $status === 503 ? now()->addMinute()->timestamp : 0;
                });
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response($event)]);
            try {
                $this->assertSame($status, $service->replay($letter));
            } finally {
                Cache::swap($originalCache);
            }
            $this->assertNotNull($snapshotB);
            $this->assertSame($snapshotB, $letter->fresh()->getRawOriginal());
            $this->assertSame(2, $letter->fresh()->attempts);
            $this->assertNull($letter->fresh()->resolved_at);
            Http::assertSentCount($status === 200 ? 1 : 0);
            if ($status === 200) {
                $this->assertPaidOnce($order);
            } else {
                $this->assertSame('pending', $order->fresh()->status);
            }
        }
    }

    public function test_txn07_replay_circuit_503_without_interleaving_counts_exactly_once(): void
    {
        $order = $this->order();
        $event = $this->event($order);
        $body = json_encode($event);
        $letter = PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'failure_A',
            'transaction_id' => $event['transaction_id'], 'attempts' => 1,
            'raw_body' => $body, 'last_attempted_at' => now()->subMinute(),
        ]);
        Cache::put('midtrans:circuit_open_until', now()->addMinute()->timestamp, 120);
        try {
            $this->assertSame(503, app(PaymentWebhookService::class)->replay($letter));
        } finally {
            Cache::forget('midtrans:circuit_open_until');
        }
        $current = $letter->fresh();
        $this->assertSame(2, $current->attempts);
        $this->assertSame('replay_http_503', $current->reason);
        $this->assertSame($body, $current->raw_body);
        $this->assertNull($current->resolved_at);
        Http::assertNothingSent();
    }

    public function test_txn07_r2_replay_503_is_counted_once_and_quarantined_hold_still_blocks_expiry(): void
    {
        $order = $this->order();
        $event = $this->event($order);
        $letter = PaymentDeadLetter::create([
            'order_id' => $order->id, 'provider' => 'midtrans', 'reason' => 'status_api_unavailable',
            'transaction_id' => $event['transaction_id'], 'attempts' => PaymentDeadLetter::MAX_REPLAY_ATTEMPTS - 1,
            'raw_body' => json_encode($event), 'last_attempted_at' => now()->subMinute(),
        ]);
        Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response([], 503)]);
        $this->assertSame(503, app(PaymentWebhookService::class)->replay($letter));
        $this->assertSame(PaymentDeadLetter::MAX_REPLAY_ATTEMPTS, $letter->fresh()->attempts);
        $this->assertSame('quarantined:replay_http_503', $letter->fresh()->reason);
        $this->assertSame(json_encode($event), $letter->fresh()->raw_body);
        $this->assertSame(1, PaymentDeadLetter::where('order_id', $order->id)->count());
        $this->assertNull($letter->fresh()->resolved_at);
        $order->inventoryReservations()->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(0, (new ReleaseExpiredReservations)->handle(app(CheckoutService::class)));
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('reserved', $order->inventoryReservations()->sole()->status);
    }

    public function test_txn04_legacy_gross_partial_requires_manual_reconciliation_even_with_shipping(): void
    {
        foreach ([0, 100000] as $shipping) {
            $order = $this->order(price: 250000, quantity: 2, discount: 100000);
            $order->update(['shipping_fee' => $shipping, 'total' => 400000 + $shipping]);
            $this->complete($order);
            $legacy = OrderReturn::create([
                'order_id' => $order->id, 'user_id' => $order->user_id,
                'return_number' => OrderReturn::generateReturnNumber($order->id),
                'status' => OrderReturn::STATUS_PENDING, 'reason' => 'damaged', 'requested_refund_amount' => 250000,
            ]);
            $legacy->items()->create([
                'order_item_id' => $order->items->first()->id, 'product_id' => $order->items->first()->product_id,
                'quantity' => 1, 'unit_price' => 250000, 'subtotal' => 250000,
            ]);
            $snapshot = $legacy->fresh()->getRawOriginal();
            $this->postJson('/orders/'.$order->id.'/returns', [
                'reason' => 'damaged', 'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
            ])->assertUnprocessable()->assertJsonValidationErrors('manual_reconciliation');
            $this->postJson('/returns/'.$legacy->id.'/refunds', ['reason' => 'Synthetic legacy claim'])
                ->assertUnprocessable()->assertJsonValidationErrors('manual_reconciliation');
            $this->assertSame($snapshot, $legacy->fresh()->getRawOriginal());
            $this->assertSame(250000, (int) $legacy->items()->sole()->subtotal);
            $this->assertSame(0, Refund::where('order_id', $order->id)->count());
            $this->assertPaidOnce($order);
        }
    }

    public function test_txn04_multiline_tax_shipping_and_zero_net_never_refund_shipping(): void
    {
        foreach ([10000, 440005] as $discount) {
            $order = $this->order(price: 100001, quantity: 3, discount: $discount, secondPrice: 70001);
            $tax = $discount === 440005 ? 0 : 12345;
            $net = 440005 - $discount + $tax;
            $order->update(['tax' => $tax, 'shipping_fee' => 60000, 'total' => $net + 60000]);
            $this->complete($order);
            $items = $order->items()->orderBy('id')->get();
            $return = $this->postJson('/orders/'.$order->id.'/returns', [
                'reason' => 'damaged', 'items' => $items->map(fn ($item) => ['order_item_id' => $item->id, 'quantity' => $item->quantity])->all(),
            ])->assertCreated()->json('return');
            $this->assertSame($net, $return['requested_refund_amount']);
            $this->assertSame($net, array_sum(array_column($return['items'], 'subtotal')));
            $response = $this->postJson('/returns/'.$return['id'].'/refunds', ['reason' => 'Synthetic tax return']);
            if ($net === 0) {
                $response->assertUnprocessable()->assertJsonValidationErrors('amount');
            } else {
                $response->assertCreated();
                $this->assertDatabaseHas('refunds', ['return_id' => $return['id'], 'amount' => $net]);
            }
            $this->assertFalse($order->payments()->whereIn('outcome', ['refund', 'refund_settlement'])->exists());
        }
    }

    private function mysqlQueryFailure(int $code): QueryException
    {
        $pdo = new class($code) extends \PDOException
        {
            public function __construct(int $driverCode)
            {
                $state = $driverCode === 1213 ? '40001' : 'HY000';
                $message = $driverCode === 1213 ? 'Deadlock found when trying to get lock; try restarting transaction'
                    : 'Lock wait timeout exceeded; try restarting transaction';
                parent::__construct('SQLSTATE['.$state.']: General error: '.$driverCode.' '.$message);
                $this->code = $state;
                $this->errorInfo = [$state, $driverCode, $message];
            }
        };

        return new QueryException('mysql', 'update synthetic set value = 1', [], $pdo);
    }

    public function test_txn01_realistic_wrapped_timeout_is_not_retried_by_outer_owner(): void
    {
        foreach ([1205, 1213] as $code) {
            $query = $this->mysqlQueryFailure($code);
            $wrapped = new DeadlockException($query->getMessage(), 0, new \RuntimeException('Framework wrapper', 0, $query));
            $this->assertSame($code === 1213, TransactionLockRetry::retryable($wrapped, 'mysql'));
            $original = DB::getFacadeRoot();
            $calls = 0;
            DB::partialMock()->shouldReceive('transactionLevel')->andReturn(0);
            DB::shouldReceive('getDriverName')->andReturn('mysql');
            DB::shouldReceive('transaction')->andReturnUsing(function ($callback, $attempts) use (&$calls, $wrapped) {
                $this->assertSame(1, $attempts, 'Framework generic retry must never receive multiple attempts.');
                $calls++;
                throw $wrapped;
            });
            try {
                try {
                    TransactionLockRetry::transaction(fn () => null, 3);
                    $this->fail('Expected injected exception.');
                } catch (DeadlockException $exception) {
                    $this->assertSame($wrapped, $exception);
                }
                $this->assertSame($code === 1205 ? 1 : 3, $calls);
            } finally {
                DB::swap($original);
            }
        }
        $this->assertFalse(TransactionLockRetry::retryable(new DeadlockException('Deadlock found when trying to get lock'), 'mysql'));
    }

    public function test_txn01_real_framework_boundary_rolls_back_before_allowlisted_retry_only(): void
    {
        // Real Laravel transaction lifecycle over a fake PDO; NOT evidence of physical InnoDB deadlocks.
        foreach ([1205, 1213] as $code) {
            $retry = $code === 1213;
            $query = $this->mysqlQueryFailure($code);
            $error = new DeadlockException($query->getMessage(), 0, $query);
            $pdo = \Mockery::mock(\PDO::class);
            $pdo->shouldReceive('beginTransaction')->times($retry ? 2 : 1)->andReturnTrue();
            $pdo->shouldReceive('inTransaction')->once()->andReturnTrue();
            $pdo->shouldReceive('rollBack')->once()->andReturnTrue();
            if ($retry) {
                $pdo->shouldReceive('commit')->once()->andReturnTrue();
            } else {
                $pdo->shouldNotReceive('commit');
            }
            $connection = new MySqlConnection($pdo, 'synthetic', '', ['driver' => 'mysql']);
            $original = DB::getFacadeRoot();
            $calls = 0;
            DB::swap($connection);
            try {
                try {
                    $result = TransactionLockRetry::transaction(function () use (&$calls, $error) {
                        $calls++;
                        if ($calls === 1) {
                            throw $error;
                        }

                        return 'committed';
                    });
                    $this->assertTrue($retry, '1205 must escape after rollback, never succeed via retry.');
                    $this->assertSame('committed', $result);
                } catch (DeadlockException $caught) {
                    $this->assertFalse($retry);
                    $this->assertSame($error, $caught);
                }
                $this->assertSame($retry ? 2 : 1, $calls);
                $this->assertSame(0, $connection->transactionLevel());
            } finally {
                DB::swap($original);
            }
        }
    }

    public function test_txn01_nested_wrapped_1205_never_performs_lookup_or_retry(): void
    {
        $query = $this->mysqlQueryFailure(1205);
        $error = new DeadlockException($query->getMessage(), 0, new \RuntimeException('wrapper', 0, $query));
        $original = DB::getFacadeRoot();
        $lookups = 0;
        DB::partialMock()->shouldReceive('transactionLevel')->andReturn(2);
        DB::shouldReceive('getDriverName')->andReturn('mysql');
        DB::shouldReceive('transaction')->once()->andThrow($error);
        try {
            try {
                (new \ReflectionMethod(CheckoutService::class, 'attempt'))->invoke(app(CheckoutService::class), fn () => null,
                    function () use (&$lookups) {
                        $lookups++;

                        return null;
                    }, fn ($order) => $order);
                $this->fail('1205 must propagate to the owner without retry.');
            } catch (DeadlockException $caught) {
                $this->assertSame($error, $caught);
            }
            $this->assertSame(0, $lookups);
        } finally {
            DB::swap($original);
        }
    }

    public function test_txn08_delivered_label_is_not_overridden_by_awb(): void
    {
        $shipment = new Shipment([
            'status' => Shipment::STATUS_DELIVERED, 'tracking_number' => 'SYNTHETIC-AWB',
            'delivered_at' => now(),
        ]);
        $shipment->setRelation('trackingEvents', collect());
        $summary = $shipment->trackingSummary();
        $this->assertSame(Shipment::STATUS_DELIVERED, $summary['status']);
        $this->assertSame(__('customer_messages.shipment.delivered'), $summary['status_label']);
        $this->assertTrue($summary['awb_assigned']);
        $this->assertSame('SYNTHETIC-AWB', $summary['tracking_number']);
        $this->assertNotNull($summary['delivered_at']);
    }
}
