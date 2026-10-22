<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title data-inertia="">{{ $t['title'] }} — ZAFIRA: La Maison de Lumière</title>
    <link rel="canonical" href="{{ $canonical }}" data-inertia="canonical">
        @isset($page)
            @viteReactRefresh
            @vite('resources/js/inertia.jsx')
        @endisset
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,500,600,700&family=manrope:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('prototype/app.css') }}">
    <style>
        :root {
            --zafira-sapphire: #0d1b2a;
            --zafira-linen: #f4ede2;
            --zafira-gold: #c5a059;
            --zafira-ivory: #faf7f2;
            --zafira-ink: #1b1b1b;
            --zafira-line: rgba(13, 27, 42, 0.12);
        }
        /* Keep the document scroller; the prototype dock belongs to its own shell. */
        html { --dock-band: 0px; height: auto; min-height: 100%; overflow: visible; }
        body.catalog-page {
            height: auto;
            min-height: 100vh;
            overflow-x: clip;
            overflow-y: visible;
            margin: 0;
            background: var(--zafira-ivory);
            color: var(--zafira-ink);
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .catalog-skip {
            position: absolute;
            left: -9999px;
            top: 12px;
            padding: 12px 18px;
            background: var(--zafira-sapphire);
            color: #fff;
            z-index: 1000;
            text-decoration: none;
            font-size: 14px;
        }
        .catalog-skip:focus { left: 16px; }
        .catalog-header {
            border-bottom: 1px solid var(--zafira-line);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
        }
        .catalog-brand {
            font-family: 'Bodoni Moda', serif;
            font-size: 26px;
            letter-spacing: 0.18em;
            color: var(--zafira-sapphire);
            text-decoration: none;
            text-transform: uppercase;
        }
        .catalog-locales {
            display: flex;
            gap: 12px;
            font-size: 12px;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }
        .catalog-locales a {
            color: var(--zafira-ink);
            text-decoration: none;
            padding: 10px 8px;
            min-height: 44px;
            min-width: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        .catalog-locales a.active {
            font-weight: 700;
            color: var(--zafira-gold);
            border-bottom: 2px solid var(--zafira-gold);
        }
        @media (max-width: 600px) {
            .catalog-header {
                padding: 12px 16px;
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 8px;
            }
            .catalog-brand { font-size: 22px; min-height: 44px; display: inline-flex; align-items: center; }
            .catalog-header > .catalog-btn { grid-column: 2; grid-row: 1; padding-inline: 12px; }
            .catalog-locales {
                grid-column: 1 / -1;
                justify-content: space-between;
                gap: 0;
            }
            .catalog-locales a { flex: 1; }
        }
        .catalog-hero {
            text-align: center;
            padding: 48px 24px 32px;
            max-width: 820px;
            margin: 0 auto;
        }
        .catalog-eyebrow {
            font-size: 12px;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--zafira-gold);
            display: block;
            margin-bottom: 8px;
        }
        .catalog-title {
            font-family: 'Bodoni Moda', serif;
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 400;
            margin: 0 0 12px;
            color: var(--zafira-sapphire);
        }
        .catalog-lead {
            font-size: 15px;
            color: #555;
            margin: 0;
            line-height: 1.6;
        }
        .catalog-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px 64px;
        }
        .catalog-filter-disclosure {
            background: #fff;
            border: 1px solid var(--zafira-line);
            margin-bottom: 32px;
        }
        .catalog-filter-summary {
            display: none;
            min-height: 44px;
            box-sizing: border-box;
            padding: 14px 24px;
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--zafira-sapphire);
            cursor: pointer;
        }
        .catalog-filter-summary:focus-visible {
            outline: 2px solid var(--zafira-gold);
            outline-offset: 2px;
        }
        .catalog-filter-summary::-webkit-details-marker {
            color: var(--zafira-gold);
        }
        .catalog-filter-disclosure .catalog-form {
            border: 0;
            margin-bottom: 0;
        }
        .catalog-form {
            background: #fff;
            border: 1px solid var(--zafira-line);
            padding: 24px;
            margin-bottom: 32px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            align-items: end;
        }
        .catalog-form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        @media (max-width: 900px) {
            .catalog-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .catalog-filter-summary { display: list-item; list-style-position: inside; }
            .catalog-form { grid-template-columns: 1fr; }
        }
        .catalog-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .catalog-field label {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 600;
            color: var(--zafira-sapphire);
        }
        .catalog-control {
            height: 44px;
            padding: 0 14px;
            font-size: 14px;
            border: 1px solid var(--zafira-line);
            background: #fff;
            color: var(--zafira-ink);
            font-family: inherit;
            box-sizing: border-box;
            border-radius: 0;
        }
        .catalog-control:focus {
            outline: 2px solid var(--zafira-gold);
            outline-offset: 1px;
        }
        .catalog-btn {
            height: 44px;
            min-width: 44px;
            padding: 0 20px;
            font-size: 12px;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 600;
            border: 1px solid var(--zafira-sapphire);
            background: var(--zafira-sapphire);
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }
        .catalog-btn:hover, .catalog-btn:focus {
            background: var(--zafira-gold);
            border-color: var(--zafira-gold);
            color: #fff;
        }
        .catalog-btn:disabled {
            background: var(--zafira-linen);
            border-color: var(--zafira-line);
            color: var(--zafira-ink);
            cursor: not-allowed;
        }
        .catalog-btn-secondary {
            background: transparent;
            color: var(--zafira-sapphire);
        }
        .catalog-chips-section {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .catalog-chips-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--zafira-sapphire);
        }
        .catalog-chips-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .catalog-chip-link {
            min-height: 44px;
            min-width: 44px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: #fff;
            border: 1px solid var(--zafira-gold);
            color: var(--zafira-sapphire);
            font-size: 12px;
            text-decoration: none;
            box-sizing: border-box;
        }
        .catalog-chip-link:focus, .catalog-chip-link:hover {
            outline: 2px solid var(--zafira-gold);
            background: var(--zafira-linen);
        }
        .catalog-meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 13px;
            color: #666;
            border-bottom: 1px solid var(--zafira-line);
            padding-bottom: 12px;
        }
        #catalog-shop-status {
            display: block;
            line-height: 1.6;
        }
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .catalog-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 540px) {
            .catalog-grid { grid-template-columns: 1fr; }
        }
        .catalog-card {
            background: #fff;
            border: 1px solid var(--zafira-line);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        .catalog-card-media {
            aspect-ratio: 4/5;
            background: var(--zafira-linen);
            overflow: hidden;
            position: relative;
        }
        .catalog-card-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }
        .catalog-card:hover .catalog-card-media img {
            transform: scale(1.03);
        }
        .catalog-card-body {
            padding: 18px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .catalog-card-category {
            font-size: 10px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--zafira-gold);
            margin-bottom: 6px;
        }
        .catalog-card-title {
            font-family: 'Bodoni Moda', serif;
            font-size: 18px;
            font-weight: 500;
            margin: 0 0 8px;
            color: var(--zafira-sapphire);
            line-height: 1.3;
        }
        .catalog-card-title a {
            color: inherit;
            text-decoration: none;
        }
        .catalog-card-title a:hover {
            color: var(--zafira-gold);
        }
        .catalog-card-attrs {
            font-size: 12px;
            color: #666;
            margin: 0 0 12px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .catalog-card-price {
            font-size: 15px;
            font-weight: 600;
            color: var(--zafira-sapphire);
            margin-top: auto;
            margin-bottom: 14px;
        }
        .catalog-card-actions {
            display: flex;
            gap: 8px;
        }
        .catalog-card-actions .catalog-btn {
            flex: 1;
            padding: 0 8px;
            font-size: 11px;
            letter-spacing: 0.08em;
        }
        .catalog-empty {
            padding: 64px 24px;
            text-align: center;
            background: #fff;
            border: 1px solid var(--zafira-line);
        }
        .catalog-empty p {
            margin: 0 0 16px;
            font-size: 15px;
            color: #666;
        }
        .catalog-pagination {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 16px;
            font-size: 13px;
        }
        .catalog-pagination a.catalog-btn {
            min-height: 44px;
            min-width: 44px;
        }
        dialog.catalog-dialog {
            border: 1px solid var(--zafira-line);
            padding: 0;
            max-width: 680px;
            width: 90vw;
            background: #fff;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
        }
        dialog.catalog-dialog::backdrop {
            background: rgba(13, 27, 42, 0.65);
        }
        .catalog-dialog-inner {
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
        }
        @media (max-width: 640px) {
            .catalog-dialog-inner { grid-template-columns: 1fr; }
        }
        .catalog-dialog-media {
            aspect-ratio: 4/5;
            background: var(--zafira-linen);
        }
        .catalog-dialog-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .catalog-dialog-content {
            padding: 28px;
            display: flex;
            flex-direction: column;
        }
        .catalog-dialog-close {
            position: absolute;
            top: 12px;
            right: 12px;
            height: 44px;
            width: 44px;
            background: #fff;
            border: 1px solid var(--zafira-line);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--zafira-ink);
            z-index: 2;
        }
        .catalog-dialog-close:focus {
            outline: 2px solid var(--zafira-gold);
        }
        .catalog-cart-form {
            margin-top: 16px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .catalog-cart-qty {
            height: 44px;
            width: 64px;
            text-align: center;
            border: 1px solid var(--zafira-line);
            font-size: 14px;
            box-sizing: border-box;
        }
        .catalog-footer {
            border-top: 1px solid var(--zafira-line);
            padding: 32px 24px;
            text-align: center;
            font-size: 12px;
            color: #888;
            background: #fff;
        }
    </style>
</head>
<body class="catalog-page">
<x-react-root :page="$page ?? null">
    <a class="catalog-skip" href="#main-catalog">{{ $t['skip'] }}</a>

    <header class="catalog-header">
        <a class="catalog-brand" href="{{ url('/') }}">ZAFIRA</a>
        <a class="catalog-btn" href="{{ route('shop.index') }}">{{ __('shop_ui.catalog.bag') }}</a>
        <nav class="catalog-locales" aria-label="{{ $t['language'] }}">
            @foreach (['id' => 'ID', 'en' => 'EN', 'zh' => 'ZH', 'fr' => 'FR', 'it' => 'IT'] as $code => $name)
                <a href="{{ $catalogUrl([...$filters, 'locale' => $code]) }}"
                   class="{{ $locale === $code ? 'active' : '' }}"
                   aria-current="{{ $locale === $code ? 'true' : 'false' }}">
                    {{ $name }}
                </a>
            @endforeach
        </nav>
    </header>

    <main id="main-catalog" class="catalog-container">
        <section class="catalog-hero">
            <span class="catalog-eyebrow">{{ $t['eyebrow'] }}</span>
            <h1 class="catalog-title">{{ $t['title'] }}</h1>
            <p class="catalog-lead">{{ $t['lead'] }}</p>
        </section>

        @php
            $activeCount = count($chips);
        @endphp

        <details id="catalog-filters" class="catalog-filter-disclosure" data-active-count="{{ $activeCount }}" open>
            <summary class="catalog-filter-summary">
                {{ $t['filters'] }}@if($activeCount > 0) <span class="catalog-filter-count">({{ $activeCount }})</span>@endif
            </summary>

            <form class="catalog-form" method="GET" autocomplete="off" action="{{ url('/koleksi') }}" role="search" aria-label="{{ $t['filters'] }}">
                <input type="hidden" name="locale" value="{{ $locale }}">
                
                <div class="catalog-field">
                    <label for="filter-q">{{ $t['search'] }}</label>
                    <input id="filter-q" class="catalog-control" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $t['search_ph'] }}" maxlength="100">
                </div>

                @isset($filters['category'])
                    <input type="hidden" name="category" value="{{ $filters['category'] }}">
                @endisset
                <fieldset class="catalog-field catalog-category-set">
                    <legend>{{ $t['category'] }}</legend>
                    @foreach ($categoryOptions as $slug => $cat)
                        <label class="catalog-check">
                            <input type="checkbox" name="kategori" value="{{ $slug }}" @checked(in_array($slug, $filters['kategori'] ?? [], true))>
                            {{ $cat->cat_title }}
                        </label>
                    @endforeach
                </fieldset>

                <div class="catalog-field">
                    <label for="filter-color">{{ $t['color'] }}</label>
                    <select id="filter-color" class="catalog-control" name="color">
                        <option value="">{{ $t['all'] }}</option>
                        @foreach ($colors as $col)
                            <option value="{{ $col }}" @selected(($filters['color'] ?? null) == $col)>{{ $col }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="catalog-field">
                    <label for="filter-material">{{ $t['material'] }}</label>
                    <select id="filter-material" class="catalog-control" name="material">
                        <option value="">{{ $t['all'] }}</option>
                        @foreach ($materials as $mat)
                            <option value="{{ $mat }}" @selected(($filters['material'] ?? null) == $mat)>{{ $mat }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="catalog-field">
                    <label for="filter-harga-min">{{ $t['harga_min'] }}</label>
                    <input id="filter-harga-min" class="catalog-control" type="number" name="harga_min" value="{{ $filters['harga_min'] ?? '' }}" min="0" step="1">
                </div>

                <div class="catalog-field">
                    <label for="filter-harga-max">{{ $t['harga_max'] }}</label>
                    <input id="filter-harga-max" class="catalog-control" type="number" name="harga_max" value="{{ $filters['harga_max'] ?? '' }}" min="0" step="1">
                </div>

                <div class="catalog-field">
                    <label for="filter-urut">{{ $t['urut'] }}</label>
                    <select id="filter-urut" class="catalog-control" name="urut">
                        <option value="">{{ $t['all'] }}</option>
                        <option value="harga" @selected(($filters['urut'] ?? null) === 'harga')>{{ $t['price_sort'] }}</option>
                        <option value="terbaru" @selected(($filters['urut'] ?? null) === 'terbaru')>{{ $t['newest'] }}</option>
                        <option value="tersedia" @selected(($filters['urut'] ?? null) === 'tersedia')>{{ $t['available'] }}</option>
                    </select>
                </div>

                <div class="catalog-field" style="justify-content:center; align-items:flex-start;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="tersedia" value="1" @checked(($filters['tersedia'] ?? null) === '1') style="width:20px; height:20px;">
                        {{ $t['available'] }}
                    </label>
                </div>

                <div class="catalog-form-actions">
                    <button type="submit" class="catalog-btn">{{ $t['apply'] }}</button>
                    <a href="{{ $catalogUrl(['locale' => $locale]) }}" class="catalog-btn catalog-btn-secondary">{{ $t['reset'] }}</a>
                </div>
            </form>
        </details>

        @if ($chips)
            <div class="catalog-chips-section">
                <span class="catalog-chips-title">{{ $t['active_filters'] }}:</span>
                <ul class="catalog-chips-list">
                    @foreach ($chips as $chip)
                        <li>
                            <a href="{{ $chip['url'] }}" class="catalog-chip-link" aria-label="{{ $t['remove_filter'] }}: {{ $chip['label'] }}">
                                <span>{{ $chip['label'] }}</span>
                                <span aria-hidden="true">✕</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ $locale === 'id' ? url('/koleksi') : $catalogUrl(['locale' => $locale]) }}" class="catalog-btn catalog-btn-secondary" style="min-height:44px; padding:0 12px; font-size:11px;">
                    {{ $t['clear_all'] }}
                </a>
            </div>
        @endif

        <p id="catalog-shop-status" class="catalog-meta-bar" role="status" aria-live="polite">{{ __('shop_ui.catalog.notice') }} <a href="{{ url('/') }}">{{ __('shop_ui.catalog.home') }}</a> {{ __('shop_ui.catalog.notice_end') }} <a href="{{ route('shop.index') }}">{{ __('shop_ui.catalog.shop') }}</a>. <strong id="catalog-feedback"></strong></p>
        @if (!empty($isFuzzy))
            <p class="catalog-meta-bar" role="status" aria-live="polite">
                {{ $t['fuzzy_notice'] }}
            </p>
        @endif
        <div class="catalog-meta-bar">
            <span role="status" aria-live="polite"><strong>{{ $products->total() }}</strong> {{ $t['results'] }}</span>
            <small>{{ $t['currency_note'] }}</small>
        </div>

        @if ($products->isEmpty())
            <div class="catalog-empty">
                <p>{{ $t['empty'] }}</p>
                <p style="font-size:13px; color:#888;">{{ isset($filters['q']) ? $t['search_empty_hint'] : $t['empty_hint'] }}</p>
                @if ($searchRecovery === 'narrow')
                    <p style="font-size:13px; color:#888;">{{ $t['search_narrow'] }}</p>
                @endif
                @if (isset($filters['q']))
                    @php
                        $withoutQ = $filters;
                        unset($withoutQ['q']);
                    @endphp
                    <a href="{{ $catalogUrl($withoutQ) }}" class="catalog-btn catalog-btn-secondary">{{ $t['clear_search'] }}</a>
                @endif
                <a href="{{ $locale === 'id' ? url('/koleksi') : $catalogUrl(['locale' => $locale]) }}" class="catalog-btn">{{ $t['clear_all'] }}</a>
            </div>
        @else
            <div class="catalog-grid">
                @foreach ($products as $product)
                    <article class="catalog-card">
                        <div class="catalog-card-media">
                            @if ($product->product_image)
                                <img src="{{ $product->imageWidth(600) }}" @if ($product->imageSrcset([400, 600, 800])) srcset="{{ $product->imageSrcset([400, 600, 800]) }}" sizes="(max-width: 700px) 50vw, 25vw" @endif decoding="async" alt="{{ $product->imageAlt() }}" loading="lazy" width="400" height="500">
                            @endif
                        </div>
                        <div class="catalog-card-body">
                            <span class="catalog-card-category">{{ $product->category?->cat_title }}</span>
                            <h2 class="catalog-card-title">
                                <a href="{{ url('/produk/'.$product->product_slug) }}">{{ $product->product_title }}</a>
                            </h2>
                            <div class="catalog-card-attrs">
                                @if ($product->product_material)
                                    <span>{{ $product->product_material }}</span>
                                @endif
                                @if ($product->product_color)
                                    <span>{{ $product->product_color }}</span>
                                @endif
                            </div>
                            <div class="catalog-card-price" translate="no">
                                Rp{{ number_format((float) $product->product_price, 0, ',', '.') }}
                            </div>
                            @php($stock = max(0, (int) $product->stock))
                            <div class="catalog-card-attrs">{{ $t['stock'] }}: {{ $stock }}</div>
                            <div class="catalog-card-actions">
                                <button type="button" class="catalog-btn catalog-btn-secondary"
                                        data-quick-view
                                        data-title="{{ $product->product_title }}"
                                        data-category="{{ $product->category?->cat_title }}"
                                        data-price="Rp{{ number_format((float) $product->product_price, 0, ',', '.') }}"
                                        data-image="{{ $product->imageWidth(800) }}"
                                        data-alt="{{ $product->imageAlt() }}"
                                        data-description="{{ $product->product_description }}"
                                        data-material="{{ $product->product_material }}"
                                        data-color="{{ $product->product_color }}"
                                        data-product-id="{{ $product->product_id }}"
                                        data-stock="{{ max(0, (int) $product->stock) }}"
                                        data-slug="{{ $product->product_slug }}">
                                    {{ $t['quick'] }}
                                </button>
                                <form method="POST" action="{{ url('/cart/items') }}">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->product_id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="catalog-btn" aria-describedby="replace-hint-{{ $product->product_id }}" @if ($product->stock <= 0) disabled @endif>{{ $product->stock <= 0 ? $t['sold_out'] : $t['set_one'] }}</button>
                                </form>
                            </div>
                            <p id="replace-hint-{{ $product->product_id }}" class="catalog-card-attrs" style="margin:6px 0 0;">{{ $t['replace_hint'] }}</p>
                            @auth
                                <button type="button" class="catalog-btn catalog-btn-secondary" style="margin-top:8px;" data-favorite="{{ $product->product_id }}">{{ __('shop_ui.catalog.favorite') }}</button>
                            @endauth
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="catalog-pagination" aria-label="{{ $t['title'] }}">
                    @if ($products->onFirstPage())
                        <span class="catalog-btn catalog-btn-secondary" aria-disabled="true" style="opacity:0.4;">{{ $t['prev'] }}</span>
                    @else
                        <a class="catalog-btn catalog-btn-secondary" href="{{ $catalogUrl([...$filters, 'page' => $products->currentPage() - 1]) }}">{{ $t['prev'] }}</a>
                    @endif

                    <span>{{ $t['page'] }} {{ $products->currentPage() }} / {{ $products->lastPage() }}</span>

                    @if ($products->hasMorePages())
                        <a class="catalog-btn catalog-btn-secondary" href="{{ $catalogUrl([...$filters, 'page' => $products->currentPage() + 1]) }}">{{ $t['next'] }}</a>
                    @else
                        <span class="catalog-btn catalog-btn-secondary" aria-disabled="true" style="opacity:0.4;">{{ $t['next'] }}</span>
                    @endif
                </nav>
            @endif
        @endif
    </main>

    <!-- Accessible Native Dialog Quick View -->
    <dialog id="catalog-quick-dialog" class="catalog-dialog" aria-modal="true" aria-labelledby="dialog-title">
        <button type="button" class="catalog-dialog-close" id="dialog-close-btn" aria-label="{{ $t['close'] }}">✕</button>
        <div class="catalog-dialog-inner">
            <div class="catalog-dialog-media">
                <img id="dialog-image" src="" alt="" width="400" height="500">
            </div>
            <div class="catalog-dialog-content">
                <span class="catalog-card-category" id="dialog-category"></span>
                <h2 class="catalog-card-title" id="dialog-title" style="font-size:22px; margin-bottom:12px;"></h2>
                <div class="catalog-card-price" id="dialog-price" translate="no" style="font-size:18px;"></div>
                <div class="catalog-card-attrs" id="dialog-attrs"></div>
                <p id="dialog-desc" style="font-size:13px; color:#555; line-height:1.5; margin:0 0 16px;"></p>
                <form id="dialog-cart-form" method="POST" action="{{ url('/cart/items') }}" class="catalog-cart-form">
                    @csrf
                    <input type="hidden" name="product_id" id="dialog-product-id" value="">
                    <label for="dialog-qty" class="sr-only" style="position:absolute;left:-9999px;">{{ $t['qty'] }}</label>
                    <input type="number" id="dialog-qty" name="quantity" class="catalog-cart-qty" value="1" min="1" max="99" aria-describedby="dialog-replace-hint">
                    <button type="submit" id="dialog-add-button" class="catalog-btn" style="flex:1;" aria-describedby="dialog-replace-hint">{{ $t['set_quantity'] }}</button>
                </form>
                <p id="dialog-replace-hint" class="catalog-card-attrs" style="margin:8px 0 0;">{{ $t['replace_hint'] }}</p>
                <div style="margin-top:16px;">
                    <a id="dialog-detail-link" href="#" class="catalog-btn catalog-btn-secondary" style="width:100%;">{{ $t['detail'] }}</a>
                </div>
            </div>
        </div>
    </dialog>

    <footer class="catalog-footer">
        <p>© {{ date('Y') }} ZAFIRA Maison. {{ $t['lead'] }}</p>
    </footer>

