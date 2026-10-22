<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/** FR-REM-007: agregat baca-saja; snapshot best-effort, bukan pemeriksaan kesehatan global. */
class OperationalMetrics
{
    private const MAIN_IDS = ['MET-01', 'MET-02', 'MET-04', 'MET-05', 'MET-06', 'MET-07'];

    public function summary(CarbonImmutable $from, CarbonImmutable $toExclusive, CarbonImmutable $asOf): array
    {
        if ($from->getOffset() !== 0 || $toExclusive->getOffset() !== 0 || $asOf->getOffset() !== 0
            || $from->greaterThanOrEqualTo($toExclusive) || $from->addDays(366)->lessThan($toExclusive)) {
            throw new InvalidArgumentException('Jendela harus UTC, berurutan, dan maksimal 366 hari.');
        }

        $observed = $asOf->format('Y-m-d\TH:i:s\Z');
        $window = fn (Builder $query, string $column) => $query
            ->where($column, '>=', $from->format('Y-m-d H:i:s'))
            ->where($column, '<', $toExclusive->format('Y-m-d H:i:s'));
        $metrics = [];
        $read = function (string $id, string $unit, string $timeBasis, string $basis, Closure $query) use (&$metrics, $observed): void {
            $metric = [
                'id' => $id, 'value' => null, 'unit' => $unit, 'time_basis' => $timeBasis,
                'status' => 'unknown', 'exact' => false, 'basis' => $basis,
                'observed_at' => $observed, 'reason' => 'source_unavailable', 'oldest_age_seconds' => null,
            ];
            try {
                $metric = array_replace($metric, ['status' => 'ok', 'exact' => true, 'reason' => null], $query());
            } catch (Throwable) {
                // Jangan serialisasi exception/SQL/nama koneksi atau fallback ke sumber lain.
            }
            $metrics[] = $metric;
        };

        $read('MET-01', 'orders', 'event_window', 'orders.created_at', fn () => [
            'value' => $window(DB::table('orders'), 'created_at')->count(),
        ]);
        $read('MET-02', 'orders', 'snapshot', 'orders.status', function (): array {
            $counts = array_fill_keys(['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'other'], 0);
            foreach (DB::table('orders')->select('status')->selectRaw('COUNT(*) AS aggregate')->groupBy('status')->get() as $row) {
                $key = array_key_exists($row->status, $counts) ? $row->status : 'other';
                $counts[$key] += (int) $row->aggregate;
            }

            return ['value' => array_sum($counts), 'by_status' => $counts];
        });
        $read('MET-04', 'events', 'event_window', 'payments.created_at,outcome', function () use ($window): array {
            $counts = array_fill_keys(['unknown_order', 'amount_mismatch', 'order_not_payable'], 0);
            foreach ($window(DB::table('payments'), 'created_at')->whereIn('outcome', array_keys($counts))
                ->select('outcome')->selectRaw('COUNT(*) AS aggregate')->groupBy('outcome')->get() as $row) {
                $counts[$row->outcome] = (int) $row->aggregate;
            }
            $value = array_sum($counts);

            return ['value' => $value, 'by_outcome' => $counts, 'status' => $value > 0 ? 'attention' : 'ok'];
        });
        $read('MET-05', 'reservations', 'snapshot', 'inventory_reservations.expires_at; open dead letters excluded', function () use ($asOf): array {
            $expired = fn () => DB::table('inventory_reservations')->where('status', 'reserved')
                ->where('expires_at', '<', $asOf->format('Y-m-d H:i:s'));
            $held = fn (Builder $query) => $query->selectRaw('1')->from('payment_dead_letters')
                ->whereColumn('payment_dead_letters.order_id', 'inventory_reservations.order_id')->whereNull('resolved_at');
            $row = $expired()->whereNotExists($held)->selectRaw('COUNT(*) AS aggregate, MIN(expires_at) AS oldest')->first();
            $age = $this->age($row->oldest, $asOf);

            return [
                'value' => (int) $row->aggregate, 'held_count' => $expired()->whereExists($held)->count(),
                'oldest_age_seconds' => $age, 'status' => $age !== null && $age > 300 ? 'attention' : 'ok',
            ];
        });
        $read('MET-06', 'dead_letters', 'snapshot', 'payment_dead_letters.resolved_at,created_at,attempts', function () use ($asOf): array {
            $row = DB::table('payment_dead_letters')->whereNull('resolved_at')
                ->selectRaw('COUNT(*) AS aggregate, MIN(created_at) AS oldest, SUM(CASE WHEN attempts >= 10 THEN 1 ELSE 0 END) AS exhausted')->first();
            $age = $this->age($row->oldest, $asOf);
            $exhausted = (int) $row->exhausted;

            return [
                'value' => (int) $row->aggregate, 'exhausted_count' => $exhausted, 'oldest_age_seconds' => $age,
                'status' => $exhausted > 0 ? 'critical' : ($age !== null && $age > 600 ? 'attention' : 'ok'),
            ];
        });
        $read('MET-07', 'notifications', 'snapshot', 'order_notifications.mail undispatched; active lease subset', function () use ($asOf): array {
            $row = DB::table('order_notifications')->where('channel', 'mail')->whereNull('dispatched_at')
                ->selectRaw('COUNT(*) AS aggregate, MIN(created_at) AS oldest, SUM(CASE WHEN lease_expires_at > ? THEN 1 ELSE 0 END) AS leased', [$asOf->format('Y-m-d H:i:s')])->first();
            $age = $this->age($row->oldest, $asOf);

            return [
                'value' => (int) $row->aggregate, 'leased_count' => (int) $row->leased, 'oldest_age_seconds' => $age,
                'status' => $age !== null && $age > 600 ? 'attention' : 'ok',
            ];
        });
        $read('MET-08', 'jobs', 'snapshot', 'configured database queue; all queues; ready age from available_at', function () use ($asOf): array {
            $configuration = config('queue.connections.'.config('queue.default'), []);
            if (($configuration['driver'] ?? null) !== 'database') {
                return $this->unsupported();
            }
            $row = DB::connection($configuration['connection'] ?? null)->table($configuration['table'] ?? 'jobs')
                ->selectRaw('COUNT(*) AS aggregate, SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS ready, SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_count, SUM(CASE WHEN reserved_at IS NOT NULL THEN 1 ELSE 0 END) AS reserved_count, MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at ELSE NULL END) AS oldest', [$asOf->timestamp, $asOf->timestamp, $asOf->timestamp])->first();
            $age = $row->oldest === null ? null : max(0, $asOf->timestamp - (int) $row->oldest);

            return [
                'value' => (int) $row->aggregate, 'ready_count' => (int) $row->ready,
                'delayed_count' => (int) $row->delayed_count, 'reserved_count' => (int) $row->reserved_count,
                'oldest_age_seconds' => $age, 'status' => $age !== null && $age > 300 ? 'attention' : 'ok',
            ];
        });
        $read('MET-09', 'jobs', 'event_window', 'configured failed-job provider; failed_at', function () use ($window): array {
            if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
                return $this->unsupported();
            }
            $value = $window(DB::connection(config('queue.failed.database'))->table(config('queue.failed.table', 'failed_jobs')), 'failed_at')->count();

            return ['value' => $value, 'status' => $value > 0 ? 'attention' : 'ok'];
        });
        foreach (['MET-03', 'MET-10', 'MET-11', 'MET-12', 'MET-13', 'MET-14', 'MET-15', 'MET-16'] as $id) {
            $read($id, $id === 'MET-03' ? 'events' : 'checks', $id === 'MET-03' ? 'event_window' : 'snapshot', 'not_in_minimal_slice', fn () => array_replace($this->unsupported(), ['reason' => 'not_in_minimal_slice']));
        }
        usort($metrics, fn (array $a, array $b) => strcmp($a['id'], $b['id']));
        $unknown = array_column(array_filter($metrics, fn (array $metric) => $metric['status'] === 'unknown'), 'id');
        $severity = 'unknown';
        foreach (['ok', 'attention', 'critical'] as $status) {
            if (in_array($status, array_column($metrics, 'status'), true)) {
                $severity = $status;
            }
        }
        $mainAvailable = array_filter($metrics, fn (array $metric) => in_array($metric['id'], self::MAIN_IDS, true) && $metric['status'] !== 'unknown');

        return [
            'contract_version' => 'ops-metrics.v1', 'as_of' => $observed,
            'window' => ['from' => $from->format('Y-m-d\TH:i:s\Z'), 'to_exclusive' => $toExclusive->format('Y-m-d\TH:i:s\Z'), 'tz' => 'UTC', 'period' => 'custom'],
            'consistency' => 'best_effort', 'metrics' => $metrics,
            'summary' => ['status' => $severity, 'unknown_ids' => array_values($unknown), 'coverage' => $unknown === [] ? 'complete' : 'partial', 'main_source_available' => $mainAvailable !== []],
        ];
    }

    private function unsupported(): array
    {
        return ['value' => null, 'status' => 'unknown', 'exact' => false, 'reason' => 'unsupported_driver'];
    }

    private function age(?string $oldest, CarbonImmutable $asOf): ?int
    {
        return $oldest === null ? null : max(0, $asOf->timestamp - CarbonImmutable::parse($oldest, 'UTC')->timestamp);
    }
}
