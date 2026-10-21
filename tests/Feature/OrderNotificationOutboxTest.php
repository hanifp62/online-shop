<?php

namespace Tests\Feature;

use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\OrderNotificationDispatcher;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderNotificationOutboxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated in-memory database, no enclosing RefreshDatabase transaction.
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function order(): Order
    {
        return Order::create([
            'customer_email' => 'guest@example.test', 'locale' => 'fr', 'status' => 'pending',
            'idempotency_key' => 'outbox', 'payload_hash' => str_repeat('a', 64),
        ]);
    }

    public function test_queue_failure_at_real_commit_leaves_retryable_notification(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'unavailable_jobs']);
        $order = $this->order();
        $failure = null;
        DB::beginTransaction();
        app(CheckoutService::class)->markPaid($order);
        $this->assertSame(1, DB::transactionLevel());
        try {
            DB::commit();
        } catch (\Throwable $exception) {
            $failure = $exception;
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertNull($failure, 'Queue outage must not fail an already committed checkout.');
        $this->assertDatabaseHas('order_notifications', ['order_id' => $order->id, 'dispatched_at' => null]);

        Mail::fake();
        $this->travel(6)->minutes();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, fn ($mail) => $mail->locale === 'fr' && $mail->hasTo('guest@example.test'));
        Mail::assertQueued(OrderStatusChanged::class, 1);
        $this->assertNotNull(DB::table('order_notifications')->value('dispatched_at'));
    }

    public function test_rolled_back_claim_is_not_queued_or_retried(): void
    {
        Mail::fake();
        $order = $this->order();
        DB::beginTransaction();
        app(CheckoutService::class)->markPaid($order);
        DB::rollBack();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertNothingQueued();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseMissing('order_notifications', ['order_id' => $order->id]);
    }

    public function test_pending_row_without_email_is_skipped(): void
    {
        Mail::fake();
        $order = $this->order();
        DB::table('order_notifications')->insert([
            'order_id' => $order->id, 'status' => 'paid', 'channel' => 'mail',
            'created_at' => now()->subMinutes(6),
        ]);
        $order->update(['customer_email' => null]);
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertNothingQueued();
        $this->assertNull(DB::table('order_notifications')->value('dispatched_at'));
    }

    public function test_overlapping_dispatch_does_not_push_twice(): void
    {
        $order = $this->order();
        $id = DB::table('order_notifications')->insertGetId([
            'order_id' => $order->id, 'status' => 'paid', 'channel' => 'mail',
            'created_at' => now()->subMinutes(30),
        ]);
        $pushes = 0;
        $pending = \Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->andReturn($pending);
        $pending->shouldReceive('locale')->with('fr')->andReturnSelf();
        $pending->shouldReceive('queue')->andReturnUsing(function () use (&$pushes, $id) {
            $this->assertSame(0, DB::transactionLevel());
            if (++$pushes === 1) {
                app(OrderNotificationDispatcher::class)->dispatch($id);
                $this->artisan('orders:retry-notifications')->assertSuccessful();
            }
        });
        app(OrderNotificationDispatcher::class)->dispatch($id);
        $this->assertSame(1, $pushes);
    }

    public function test_missing_emails_do_not_starve_valid_rows_and_can_be_restored(): void
    {
        Mail::fake();
        for ($i = 0; $i < 101; $i++) {
            $order = Order::create([
                'customer_email' => $i === 100 ? 'guest@example.test' : null,
                'locale' => 'fr', 'status' => 'paid', 'idempotency_key' => 'orphan-'.$i,
                'payload_hash' => str_repeat('a', 64),
            ]);
            DB::table('order_notifications')->insert([
                'order_id' => $order->id, 'status' => 'paid', 'channel' => 'mail',
                'created_at' => now()->subMinutes(6),
            ]);
        }
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, 1);
        Order::first()->update(['customer_email' => 'restored@example.test']);
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, 2);
    }

    public function test_queue_failure_releases_lease_for_next_retry(): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'unavailable_jobs']);
        $order = $this->order();
        $id = DB::table('order_notifications')->insertGetId([
            'order_id' => $order->id, 'status' => 'paid', 'channel' => 'mail', 'created_at' => now()->subMinutes(6),
        ]);
        app(OrderNotificationDispatcher::class)->dispatch($id);
        $row = DB::table('order_notifications')->find($id);
        $this->assertNull($row->dispatched_at);
        $this->assertNull($row->lease_token);
        $this->assertNull($row->lease_expires_at);
    }

    public function test_expired_lease_is_recovered_and_stale_owner_cannot_overwrite(): void
    {
        Mail::fake();
        $order = $this->order();
        $id = DB::table('order_notifications')->insertGetId([
            'order_id' => $order->id, 'status' => 'paid', 'channel' => 'mail', 'created_at' => now()->subMinutes(30),
            'lease_token' => 'stale-owner', 'lease_expires_at' => now()->addMinutes(5),
        ]);
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->travel(6)->minutes();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, 1);
        $dispatchedAt = DB::table('order_notifications')->where('id', $id)->value('dispatched_at');
        $this->assertNotNull($dispatchedAt);

        // The stale owner's late completion/release is conditional on its token: no effect.
        $this->assertSame(0, DB::table('order_notifications')->where('id', $id)->where('lease_token', 'stale-owner')
            ->update(['dispatched_at' => null, 'lease_token' => null, 'lease_expires_at' => null]));
        $this->assertSame($dispatchedAt, DB::table('order_notifications')->where('id', $id)->value('dispatched_at'));
    }

    public function test_late_sender_cannot_complete_or_release_successor_lease(): void
    {
        $order = $this->order();
        foreach ([false, true] as $throw) {
            $status = $throw ? 'shipped' : 'paid';
            $id = DB::table('order_notifications')->insertGetId([
                'order_id' => $order->id, 'status' => $status, 'created_at' => now()->subMinutes(30),
            ]);
            $pending = \Mockery::mock(PendingMail::class);
            Mail::swap(\Mockery::mock(Mailer::class));
            Mail::shouldReceive('to')->andReturn($pending);
            $pending->shouldReceive('locale')->andReturnSelf();
            $pending->shouldReceive('queue')->once()->andReturnUsing(function () use ($id, $throw) {
                // Simulate a successor acquiring the expired lease while the first push stalls.
                DB::table('order_notifications')->where('id', $id)->update([
                    'lease_token' => 'successor', 'lease_expires_at' => now()->addMinutes(10),
                ]);
                if ($throw) {
                    throw new \RuntimeException('Queue push failed');
                }
            });
            app(OrderNotificationDispatcher::class)->dispatch($id);
            $this->assertDatabaseHas('order_notifications', [
                'id' => $id, 'lease_token' => 'successor', 'dispatched_at' => null,
            ]);
        }
    }

    private function pendingRow(string $status = 'paid', ?Order $order = null): int
    {
        return DB::table('order_notifications')->insertGetId([
            'order_id' => ($order ?? $this->order())->id, 'status' => $status, 'channel' => 'mail',
            'created_at' => now()->subMinutes(30),
        ]);
    }

    /** Throw before a matching query executes, emulating a lost connection without touching data. */
    private function failQuery(string $sqlFragment, int $id, int $times = 1): void
    {
        DB::connection()->beforeExecuting(function ($sql, $bindings) use ($sqlFragment, $id, &$times) {
            if ($times > 0 && str_contains($sql, $sqlFragment) && in_array($id, $bindings, true)) {
                $times--;
                throw new \RuntimeException('Simulated database fault');
            }
        });
    }

    public function test_claim_fault_is_reported_and_batch_continues(): void
    {
        Mail::fake();
        Exceptions::fake();
        $order = $this->order();
        $broken = $this->pendingRow('paid', $order);
        $healthy = $this->pendingRow('shipped', $order);
        $this->failQuery('update "order_notifications" set "lease_token"', $broken);

        $this->artisan('orders:retry-notifications')->assertSuccessful();

        Exceptions::assertReported(fn (\RuntimeException $e) => $e->getMessage() === 'Simulated database fault');
        Mail::assertQueued(OrderStatusChanged::class, 1);
        $this->assertNotNull(DB::table('order_notifications')->where('id', $healthy)->value('dispatched_at'));
        $this->assertNull(DB::table('order_notifications')->where('id', $broken)->value('dispatched_at'));
    }

    public function test_read_fault_after_claim_releases_lease_and_does_not_escape(): void
    {
        Mail::fake();
        Exceptions::fake();
        $id = $this->pendingRow();
        $this->failQuery('select * from "order_notifications" where "id" = ?', $id);

        app(OrderNotificationDispatcher::class)->dispatch($id);

        Exceptions::assertReportedCount(1);
        Mail::assertNothingQueued();
        $this->assertDatabaseHas('order_notifications', ['id' => $id, 'lease_token' => null, 'dispatched_at' => null]);
    }

    public function test_release_fault_is_reported_separately_and_retry_recovers(): void
    {
        Mail::fake();
        Exceptions::fake();
        $id = $this->pendingRow();
        $this->failQuery('select * from "order_notifications" where "id" = ?', $id);
        $this->failQuery('update "order_notifications" set "lease_token" = ?, "lease_expires_at" = ? where "id" = ? and "lease_token" = ?', $id);

        app(OrderNotificationDispatcher::class)->dispatch($id);

        Exceptions::assertReportedCount(2);
        // Release failed, so the lease remains; the row must still be recoverable on a later tick.
        $this->assertNotNull(DB::table('order_notifications')->where('id', $id)->value('lease_token'));
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertNothingQueued();
        $this->travel(11)->minutes();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, 1);
    }

    public function test_expired_lease_during_slow_push_can_duplicate_at_least_once(): void
    {
        $id = $this->pendingRow();
        $pushes = 0;
        $pending = \Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->andReturn($pending);
        $pending->shouldReceive('locale')->andReturnSelf();
        $pending->shouldReceive('queue')->andReturnUsing(function () use (&$pushes, $id) {
            if (++$pushes === 1) {
                // The first push stalls past the lease; a successor legitimately takes over.
                $this->travel(11)->minutes();
                app(OrderNotificationDispatcher::class)->dispatch($id);
            }
        });

        app(OrderNotificationDispatcher::class)->dispatch($id);

        // Documented at-least-once behaviour: both senders pushed. Exactly one completion persists.
        $this->assertSame(2, $pushes);
        $this->assertNotNull(DB::table('order_notifications')->where('id', $id)->value('dispatched_at'));
        $this->assertNull(DB::table('order_notifications')->where('id', $id)->value('lease_token'));
    }

    public function test_successful_dispatch_is_not_retried(): void
    {
        Mail::fake();
        DB::transaction(fn () => app(CheckoutService::class)->markPaid($this->order()));
        $this->travel(6)->minutes();
        $this->artisan('orders:retry-notifications')->assertSuccessful();
        Mail::assertQueued(OrderStatusChanged::class, 1);
        $this->assertNotNull(DB::table('order_notifications')->value('dispatched_at'));
    }
}
