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
</head>
<body class="storefront product-page">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    @include('partials.product-header')

    <main id="main-content">
        <section class="product-detail section-shell">
            <a class="text-link back-link" href="{{ route('home') }}">← Kembali ke koleksi</a>
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
                    <div class="product-detail-assurance">
                        <span>✓ Pengiriman complimentary seluruh Indonesia</span>
                        <span>✓ Signature wrapping</span>
                        <span>✓ Care for Life</span>
                    </div>
                    <div class="product-detail-actions">
                        <button class="button button-sapphire" type="button" data-product-add
                            data-id="{{ $product->product_slug }}"
                            data-name="{{ $product->product_title }}"
                            data-category="{{ $product->category?->cat_title }}"
                            data-price="{{ $product->product_price }}"
                            data-price-label="Rp{{ number_format($product->product_price, 0, ',', '.') }}"
                            data-image="{{ $product->imageWidth(800) }}"
                            data-description="{{ $product->product_description }}"
                            data-color="{{ $product->product_color }}">
                            Tambahkan ke tas <svg aria-hidden="true"><use href="#icon-bag"/></svg>
                        </button>
                        <button class="text-link" type="button" data-open-concierge>Konsultasikan dengan advisor <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
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
                        data-id="{{ $item->product_slug }}"
                        data-name="{{ $item->product_title }}"
                        data-category="{{ $item->category?->cat_title }}"
                        data-price="{{ $item->product_price }}"
                        data-price-label="Rp{{ number_format($item->product_price, 0, ',', '.') }}"
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
                            <div class="product-color"><i style="--swatch: {{ $item->swatchHex() }}"></i>{{ $item->product_color }}</div>
                            <button class="product-add" type="button" data-card-add aria-label="Tambahkan {{ $item->product_title }} ke tas"><svg aria-hidden="true"><use href="#icon-bag"/></svg><span>Tambah</span></button>
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