</x-react-root>
    <script type="application/json" id="catalog-purchase-copy">@json(__('shop_ui.catalog.js'))</script>
    <script>
        (function() {
            var dialog = document.getElementById('catalog-quick-dialog');
            var closeBtn = document.getElementById('dialog-close-btn');
            var dialogAddButton = document.getElementById('dialog-add-button');
            var dialogQtyInput = document.getElementById('dialog-qty');
            var lastActiveElement = null;
            var textSetQuantity = @json($t['set_quantity']);
            var textSoldOut = @json($t['sold_out']);

            var filters = document.getElementById('catalog-filters');
            var mobile = window.matchMedia('(max-width: 600px)');
            function syncFilters() {
                filters.open = !mobile.matches || filters.dataset.activeCount !== '0';
            }
            syncFilters();
            mobile.addEventListener('change', syncFilters);
            document.getElementById('app')?.addEventListener('zafira:react-claim', function() {
                mobile.removeEventListener('change', syncFilters);
            }, {once: true});

            document.querySelectorAll('[data-quick-view]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    lastActiveElement = btn;
                    document.getElementById('dialog-image').src = btn.getAttribute('data-image') || '';
                    document.getElementById('dialog-image').alt = btn.getAttribute('data-alt') || '';
                    document.getElementById('dialog-category').textContent = btn.getAttribute('data-category') || '';
                    document.getElementById('dialog-title').textContent = btn.getAttribute('data-title') || '';
                    document.getElementById('dialog-price').textContent = btn.getAttribute('data-price') || '';
                    document.getElementById('dialog-desc').textContent = btn.getAttribute('data-description') || '';
                    document.getElementById('dialog-product-id').value = btn.getAttribute('data-product-id') || '';
                    document.getElementById('dialog-detail-link').href = new URL('/produk/' + encodeURIComponent(btn.getAttribute('data-slug') || ''), window.location.origin).href;
                    dialogQtyInput.value = '1';
                    var soldOut = Number(btn.getAttribute('data-stock')) <= 0;
                    dialogAddButton.disabled = soldOut;
                    dialogQtyInput.disabled = soldOut;
                    dialogAddButton.textContent = soldOut ? textSoldOut : textSetQuantity;
                    
                    var attrsContainer = document.getElementById('dialog-attrs');
                    attrsContainer.innerHTML = '';
                    var mat = btn.getAttribute('data-material');
                    var col = btn.getAttribute('data-color');
                    if (mat) {
                        var s1 = document.createElement('span');
                        s1.textContent = mat;
                        attrsContainer.appendChild(s1);
                    }
                    if (col) {
                        var s2 = document.createElement('span');
                        s2.textContent = col;
                        attrsContainer.appendChild(s2);
                    }

                    if (typeof dialog.showModal === 'function') {
                        dialog.showModal();
                        closeBtn.focus();
                    }
                });
            });

            function closeDialog() {
                if (dialog.open) {
                    dialog.close();
                    if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
                        lastActiveElement.focus();
                    }
                }
            }

            var feedback = document.getElementById('catalog-feedback');
            var csrf = document.querySelector('meta[name="csrf-token"]').content;
            // Copy comes from lang/*/shop_ui.php via #catalog-purchase-copy; fallbacks are the id text.
            var catalogCopy = JSON.parse((document.getElementById('catalog-purchase-copy') || {}).textContent || '{}');
            function catalogText(key, fallback) { return catalogCopy[key] || fallback; }
            function send(url, body) {
                return fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify(body)
                }).then(function(res) {
                    return res.json().catch(function() { return {}; }).then(function(data) {
                        if (res.ok) return data;
                        var msg = res.status === 401 ? catalogText('login', 'Silakan masuk terlebih dahulu.')
                            : (data.message || Object.values(data.errors || {}).flat().join(' ') || catalogText('failed', 'Permintaan gagal. Coba lagi.'));
                        throw new Error(msg);
                    });
                }, function() { throw new Error(catalogText('network', 'Jaringan terputus. Coba lagi.')); });
            }
            function report(promise, ok, name) {
                feedback.textContent = catalogText('processing', 'Memproses…');
                promise.then(function() { feedback.textContent = ok + (name ? ': ' + name : ''); })
                    .catch(function(e) { feedback.textContent = e.message; });
            }
            var setRequests = Promise.resolve();
            var setVersion = 0;
            document.querySelectorAll('form[action$="/cart/items"]').forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var fields = new FormData(form);
                    var qty = Math.min(99, Math.max(1, parseInt(fields.get('quantity'), 10) || 1));
                    var payload = {product_id: Number(fields.get('product_id')), quantity: qty};
                    var version = ++setVersion;
                    feedback.textContent = catalogText('processing', 'Memproses…');
                    // Snapshot intent now; serialize server writes, not just their UI callbacks.
                    setRequests = setRequests.catch(function() {}).then(function() {
                        return send(form.action, payload);
                    }).then(function() {
                        if (version === setVersion) feedback.textContent = catalogText('updated', 'Tas belanja diperbarui. Jumlah :qty menggantikan jumlah sebelumnya').replace(':qty', qty);
                    }).catch(function(error) {
                        if (version === setVersion) feedback.textContent = error.message;
                    });
                    if (dialog.open) closeDialog();
                });
            });
            document.querySelectorAll('[data-favorite]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    report(send('{{ url('/favorites') }}', {product_id: Number(btn.getAttribute('data-favorite'))}), catalogText('saved', 'Tersimpan di favorit'));
                });
            });

            closeBtn.addEventListener('click', closeDialog);
            dialog.addEventListener('cancel', function(e) {
                e.preventDefault();
                closeDialog();
            });
            dialog.addEventListener('click', function(e) {
                var rect = dialog.getBoundingClientRect();
                var isInDialog = (rect.top <= e.clientY && e.clientY <= rect.top + rect.height
                    && rect.left <= e.clientX && e.clientX <= rect.left + rect.width);
                if (!isInDialog) {
                    closeDialog();
                }
            });
        })();
    </script>
</body>
</html>
