<?php

namespace App\Services;

use App\Jobs\RecordProcessingHeartbeat;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Lock;
use Illuminate\Cache\RedisStore;
use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\RedisQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** CICD-07 / NFR-REM-002: processing evidence, terpisah dari dependency health. */
class ProcessingReadiness
{
    public const STATE_KEY = 'ops:processing:v1:state';

    public const LOCK_KEY = 'ops:processing:v1:lock';

    /** Snapshot baca-saja; hanya nilai/label allowlist, tidak mengekspor konfigurasi atau exception. */
    public function snapshot(): array
    {
        $now = now()->timestamp;
        $report = [
            'contract_version' => 'processing-readiness.v1', 'scope' => 'default_queue',
            'status' => 'fail', 'observed_at' => $now, 'consistency' => 'best_effort',
            'checks' => array_fill_keys(['configuration', 'scheduler', 'worker', 'probe', 'queue'], $this->check('unavailable')),
        ];
        try {
            $context = $this->context();
            if (isset($context['error'])) {
                $report['checks']['configuration'] = $this->check($context['error']);

                return $report;
            }
            $state = $context['cache']->get(self::STATE_KEY);
            if ($state !== null && (! is_array($state) || ($state['scope'] ?? null) !== $context['scope'])) {
                $report['checks']['configuration'] = $this->check('scope_mismatch');

                return $report;
            }
            $limits = $context['limits'];
            $report['checks'] = [
                'configuration' => $this->check(null),
                'scheduler' => $this->ageCheck($state['scheduler_at'] ?? null, $now, $limits['heartbeat_max_age_seconds']),
                'worker' => $this->workerCheck($state['worker'] ?? null, $now, $limits),
                'probe' => $this->probeCheck($state['probe'] ?? null, $now, $limits),
                'queue' => $this->queueCheck($context, $now),
            ];
            $report['status'] = in_array('fail', array_column($report['checks'], 'status'), true) ? 'fail' : 'ok';
        } catch (Throwable) {
            $report['checks']['configuration'] = $this->check('source_unavailable');
        }

        return $report;
    }

    /** Scheduler tick: bounded enqueue; sukses tick tidak sama dengan readiness. */
    public function heartbeat(): bool
    {
        try {
            $context = $this->context();
            if (isset($context['error'])) {
                return false;
            }
            $lock = $context['cache']->lock(self::LOCK_KEY, 30);
            if (! $lock->get()) {
                return false;
            }
            try {
                $now = now()->timestamp;
                $state = $context['cache']->get(self::STATE_KEY);
                if ($state === null || (is_array($state) && ($state['scope'] ?? null) !== $context['scope'])) {
                    $state = ['scope' => $context['scope'], 'probe' => null, 'worker' => null];
                }
                if (! is_array($state)) {
                    return false;
                }
                $state['scheduler_at'] = $now;
                $this->write($context, $state, $lock);
                $probe = $state['probe'] ?? null;
                $limits = $context['limits'];
                if ($probe !== null) {
                    if (! $this->validProbe($probe) || ! $this->validTime($probe['requested_at'], $now)) {
                        return false;
                    }
                    $age = $now - $probe['requested_at'];
                    if ($age < $limits['probe_interval_seconds']
                        || (! $probe['finished'] && $age < $limits['probe_retry_after_seconds'])) {
                        return true;
                    }
                }
                $size = $context['queue']->size($context['queue_name']);
                if (! is_int($size) || $size < 0) {
                    return false;
                }
                // Pending evidence never expires: a dead worker leaves at most one probe.
                // Recover a lost/uncertain enqueue only after the lease AND an empty
                // queue (size includes reserved/delayed jobs). Never release on timeout alone.
                if ($probe !== null && ! $probe['finished'] && $size > 0) {
                    return true;
                }
                // Also cap growth after cache eviction or an operator clearing evidence.
                if ($size >= $limits['max_backlog']) {
                    return false;
                }
                $state['probe'] = ['id' => (string) Str::uuid(), 'requested_at' => $now, 'finished' => false, 'error' => null];
                $this->write($context, $state, $lock);
                $job = (new RecordProcessingHeartbeat($state['probe']['id'], $now))
                    ->onConnection($context['connection'])->onQueue($context['queue_name'])->beforeCommit();
                try {
                    // Explicit push keeps transport failures inside this boundary (no deferred destructor).
                    $context['queue']->push($job, '', $context['queue_name']);
                } catch (Throwable) {
                    $state['probe']['error'] = 'dispatch_failed';
                    $this->write($context, $state, $lock);

                    return false;
                }

                return true;
            } finally {
                $lock->release();
            }
        } catch (Throwable) {
            return false;
        }
    }

