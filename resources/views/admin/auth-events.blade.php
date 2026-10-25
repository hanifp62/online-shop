@extends('admin.layouts.app', ['pageTitle' => __('admin_ui.events.heading'), 'activePage' => 'auth-events'])
@section('title', __('admin_ui.events.heading'))
@section('styles')
    <style>
        .admin-auth-events .filters { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; padding: 20px; border: 1px solid var(--line); border-radius: 4px; background: var(--surface); align-items: end; }
        .admin-auth-events .filter-field { display: flex; flex: 1 1 12rem; min-width: 0; flex-direction: column; gap: 6px; }
        .admin-auth-events .filter-field label { font-size: 12px; color: var(--muted); font-weight: 600; }
        .admin-auth-events .filters :is(select, input, button, a) { min-height: 44px; min-width: 44px; padding: 8px 12px; border: 1px solid var(--muted); border-radius: 3px; color: var(--ink); background: var(--surface); font: inherit; }
        .admin-auth-events .filters :is(select, input) { width: 100%; font-size: 16px; }
        .admin-auth-events .filters button { background: var(--accent); color: var(--on-accent); border-color: var(--accent); cursor: pointer; }
        .admin-auth-events .filters button:hover { background: var(--surface); color: var(--ink); border-color: var(--ink); }
        .admin-auth-events .auth-events-table-wrap { max-width: 100%; overflow-x: auto; border: 1px solid var(--line); border-radius: 4px; background: var(--surface); }
        .admin-auth-events table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .admin-auth-events th, .admin-auth-events td { padding: 12px 16px; text-align: left; vertical-align: top; border-bottom: 1px solid var(--line); }
        .admin-auth-events th { background: var(--surface-alt); font-size: 12px; color: var(--muted); }
        .admin-auth-events code, .admin-auth-events pre { font-size: 12px; }
        .admin-auth-events .badge { display: inline-block; padding: 3px 8px; border: 1px solid var(--muted); border-radius: 3px; font-size: 12px; font-weight: 600; color: var(--ink); background: var(--surface-alt); }
        .admin-auth-events .badge-failure { border-style: dashed; }
        .admin-auth-events .active-filters-box { margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; overflow-wrap: anywhere; }
        .admin-auth-events .pagination-container { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 20px; }
        .admin-auth-events .pagination-container a { display: inline-flex; align-items: center; min-height: 44px; padding: 8px 12px; color: var(--ink); text-decoration: underline; text-underline-offset: 3px; }
        @media (max-width: 640px) {
            .admin-auth-events .filters { flex-direction: column; align-items: stretch; }
            .admin-auth-events .filter-field { flex-basis: auto; }
        }
    </style>
@endsection
@section('content')
<div class="admin-auth-events">
    <p class="admin-page-intro">{{ __('admin_ui.events.intro') }}</p>
    @php
        $activeFilters = array_filter([
            'event' => request('event'),
            'outcome' => request('outcome'),
            'request_id' => request('request_id'),
        ]);
    @endphp
    @if (!empty($activeFilters))
        <div class="active-filters-box">
            <span>{{ __('admin_ui.common.active_filters') }}</span>
            @foreach ($activeFilters as $key => $val)
                <span class="badge" data-active-filter="{{ $key }}: {{ $val }}">
                    {{ $key }}: {{ $val }}
                </span>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.auth-events.index') }}" class="filters">
        <div class="filter-field">
            <label for="auth-event-filter">{{ __('admin_ui.events.event') }}</label>
            <select id="auth-event-filter" name="event">
                <option value="">{{ __('admin_ui.events.all_events') }}</option>
                @foreach(\App\Enums\AuthEventName::ALL as $ev)
                    <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>{{ $ev }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="auth-outcome-filter">{{ __('admin_ui.events.outcome') }}</label>
            <select id="auth-outcome-filter" name="outcome">
                <option value="">{{ __('admin_ui.events.all_outcomes') }}</option>
                <option value="success" {{ request('outcome') === 'success' ? 'selected' : '' }}>{{ __('admin_ui.events.success') }}</option>
                <option value="failure" {{ request('outcome') === 'failure' ? 'selected' : '' }}>{{ __('admin_ui.events.failure') }}</option>
            </select>
        </div>
        <div class="filter-field">
            <label for="auth-request-filter">{{ __('admin_ui.events.request_id') }}</label>
            <input id="auth-request-filter" name="request_id" type="text" value="{{ request('request_id') }}" placeholder="{{ __('admin_ui.events.request_hint') }}" maxlength="64" spellcheck="false">
        </div>
        <button type="submit">{{ __('admin_ui.common.filter') }}</button>
        @if(request()->hasAny(['event', 'outcome', 'request_id']))
            <a href="{{ route('admin.auth-events.index') }}" style="align-self: center; margin-left: 8px; font-size: 13px; color: var(--zafira-muted);">{{ __('admin_ui.common.reset') }}</a>
        @endif
    </form>

    <p data-result-count>{{ __('admin_ui.common.results', ['count' => $events->total()]) }}</p>

    <div class="auth-events-table-wrap" tabindex="0" role="region" aria-label="{{ __('admin_ui.events.region') }}">
    <table>
        <caption class="sr-only">{{ __('admin_ui.events.caption') }}</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">{{ __('admin_ui.events.time') }}</th>
                <th scope="col">{{ __('admin_ui.events.actor') }}</th>
                <th scope="col">{{ __('admin_ui.events.event') }}</th>
                <th scope="col">{{ __('admin_ui.events.outcome') }}</th>
                <th scope="col">IP</th>
                <th scope="col">{{ __('admin_ui.events.request_id') }}</th>
                <th scope="col">{{ __('admin_ui.events.context') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($events as $event)
                <tr>
                    <td>#{{ $event->id }}</td>
                    <td>{{ $event->occurred_at ? $event->occurred_at->format('Y-m-d H:i:s') : '-' }}</td>
                    <td>
                        @if($event->actor_type)
                            <span style="font-weight: 600;">{{ $event->actor_type }}</span>
                            @if($event->actor_id) (ID: {{ $event->actor_id }}) @endif
                        @else
                            <span style="color: var(--zafira-muted);">-</span>
                        @endif
                    </td>
                    <td><code>{{ $event->event }}</code></td>
                    <td>
                        <span class="badge {{ $event->outcome === 'success' ? 'badge-success' : 'badge-failure' }}">
                            {{ in_array($event->outcome, ['success', 'failure'], true) ? __('admin_ui.events.'.$event->outcome) : $event->outcome }}
                        </span>
                    </td>
                    <td>{{ $event->ip ?? '-' }}</td>
                    <td>@if($event->request_id)<code style="font-size: 11px;">{{ $event->request_id }}</code>@else<span style="color: var(--zafira-muted);">-</span>@endif</td>
                    <td>
                        @php($safeContext = app(\App\Services\AuthEventRecorder::class)->sanitizeContext((array) $event->context))
                        @if(!empty($safeContext))
                            <pre style="font-size: 11px; margin: 0;">{{ json_encode($safeContext, JSON_UNESCAPED_SLASHES) }}</pre>
                        @else
                            <span style="color: var(--zafira-muted);">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--zafira-muted); padding: 32px;">{{ __('admin_ui.events.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="pagination-container">
        {{ $events->links() }}
    </div>
</div>
@endsection
