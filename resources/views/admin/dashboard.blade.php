@extends('admin.layouts.app', ['pageTitle' => 'Dashboard Maison.', 'activePage' => 'dashboard'])
@section('title', 'Dashboard Admin')
@section('styles')
    <style>
        :root {
            --linen: #f4ede2;
            --zafira-muted: var(--muted, #6e6a62);
        }
        .admin-shell { max-width: 1100px; margin: 0 auto; padding: 40px 24px 60px; }
        .admin-top { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .admin-top h1 { margin: 0; font-family: var(--serif); font-size: 44px; font-weight: 400; text-wrap: balance; }
        .admin-top p { margin: 4px 0 0; color: var(--muted); }
        .admin-nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
        .admin-nav a { display: inline-flex; align-items: center; min-height: 44px; min-width: 44px; font-size: 10px; letter-spacing: .13em; text-transform: uppercase; padding: 8px 14px; border: 1px solid var(--line); background: var(--white); }
        .admin-nav a:hover { border-color: var(--sapphire); }
        .stat-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: var(--white); border: 1px solid var(--line); padding: 16px 18px; }
        .stat-card span { font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
        .stat-card strong { display: block; font-family: var(--serif); font-size: 34px; font-weight: 500; font-variant-numeric: tabular-nums; }
        .admin-panel { background: var(--white); border: 1px solid var(--line); margin-bottom: 20px; scroll-margin-top: 90px; }
        .admin-panel h2 { margin: 0; padding: 14px 18px; font-family: var(--serif); font-size: 22px; font-weight: 500; border-bottom: 1px solid var(--line); text-wrap: balance; }
        .admin-panel p.panel-hint { margin: 0; padding: 10px 18px 0; color: var(--muted); font-size: 13px; }
        .panel-context { padding: 10px 18px; color: var(--muted); font-size: 13px; }
        .panel-context .badge { display: inline-block; padding: 3px 8px; border: 1px solid rgba(32, 32, 29, 0.48); background: var(--linen); color: var(--ink); }
        .panel-context a { text-decoration: underline; }
        .admin-table-wrap { overflow-x: auto; }
        .admin-table-wrap:focus-visible { outline: 2px solid var(--focus-ring); outline-offset: 2px; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .admin-table th, .admin-table td { text-align: left; padding: 10px 18px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .admin-table th { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        .admin-table td.num, .admin-table th.num { font-variant-numeric: tabular-nums; }
        .admin-table tr:last-child td { border-bottom: 0; }
        .admin-table a { text-decoration: underline; text-underline-offset: 3px; }
        .admin-table td > a { display: inline-flex; align-items: center; min-width: 44px; min-height: 44px; }
        .admin-table .status-form { gap: 8px; }
        .admin-empty { padding: 16px 18px; color: var(--muted); }
        .admin-actions { display: flex; gap: 10px; align-items: center; }
        .admin-actions .button { min-height: 44px; min-width: 44px; padding: 10px 22px; font-size: 12px; }
        .status-form { display: flex; gap: 6px; align-items: center; }
        .status-form select, .status-form input { min-height: 44px; padding: 6px 8px; font-size: 12px; border: 1px solid var(--line); background: var(--white); color: var(--ink); }
        .status-form button, button.status-form { min-height: 44px; min-width: 44px; padding: 6px 12px; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; background: var(--sapphire); color: #fff; cursor: pointer; }
        .status-form button.danger, button.status-form.danger { background: #8c2f2f; }
        .badge-status { display: inline-block; padding: 2px 7px; border: 1px solid currentColor; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .badge-new { background: #e0f2fe; color: #075985; }
        .badge-contacted { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #dcfce7; color: #166534; }
        .badge-completed { background: #f3e8ff; color: #7e22ce; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-processing { background: #e0f2fe; color: #075985; }
        .badge-shipped { background: #ede9fe; color: #5b21b6; }
        .concierge-notes-box { font-size: 11px; line-height: 1.4; color: var(--ink); background: #f8fafc; padding: 6px 10px; border-radius: 4px; border-left: 3px solid var(--sapphire); margin-top: 6px; max-width: 320px; white-space: pre-wrap; word-break: break-word; }
        .inline-form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; padding: 16px 18px; align-items: end; }
        .inline-form .field { margin: 0; }
        .inline-form button { padding: 10px 20px; font-size: 11px; }
        .inline-form input:not([type="hidden"]), .inline-form select, .inline-form button { min-height: 44px; }
        .panel-context a { display: inline-flex; align-items: center; min-height: 44px; }
        [data-table-error] { color: #8c2f2f; margin: 0; }
        [data-admin-table][aria-busy="true"] { cursor: progress; }
        [data-admin-table][aria-busy="true"] .admin-table-wrap { border-top: 2px solid var(--sapphire); }
        @media (max-width: 900px) { .stat-grid { grid-template-columns: 1fr 1fr 1fr; } .inline-form { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 640px) { .stat-grid { grid-template-columns: 1fr 1fr; } .inline-form { grid-template-columns: 1fr; } .inline-form--mobile-stack { grid-template-columns: 1fr !important; } .admin-top { flex-direction: column; align-items: start; } }
    </style>
@endsection
@section('content')
    <p style="margin-bottom: 16px; color: var(--zafira-muted);">Seluruh pembaruan data tersinkronisasi langsung ke storefront, katalog kriya, dan riwayat akun klien.</p>
    {{-- Persistent outside swapped regions so screen readers hear loading and the new page summary. --}}
    <p class="sr-only" data-table-announcement role="status" aria-live="polite"></p>
    <nav class="admin-nav" aria-label="Navigasi panel admin">
        <a href="#panel-kategori">Kategori</a>
        <a href="#panel-produk">Produk</a>
        <a href="#panel-pesanan">Pesanan &amp; Laporan</a>
        <a href="#panel-user">User</a>
        <a href="#panel-audit">Arsip audit</a>
        <a href="#panel-concierge">Concierge</a>
        <a href="#panel-subscriber">Subscriber</a>
        <a href="#panel-keamanan">Keamanan</a>
    </nav>

    @if (auth('admin')->user()->getRawOriginal('totp_secret') === null)
        <div class="auth-errors" role="status">
            <a class="button button-sapphire" href="{{ route('admin.totp.enrol') }}">Aktifkan Verifikasi Dua Langkah</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="auth-errors" role="alert" style="margin-bottom:16px"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stat-grid">
        <div class="stat-card"><span>User</span><strong>{{ $stats['users'] }}</strong></div>
        <div class="stat-card"><span>Arsip audit</span><strong>{{ $stats['backups'] }}</strong></div>
        <div class="stat-card"><span>Concierge</span><strong>{{ $stats['concierge'] }}</strong></div>
        <div class="stat-card"><span>Subscriber</span><strong>{{ $stats['subscribers'] }}</strong></div>
        <div class="stat-card"><span>Produk</span><strong>{{ $stats['products'] }}</strong></div>
        <div class="stat-card"><span>Kategori</span><strong>{{ $stats['categories'] }}</strong></div>
    </div>

    @php
        $filterLabels = ['oq' => 'Pencarian pesanan', 'ostatus' => 'Status pesanan', 'status' => 'Status permintaan', 'q' => 'Pencarian concierge'];
        $conciergeStatusLabels = ['new' => 'Baru', 'contacted' => 'Dihubungi', 'confirmed' => 'Dikonfirmasi', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
        $kategoriAria = $filters['kdir'] === 'asc' ? 'ascending' : 'descending';
        $kategoriOther = \Illuminate\Support\Arr::except($tableState, ['kq', 'ksort', 'kdir', 'kpage']);
    @endphp
    <section class="admin-panel" id="panel-kategori">
        {{-- Hanya region ini yang diganti oleh admin-table.js; form tambah/laporan berada di luar agar draf tidak hilang. --}}
        <div data-admin-table="kategori" aria-busy="false">
        <h2 tabindex="-1">Kategori — tampil sebagai pill filter di beranda</h2>
        <p class="panel-hint">Tambah, ubah, atau hapus di sini langsung mengubah filter kategori di storefront.</p>
        <form class="inline-form" data-admin-table-form="kategori" method="GET" action="{{ route('admin.dashboard') }}#panel-kategori" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['kq', 'ksort', 'kdir', 'kpage']])
            <div class="field"><label for="filter-kq">Cari kategori</label><input id="filter-kq" name="kq" type="search" value="{{ $filters['kq'] ?? '' }}" placeholder="cth: Koleksi…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'kategori', 'sortName' => 'ksort', 'dirName' => 'kdir', 'sortOptions' => ['cat_id' => 'ID', 'cat_title' => 'Judul', 'products_count' => 'Jumlah produk'], 'currentSort' => $filters['ksort'], 'currentDir' => $filters['kdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="kategori" aria-live="polite">
            @if (($filters['kq'] ?? '') !== '')
                Filter aktif: <span class="badge" data-active-filter="kq: {{ $filters['kq'] }}">Kategori: {{ $filters['kq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', $kategoriOther) }}#panel-kategori">Reset</a> ·
            @endif
            Menampilkan {{ $categories->count() }} dari {{ $categories->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'kategori'])
        @if ($categories->isEmpty())
            <p class="admin-empty">{{ ($filters['kq'] ?? '') !== '' ? 'Tidak ada kategori yang cocok.' : 'Belum ada kategori.' }}</p>
        @else
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel kategori, dapat digulir mendatar">
        <table class="admin-table" data-density="compact" data-table-body="kategori">
            <caption class="sr-only">Kategori</caption>
            <thead><tr><th scope="col" class="num" @if ($filters['ksort'] === 'cat_id') aria-sort="{{ $kategoriAria }}" @endif>ID</th><th scope="col" @if ($filters['ksort'] === 'cat_title') aria-sort="{{ $kategoriAria }}" @endif>Judul</th><th scope="col" class="num" @if ($filters['ksort'] === 'products_count') aria-sort="{{ $kategoriAria }}" @endif>Produk</th><th scope="col">Aksi</th></tr></thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="num">#{{ $category->cat_id }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.categories.update', $category->cat_id) }}">
                            @csrf
                            @method('PATCH')
                            <input name="cat_title" type="text" value="{{ $category->cat_title }}" required maxlength="255" aria-label="Judul kategori #{{ $category->cat_id }}">
                            <button type="submit" aria-label="Simpan kategori #{{ $category->cat_id }}">Simpan</button>
                        </form>
                    </td>
                    <td class="num">{{ $category->products_count }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category->cat_id) }}" onsubmit="return confirm(@js('Hapus kategori '.$category->cat_title.'?'))">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit" aria-label="Hapus kategori {{ $category->cat_title }}">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'kategori', 'label' => 'kategori', 'paginator' => $categories])
        @endif
        </div>
        <form class="inline-form" method="POST" action="{{ route('admin.categories.store') }}" style="grid-template-columns: 1fr auto">
            @csrf
            <div class="field"><label for="cat_title">Tambah kategori</label><input id="cat_title" name="cat_title" type="text" required maxlength="255" placeholder="cth: Koleksi Kapsul…" autocomplete="off"></div>
            <button class="button button-sapphire" type="submit">Tambah</button>
        </form>
    </section>

    @php
        $produkAria = $filters['pdir'] === 'asc' ? 'ascending' : 'descending';
    @endphp
    <section class="admin-panel" id="panel-produk">
        <div data-admin-table="produk" aria-busy="false">
        <h2 tabindex="-1">Produk — kelola katalog dan pindahkan kategori yang dilihat user</h2>
        <p class="panel-hint">Memindahkan kategori produk mengubah label kategori di kartu produk, halaman detail, dan hasil filter user.</p>
        <form class="inline-form" data-admin-table-form="produk" method="GET" action="{{ route('admin.dashboard') }}#panel-produk" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['pq', 'psort', 'pdir', 'ppage']])
            <div class="field"><label for="filter-pq">Cari produk</label><input id="filter-pq" name="pq" type="search" value="{{ $filters['pq'] ?? '' }}" placeholder="cth: Sac Royale…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'produk', 'sortName' => 'psort', 'dirName' => 'pdir', 'sortOptions' => ['product_id' => 'No. katalog', 'product_title' => 'Judul', 'product_price' => 'Harga', 'stock' => 'Stok'], 'currentSort' => $filters['psort'], 'currentDir' => $filters['pdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="produk" aria-live="polite">
            @if (($filters['pq'] ?? '') !== '')
                Filter aktif: <span class="badge" data-active-filter="pq: {{ $filters['pq'] }}">Produk: {{ $filters['pq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['pq', 'psort', 'pdir', 'ppage'])) }}#panel-produk">Reset</a> ·
            @endif
            Menampilkan {{ $latestProducts->count() }} dari {{ $latestProducts->total() }} hasil
        </div>

        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'produk'])
        @if ($latestProducts->isEmpty())
            <p class="admin-empty">{{ ($filters['pq'] ?? '') !== '' ? 'Tidak ada produk yang cocok dengan pencarian.' : 'Belum ada produk.' }}</p>
        @else
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel produk, dapat digulir mendatar">
        <table class="admin-table" data-density="compact" data-table-body="produk">
            <caption class="sr-only">Produk</caption>
            <thead><tr><th scope="col" @if ($filters['psort'] === 'product_title') aria-sort="{{ $produkAria }}" @endif>Produk</th><th scope="col" class="num" @if ($filters['psort'] === 'product_id') aria-sort="{{ $produkAria }}" @endif>ID</th><th scope="col" class="num" @if ($filters['psort'] === 'product_price') aria-sort="{{ $produkAria }}" @endif>Harga</th><th scope="col" @if ($filters['psort'] === 'stock') aria-sort="{{ $produkAria }}" @endif>Kategori user / Stok</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
            <tbody>
            @foreach ($latestProducts as $product)
                <tr>
                    <td><a href="{{ route('products.show', $product->product_slug) }}">{{ $product->product_title }}</a><br><small style="color:var(--muted)">{{ $product->product_slug }}</small></td>
                    <td class="num">#{{ $product->product_id }}</td>
                    <td class="num">Rp{{ number_format($product->product_price, 0, ',', '.') }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.products.update', $product->product_id) }}">
                            @csrf
                            @method('PATCH')
                            <select name="product_cat" aria-label="Kategori {{ $product->product_title }}">
                                @foreach ($categoryOptions as $category)
                                    <option value="{{ $category->cat_id }}" @selected($product->product_cat == $category->cat_id)>{{ $category->cat_title }}</option>
                                @endforeach
                            </select>
                            <input name="stock" type="number" min="0" max="100000" step="1" value="{{ $product->stock }}" required aria-label="Stok {{ $product->product_title }}" style="width:90px">
                            <select name="is_active" aria-label="Visibilitas {{ $product->product_title }}"><option value="1" @selected($product->is_active)>Aktif</option><option value="0" @selected(! $product->is_active)>Nonaktif</option></select>
                            <button type="submit" aria-label="Simpan produk {{ $product->product_title }}">Simpan</button>
                        </form>
                    </td>
                    <td><span class="badge-status {{ $product->is_active ? 'badge-confirmed' : 'badge-cancelled' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.products.destroy', $product->product_id) }}" onsubmit="return confirm(@js('Hapus produk '.$product->product_title.'?'))">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;" aria-label="Hapus produk {{ $product->product_title }}">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'produk', 'label' => 'produk', 'paginator' => $latestProducts])
        @endif
        </div>

        <details style="margin: 12px 18px 16px; border: 1px solid var(--line); padding: 14px 18px; background: #fafafa;">
            <summary style="font-weight: 600; cursor: pointer; font-size: 13px; color: var(--sapphire);">+ Tambah Produk Baru</summary>
            <form class="inline-form" method="POST" action="{{ route('admin.products.store') }}" style="grid-template-columns: 1fr 1fr 1fr; padding: 14px 0 0; gap: 12px;">
                @csrf
                <div class="field"><label for="product_title">Nama Produk</label><input id="product_title" name="product_title" type="text" required placeholder="cth: Sac Royale"></div>
                <div class="field"><label for="product_cat">Kategori</label>
                    <select id="product_cat" name="product_cat" required>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category->cat_id }}">{{ $category->cat_title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="product_price">Harga (Rp)</label><input id="product_price" name="product_price" type="number" min="0" required placeholder="cth: 15000000"></div>
                <div class="field"><label for="product_stock">Stok</label><input id="product_stock" name="stock" type="number" min="0" max="100000" step="1" value="{{ old('stock', 0) }}" required></div>
                <div class="field"><label for="product_active">Tampil di katalog</label><select id="product_active" name="is_active"><option value="1" @selected(old('is_active', '1') == '1')>Aktif</option><option value="0" @selected(old('is_active', '1') == '0')>Nonaktif</option></select></div>
                <div class="field"><label for="product_image">URL Gambar</label><input id="product_image" name="product_image" type="url" required placeholder="https://images.unsplash.com/..."></div>
                <div class="field"><label for="product_color">Warna</label><input id="product_color" name="product_color" type="text" placeholder="cth: Noir Ébène"></div>
                <div class="field"><label for="product_badge">Badge</label><input id="product_badge" name="product_badge" type="text" placeholder="cth: Édition Limitée"></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_description">Deskripsi</label><textarea id="product_description" name="product_description" rows="2" placeholder="Deskripsi kreasi..."></textarea></div>
                <div class="field"><label for="product_material">Material</label><input id="product_material" name="product_material" type="text" placeholder="cth: Kulit anak sapi full-grain"></div>
                <div class="field"><label for="product_composition">Komposisi</label><input id="product_composition" name="product_composition" type="text" placeholder="cth: 100% kulit, lining katun"></div>
                <div class="field"><label for="product_origin">Asal / Provenance</label><input id="product_origin" name="product_origin" type="text" placeholder="cth: Kulit: Toskana · Jahit: Jakarta"></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_craft">Keahlian / Craft</label><textarea id="product_craft" name="product_craft" rows="2" placeholder="Detail jahitan, jam pengerjaan..."></textarea></div>
                <div class="field" style="grid-column: 1 / -1;"><label for="product_care">Perawatan</label><textarea id="product_care" name="product_care" rows="2" placeholder="Cara merawat kreasi..."></textarea></div>
                <div style="grid-column: 1 / -1;"><button class="button button-sapphire" type="submit">Tambah Produk</button></div>
            </form>
        </details>
    </section>

    @php
        $pesananAria = $filters['odir'] === 'asc' ? 'ascending' : 'descending';
        $pesananFilters = array_filter(['oq' => $filters['oq'] ?? '', 'ostatus' => $filters['ostatus'] ?? ''], fn ($v) => $v !== '');
        $orderStatusLabels = ['pending' => 'Menunggu', 'paid' => 'Dibayar', 'processing' => 'Diproses', 'shipped' => 'Dikirim', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];
    @endphp
    <section class="admin-panel" id="panel-pesanan">
        <div data-admin-table="pesanan" aria-busy="false">
        <h2 tabindex="-1">Pesanan &amp; Laporan</h2>
        <p class="panel-hint">{{ $stats['orders'] }} pesanan tercatat. Status administratif bukan bukti pembayaran.</p>
        <form class="inline-form inline-form--mobile-stack" data-admin-table-form="pesanan" method="GET" action="{{ route('admin.dashboard') }}#panel-pesanan" style="grid-template-columns: 1fr 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['oq', 'osort', 'odir', 'opage', 'ostatus']])
            <div class="field"><label for="filter-ostatus">Filter status</label><select id="filter-ostatus" name="ostatus"><option value="">Semua status</option>@foreach (array_keys(\App\Models\Order::STATUS_FLOW) as $orderStatus)<option value="{{ $orderStatus }}" @selected(($filters['ostatus'] ?? '') === $orderStatus)>{{ $orderStatusLabels[$orderStatus] }}</option>@endforeach</select></div>
            <div class="field"><label for="filter-oq">Cari no. pesanan/nama/email</label><input id="filter-oq" name="oq" type="search" value="{{ $filters['oq'] ?? '' }}" placeholder="cth: 1024 atau nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Filter</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'pesanan', 'sortName' => 'osort', 'dirName' => 'odir', 'sortOptions' => ['id' => 'No. pesanan', 'created_at' => 'Tanggal', 'total' => 'Nilai'], 'currentSort' => $filters['osort'], 'currentDir' => $filters['odir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="pesanan" aria-live="polite">
            @if (! empty($pesananFilters))
                Filter aktif:
                @foreach ($pesananFilters as $key => $val)
                    <span class="badge" data-active-filter="{{ $key }}: {{ $val }}">{{ $filterLabels[$key] ?? $key }}: {{ $key === 'ostatus' ? ($orderStatusLabels[$val] ?? $val) : $val }}</span>
                @endforeach
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['oq', 'osort', 'odir', 'opage', 'ostatus'])) }}#panel-pesanan">Reset</a> ·
            @endif
            Menampilkan {{ $latestOrders->count() }} dari {{ $latestOrders->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'pesanan'])
        @if ($latestOrders->isNotEmpty())
            <div class="table-selection-toolbar" hidden data-table-enhancement>
                <label><input type="checkbox" data-table-select-all="pesanan"> Pilih semua di halaman ini</label>
                <span class="table-selection-count" data-table-selection-count="pesanan" aria-live="polite">0 dipilih</span>
                <button class="button button-dark" type="button" data-table-copy-selection="pesanan" disabled>Salin no. pesanan</button>
            </div>
        @endif
        <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel pesanan, dapat digulir mendatar"><table class="admin-table" data-density="compact" data-table-body="pesanan">
            <caption class="sr-only">Pesanan</caption>
            <thead><tr><th scope="col" class="table-select-cell" hidden data-table-enhancement><span class="sr-only">Pilih</span></th><th scope="col" @if ($filters['osort'] === 'id') aria-sort="{{ $pesananAria }}" @endif>No. pesanan</th><th scope="col" @if ($filters['osort'] === 'created_at') aria-sort="{{ $pesananAria }}" @endif>Tanggal</th><th scope="col">Klien</th><th scope="col" @if ($filters['osort'] === 'total') aria-sort="{{ $pesananAria }}" @endif>Nilai tercatat</th><th scope="col">Status</th><th scope="col">Dokumen</th></tr></thead>
            <tbody>
            @forelse ($latestOrders as $order)
                <tr>
                    <td class="table-select-cell" hidden data-table-enhancement><label class="table-select-label"><input type="checkbox" data-table-select="pesanan" value="{{ $order->id }}" aria-label="Pilih pesanan #{{ $order->id }}"></label></td>
                    <td>#{{ $order->id }}</td>
                    <td>{{ $order->created_at->format('d M Y H:i') }} UTC</td>
                    <td>{{ $order->customer_name ?? $order->user?->name ?? 'Tamu' }}</td>
                    <td>Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                    <td><span class="badge-status badge-{{ $order->status }}">{{ $orderStatusLabels[$order->status] ?? $order->status }}</span>
                        @if (!empty(\App\Models\Order::STATUS_FLOW[$order->status]))
                            <form class="status-form" data-order-status-form method="POST" action="{{ route('admin.orders.update', $order->id) }}">
                                @csrf @method('PATCH')
                                <select name="status" required aria-label="Status pesanan #{{ $order->id }}">
                                    <option value="" disabled selected>Pilih status…</option>
                                    @foreach (array_diff(\App\Models\Order::STATUS_FLOW[$order->status], \App\Models\Order::MANUAL_BLOCKED) as $nextStatus)
                                        <option value="{{ $nextStatus }}">{{ $orderStatusLabels[$nextStatus] ?? $nextStatus }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" aria-label="Simpan status pesanan #{{ $order->id }}">Simpan</button>
                            </form>
                        @endif
                    </td>
                    <td><a href="{{ route('admin.orders.invoice', $order->id) }}">Slip pesanan (HTML)</a></td>
                </tr>
            @empty
                <tr><td colspan="7">{{ $pesananFilters ? 'Tidak ada pesanan yang cocok dengan filter ini.' : 'Belum ada pesanan.' }}@if ($pesananFilters) <a href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['oq', 'osort', 'odir', 'opage', 'ostatus'])) }}#panel-pesanan">Reset</a>@endif</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'pesanan', 'label' => 'pesanan', 'paginator' => $latestOrders])
        </div>
        <form class="inline-form" method="GET" action="{{ route('admin.orders.report') }}">
            <div class="field"><label for="report-period">Periode laporan</label><select id="report-period" name="period"><option value="today">Hari ini</option><option value="7d">7 hari terakhir</option><option value="30d">30 hari terakhir</option><option value="custom">Rentang tanggal</option></select></div>
            <div class="field"><label for="report-from">Dari (UTC, untuk rentang)</label><input id="report-from" type="date" name="from"></div>
            <div class="field"><label for="report-to">Sampai (UTC, untuk rentang)</label><input id="report-to" type="date" name="to"></div>
            <button class="button button-sapphire" type="submit">Unduh PDF</button>
        </form>
    </section>

    @php
        $userSort = $filters['usort'];
        $userDir = $filters['udir'] === 'asc' ? 'asc' : 'desc';
        $userAriaValue = $userDir === 'asc' ? 'ascending' : 'descending';
    @endphp
    <section class="admin-panel" id="panel-user" data-admin-table="user" aria-busy="false">
        <h2 tabindex="-1">User — sama dengan data profil /akun user</h2>
        <form class="inline-form" data-admin-table-form="user" method="GET" action="{{ route('admin.dashboard') }}#panel-user" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['uq', 'usort', 'udir', 'upage']])
            <div class="field"><label for="filter-uq">Cari user</label><input id="filter-uq" name="uq" type="search" value="{{ $filters['uq'] ?? '' }}" placeholder="cth: nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'user', 'sortName' => 'usort', 'dirName' => 'udir', 'sortOptions' => ['created_at' => 'Tanggal dibuat', 'name' => 'Nama', 'email' => 'Email'], 'currentSort' => $userSort, 'currentDir' => $userDir])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="user" aria-live="polite">
            @if (($filters['uq'] ?? '') !== '')
                Filter aktif: <span class="badge" data-active-filter="uq: {{ $filters['uq'] }}">Nama/email/HP: {{ $filters['uq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['uq', 'usort', 'udir', 'upage'])) }}#panel-user">Reset</a> ·
            @endif
            Menampilkan {{ $latestUsers->count() }} dari {{ $latestUsers->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'user'])
        @if ($latestUsers->isEmpty())
            <p class="admin-empty">Tidak ada user yang cocok.</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel user, dapat digulir mendatar">
            <table class="admin-table" data-density="compact" data-table-body="user">
                <caption class="sr-only">User</caption>
            <thead><tr><th scope="col" @if ($userSort === 'name') aria-sort="{{ $userAriaValue }}" @endif>Nama</th><th scope="col" @if ($userSort === 'email') aria-sort="{{ $userAriaValue }}" @endif>Email</th><th scope="col">HP</th><th scope="col">Alamat</th><th scope="col" @if ($userSort === 'created_at') aria-sort="{{ $userAriaValue }}" @endif>Dibuat</th></tr></thead>
                <tbody>
                @foreach ($latestUsers as $user)
                    <tr>
                        <td>{{ trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->mobile ?? '—' }}</td>
                        <td style="overflow-wrap:anywhere">{{ $user->address1 ?? '—' }}{{ $user->address2 ? ', '.$user->address2 : '' }}</td>
                        <td class="num">{{ $user->created_at?->format('d M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'user', 'label' => 'user', 'paginator' => $latestUsers])
        @endif
    </section>

    @php($auditAria = $filters['bdir'] === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-audit" data-admin-table="audit" aria-busy="false">
        <h2 tabindex="-1">Arsip audit — riwayat pendaftaran klien</h2>
        <form class="inline-form" data-admin-table-form="audit" method="GET" action="{{ route('admin.dashboard') }}#panel-audit" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['bq', 'bsort', 'bdir', 'bpage']])
            <div class="field"><label for="filter-bq">Cari nama/email</label><input id="filter-bq" name="bq" type="search" value="{{ $filters['bq'] ?? '' }}" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'audit', 'sortName' => 'bsort', 'dirName' => 'bdir', 'sortOptions' => ['created_at' => 'Tanggal arsip', 'email' => 'Email', 'first_name' => 'Nama', 'user_id' => 'No. klien'], 'currentSort' => $filters['bsort'], 'currentDir' => $filters['bdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="audit" aria-live="polite">
            @if (($filters['bq'] ?? '') !== '')
                Filter aktif: <span class="badge" data-active-filter="bq: {{ $filters['bq'] }}">Nama atau email: {{ $filters['bq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['bq', 'bsort', 'bdir', 'bpage'])) }}#panel-audit">Reset</a> ·
            @endif
            Menampilkan {{ $latestBackups->count() }} dari {{ $latestBackups->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'audit'])
        @if ($latestBackups->isEmpty())
            <p class="admin-empty">{{ ($filters['bq'] ?? '') !== '' ? 'Tidak ada arsip yang cocok dengan pencarian ini.' : 'Belum ada arsip. Dibuat otomatis setiap klien mendaftar.' }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel arsip audit, dapat digulir mendatar">
            <table class="admin-table" data-density="compact" data-table-body="audit">
                <caption class="sr-only">Arsip audit</caption>
            <thead><tr><th scope="col" class="num" @if ($filters['bsort'] === 'user_id') aria-sort="{{ $auditAria }}" @endif>User ID</th><th scope="col" @if ($filters['bsort'] === 'first_name') aria-sort="{{ $auditAria }}" @endif>Nama</th><th scope="col" @if ($filters['bsort'] === 'email') aria-sort="{{ $auditAria }}" @endif>Email</th><th scope="col">HP</th><th scope="col" class="num" @if ($filters['bsort'] === 'created_at') aria-sort="{{ $auditAria }}" @endif>Diarsipkan</th></tr></thead>
                <tbody>
                @foreach ($latestBackups as $backup)
                    <tr>
                        <td class="num">#{{ $backup->user_id }}</td>
                        <td>{{ trim(($backup->first_name ?? '').' '.($backup->last_name ?? '')) ?: ($backup->user?->name ?? '—') }}</td>
                        <td>{{ $backup->email }}</td>
                        <td>{{ $backup->mobile ?? '—' }}</td>
                        <td class="num">{{ $backup->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'audit', 'label' => 'audit', 'paginator' => $latestBackups])
        @endif
    </section>

    @php($conciergeSort = $filters['csort'])
    @php($conciergeDir = $filters['cdir'] === 'asc' ? 'asc' : 'desc')
    @php($conciergeAriaValue = $conciergeDir === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-concierge" data-admin-table="concierge" aria-busy="false">
        <h2 tabindex="-1">Concierge — status di sini tampil di riwayat /akun user</h2>
        <form class="inline-form inline-form--mobile-stack" data-admin-table-form="concierge" method="GET" action="{{ route('admin.dashboard') }}#panel-concierge" style="grid-template-columns: 1fr 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['status', 'q', 'csort', 'cdir', 'cpage']])
            <div class="field"><label for="filter-status">Filter status</label><select id="filter-status" name="status"><option value="">Semua status</option>@foreach (['new', 'contacted', 'confirmed', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $conciergeStatusLabels[$status] ?? $status }}</option>@endforeach</select></div>
            <div class="field"><label for="filter-q">Cari nama/email/HP</label><input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="cth: nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Filter</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'concierge', 'sortName' => 'csort', 'dirName' => 'cdir', 'sortOptions' => ['created_at' => 'Tanggal dibuat', 'name' => 'Nama', 'email' => 'Email', 'status' => 'Status', 'preferred_date' => 'Jadwal'], 'currentSort' => $conciergeSort, 'currentDir' => $conciergeDir])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        @php($conciergeFilters = array_filter(['status' => $filters['status'] ?? '', 'q' => $filters['q'] ?? ''], fn ($v) => $v !== ''))
        <div class="panel-context" data-panel-count="concierge" aria-live="polite">
            @if (! empty($conciergeFilters))
                Filter aktif:
                @foreach ($conciergeFilters as $key => $val)
                    <span class="badge" data-active-filter="{{ $key }}: {{ $val }}">{{ $filterLabels[$key] ?? $key }}: {{ $key === 'status' ? ($conciergeStatusLabels[$val] ?? $val) : $val }}</span>
                @endforeach
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['status', 'q', 'csort', 'cdir', 'cpage'])) }}#panel-concierge">Reset</a> ·
            @endif
            Menampilkan {{ $latestConcierge->count() }} dari {{ $latestConcierge->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'concierge'])
        @if ($latestConcierge->isEmpty())
            <p class="admin-empty">Tidak ada permintaan yang cocok dengan filter.</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel concierge, dapat digulir mendatar">
            <table class="admin-table" data-density="compact" data-table-body="concierge">
                <caption class="sr-only">Concierge</caption>
            <thead><tr><th scope="col" @if ($conciergeSort === 'name') aria-sort="{{ $conciergeAriaValue }}" @endif>Nama & Catatan</th><th scope="col" @if ($conciergeSort === 'email') aria-sort="{{ $conciergeAriaValue }}" @endif>Email</th><th scope="col">Layanan</th><th scope="col" @if ($conciergeSort === 'preferred_date') aria-sort="{{ $conciergeAriaValue }}" @endif>Jadwal</th><th scope="col" @if ($conciergeSort === 'status') aria-sort="{{ $conciergeAriaValue }}" @endif>Status</th></tr></thead>
                <tbody>
                @foreach ($latestConcierge as $item)
                    <tr>
                        <td style="overflow-wrap:anywhere">
                            <strong>{{ $item->name }}</strong>{{ $item->user ? ' · #'.$item->user_id.' '.$item->user->name : '' }}
                            @if ($item->phone)<br><small style="color:var(--muted)">WA: {{ $item->phone }}</small>@endif
                            @if ($item->notes)
                                <div class="concierge-notes-box"><strong>Catatan / Pesanan:</strong><br>{{ $item->notes }}</div>
                            @endif
                        </td>
                        <td style="overflow-wrap:anywhere">{{ $item->email }}</td>
                        <td>{{ $item->service }}</td>
                        <td class="num">{{ $item->preferred_date?->format('d M Y') ?? '—' }} {{ $item->preferred_time ?? '' }}</td>
                        <td>
                            <span class="badge-status badge-{{ $item->status }}">{{ $conciergeStatusLabels[$item->status] ?? $item->status }}</span>
                            @php($available = $item->availableTransitions())
                            @if (!empty($available))
                                <form class="status-form" method="POST" action="{{ route('admin.concierge.update', $item) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" aria-label="Status permintaan #{{ $item->id }}">
                                        @foreach ($available as $status)
                                            <option value="{{ $status }}" @selected($item->status === $status)>{{ $conciergeStatusLabels[$status] ?? $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" aria-label="Simpan status permintaan #{{ $item->id }}">Simpan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'concierge', 'label' => 'concierge', 'paginator' => $latestConcierge])
        @endif
    </section>

    @php($subscriberAria = $filters['sdir'] === 'asc' ? 'ascending' : 'descending')
    <section class="admin-panel" id="panel-subscriber" data-admin-table="subscriber" aria-busy="false">
        <h2 tabindex="-1">Subscriber — sama dengan form La Lettre di beranda</h2>
        <form class="inline-form" data-admin-table-form="subscriber" method="GET" action="{{ route('admin.dashboard') }}#panel-subscriber" style="grid-template-columns: 1fr auto">
            @include('admin.components.table-controls', ['part' => 'state', 'own' => ['sq', 'ssort', 'sdir', 'spage']])
            <div class="field"><label for="filter-sq">Cari email</label><input id="filter-sq" name="sq" type="search" value="{{ $filters['sq'] ?? '' }}" placeholder="cth: nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
            @include('admin.components.table-controls', ['part' => 'sort', 'table' => 'subscriber', 'sortName' => 'ssort', 'dirName' => 'sdir', 'sortOptions' => ['created_at' => 'Tanggal daftar', 'email' => 'Email'], 'currentSort' => $filters['ssort'], 'currentDir' => $filters['sdir']])
        </form>
        <p class="admin-empty" data-table-error role="alert" hidden></p>
        <div class="panel-context" data-panel-count="subscriber" aria-live="polite">
            @if (($filters['sq'] ?? '') !== '')
                Filter aktif: <span class="badge" data-active-filter="sq: {{ $filters['sq'] }}">Email: {{ $filters['sq'] }}</span>
                <a data-table-link href="{{ route('admin.dashboard', \Illuminate\Support\Arr::except($tableState, ['sq', 'ssort', 'sdir', 'spage'])) }}#panel-subscriber">Reset</a> ·
            @endif
            Menampilkan {{ $latestSubscribers->count() }} dari {{ $latestSubscribers->total() }} hasil
        </div>
        @include('admin.components.table-controls', ['part' => 'density', 'table' => 'subscriber'])
        @if ($latestSubscribers->isEmpty())
            <p class="admin-empty">{{ ($filters['sq'] ?? '') !== '' ? 'Tidak ada subscriber yang cocok.' : 'Belum ada subscriber.' }}</p>
        @else
            <div class="admin-table-wrap" tabindex="0" role="region" aria-label="Tabel subscriber, dapat digulir mendatar">
            <table class="admin-table" data-density="compact" data-table-body="subscriber">
                <caption class="sr-only">Subscriber</caption>
            <thead><tr><th scope="col" @if ($filters['ssort'] === 'email') aria-sort="{{ $subscriberAria }}" @endif>Email</th><th scope="col" class="num" @if ($filters['ssort'] === 'created_at') aria-sort="{{ $subscriberAria }}" @endif>Sejak</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                @foreach ($latestSubscribers as $subscriber)
                    <tr>
                        <td style="overflow-wrap:anywhere">{{ $subscriber->email }}</td>
                        <td class="num">{{ $subscriber->created_at?->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber->id) }}" onsubmit="return confirm(@js('Hapus subscriber '.$subscriber->email.'?'))">
                                @csrf
                                @method('DELETE')
                                <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;" aria-label="Hapus subscriber {{ $subscriber->email }}">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            @include('admin.components.table-controls', ['part' => 'pagination', 'table' => 'subscriber', 'label' => 'subscriber', 'paginator' => $latestSubscribers])
        @endif
    </section>

    <section class="admin-panel" id="panel-keamanan">
        <h2>Ganti password admin</h2>
        @if ($errors->any())
            <div class="auth-errors" role="alert" style="margin:16px 18px 0"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form class="inline-form" method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            @method('PATCH')
            <div class="field"><label for="current_password">Password saat ini</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
            <div class="field"><label for="password">Password baru</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="field"><label for="password_confirmation">Konfirmasi baru</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
            <button class="button button-sapphire" type="submit">Ganti</button>
        </form>
    </section>

    <p style="margin-top:20px">
        <a class="text-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            <span>Kembali ke Maison</span>
        </a>
    </p>
@endsection
@section('after-content')
<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
@endsection
