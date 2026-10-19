@extends('admin.layouts.app', ['pageTitle' => 'Log Peristiwa Autentikasi', 'activePage' => 'auth-events'])
@section('title', 'Log Peristiwa Autentikasi')
@section('styles')
    <style>
        :root {
            --zafira-sapphire: #17263f;
            --zafira-gold: #c5a059;
            --zafira-linen: #f4efe6;
            --zafira-ivory: #faf7f2;
            --zafira-charcoal: #23201d;
            --zafira-muted: #6b635b;
            --zafira-border: #e6ded2;
            --zafira-danger: #8a2be2;
        }
        .admin-work-main *, .admin-work-main *::before, .admin-work-main *::after { box-sizing: border-box; }
        .admin-work-main { font-family: 'Manrope', -apple-system, sans-serif; color: var(--zafira-charcoal); font-size: 14px; }
        .admin-work-main h1 {
            font-family: 'Bodoni Moda', serif;
            font-size: 28px;
            color: var(--zafira-sapphire);
            font-weight: 600;
        }
        .admin-work-main .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
            background: #fff;
            padding: 16px;
            border-radius: 4px;
            border: 1px solid var(--zafira-border);
            align-items: flex-end;
        }
        .admin-work-main .filter-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .admin-work-main .filter-field label {
            font-size: 11px;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--zafira-muted);
            font-weight: 600;
        }
        .admin-work-main select, .admin-work-main button {
            font-family: inherit;
            padding: 8px 12px;
            border: 1px solid var(--zafira-border);
            border-radius: 4px;
            background: #fff;
            font-size: 13px;
        }
        .admin-work-main button {
            background: var(--zafira-sapphire);
            color: #fff;
            cursor: pointer;
            border: none;
        }
        .admin-work-main .auth-events-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border: 1px solid var(--zafira-border);
            background: #fff;
        }
        .admin-work-main table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .admin-work-main th, .admin-work-main td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--zafira-border);
        }
        .admin-work-main th {
            background: var(--zafira-linen);
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--zafira-muted);
        }
        .admin-work-main .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .admin-work-main .badge-success { background: #e6f4ea; color: #137333; }
        .admin-work-main .badge-failure { background: #fce8e6; color: #c5221f; }
        .pagination-container {
            margin-top: 20px;
        }
        @media (max-width: 640px) {
            .admin-work-main .filters { flex-direction: column; align-items: stretch; }
            .admin-work-main .filters select, .admin-work-main .filters button { width: 100%; }
        }
    </style>
@endsection
@section('content')
    <p style="margin-bottom: 16px; color: var(--zafira-muted);">Pencatatan resmi keamanan &amp; sesi konsol Maison ZAFIRA</p>
    @php
        $activeFilters = array_filter([
            'event' => request('event'),
            'outcome' => request('outcome'),
        ]);
    @endphp
    @if (!empty($activeFilters))
        <div class="active-filters-box" style="margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
            <span style="font-size: 12px; color: var(--zafira-muted); text-transform: uppercase; letter-spacing: 0.05em;">Filter aktif:</span>
            @foreach ($activeFilters as $key => $val)
                <span class="badge" data-active-filter="{{ $key }}: {{ $val }}" style="background: var(--zafira-linen); color: var(--zafira-charcoal); border: 1px solid var(--zafira-border);">
                    {{ $key }}: {{ $val }}
                </span>
            @endforeach
        </div>
    @endif

    <form method="GET" action="{{ route('admin.auth-events.index') }}" class="filters">
        <div class="filter-field">
            <label for="auth-event-filter">Peristiwa</label>
            <select id="auth-event-filter" name="event">
                <option value="">Semua Peristiwa</option>
                @foreach(\App\Enums\AuthEventName::ALL as $ev)
                    <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>{{ $ev }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label for="auth-outcome-filter">Hasil</label>
            <select id="auth-outcome-filter" name="outcome">
                <option value="">Semua Hasil</option>
                <option value="success" {{ request('outcome') === 'success' ? 'selected' : '' }}>Success</option>
                <option value="failure" {{ request('outcome') === 'failure' ? 'selected' : '' }}>Failure</option>
            </select>
        </div>
        <button type="submit">Filter</button>
        @if(request()->hasAny(['event', 'outcome']))
            <a href="{{ route('admin.auth-events.index') }}" style="align-self: center; margin-left: 8px; font-size: 13px; color: var(--zafira-muted);">Reset</a>
        @endif
    </form>

    <p data-result-count>{{ $events->total() }} hasil</p>

    <div class="auth-events-table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Waktu</th>
                <th>Aktor</th>
                <th>Peristiwa</th>
                <th>Hasil</th>
                <th>IP</th>
                <th>Konteks</th>
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
                            {{ $event->outcome }}
                        </span>
                    </td>
                    <td>{{ $event->ip ?? '-' }}</td>
                    <td>
                        @if($event->context && !empty((array)$event->context))
                            <pre style="font-size: 11px; margin: 0;">{{ json_encode($event->context, JSON_UNESCAPED_SLASHES) }}</pre>
                        @else
                            <span style="color: var(--zafira-muted);">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--zafira-muted); padding: 32px;">Belum ada rekaman peristiwa autentikasi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="pagination-container">
        {{ $events->links() }}
    </div>
@endsection