    public function recordWorkerHeartbeat(string $probeId, int $requestedAt): void
    {
        try {
            $context = $this->context();
            if (isset($context['error'])) {
                throw new RuntimeException('processing_heartbeat_unavailable');
            }
            $lock = $context['cache']->lock(self::LOCK_KEY, 30);
            $lock->block(1, function () use ($context, $lock, $probeId, $requestedAt): void {
                $state = $context['cache']->get(self::STATE_KEY);
                $probe = is_array($state) ? ($state['probe'] ?? null) : null;
                if (($state['scope'] ?? null) !== $context['scope'] || ! $this->validProbe($probe)
                    || $probe['id'] !== $probeId || $probe['requested_at'] !== $requestedAt || $probe['finished']) {
                    return;
                }
                $now = now()->timestamp;
                $valid = $this->validTime($requestedAt, $now)
                    && $now - $requestedAt <= $context['limits']['probe_max_latency_seconds'];
                $state['probe']['finished'] = true;
                $state['probe']['error'] = $valid ? null : 'invalid_request_age';
                if ($valid) {
                    $state['worker'] = ['requested_at' => $requestedAt, 'processed_at' => $now];
                }
                $this->write($context, $state, $lock);
            });
        } catch (Throwable) {
            // Failed-job storage/logging must not receive driver SQL, connection strings or secrets.
            throw new RuntimeException('processing_heartbeat_unavailable');
        }
    }

