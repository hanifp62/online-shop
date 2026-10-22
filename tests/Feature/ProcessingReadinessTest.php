<?php

namespace Tests\Feature;

use App\Jobs\RecordProcessingHeartbeat;
use App\Services\ProcessingReadiness;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\RedisQueue;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** CICD-07 / NFR-REM-002: simulated shared cache/async transport, not runtime worker evidence. */
class ProcessingReadinessTest extends TestCase
{
    private Repository $cache;

    private ArrayStore $storage;

    private DatabaseQueue $queue;

    private array $jobs = [];

    private int $queueSize = 0;

    private int $pendingSize = 0;

    private ?int $oldestPending = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config([
            'cache.default' => 'processing-test',
            'cache.stores.processing-test' => ['driver' => 'redis'],
            'queue.default' => 'processing-test',
            'queue.connections.processing-test' => ['driver' => 'database', 'queue' => 'processing-fixture'],
        ]);

        // Only the test double uses memory. Production rejects an actual ArrayStore.
        $this->storage = new ArrayStore;
        $store = Mockery::mock(RedisStore::class);
        $store->shouldReceive('get')->andReturnUsing(fn ($key) => $this->storage->get($key));
        $store->shouldReceive('forever')->andReturnUsing(fn ($key, $value) => $this->storage->forever($key, $value))->byDefault();
        $store->shouldReceive('lock')->andReturnUsing(fn ($key, $seconds) => $this->storage->lock($key, $seconds));
        $this->cache = new Repository($store);
        Cache::shouldReceive('store')->with('processing-test')->andReturn($this->cache)->byDefault();

