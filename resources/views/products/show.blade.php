<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $product->product_alt }}">
    <meta property="og:title" content="{{ $product->product_title }} — ZAFIRA">
    <meta property="og:description" content="{{ $product->product_description }}">
    <meta property="og:image" content="{{ $product->imageWidth(1200) }}">
    <meta property="og:type" content="product">
    <meta name="twitter:card" content="summary_large_image">
    <title>{{ $product->product_title }} — ZAFIRA</title>
    <link rel="canonical" href="{{ route('products.show', $product->product_slug) }}">
    <script type="application/ld+json">
    {!! $jsonLd !!}
    </script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://images.unsplash.com">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [data-product-add]:disabled { cursor: not-allowed; }
    </style>
</head>
<body class="storefront product-page">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    @include('partials.product-header')

    <main id="main-content">
        <section class="product-detail section-shell">
            <a class="text-link back-link" href="{{ route('home') }}" style="display:inline-flex; align-items:center; gap:6px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <span>Kembali ke koleksi</span>
            </a>
            <div class="product-detail-grid">
                <div class="product-detail-media">
                    <img src="{{ $product->imageWidth(1600) }}" alt="{{ $product->product_alt }}" fetchpriority="high" width="1200" height="1500">
                </div>
                <div class="product-detail-info">
                    <span class="eyebrow">{{ $product->category?->cat_title }}</span>
                    <h1>{{ $product->product_title }}</h1>
                    @if ($product->product_badge)
                        <span class="product-badge">{{ $product->product_badge }}</span>
                    @endif
                    <p class="product-detail-price" translate="no">Rp{{ number_format($product->product_price, 0, ',', '.') }}</p>
                    <p class="product-detail-description">{{ $product->product_description }}</p>
                    <div class="product-detail-color">
                        <span>Warna</span>
                        <strong>{{ $product->product_color }}</strong>
                    </div>
                    @if ($product->product_material || $product->product_origin)
                        <dl class="product-provenance" aria-label="Kualitas dan asal kreasi">
                            @if ($product->product_material)
                                <div><dt>Material</dt><dd>{{ $product->product_material }}</dd></div>
                            @endif
                            @if ($product->product_composition)
                                <div><dt>Komposisi</dt><dd>{{ $product->product_composition }}</dd></div>
                            @endif
                            @if ($product->product_origin)
                                <div><dt>Asal</dt><dd>{{ $product->product_origin }}</dd></div>
                            @endif
                        </dl>
                    @endif
                    @if ($product->product_craft || $product->product_care)
                        <details class="product-craft" @if ($product->product_craft) open @endif>
                            <summary>Keahlian &amp; perawatan</summary>
                            @if ($product->product_craft)
                                <p><strong>Keahlian.</strong> {{ $product->product_craft }}</p>
                            @endif
                            @if ($product->product_care)
                                <p><strong>Perawatan.</strong> {{ $product->product_care }}</p>
                            @endif
                        </details>
                    @endif
                    @if (preg_match('/busana|dress|robe|kebaya|chemise|manteau|kemeja|gaun|mantel/i', $product->category?->cat_title.' '.$product->product_title))
                    <div style="margin: 16px 0 14px;">
                        <label for="pdp-size" class="eyebrow" style="font-size:10px;">Ukuran (permintaan concierge)</label>
                        <select id="pdp-size" style="display:block; padding:8px; border:1px solid var(--line); background:var(--white); color:var(--ink);">
                            <option value="">Pilih ukuran</option>
                            <option value="FR 36">FR 36</option><option value="FR 38">FR 38</option>
                            <option value="FR 40">FR 40</option><option value="FR 42">FR 42</option>
                        </select>
                        <button type="button" class="text-link" data-open-size-guide>Panduan ukuran</button>
                        <section class="drawer size-guide-drawer" data-size-guide role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="size-guide-title" tabindex="-1">
                            <div class="drawer-header"><div><span class="eyebrow">Guide des Tailles</span><h2 id="size-guide-title">Panduan Ukuran</h2></div><button class="icon-button" type="button" data-close-size-guide aria-label="Tutup panduan ukuran"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
                            <p>Ukuran FR adalah referensi pilihan awal. Konsultasikan kecocokan ukuran dengan concierge sebelum pemesanan.</p>
                        </section>
                    </div>
                    @endif

                    <!-- Live Monogram Personalization Card (Norman Constraints) -->
                    <div style="display:flex; align-items:center; gap:14px; padding:12px 14px; background:var(--ivory); border-radius:4px; border:1px solid var(--line); margin-bottom:16px;">
                        <div id="pdp-monogram-badge" style="width:42px; height:42px; border-radius:50%; border:2px solid var(--gold); background:#fff; color:var(--ink); display:flex; align-items:center; justify-content:center; font-family:var(--serif); font-size:16px; font-weight:600; box-shadow:0 2px 8px rgba(182,154,100,0.2);">Z</div>
                        <div style="flex:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:3px;">
                                <label for="pdp-monogram-input" style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--ink); margin:0;">Monogram (permintaan concierge)</label>
                                <span id="pdp-monogram-counter" aria-live="polite" style="font-size:10px; font-weight:700; color:var(--muted);">0 / 3</span>
                            </div>
                            <input type="text" id="pdp-monogram-input" maxlength="3" value="" placeholder="A-Z" aria-describedby="pdp-monogram-help" aria-label="Inisial monogram, maksimal tiga huruf" style="width:100%; padding:5px 10px; font-size:12px; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; border:1px solid var(--line); border-radius:3px; background:#fff;">
                            <small id="pdp-monogram-help" style="font-size:9.5px; color:var(--muted); display:block; margin-top:2px;">Masukkan hingga tiga huruf A–Z.</small>
                        </div>
                    </div>


                    <div class="product-detail-assurance">
                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Pengiriman complimentary seluruh Indonesia</span>
                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Signature wrapping</span>
                        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align:middle; margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg>Care for Life</span>
                    </div>
                    <p class="muted" data-personalization-note>Ukuran dan monogram hanya melalui concierge; keduanya tidak masuk tas pembelian standar.</p>
                    @php($soldOut = (int) $product->stock <= 0)
                    @if ($soldOut)
                        <p class="muted" id="pdp-sold-out-note">Stok habis. Hubungi advisor untuk informasi ketersediaan.</p>
                    @endif
                    <div class="product-detail-actions">
                        <button class="button button-sapphire" type="button" data-product-add
                            @if ($soldOut) disabled aria-describedby="pdp-sold-out-note" @endif
                            data-product-id="{{ $product->product_id }}"
                            data-id="{{ $product->product_slug }}"
                            data-name="{{ $product->product_title }}"
                            data-category="{{ $product->category?->cat_title }}"
                            data-price="{{ $product->product_price }}"
                            data-price-label="Rp{{ number_format($product->product_price, 0, ',', '.') }}"
                            data-image="{{ $product->imageWidth(800) }}"
                            data-description="{{ $product->product_description }}"
                            data-color="{{ $product->product_color }}">
                            {{ $soldOut ? 'Stok habis' : 'Tambahkan ke tas' }} <svg aria-hidden="true"><use href="#icon-bag"/></svg>
                        </button>
                        <button class="text-link" type="button" data-open-concierge data-pdp-inquiry>Konsultasikan dengan advisor <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                    </div>
                </div>
            </div>
        </section>

        @if ($related->isNotEmpty())
        <section class="related-section section-shell">
            <div class="products-heading">
                <div><span class="eyebrow">À Découvrir</span><h2>Lengkapkan kisah Anda</h2></div>
            </div>
            <div class="product-grid" data-product-grid>
                @foreach ($related as $index => $item)
                    <article class="product-card reveal" data-product-card
                        data-product-id="{{ $item->product_id }}"
                        data-id="{{ $item->product_slug }}"
                        data-name="{{ $item->product_title }}"
                        data-category="{{ $item->category?->cat_title }}"
                        data-price="{{ $item->product_price }}"
                        data-price-label="Rp{{ number_format($item->product_price, 0, ',', '.') }}"
                        data-stock="{{ max(0, (int) $item->stock) }}"
                        data-image="{{ $item->imageWidth(800) }}"
                        data-description="{{ $item->product_description }}"
                        data-color="{{ $item->product_color }}"
                        data-material="{{ $item->product_material }}"
                        data-origin="{{ $item->product_origin }}">
                        <div class="product-media">
                            <a href="{{ route('products.show', $item->product_slug) }}">
                                <img src="{{ $item->imageWidth(800) }}" alt="{{ $item->product_alt }}" loading="lazy" width="800" height="1000">
                            </a>
                            @if ($item->product_badge)<span class="product-badge">{{ $item->product_badge }}</span>@endif
                            <button class="wishlist-button" type="button" data-toggle-wishlist aria-label="Tambahkan {{ $item->product_title }} ke favorit" aria-pressed="false">
                                <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                            </button>
                            <button class="quick-view-button" type="button" data-quick-view>Lihat cepat</button>
                        </div>
                        <div class="product-info">
                            <span>{{ $item->category?->cat_title }}</span>
                            <h3><button type="button" data-quick-view>{{ $item->product_title }}</button></h3>
                            <p translate="no">Rp{{ number_format($item->product_price, 0, ',', '.') }}</p>
                            @if ($item->stock <= 0)<p>Stok habis</p>@endif
                            <div class="product-color"><i style="--swatch: {{ $item->swatchHex() }}"></i>{{ $item->product_color }}</div>
                            <button class="product-add" type="button" data-card-add @if ($item->stock <= 0) disabled @endif aria-label="{{ $item->stock <= 0 ? $item->product_title.' — stok habis' : 'Tambahkan '.$item->product_title.' ke tas' }}"><svg aria-hidden="true"><use href="#icon-bag"/></svg><span>{{ $item->stock <= 0 ? 'Stok habis' : 'Tambah' }}</span></button>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        @endif
    </main>

    @include('partials.product-footer')


</body>
</html>