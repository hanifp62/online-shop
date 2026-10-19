{{--
    Kontrol tabel admin yang dapat dipakai ulang.
    part = 'sort'       : select kolom + arah (letakkan di dalam form GET tabel).
    part = 'pagination' : tautan Sebelumnya/Berikutnya + ringkasan halaman (di luar form).
    part = 'state'      : input tersembunyi untuk mempertahankan state tabel lain dalam form GET.
    part = 'density'    : tombol Baris ringkas, tersembunyi sampai enhancement JavaScript aktif.
--}}
@php($table = $table ?? 'table')

@once
    <style>
        .table-sort-controls { grid-column: 1 / -1; display: flex; align-items: end; gap: 10px; flex-wrap: wrap; }
        .table-sort-controls .field { margin: 0; }
        .table-sort-controls select { min-height: 44px; }
        .table-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 18px; border-top: 1px solid var(--line); flex-wrap: wrap; }
        .table-pagination a, .table-pagination span.is-disabled { min-height: 44px; min-width: 44px; display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; border: 1px solid rgba(32, 32, 29, 0.48); font-size: 12px; }
        .table-pagination a { text-decoration: none; background: var(--white); }
        .table-pagination a:hover, .table-pagination a:focus-visible { border-color: var(--sapphire); }
        .table-pagination span.is-disabled { color: var(--muted); opacity: 1; border-style: dashed; }
        .table-pagination-status { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }
        .table-density-controls { display: flex; align-items: center; gap: 8px; padding: 10px 18px; color: var(--muted); font-size: 12px; }
        .table-density-controls button { min-height: 44px; padding: 8px 12px; border: 1px solid rgba(32, 32, 29, 0.48); background: var(--white); cursor: pointer; }
        .table-density-controls button[aria-pressed="true"] { background: var(--sapphire); color: #ffffff; border-color: var(--sapphire); }
        .admin-table[data-density="compact"] th, .admin-table[data-density="compact"] td { padding-top: 5px; padding-bottom: 5px; }
        .table-selection-count { color: var(--muted); font-size: 12px; }
        .table-select-cell { width: 44px; text-align: center !important; }
        .table-select-cell input { width: 18px; height: 18px; }
        .table-select-label { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; min-height: 44px; cursor: pointer; }
        .table-selection-toolbar { display: flex; align-items: center; gap: 12px; padding: 10px 18px; border-bottom: 1px solid var(--line); flex-wrap: wrap; }
        .table-density-controls[hidden], .table-selection-toolbar[hidden] { display: none; }
        .table-selection-toolbar label { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; }
        .table-selection-toolbar input { width: 18px; height: 18px; }
        .table-selection-toolbar button { min-height: 44px; padding: 8px 12px; }
        .admin-table[data-density="comfortable"] th, .admin-table[data-density="comfortable"] td { padding-top: 14px; padding-bottom: 14px; }
    </style>
@endonce

@if (($part ?? 'sort') === 'state')
    {{-- Hidden input state tabel lain; $own = kunci milik tabel ini. --}}
    @foreach (\Illuminate\Support\Arr::except($tableState ?? [], $own ?? []) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
@elseif (($part ?? 'sort') === 'sort')
    <div class="table-sort-controls">
        <div class="field">
            <label for="{{ $table }}-sort">Urutkan berdasarkan</label>
            <select id="{{ $table }}-sort" name="{{ $sortName }}">
                @foreach ($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="{{ $table }}-dir">Arah urutan</label>
            <select id="{{ $table }}-dir" name="{{ $dirName }}">
                <option value="desc" @selected($currentDir === 'desc')>Menurun (Z–A, terbaru)</option>
                <option value="asc" @selected($currentDir === 'asc')>Menaik (A–Z, terlama)</option>
            </select>
        </div>
    </div>
@elseif (($part ?? '') === 'density')
    <div class="table-density-controls" hidden data-table-enhancement>
        <button type="button" data-table-density="{{ $table }}" aria-pressed="false">Baris ringkas</button>
    </div>
@elseif ($paginator->hasPages())
    <nav class="table-pagination" aria-label="Halaman tabel {{ $label ?? $table }}">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true">Sebelumnya</span>
        @else
            <a data-table-link rel="prev" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
        @endif
        <span class="table-pagination-status" aria-current="page">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a data-table-link rel="next" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
        @else
            <span class="is-disabled" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
