<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#17263f">
    <title>Dashboard Admin — ZAFIRA</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .admin-shell { max-width: 1100px; margin: 0 auto; padding: 40px 24px 60px; }
        .admin-top { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .admin-top h1 { margin: 0; font-family: var(--serif); font-size: 44px; font-weight: 400; text-wrap: balance; }
        .admin-top p { margin: 4px 0 0; color: var(--muted); }
        .admin-nav { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
        .admin-nav a { font-size: 10px; letter-spacing: .13em; text-transform: uppercase; padding: 8px 14px; border: 1px solid var(--line); background: var(--white); }
        .admin-nav a:hover { border-color: var(--sapphire); }
        .stat-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 28px; }
        .stat-card { background: var(--white); border: 1px solid var(--line); padding: 16px 18px; }
        .stat-card span { font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); }
        .stat-card strong { display: block; font-family: var(--serif); font-size: 34px; font-weight: 500; font-variant-numeric: tabular-nums; }
        .admin-panel { background: var(--white); border: 1px solid var(--line); margin-bottom: 20px; scroll-margin-top: 90px; }
        .admin-panel h2 { margin: 0; padding: 14px 18px; font-family: var(--serif); font-size: 22px; font-weight: 500; border-bottom: 1px solid var(--line); text-wrap: balance; }
        .admin-panel > p.panel-hint { margin: 0; padding: 10px 18px 0; color: var(--muted); font-size: 13px; }
        .admin-table-wrap { overflow-x: auto; }
        .admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .admin-table th, .admin-table td { text-align: left; padding: 10px 18px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .admin-table th { font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        .admin-table td.num, .admin-table th.num { font-variant-numeric: tabular-nums; }
        .admin-table tr:last-child td { border-bottom: 0; }
        .admin-table a { text-decoration: underline; text-underline-offset: 3px; }
        .admin-empty { padding: 16px 18px; color: var(--muted); }
        .admin-actions { display: flex; gap: 10px; align-items: center; }
        .admin-actions .button { padding: 10px 22px; font-size: 12px; }
        .status-form { display: flex; gap: 6px; align-items: center; }
        .status-form select, .status-form input { padding: 6px 8px; font-size: 12px; border: 1px solid var(--line); background: var(--white); color: var(--ink); }
        .status-form button { padding: 6px 12px; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; background: var(--sapphire); color: #fff; cursor: pointer; }
        .status-form button.danger { background: #8c2f2f; }
        .badge-status { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 4px; }
        .badge-new { background: #e0f2fe; color: #0284c7; }
        .badge-contacted { background: #fef3c7; color: #d97706; }
        .badge-confirmed { background: #dcfce7; color: #16a34a; }
        .badge-completed { background: #f3e8ff; color: #7e22ce; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .concierge-notes-box { font-size: 11px; line-height: 1.4; color: var(--ink); background: #f8fafc; padding: 6px 10px; border-radius: 4px; border-left: 3px solid var(--sapphire); margin-top: 6px; max-width: 320px; white-space: pre-wrap; word-break: break-word; }
        .inline-form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 10px; padding: 16px 18px; align-items: end; }
        .inline-form .field { margin: 0; }
        .inline-form button { padding: 10px 20px; font-size: 11px; }
        @media (max-width: 900px) { .stat-grid { grid-template-columns: 1fr 1fr 1fr; } .inline-form { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 640px) { .stat-grid { grid-template-columns: 1fr 1fr; } .inline-form { grid-template-columns: 1fr; } .admin-top { flex-direction: column; align-items: start; } }
    </style>
</head>
<body class="storefront">
<a class="skip-link" href="#panel-concierge">Lewati ke concierge</a>
<div class="announcement-bar">
    <p>Administration · ZAFIRA Maison</p>
    <a href="{{ route('home') }}" style="position:absolute;right:42px;color:#fff;border-bottom:1px solid rgba(255,255,255,.45);font-size:inherit;letter-spacing:inherit;text-transform:inherit">Lihat toko</a>
</div>
<main class="admin-shell">
    <div class="admin-top">
        <div>
            <span class="eyebrow">Administration</span>
            <h1>Dashboard Maison.</h1>
            <p>Masuk sebagai {{ auth('admin')->user()->email }} · Seluruh pembaruan data tersinkronisasi langsung ke storefront, katalog kriya, dan riwayat akun klien.</p>
        </div>
        <div class="admin-actions">
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button button-dark" type="submit">Keluar</button></form>
        </div>
    </div>

    <nav class="admin-nav" aria-label="Navigasi panel admin">
        <a href="#panel-kategori">Kategori</a>
        <a href="#panel-produk">Produk</a>
        <a href="#panel-user">User</a>
        <a href="#panel-audit">Arsip audit</a>
        <a href="#panel-concierge">Concierge</a>
        <a href="#panel-subscriber">Subscriber</a>
        <a href="#panel-keamanan">Keamanan</a>
    </nav>

    @if (session('success'))
        <div class="auth-errors" role="status" style="margin-bottom:16px"><ul><li>{{ session('success') }}</li></ul></div>
    @endif

    <div class="stat-grid">
        <div class="stat-card"><span>User</span><strong>{{ $stats['users'] }}</strong></div>
        <div class="stat-card"><span>Arsip audit</span><strong>{{ $stats['backups'] }}</strong></div>
        <div class="stat-card"><span>Concierge</span><strong>{{ $stats['concierge'] }}</strong></div>
        <div class="stat-card"><span>Subscriber</span><strong>{{ $stats['subscribers'] }}</strong></div>
        <div class="stat-card"><span>Produk</span><strong>{{ $stats['products'] }}</strong></div>
        <div class="stat-card"><span>Kategori</span><strong>{{ $categories->count() }}</strong></div>
    </div>

    <section class="admin-panel" id="panel-kategori">
        <h2>Kategori — tampil sebagai pill filter di beranda</h2>
        <p class="panel-hint">Tambah/rename/hapus di sini langsung mengubah pill filter user setelah cache di-flush otomatis.</p>
        <form class="inline-form" method="POST" action="{{ route('admin.categories.store') }}" style="grid-template-columns: 1fr auto">
            @csrf
            <div class="field"><label for="cat_title">Tambah kategori</label><input id="cat_title" name="cat_title" type="text" required maxlength="255" placeholder="cth: Koleksi Kapsul…" autocomplete="off"></div>
            <button class="button button-sapphire" type="submit">Tambah</button>
        </form>
        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th class="num">ID</th><th>Judul</th><th class="num">Produk</th><th>Aksi</th></tr></thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="num">#{{ $category->cat_id }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.categories.update', $category->cat_id) }}">
                            @csrf
                            @method('PATCH')
                            <input name="cat_title" type="text" value="{{ $category->cat_title }}" required maxlength="255" aria-label="Judul kategori #{{ $category->cat_id }}">
                            <button type="submit">Simpan</button>
                        </form>
                    </td>
                    <td class="num">{{ $category->products_count }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category->cat_id) }}" onsubmit="return confirm('Hapus kategori {{ $category->cat_title }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </section>

    <section class="admin-panel" id="panel-produk">
        <h2>Produk — kelola katalog dan pindahkan kategori yang dilihat user</h2>
        <p class="panel-hint">Memindahkan kategori produk mengubah label kategori di kartu produk, halaman detail, dan hasil filter user.</p>
        
        <details style="margin: 12px 18px 16px; border: 1px solid var(--line); padding: 14px 18px; background: #fafafa;">
            <summary style="font-weight: 600; cursor: pointer; font-size: 13px; color: var(--sapphire);">+ Tambah Produk Baru</summary>
            <form class="inline-form" method="POST" action="{{ route('admin.products.store') }}" style="grid-template-columns: 1fr 1fr 1fr; padding: 14px 0 0; gap: 12px;">
                @csrf
                <div class="field"><label for="product_title">Nama Produk</label><input id="product_title" name="product_title" type="text" required placeholder="cth: Sac Royale"></div>
                <div class="field"><label for="product_cat">Kategori</label>
                    <select id="product_cat" name="product_cat" required>
                        @foreach ($categories as $category)
                            <option value="{{ $category->cat_id }}">{{ $category->cat_title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field"><label for="product_price">Harga (Rp)</label><input id="product_price" name="product_price" type="number" min="0" required placeholder="cth: 15000000"></div>
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

        <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Produk</th><th>Harga</th><th>Kategori user</th><th>Aksi</th></tr></thead>
            <tbody>
            @foreach ($latestProducts as $product)
                <tr>
                    <td><a href="{{ route('products.show', $product->product_slug) }}">{{ $product->product_title }}</a><br><small style="color:var(--muted)">{{ $product->product_slug }}</small></td>
                    <td class="num">Rp{{ number_format($product->product_price, 0, ',', '.') }}</td>
                    <td>
                        <form class="status-form" method="POST" action="{{ route('admin.products.update', $product->product_id) }}">
                            @csrf
                            @method('PATCH')
                            <select name="product_cat" aria-label="Kategori {{ $product->product_title }}">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->cat_id }}" @selected($product->product_cat == $category->cat_id)>{{ $category->cat_title }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Pindah</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.products.destroy', $product->product_id) }}" onsubmit="return confirm('Hapus produk {{ $product->product_title }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;">Hapus</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </section>

    <section class="admin-panel" id="panel-user">
        <h2>User — sama dengan data profil /akun user</h2>
        <form class="inline-form" method="GET" action="{{ route('admin.dashboard') }}#panel-user" style="grid-template-columns: 1fr auto">
            <div class="field"><label for="filter-uq">Cari user</label><input id="filter-uq" name="uq" type="search" value="{{ $filters['uq'] ?? '' }}" placeholder="cth: nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Cari</button>
        </form>
        @if ($latestUsers->isEmpty())
            <p class="admin-empty">Tidak ada user yang cocok.</p>
        @else
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nama</th><th>Email</th><th>HP</th><th>Alamat</th><th>Dibuat</th></tr></thead>
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
        @endif
    </section>

    <section class="admin-panel" id="panel-audit">
        <h2>Arsip audit — snapshot registrasi via UserObserver</h2>
        @if ($latestBackups->isEmpty())
            <p class="admin-empty">Belum ada arsip — arsip dibuat otomatis setiap registrasi via UserObserver.</p>
        @else
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th class="num">User ID</th><th>Nama</th><th>Email</th><th>HP</th><th>Diarsipkan</th></tr></thead>
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
        @endif
    </section>

    <section class="admin-panel" id="panel-concierge">
        <h2>Concierge — status di sini tampil di riwayat /akun user</h2>
        <form class="inline-form" method="GET" action="{{ route('admin.dashboard') }}#panel-concierge" style="grid-template-columns: 1fr 1fr auto">
            <div class="field"><label for="filter-status">Filter status</label><select id="filter-status" name="status"><option value="">Semua status</option>@foreach (['new', 'contacted', 'confirmed', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="field"><label for="filter-q">Cari nama/email/HP</label><input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="cth: nama@email.id…" autocomplete="off"></div>
            <button class="button button-dark" type="submit">Filter</button>
        </form>
        @if ($latestConcierge->isEmpty())
            <p class="admin-empty">Tidak ada permintaan yang cocok dengan filter.</p>
        @else
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nama & Catatan</th><th>Email</th><th>Layanan</th><th>Jadwal</th><th>Status</th></tr></thead>
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
                            <span class="badge-status badge-{{ $item->status }}">{{ $item->status }}</span>
                            <form class="status-form" method="POST" action="{{ route('admin.concierge.update', $item) }}">
                                @csrf
                                @method('PATCH')
                                <select name="status" aria-label="Status permintaan #{{ $item->id }}">
                                    @foreach (['new', 'contacted', 'confirmed', 'completed', 'cancelled'] as $status)
                                        <option value="{{ $status }}" @selected($item->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                <button type="submit">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </section>

    <section class="admin-panel" id="panel-subscriber">
        <h2>Subscriber — sama dengan form La Lettre di beranda</h2>
        @if ($latestSubscribers->isEmpty())
            <p class="admin-empty">Belum ada subscriber.</p>
        @else
            <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Email</th><th class="num">Sejak</th><th>Aksi</th></tr></thead>
                <tbody>
                @foreach ($latestSubscribers as $subscriber)
                    <tr>
                        <td style="overflow-wrap:anywhere">{{ $subscriber->email }}</td>
                        <td class="num">{{ $subscriber->created_at?->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber->id) }}" onsubmit="return confirm('Hapus subscriber {{ $subscriber->email }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="status-form button danger" type="submit" style="padding: 5px 10px; font-size: 10px;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
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
</main>

<!-- Floating Luxury Navigation Dock -->
<nav class="floating-luxury-dock" style="position:fixed; bottom:20px; left:50%; transform:translateX(-50%); display:inline-flex; align-items:center; gap:4px; background:rgba(8, 16, 29, 0.94); backdrop-filter:blur(16px); border:1px solid rgba(182, 154, 100, 0.45); border-radius:9999px; padding:5px 10px; z-index:999; box-shadow:0 16px 36px rgba(0,0,0,0.35);" aria-label="Navigasi Cepat Atelier">
    <a class="dock-btn" href="{{ route('home') }}" style="background:transparent; border:none; color:rgba(255,255,255,0.78); font-size:11px; font-weight:500; padding:7px 12px; border-radius:9999px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none;" title="Storefront Utama">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
        <span>Storefront</span>
    </a>
    @unless(app()->environment('production'))
    <a class="dock-btn" href="/prototype/#studio-view" style="background:transparent; border:none; color:rgba(255,255,255,0.78); font-size:11px; font-weight:500; padding:7px 12px; border-radius:9999px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none;" title="Studio Produk">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
        <span>Studio Z</span>
    </a>
    @endunless
    <a class="dock-btn" href="{{ route('account.show') }}" style="background:transparent; border:none; color:rgba(255,255,255,0.78); font-size:11px; font-weight:500; padding:7px 12px; border-radius:9999px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none;" title="Portal Akun Klien VIP">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        <span>Akun VIP</span>
    </a>
    <a class="dock-btn active" href="/admin" style="background:rgba(182, 154, 100, 0.22); color:#fff; border:none; font-size:11px; font-weight:500; padding:7px 12px; border-radius:9999px; display:inline-flex; align-items:center; gap:6px; cursor:pointer; text-decoration:none;" title="Konsol Admin Atelier">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
        <span>Admin Atelier</span>
    </a>
</nav>

<div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
<svg class="icon-sprite" aria-hidden="true"><symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol></svg>
</body>
</html>
