@extends('admin.layouts.app', ['pageTitle' => 'Ringkasan operasional', 'activePage' => 'operations-summary'])
@section('title', 'Ringkasan operasional')
@section('styles')
<style>
    .ops-filters { display: flex; flex-wrap: wrap; align-items: end; gap: 1rem; padding: 20px; }
    .ops-filters .field { display: grid; flex: 1 1 12rem; min-width: 0; gap: .5rem; margin: 0; }
    .ops-filters input, .ops-filters select, .ops-filters button { min-height: 44px; min-width: 44px; font: inherit; }
    .ops-filters :focus-visible, .ops-table:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 3px; }
    .ops-table { overflow-x: auto; }
    .ops-table table { min-width: 40rem; }
    .ops-detail { display: block; margin-top: .5rem; color: var(--muted); font-size: 12px; font-weight: 400; }
    .admin-operations .admin-panel > p:not(.panel-hint):not(.auth-errors) { margin: 0; padding: 12px 20px; overflow-wrap: anywhere; }
    .admin-operations .admin-panel > p:last-child { padding-bottom: 20px; }
    .admin-operations .ops-table th[scope="row"] { font-size: 13px; background: var(--surface); color: var(--ink); text-transform: none; }
    .admin-operations .ops-table dd { margin: 0 0 8px; font-variant-numeric: tabular-nums; }
    @media (max-width: 640px) { .ops-filters { align-items: stretch; } .ops-filters button { width: 100%; } }