        $this->queue = Mockery::mock(DatabaseQueue::class);
        $this->queue->shouldReceive('size')->with('processing-fixture')->andReturnUsing(fn () => $this->queueSize)->byDefault();
        $this->queue->shouldReceive('pendingSize')->with('processing-fixture')->andReturnUsing(fn () => $this->pendingSize)->byDefault();
        $this->queue->shouldReceive('creationTimeOfOldestPendingJob')->with('processing-fixture')
            ->andReturnUsing(fn () => $this->oldestPending)->byDefault();
        $this->queue->shouldReceive('push')->withArgs(function ($job, $data, $queue) {
            return $job instanceof RecordProcessingHeartbeat && $data === '' && $queue === 'processing-fixture';
        })->andReturnUsing(function ($job) {
            $this->jobs[] = $job;
            $this->queueSize++;

            return count($this->jobs);
        })->byDefault();
        Queue::shouldReceive('connection')->with('processing-test')->andReturn($this->queue)->byDefault();
    }

    public function test_missing_heartbeats_fail_even_when_queue_is_empty_and_reachable(): void
    {
        $report = $this->readiness(1);
        $this->assertSame('missing', $report['checks']['scheduler']['reason']);
        $this->assertSame('missing', $report['checks']['worker']['reason']);
        $this->assertSame(0, $report['checks']['queue']['size']);
        $this->assertSame([], $this->jobs, 'Readiness must not dispatch or manufacture evidence.');
        $this->assertNull($this->cache->get(ProcessingReadiness::STATE_KEY));
    }

    public function test_scheduler_alone_does_not_prove_a_worker_but_async_completion_does(): void
    {
        $this->assertTrue($this->service()->heartbeat());
        $this->readiness(1);
        $this->travel(5)->seconds();
        $this->consume($this->jobs[0]);

        $report = $this->readiness(0);
        $this->assertSame('processing-readiness.v1', $report['contract_version']);
        $this->assertSame('default_queue', $report['scope']);
        $this->assertSame(5, $report['checks']['scheduler']['age_seconds']);
        $this->assertSame(0, $report['checks']['worker']['age_seconds']);
        $this->assertSame(5, $report['checks']['worker']['request_age_seconds']);
        $this->assertSame(5, $report['checks']['worker']['latency_seconds']);
        $this->assertSame('ok', $report['checks']['queue']['status']);
    }

    public function test_redis_transport_uses_the_same_async_completion_contract(): void
    {
        config(['queue.connections.processing-test.driver' => 'redis']);
        $queue = Mockery::mock(RedisQueue::class);
        $queue->shouldReceive('size')->with('processing-fixture')->andReturn(0);
        $queue->shouldReceive('pendingSize')->with('processing-fixture')->andReturn(0);
        $queue->shouldReceive('creationTimeOfOldestPendingJob')->with('processing-fixture')->andReturn(null);
        $queue->shouldReceive('push')->once()->andReturnUsing(function ($job) {
            $this->jobs[] = $job;

            return 'synthetic-queue-id';
        });
        Queue::shouldReceive('connection')->with('processing-test')->andReturn($queue);
        $this->assertTrue($this->service()->heartbeat());
        $this->readiness(1);
        $runtime = Mockery::mock(RedisJob::class);
        $runtime->shouldReceive('getConnectionName')->andReturn('processing-test');
        $runtime->shouldReceive('getQueue')->andReturn('processing-fixture');
        $this->jobs[0]->setJob($runtime)->handle($this->service());
        $this->readiness(0);
    }

    public function test_freshness_and_latency_threshold_boundaries_are_inclusive(): void
    {
        $this->ready();
        $this->travel(180)->seconds();
        $this->readiness(0);
        $this->travel(1)->seconds();
        $this->readiness(1);
        $this->service()->heartbeat();
        $this->travel(60)->seconds();
        $this->consume($this->jobs[1]);
        $this->assertSame(60, $this->readiness(0)['checks']['worker']['latency_seconds']);
        $this->changeState(function ($state) {
            $state['worker'] = ['requested_at' => now()->timestamp - 61, 'processed_at' => now()->timestamp];

            return $state;
        });
        $this->assertSame('latency_exceeded', $this->readiness(1)['checks']['worker']['reason']);
    }

    public function test_stale_scheduler_fails_without_losing_a_fresh_worker_observation(): void
    {
        $this->ready();
        $this->changeState(fn ($state) => array_replace($state, ['scheduler_at' => now()->timestamp - 181]));
        $report = $this->readiness(1);
        $this->assertSame('stale', $report['checks']['scheduler']['reason']);
        $this->assertSame('ok', $report['checks']['worker']['status']);
    }

    public function test_worker_freshness_uses_request_age_not_only_completion_time(): void
    {
        $this->ready();
        $this->changeState(function ($state) {
            $state['worker'] = ['requested_at' => now()->timestamp - 181, 'processed_at' => now()->timestamp - 130];

            return $state;
        });
        $report = $this->readiness(1);
        $this->assertSame('stale', $report['checks']['worker']['reason']);
        $this->assertSame(181, $report['checks']['worker']['request_age_seconds']);
    }

    public function test_future_malformed_and_reversed_timestamps_fail_closed(): void
    {
        $this->ready();
        $good = $this->state();
        foreach ([now()->timestamp + 1, 'not-a-time', -1] as $timestamp) {
            $this->cache->forever(ProcessingReadiness::STATE_KEY, array_replace($good, ['scheduler_at' => $timestamp]));
            $this->assertSame('fail', $this->readiness(1)['checks']['scheduler']['status']);
        }
        foreach ([
            ['requested_at' => now()->timestamp + 1, 'processed_at' => now()->timestamp + 1],
            ['requested_at' => now()->timestamp, 'processed_at' => now()->timestamp + 1],
            ['requested_at' => now()->timestamp, 'processed_at' => now()->timestamp - 1],
            ['requested_at' => 'yesterday', 'processed_at' => now()->timestamp],
        ] as $worker) {
            $this->cache->forever(ProcessingReadiness::STATE_KEY, array_replace($good, ['worker' => $worker]));
            $this->assertSame('fail', $this->readiness(1)['checks']['worker']['status']);
        }
    }

    public function test_backlog_limit_and_oldest_pending_age_are_enforced(): void
    {
        $this->ready();
        $this->queueSize = 100;
        $this->assertSame('backlog', $this->readiness(1)['checks']['queue']['reason']);
        $this->queueSize = 1;
        $this->pendingSize = 1;
        $this->oldestPending = now()->timestamp - 301;
        $report = $this->readiness(1);
        $this->assertSame('stale', $report['checks']['queue']['reason']);
        $this->assertSame(301, $report['checks']['queue']['oldest_pending_age_seconds']);
        $this->oldestPending = now()->timestamp - 300;
        $this->readiness(0);
        $this->oldestPending = now()->timestamp + 1;
        $this->assertSame('invalid_timestamp', $this->readiness(1)['checks']['queue']['reason']);
        $this->oldestPending = null;
        $this->assertSame('age_unavailable', $this->readiness(1)['checks']['queue']['reason']);
    }

    public function test_sync_and_unverified_queue_drivers_never_report_async_readiness(): void
    {
        $this->ready();
        foreach (['sync', 'null', 'deferred', 'background', 'failover', 'unknown'] as $driver) {
            config(['queue.connections.processing-test.driver' => $driver]);
            $report = $this->readiness(1);
            $this->assertSame('async_queue_required', $report['checks']['configuration']['reason']);
            $this->assertFalse($this->service()->heartbeat());
        }
        $this->assertCount(1, $this->jobs);
    }

    public function test_declared_async_queue_cannot_hide_a_sync_transport(): void
    {
        Queue::shouldReceive('connection')->with('processing-test')->andReturn(Mockery::mock(SyncQueue::class));
        $this->assertSame('async_queue_required', $this->readiness(1)['checks']['configuration']['reason']);
        $this->assertFalse($this->service()->heartbeat());
    }

    public function test_nonshared_cache_drivers_fail_in_production_without_fallback(): void
    {
        $this->ready();
        $this->app->detectEnvironment(fn () => 'production');
        foreach (['array', 'file', 'null', 'octane', 'failover', 'unknown'] as $driver) {
            config(['cache.stores.processing-test.driver' => $driver]);
            $report = $this->readiness(1);
            $this->assertSame('shared_cache_required', $report['checks']['configuration']['reason']);
            $this->assertFalse($this->service()->heartbeat());
        }
    }

    public function test_declared_shared_cache_cannot_hide_an_actual_array_store(): void
    {
        Cache::shouldReceive('store')->with('processing-test')->andReturn(new Repository(new ArrayStore));
        $this->assertSame('shared_cache_required', $this->readiness(1)['checks']['configuration']['reason']);
    }

    public function test_in_memory_database_cache_cannot_prove_cross_process_readiness(): void
    {
        config([
            'cache.stores.processing-test' => ['driver' => 'database', 'connection' => 'processing-memory'],
            'database.connections.processing-memory' => ['driver' => 'sqlite', 'database' => ':memory:'],
        ]);
        $this->assertSame('shared_cache_required', $this->readiness(1)['checks']['configuration']['reason']);
    }

    public function test_cache_and_queue_exceptions_are_json_safe_and_fail_closed(): void
    {
        $this->ready();
        $this->queue->shouldReceive('size')->andThrow(new RuntimeException('synthetic-password SQLSTATE private-host'));
        $report = $this->readiness(1);
        $this->assertSame('source_unavailable', $report['checks']['configuration']['reason']);
        $this->assertStringNotContainsString('synthetic-password', Artisan::output());
        $this->assertStringNotContainsString('SQLSTATE', Artisan::output());
        $this->assertStringNotContainsString('private-host', Artisan::output());

        Cache::shouldReceive('store')->andThrow(new RuntimeException('synthetic-cache-secret'));
        $this->readiness(1);
        $this->assertStringNotContainsString('synthetic-cache-secret', Artisan::output());
    }

    public function test_failed_dispatch_keeps_a_lease_and_is_not_reported_as_ready(): void
    {
        $this->ready();
        $this->travel(60)->seconds();
        $this->queue->shouldReceive('push')->once()->andThrow(new RuntimeException('synthetic-dispatch-secret'));
        $this->assertFalse($this->service()->heartbeat());
        $this->assertTrue($this->service()->heartbeat(), 'Do not retry enqueue immediately after an uncertain failure.');
        $this->readiness(1);
        $this->assertStringNotContainsString('synthetic-dispatch-secret', Artisan::output());
        $this->assertCount(1, $this->jobs);
    }

    public function test_dead_worker_does_not_accumulate_probes_after_lease_expiry(): void
    {
        $this->assertTrue($this->service()->heartbeat());
        for ($minute = 0; $minute < 120; $minute++) {
            $this->travel(60)->seconds();
            $this->assertTrue($this->service()->heartbeat());
        }
        $this->assertCount(1, $this->jobs);
        $report = $this->readiness(1);
        $this->assertSame(0, $report['checks']['scheduler']['age_seconds']);
        $this->assertSame('stale', $report['checks']['probe']['reason']);
    }

    public function test_lost_probe_is_replaced_only_after_lease_and_empty_queue(): void
    {
        $this->assertTrue($this->service()->heartbeat());
        $this->queueSize = 0;
        $this->travel(299)->seconds();
        $this->service()->heartbeat();
        $this->assertCount(1, $this->jobs);
        $this->travel(1)->seconds();
        $this->service()->heartbeat();
        $this->assertCount(2, $this->jobs);
        $this->consume($this->jobs[0], acknowledge: false);
        $this->assertNull($this->state()['worker'], 'An obsolete probe cannot complete the replacement request.');
        $this->consume($this->jobs[1]);
        $this->readiness(0);
    }

    public function test_stale_probe_cannot_write_a_false_fresh_worker_heartbeat(): void
    {
        $this->service()->heartbeat();
        $this->travel(61)->seconds();
        $this->consume($this->jobs[0]);
        $this->assertNull($this->state()['worker']);
        $this->assertSame('probe_failed', $this->readiness(1)['checks']['probe']['reason']);
        $this->service()->heartbeat();
        $this->consume($this->jobs[1]);
        $this->readiness(0);
    }

    public function test_future_probe_is_rejected_instead_of_refreshing_worker_evidence(): void
    {
        $this->service()->heartbeat();
        $job = $this->jobs[0];
        $this->travel(-1)->seconds();
        $this->consume($job);
        $this->assertNull($this->state()['worker']);
        $this->readiness(1);
    }

    public function test_duplicate_jobs_and_ticks_are_idempotent_and_cadence_is_bounded(): void
    {
        $this->ready();
        $worker = $this->state()['worker'];
        $this->travel(20)->seconds();
        $this->consume($this->jobs[0], acknowledge: false);
        $this->service()->heartbeat();
        $this->assertSame($worker, $this->state()['worker']);
        $this->assertCount(1, $this->jobs);
        $this->travel(40)->seconds();
        $this->service()->heartbeat();
        $this->service()->heartbeat();
        $this->assertCount(2, $this->jobs);
        $this->readiness(0, 'A fresh pending probe can coexist with previous timely completion.');
        $this->travel(61)->seconds();
        $this->assertSame('stale', $this->readiness(1)['checks']['probe']['reason']);
    }

    public function test_direct_and_sync_execution_are_not_worker_evidence(): void
    {
        $this->service()->heartbeat();
        $job = $this->jobs[0];
        $job->handle($this->service());
        $this->assertNull($this->state()['worker']);
        $job->setJob(Mockery::mock(SyncJob::class));
        $job->handle($this->service());
        $this->assertNull($this->state()['worker']);
        $this->readiness(1);
    }

    public function test_wrong_queue_delivery_or_changed_configuration_cannot_reuse_evidence(): void
    {
        $this->service()->heartbeat();
        $job = $this->jobs[0];
        $runtime = Mockery::mock(DatabaseJob::class);
        $runtime->shouldReceive('getConnectionName')->andReturn('processing-test');
        $runtime->shouldReceive('getQueue')->andReturn('another-queue');
        $job->setJob($runtime)->handle($this->service());
        $this->assertNull($this->state()['worker']);
        $this->consume($job);
        config(['queue.connections.processing-test.queue' => 'another-queue']);
        $this->assertSame('scope_mismatch', $this->readiness(1)['checks']['configuration']['reason']);
    }

    public function test_lock_contention_does_not_dispatch_another_probe(): void
    {
        $lock = $this->cache->lock(ProcessingReadiness::LOCK_KEY, 30);
        $this->assertTrue($lock->get());
        try {
            $this->assertFalse($this->service()->heartbeat());
            $this->assertSame([], $this->jobs);
        } finally {
            $lock->release();
        }
        $this->assertTrue($this->service()->heartbeat());
    }

    public function test_backlog_caps_probes_even_when_cache_evidence_has_been_evicted(): void
    {
        $this->queueSize = 100;
        $this->assertFalse($this->service()->heartbeat());
        $this->assertSame([], $this->jobs);
        $this->assertSame('backlog', $this->readiness(1)['checks']['queue']['reason']);
    }

    public function test_false_cache_write_is_failure_and_does_not_enqueue(): void
    {
        $this->cache->getStore()->shouldReceive('forever')->andReturn(false);
        $this->assertFalse($this->service()->heartbeat());
        $this->assertSame([], $this->jobs);
        $this->readiness(1);
    }

    public function test_worker_storage_failure_is_sanitized_and_not_a_completion(): void
    {
        $this->service()->heartbeat();
        $this->cache->getStore()->shouldReceive('forever')->andThrow(new RuntimeException('synthetic-worker-secret'));
        try {
            $this->consume($this->jobs[0]);
            $this->fail('Failed worker writes must fail the job.');
        } catch (RuntimeException $exception) {
            $this->assertSame('processing_heartbeat_unavailable', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertNull($this->state()['worker']);
    }

    public function test_heartbeat_command_is_scheduled_every_minute_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'ops:heartbeat'));
        $this->assertCount(1, $events);
        $this->assertSame('* * * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
        $this->assertSame(0, Artisan::call('ops:heartbeat'));
        $this->assertSame(['status' => 'ok'], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
        $this->assertCount(1, $this->jobs);
    }

    public function test_heartbeat_command_reports_failure_as_safe_json(): void
    {
        Cache::shouldReceive('store')->andThrow(new RuntimeException('synthetic-cache-secret'));
        $this->assertSame(1, Artisan::call('ops:heartbeat'));
        $this->assertSame(['status' => 'fail'], json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR));
    }

    public function test_invalid_thresholds_cannot_disable_the_gate(): void
    {
        foreach ([0, -1, 100000, '180', null] as $value) {
            config(['operations.processing.heartbeat_max_age_seconds' => $value]);
            $this->assertSame('invalid_configuration', $this->readiness(1)['checks']['configuration']['reason']);
        }
    }

    private function service(): ProcessingReadiness
    {
        return app(ProcessingReadiness::class);
    }

    private function readiness(int $exitCode, string $message = ''): array
    {
        $this->assertSame($exitCode, Artisan::call('ops:readiness'), $message);
        $output = trim(Artisan::output());
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($exitCode === 0 ? 'ok' : 'fail', $report['status']);
        $this->assertStringNotContainsString('processing-fixture', $output);
        $this->assertStringNotContainsString('processing-test', $output);

        return $report;
    }

    private function ready(): void
    {
        $this->assertTrue($this->service()->heartbeat());
        $this->consume($this->jobs[0]);
    }

    private function consume(RecordProcessingHeartbeat $job, bool $acknowledge = true): void
    {
        $runtime = Mockery::mock(DatabaseJob::class);
        $runtime->shouldReceive('getConnectionName')->andReturn($job->connection);
        $runtime->shouldReceive('getQueue')->andReturn($job->queue);
        $job->setJob($runtime)->handle($this->service());
        if ($acknowledge) {
            $this->queueSize = max(0, $this->queueSize - 1);
        }
    }

    private function state(): array
    {
        return $this->cache->get(ProcessingReadiness::STATE_KEY);
    }

    private function changeState(callable $change): void
    {
        $this->cache->forever(ProcessingReadiness::STATE_KEY, $change($this->state()));
    }
}