    private function context(): array
    {
        $connection = config('queue.default');
        $queueConfig = config('queue.connections.'.$connection, []);
        $queueDriver = $queueConfig['driver'] ?? null;
        if (! in_array($queueDriver, ['database', 'redis'], true)) {
            return ['error' => 'async_queue_required'];
        }
        $storeName = config('cache.default');
        $cacheConfig = config('cache.stores.'.$storeName, []);
        $cacheDriver = $cacheConfig['driver'] ?? null;
        if (! in_array($cacheDriver, ['database', 'redis'], true)) {
            return ['error' => 'shared_cache_required'];
        }
        if ($cacheDriver === 'database') {
            foreach (['connection', 'lock_connection'] as $key) {
                $db = $cacheConfig[$key] ?? $cacheConfig['connection'] ?? config('database.default');
                $effective = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.$db, []));
                $database = $effective['database'] ?? '';
                if (($effective['driver'] ?? null) === 'sqlite'
                    && ($database === '' || str_contains($database, ':memory:') || str_contains($database, 'mode=memory'))) {
                    return ['error' => 'shared_cache_required'];
                }
            }
        }
        $limits = config('operations.processing', []);
        foreach (['heartbeat_max_age_seconds', 'probe_max_latency_seconds', 'probe_interval_seconds', 'probe_retry_after_seconds', 'queue_max_age_seconds', 'max_backlog'] as $key) {
            if (! is_int($limits[$key] ?? null) || $limits[$key] < 1 || $limits[$key] > 3600) {
                return ['error' => 'invalid_configuration'];
            }
        }
        if ($limits['probe_interval_seconds'] < 60
            || $limits['heartbeat_max_age_seconds'] < $limits['probe_interval_seconds'] + $limits['probe_max_latency_seconds']
            || $limits['probe_retry_after_seconds'] <= $limits['probe_max_latency_seconds']) {
            return ['error' => 'invalid_configuration'];
        }
        $cache = Cache::store($storeName);
        $store = $cache->getStore();
        if (! ($cacheDriver === 'database' ? $store instanceof DatabaseStore : $store instanceof RedisStore)) {
            return ['error' => 'shared_cache_required'];
        }
        $queue = Queue::connection($connection);
        if (! ($queueDriver === 'database' ? $queue instanceof DatabaseQueue : $queue instanceof RedisQueue)) {
            return ['error' => 'async_queue_required'];
        }
        $queueName = $queueConfig['queue'] ?? 'default';
        if (! is_string($queueName) || $queueName === '' || ! is_string($connection)) {
            return ['error' => 'invalid_configuration'];
        }

        return [
            'cache' => $cache, 'queue' => $queue, 'connection' => $connection, 'queue_name' => $queueName, 'limits' => $limits,
            // An old target's observations cannot pass after connection/queue/backend changes.
            // Digest is internal only; no config values or digest are emitted in JSON.
            'scope' => hash('sha256', serialize([$connection, $queueConfig, $storeName, $cacheConfig, config('database')])),
        ];
    }

    private function write(array $context, array $state, Lock $lock): void
    {
        if (! $lock->isOwnedByCurrentProcess() || $context['cache']->forever(self::STATE_KEY, $state) !== true) {
            throw new RuntimeException('processing_heartbeat_unavailable');
        }
    }

    private function queueCheck(array $context, int $now): array
    {
        $queue = $context['queue'];
        $name = $context['queue_name'];
        $size = $queue->size($name);
        $pending = $queue->pendingSize($name);
        $oldest = $queue->creationTimeOfOldestPendingJob($name);
        if (! is_int($size) || ! is_int($pending) || $size < 0 || $pending < 0 || $pending > $size) {
            return $this->check('invalid_queue_observation');
        }
        $age = $oldest === null ? null : $this->ageCheck($oldest, $now, $context['limits']['queue_max_age_seconds']);
        $reason = $size >= $context['limits']['max_backlog'] ? 'backlog'
            : ($age['reason'] ?? ($pending > 0 && $oldest === null ? 'age_unavailable' : null));

        return $this->check($reason, ['size' => $size, 'pending' => $pending, 'oldest_pending_age_seconds' => $age['age_seconds'] ?? null]);
    }

    private function workerCheck(mixed $worker, int $now, array $limits): array
    {
        $details = ['age_seconds' => null, 'request_age_seconds' => null, 'latency_seconds' => null];
        if ($worker === null) {
            return $this->check('missing', $details);
        }
        if (! is_array($worker) || ! $this->validTime($worker['requested_at'] ?? null, $now)
            || ! $this->validTime($worker['processed_at'] ?? null, $now) || $worker['processed_at'] < $worker['requested_at']) {
            return $this->check('invalid_timestamp', $details);
        }
        $details = [
            'age_seconds' => $now - $worker['processed_at'], 'request_age_seconds' => $now - $worker['requested_at'],
            'latency_seconds' => $worker['processed_at'] - $worker['requested_at'],
        ];
        $reason = $details['latency_seconds'] > $limits['probe_max_latency_seconds'] ? 'latency_exceeded'
            : ($details['request_age_seconds'] > $limits['heartbeat_max_age_seconds'] ? 'stale' : null);

        return $this->check($reason, $details);
    }

    private function probeCheck(mixed $probe, int $now, array $limits): array
    {
        if ($probe === null) {
            return $this->check('missing');
        }
        if (! $this->validProbe($probe)) {
            return $this->check('invalid_probe');
        }
        $check = $this->ageCheck($probe['requested_at'], $now, $probe['finished']
            ? $limits['heartbeat_max_age_seconds'] : $limits['probe_max_latency_seconds']);
        if ($probe['error'] !== null) {
            $check = array_replace($check, $this->check('probe_failed'));
        }

        return $check + ['pending' => ! $probe['finished']];
    }

    private function validProbe(mixed $probe): bool
    {
        return is_array($probe) && is_string($probe['id'] ?? null) && Str::isUuid($probe['id']) && is_int($probe['requested_at'] ?? null)
            && is_bool($probe['finished'] ?? null) && array_key_exists('error', $probe);
    }

    private function ageCheck(mixed $timestamp, int $now, int $maximum): array
    {
        if ($timestamp === null) {
            return $this->check('missing', ['age_seconds' => null]);
        }
        if (! $this->validTime($timestamp, $now)) {
            return $this->check('invalid_timestamp', ['age_seconds' => null]);
        }
        $age = $now - $timestamp;

        return $this->check($age > $maximum ? 'stale' : null, ['age_seconds' => $age]);
    }

    private function validTime(mixed $timestamp, int $now): bool
    {
        return is_int($timestamp) && $timestamp > 0 && $timestamp <= $now;
    }

    private function check(?string $reason, array $details = []): array
    {
        return ['status' => $reason === null ? 'ok' : 'fail', 'reason' => $reason] + $details;
    }
}