</style>
@endsection
@section('content')
<div class="admin-operations">
<section class="admin-panel" aria-labelledby="ops-filter-heading" lang="id">
    <h2 id="ops-filter-heading">Periode kejadian</h2>
    <p id="ops-window-help" class="panel-hint">Tanggal UTC, maksimal 366 hari. Pilih Khusus untuk memakai tanggal mulai dan akhir (inklusif). Filter hanya mengubah metrik kejadian, bukan backlog. Tidak ada penyegaran otomatis.</p>
    @if (!empty($validationErrors))
        <div class="auth-errors" data-ops-validation-errors role="alert"><p>Periksa rentang tanggal UTC.</p><ul>@foreach ($validationErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form class="ops-filters" data-ops-filters method="GET" action="{{ route('admin.reports.operations-summary') }}" aria-describedby="ops-window-help">
        <div class="field"><label for="ops-period">Periode</label><select id="ops-period" name="period">
            @foreach (['today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari', 'custom' => 'Khusus'] as $value => $label)
                <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <div class="field"><label for="ops-from">Mulai (UTC)</label><input id="ops-from" name="from" type="date" value="{{ $filters['from'] }}"></div>
        <div class="field"><label for="ops-to">Akhir (UTC, inklusif)</label><input id="ops-to" name="to" type="date" value="{{ $filters['to'] }}"></div>
        <button class="button button-sapphire" type="submit">Terapkan / Segarkan</button>
    </form>
</section>
<p id="ops-refresh-status" data-ops-refresh-status role="status" aria-live="polite" aria-atomic="true" lang="id"></p>
<div data-ops-report data-as-of="{{ $report['as_of'] ?? '' }}" aria-describedby="ops-refresh-status" aria-busy="false">
@if ($report)
@php
    $metrics = collect($report['metrics'])->keyBy('id');
    $statusLabels = ['ok' => '✓ Tidak ada peringatan terukur', 'attention' => '△ Perlu perhatian', 'critical' => '! Kritis', 'unknown' => '? Belum diperiksa'];
    $reasonLabels = ['source_unavailable' => 'Sumber belum tersedia. Coba segarkan kembali.', 'unsupported_driver' => 'Penyimpanan ini belum didukung.', 'not_in_minimal_slice' => 'Belum dihitung pada ringkasan ini.'];
    $groups = [
        'Konteks volume dan status' => ['MET-01' => 'Order dibuat', 'MET-02' => 'Order per status saat ini'],
        'Tersangkut' => ['MET-05' => 'Baris reservasi kedaluwarsa, tidak ditahan', 'MET-06' => 'Dead letter terbuka'],
        'Rekonsiliasi' => ['MET-04' => 'Event anomali pembayaran'],
        'Antrean' => ['MET-07' => 'Notifikasi mail belum diantrekan', 'MET-08' => 'Job pada penyimpanan antrean', 'MET-09' => 'Job gagal dalam periode'],
        'Integritas — Belum diperiksa' => ['MET-10' => 'Stok negatif', 'MET-11' => 'Lunas tanpa ledger', 'MET-12' => 'Ledger lunas, order pending', 'MET-13' => 'Batal dengan reservasi', 'MET-14' => 'Aritmetika order'],
    ];
    $detailLabels = ['held_count' => 'Reservasi ditahan dead letter (terpisah)', 'exhausted_count' => 'Percobaan ≥10 (subset)', 'leased_count' => 'Lease aktif (subset)', 'ready_count' => 'Siap', 'delayed_count' => 'Ditunda', 'reserved_count' => 'Sedang diambil worker'];
@endphp
<section class="admin-panel" aria-labelledby="ops-reading-heading" lang="id">
    <h2 id="ops-reading-heading">Hasil pembacaan</h2>
    @if (isset($report['message']))<p class="auth-errors" role="alert">{{ $report['message'] }}</p>@endif
    <p><strong>{{ $statusLabels[$report['summary']['status']] }}</strong> · Cakupan {{ $report['summary']['coverage'] === 'partial' ? 'sebagian' : 'lengkap' }}.</p>
    <p class="panel-hint">Bukan kesehatan sistem menyeluruh, bukti worker hidup, pendapatan, atau kapasitas transaksi. Jangan jumlahkan angka berlainan unit atau subset.</p>
    <p>Cutoff permintaan: <time datetime="{{ $report['as_of'] }}">{{ $report['as_of'] }}</time>. Pembacaan best-effort, bukan snapshot atomik.</p>
    <p>Kejadian: {{ $report['window']['from'] }} ≤ waktu &lt; {{ $report['window']['to_exclusive'] }} (UTC).</p>
    <p><strong>Backlog saat ini — seluruh umur</strong>: tidak dibatasi periode kejadian. Angka hanya berlaku pada pembacaan ini; bila memakai halaman lama, perlakukan sebagai <strong>Data lama</strong> dan segarkan manual.</p>
</section>
@foreach ($groups as $heading => $items)
<section class="admin-panel" lang="id">
    <h2>{{ $heading }}</h2>
    <div class="admin-table-wrap ops-table" tabindex="0" role="region" aria-label="{{ $heading }}">
    <table class="admin-table">
        <caption class="sr-only">{{ $heading }} — agregat tanpa detail pelanggan</caption>
        <thead><tr><th scope="col">Metrik / basis</th><th scope="col">Jumlah</th><th scope="col">Status dan rincian</th><th scope="col">Umur terlama</th></tr></thead>
        <tbody>
        @foreach ($items as $id => $label)
        @php($metric = $metrics[$id])
        <tr>
            <th scope="row">{{ $label }} <small class="ops-detail">{{ $id }} · {{ $metric['time_basis'] === 'snapshot' ? 'Backlog/status saat ini' : 'Kejadian dalam periode' }}<br>{{ $metric['basis'] }}</small></th>
            <td class="num">{{ $metric['value'] === null ? '—' : number_format($metric['value'], 0, ',', '.') }} @if($metric['value'] === 0)<small class="ops-detail">Tidak ada pada pembacaan ini</small>@endif</td>
            <td>{{ $statusLabels[$metric['status']] }}
                @if ($metric['reason'])<small class="ops-detail">{{ $reasonLabels[$metric['reason']] }}</small>@endif
                @foreach ($detailLabels as $key => $detailLabel)
                    @if (array_key_exists($key, $metric))<small class="ops-detail">{{ $detailLabel }}: {{ $metric[$key] }}</small>@endif
                @endforeach
                @foreach (['by_status', 'by_outcome'] as $key)
                    @if (isset($metric[$key]))
                        <dl>@foreach ($metric[$key] as $name => $count)<dt>{{ $name }}</dt><dd>{{ $count }}</dd>@endforeach</dl>
                    @endif
                @endforeach
            </td>
            <td>{{ $metric['oldest_age_seconds'] === null ? '—' : $metric['oldest_age_seconds'].' detik' }}@if($id === 'MET-08')<small class="ops-detail">Hanya job siap, sejak available_at</small>@endif</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</section>
@endforeach
@else
<p class="admin-empty" lang="id">Ringkasan belum tersedia. Terapkan periode untuk membaca data operasional.</p>
@endif
</div>
</div>
@endsection
@section('after-content')
<script type="module" src="{{ asset('js/admin-operations-summary.js') }}"></script>
@endsection
