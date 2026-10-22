<?php

namespace App\Support;

use App\Enums\AuthEventName;
use App\Services\AuthEventRecorder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** FR-FE-001/002, NFR-FE-001: explicit read-only DTO; never serialize models. */
final class AdminReadPageProps
{
    public static function events(LengthAwarePaginator $events, array $filters): array
    {
        $common = __('admin_ui.common');
        if (! array_filter($filters)) {
            unset($common['active_filters']);
        }

        return [
            'locale' => app()->getLocale(),
            'copy' => ['common' => $common, 'events' => __('admin_ui.events')],
            'url' => route('admin.auth-events.index'),
            'filters' => array_intersect_key($filters, array_flip(['event', 'outcome', 'request_id'])),
            'eventOptions' => AuthEventName::ALL,
            'rows' => collect($events->items())->map(fn ($event) => [
                'id' => $event->id,
                'time' => $event->occurred_at?->format('Y-m-d H:i:s'),
                'actor_type' => $event->actor_type,
                'actor_id' => $event->actor_id,
                'event' => $event->event,
                'outcome' => $event->outcome,
                'ip' => $event->ip,
                'request_id' => $event->request_id,
                // Legacy rows may predate recorder sanitization. Do not expose arbitrary context.
                'context' => app(AuthEventRecorder::class)->sanitizeContext((array) $event->context),
            ])->all(),
            'pagination' => [
                'total' => $events->total(), 'current' => $events->currentPage(), 'last' => $events->lastPage(),
                'previous' => $events->previousPageUrl(), 'next' => $events->nextPageUrl(),
            ],
        ];
    }

    public static function operations(?array $report, array $filters, array $errors = []): array
    {
        return [
            'locale' => app()->getLocale(), 'url' => route('admin.reports.operations-summary'),
            'filters' => array_intersect_key($filters, array_flip(['period', 'from', 'to'])),
            'validationErrors' => array_values($errors), 'report' => self::report($report),
        ];
    }

    public static function report(?array $report): ?array
    {
        if ($report === null) {
            return null;
        }
        $result = array_intersect_key($report, array_flip(['contract_version', 'as_of', 'consistency', 'message']));
        $result['window'] = array_intersect_key($report['window'], array_flip(['from', 'to_exclusive', 'tz', 'period']));
        $result['summary'] = array_intersect_key($report['summary'], array_flip(['status', 'coverage', 'unknown_ids', 'main_source_available']));
        $result['metrics'] = array_map(fn (array $metric) => array_intersect_key($metric, array_flip([
            'id', 'value', 'unit', 'time_basis', 'status', 'exact', 'basis', 'observed_at', 'reason', 'oldest_age_seconds',
            'held_count', 'exhausted_count', 'leased_count', 'ready_count', 'delayed_count', 'reserved_count', 'by_status', 'by_outcome',
        ])), $report['metrics']);

        return $result;
    }
}
