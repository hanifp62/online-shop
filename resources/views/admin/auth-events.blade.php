<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17263f">
    <title>Log Peristiwa Autentikasi — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,600,700,900|manrope:300,400,500,600,700&display=swap" rel="stylesheet" />
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
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Manrope', -apple-system, sans-serif;
            background: var(--zafira-ivory);
            color: var(--zafira-charcoal);
            padding: 32px;
            font-size: 14px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--zafira-border);
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        h1 {
            font-family: 'Bodoni Moda', serif;
            font-size: 28px;
            color: var(--zafira-sapphire);
            font-weight: 600;
        }
        .filters {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: #fff;
            padding: 16px;
            border-radius: 4px;
            border: 1px solid var(--zafira-border);
        }
        select, button {
            font-family: inherit;
            padding: 8px 12px;
            border: 1px solid var(--zafira-border);
            border-radius: 4px;
            background: #fff;
            font-size: 13px;
        }
        button {
            background: var(--zafira-sapphire);
            color: #fff;
            cursor: pointer;
            border: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border: 1px solid var(--zafira-border);
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--zafira-border);
        }
        th {
            background: var(--zafira-linen);
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--zafira-muted);
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: #e6f4ea; color: #137333; }
        .badge-failure { background: #fce8e6; color: #c5221f; }
        .pagination-container {
            margin-top: 20px;
        }
        a.back-btn {
            color: var(--zafira-sapphire);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Log Peristiwa Autentikasi</h1>
            <p style="color: var(--zafira-muted); font-size: 13px; margin-top: 4px;">Pencatatan resmi keamanan & sesi konsol Maison ZAFIRA</p>
        </div>
        <div>
            <a href="{{ route('admin.dashboard') }}" class="back-btn">&larr; Kembali ke Dashboard</a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.auth-events.index') }}" class="filters">
        <select name="event">
            <option value="">Semua Peristiwa</option>
            @foreach(\App\Enums\AuthEventName::ALL as $ev)
                <option value="{{ $ev }}" {{ request('event') === $ev ? 'selected' : '' }}>{{ $ev }}</option>
            @endforeach
        </select>
        <select name="outcome">
            <option value="">Semua Hasil</option>
            <option value="success" {{ request('outcome') === 'success' ? 'selected' : '' }}>Success</option>
            <option value="failure" {{ request('outcome') === 'failure' ? 'selected' : '' }}>Failure</option>
        </select>
        <button type="submit">Filter</button>
        @if(request()->hasAny(['event', 'outcome']))
            <a href="{{ route('admin.auth-events.index') }}" style="align-self: center; margin-left: 8px; font-size: 13px; color: var(--zafira-muted);">Reset</a>
        @endif
    </form>

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

    <div class="pagination-container">
        {{ $events->links() }}
    </div>
</body>
</html>
