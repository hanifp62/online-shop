@php
    $products = $products ?? [];
    $localAssets = [
        'sac-seraphine' => asset('prototype/assets/minaudiere_saphir.jpg'),
        'chemise-riviere' => asset('prototype/assets/chemise_riviere.jpg'),
        'elan-dress' => asset('prototype/assets/robe_elan.jpg'),
        'manteau-firenze' => asset('prototype/assets/manteau_firenze.jpg'),
        'echarpe-eclat' => asset('prototype/assets/echarpe_eclat.jpg'),
        'robe-aurore' => asset('prototype/assets/robe_aurore.jpg'),
        'riva-loafer' => asset('prototype/assets/mocassin_riva.jpg'),
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="ZAFIRA: rumah mode Paris–Italia–Indonesia. Siluet Paris, keahlian Italia, batik tulis, songket, dan tenun Nusantara.">
    <meta property="og:title" content="ZAFIRA: La Maison de Lumière">
    <meta property="og:description" content="Siluet Paris, keahlian Italia, jiwa Nusantara — material, asal, dan pengrajin tercantum di setiap kreasi.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('favicon.svg') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <title>ZAFIRA: La Maison de Lumière - Haute Couture Flagship v3.9</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <script type="application/ld+json">
    {!! $jsonLd !!}
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preload" as="image" href="{{ asset('prototype/assets/hero_couture.jpg') }}" fetchpriority="high">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600,700&family=manrope:300,400,500,600,700&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('prototype/app.css') }}?v=4.0.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function() {
            var theme = localStorage.getItem('zafira-theme') || 'system';
            var isDark = theme === 'dark' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme-setting', theme);
            var lang = localStorage.getItem('zafira-lang') || 'id';
            document.documentElement.setAttribute('lang', lang);
        })();
    </script>
    <script src="{{ asset('js/zafira-core.js') }}"></script>
    <style>
        /* Bespoke Luxury Overrides & Film Grain Texture */
        .film-grain-overlay {
            display: none;
        }
        .hero { position: relative; min-height: 90vh; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #08101d; }
        .hero-media { position: absolute; inset: 0; }
        .hero-media img { width: 100%; height: 100%; object-fit: cover; object-position: center 25%; filter: brightness(0.72) contrast(1.05); }
        .hero-overlay { position: absolute; inset: 0; background: rgba(8, 16, 29, 0.45); }
        .hero-edition { position: absolute; top: 120px; right: 4vw; color: rgba(255,255,255,0.7); font-family: var(--mono, monospace); font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; z-index: 2; text-align: right; }
        .hero-content { position: relative; z-index: 2; max-width: 800px; text-align: center; padding: 0 20px; color: #fff; margin-top: 40px; }
        .hero-content h1 { font-family: 'Bodoni Moda', serif; font-size: clamp(38px, 6vw, 68px); font-weight: 400; line-height: 1.1; margin: 18px 0 24px; text-shadow: 0 4px 24px rgba(0,0,0,0.4); }
        .hero-content p { font-size: clamp(14px, 1.8vw, 17px); color: rgba(255,255,255,0.88); max-width: 580px; margin: 0 auto 32px; font-weight: 300; }
        .hero-scroll { position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.75); font-size: 11px; letter-spacing: 0.25em; text-transform: uppercase; display: flex; flex-direction: column; align-items: center; gap: 8px; z-index: 2; }
        .hero-scroll span { width: 1px; height: 32px; background: rgba(182, 154, 100, 0.65); }
        
        /* Floating Luxury Dock */
        .floating-luxury-dock {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
            display: flex; align-items: center; gap: 6px;
            background: rgba(14, 23, 38, 0.88); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(182, 154, 100, 0.35); box-shadow: 0 16px 40px rgba(0,0,0,0.35);
            padding: 6px 12px; border-radius: 100px; z-index: 100; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .floating-luxury-dock .dock-badge { display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-right: 1px solid rgba(255,255,255,0.15); margin-right: 4px; }
        .floating-luxury-dock .dock-brand { font-family: 'Bodoni Moda', serif; font-size: 13px; font-weight: 600; color: #b69a64; letter-spacing: 0.1em; }
        .floating-luxury-dock .dock-ver { font-family: 'JetBrains Mono', monospace; font-size: 9px; color: rgba(255,255,255,0.5); }
        .floating-luxury-dock .dock-btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
            border-radius: 100px; font-size: 11.5px; font-weight: 500; color: rgba(255,255,255,0.85);
            background: transparent; border: 1px solid transparent; cursor: pointer; text-decoration: none;
            transition: all 0.2s ease;
        }
        .floating-luxury-dock .dock-btn:hover, .floating-luxury-dock .dock-btn.active {
            background: rgba(255,255,255,0.1); color: #fff; border-color: rgba(182, 154, 100, 0.4);
        }
        @media (max-width: 768px) {
            .hero-scroll { display: none !important; }
            .floating-luxury-dock { bottom: 14px; padding: 4px 8px; max-width: calc(100vw - 24px); }
            .floating-luxury-dock .dock-ver { display: none; }
            .floating-luxury-dock .dock-btn { padding: 6px 10px; font-size: 10.5px; }
        }
        @media (max-width: 520px) {
            .floating-luxury-dock .dock-badge { display: none; }
            .floating-luxury-dock .dock-btn span { display: none; }
            .floating-luxury-dock .dock-btn { padding: 8px 12px; }
        }
        @media (max-width: 768px) {
            .desktop-only-flex { display: none !important; }
            .announcement-bar { padding: 0 16px; height: 34px; justify-content: center; text-align: center; }
            .announcement-bar p { font-size: 8.5px; letter-spacing: 0.1em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0; }
        }
        
        /* Dark Theme Price Typography & High Contrast */
        html[data-theme="dark"] .product-info p,
        html[data-theme="dark"] .product-price,
        html[data-theme="dark"] .quick-price,
        html[data-theme="dark"] .cart-item-info > p,
        html[data-theme="dark"] [translate="no"],
        html[data-theme="dark"] [data-quick-price] {
            color: #f2eee8 !important;
            font-weight: 600;
        }
        html[data-theme="dark"] .cart-total strong,
        html[data-theme="dark"] [data-cart-total] {
            color: #f1d59b !important;
            font-weight: 700;
        }
    </style>
</head>
<body class="storefront">
    <div class="film-grain-overlay" aria-hidden="true"></div>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>

    <svg class="icon-sprite" aria-hidden="true">
        <symbol id="icon-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 17h16"/></symbol>
        <symbol id="icon-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></symbol>
        <symbol id="icon-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5.5 20c.8-4 3-6 6.5-6s5.7 2 6.5 6"/></symbol>
        <symbol id="icon-heart" viewBox="0 0 24 24"><path d="M20.8 5.9c-1.7-2.1-5-2.1-6.9-.2L12 7.6l-1.9-1.9c-1.9-1.9-5.2-1.9-6.9.2-1.5 1.9-1.2 4.7.5 6.4L12 20l8.3-7.7c1.7-1.7 2-4.5.5-6.4Z"/></symbol>
        <symbol id="icon-bag" viewBox="0 0 24 24"><path d="M5 8.5h14l-1 12H6l-1-12Z"/><path d="M9 9V6.5a3 3 0 0 1 6 0V9"/></symbol>
        <symbol id="icon-close" viewBox="0 0 24 24"><path d="m5 5 14 14M19 5 5 19"/></symbol>
        <symbol id="icon-arrow" viewBox="0 0 24 24"><path d="M4 12h15M14 6l6 6-6 6"/></symbol>
        <symbol id="icon-chevron" viewBox="0 0 24 24"><path d="m7 9 5 5 5-5"/></symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="icon-minus" viewBox="0 0 24 24"><path d="M5 12h14"/></symbol>
        <symbol id="icon-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
        <symbol id="icon-instagram" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></symbol>
    </svg>

    <div class="announcement-bar">
        <p data-i18n="announcement">Complimentary signature wrapping &amp; pengiriman seluruh Nusantara</p>
        <div class="desktop-only-flex" style="display:flex; align-items:center; gap:16px;">
            <div class="lang-switcher" role="radiogroup" aria-label="Language Selector">
                <button type="button" class="lang-btn active" data-set-lang="id" aria-label="Bahasa Indonesia">ID</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="en" aria-label="English">EN</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="fr" aria-label="Français">FR</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="it" aria-label="Italiano">IT</button>
            </div>
            <div class="theme-switcher" role="radiogroup" aria-label="Theme Selector">
                <button type="button" class="theme-btn" data-set-theme="light" title="Mode Terang" aria-label="Mode Terang">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <button type="button" class="theme-btn" data-set-theme="dark" title="Mode Gelap" aria-label="Mode Gelap">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <button type="button" class="theme-btn active" data-set-theme="system" title="Mengikuti Sistem" aria-label="Mengikuti Sistem">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </button>
            </div>
            <button type="button" data-open-concierge data-i18n="rendezvous_btn">Rendez-vous privat</button>
        </div>
    </div>

    <header class="site-header" id="site-header">
        <button class="icon-button mobile-menu-button" type="button" aria-label="Buka menu" aria-controls="mobile-menu" aria-expanded="false">
            <svg aria-hidden="true"><use href="#icon-menu"/></svg>
        </button>

        <nav class="desktop-nav" aria-label="Navigasi utama">
            <a href="#collection" data-i18n="nav_collection">Koleksi</a>
            <a href="#new-arrivals" data-i18n="nav_arrivals">Terbaru</a>
            <a href="#heritage" data-i18n="nav_heritage">Warisan</a>
            <a href="#atelier" data-i18n="nav_atelier">Atelier Z</a>
            <a href="#packaging-section" data-i18n="nav_packaging">L'Art d'Offrir</a>
            <a href="#services" data-i18n="nav_services">Layanan</a>
            @unless(app()->environment('production'))
            <a href="/prototype/" style="color:var(--gold); font-weight:600;" data-i18n="nav_digital_atelier">Atelier Digital</a>
            @endunless
        </nav>

        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA: kembali ke beranda">
            <x-brand-mark />
        </a>

        <div class="header-actions">
            <button class="icon-button" type="button" data-open-search aria-label="Cari">
                <svg aria-hidden="true"><use href="#icon-search"/></svg>
            </button>
            <button class="icon-button" type="button" id="btn-header-profile" data-open-profile aria-label="Profil VIP &amp; Pilihan Salon" title="Profil VIP &amp; Pilihan Salon" style="position:relative;">
                <svg aria-hidden="true"><use href="#icon-user"/></svg>
                <span style="position:absolute; top:7px; right:7px; width:7px; height:7px; border-radius:50%; background:var(--gold); box-shadow:0 0 8px var(--gold);"></span>
            </button>
            <button class="icon-button wishlist-trigger" type="button" data-show-wishlist aria-label="Tampilkan favorit">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg><span class="action-count wishlist-count">0</span>
            </button>
            <button class="icon-button" type="button" data-open-cart aria-label="Buka tas belanja">
                <svg aria-hidden="true"><use href="#icon-bag"/></svg><span class="action-count cart-count">0</span>
            </button>
        </div>
    </header>

    <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
        <div class="mobile-menu-top">
            <a class="brand brand-horizontal" href="{{ route('home') }}" aria-label="ZAFIRA beranda"><x-brand-mark /></a>
            <button class="icon-button" type="button" data-close-menu aria-label="Tutup menu"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        </div>
        <nav>
            <a href="#collection"><span style="color:var(--gold);">01</span><span data-i18n="nav_collection">Koleksi</span></a>
            <a href="#new-arrivals"><span style="color:var(--gold);">02</span><span data-i18n="nav_arrivals">Terbaru</span></a>
            <a href="#heritage"><span style="color:var(--gold);">03</span><span data-i18n="nav_heritage">Warisan</span></a>
            <a href="#atelier"><span style="color:var(--gold);">04</span><span data-i18n="nav_atelier">Atelier Z</span></a>
            <a href="#packaging-section"><span style="color:var(--gold);">05</span><span data-i18n="nav_packaging">L'Art d'Offrir</span></a>
            @unless(app()->environment('production'))
            <a href="/prototype/" style="color:var(--gold);"><span style="color:var(--gold);">06</span><span data-i18n="nav_digital_atelier">Atelier Digital &amp; Kriya</span></a>
            @endunless
        </nav>
        <div class="mobile-utilities" style="display:flex; flex-direction:column; gap:14px; padding:18px 0; border-top:1px solid rgba(255,255,255,0.15); border-bottom:1px solid rgba(255,255,255,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:10px; text-transform:uppercase; letter-spacing:0.15em; color:var(--gold);" data-i18n="lang_selector">Bahasa</span>
                <div class="lang-switcher" role="radiogroup">
                    <button type="button" class="lang-btn active" data-set-lang="id">ID</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="en">EN</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="fr">FR</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="it">IT</button>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:10px; text-transform:uppercase; letter-spacing:0.15em; color:var(--gold);">Mode Tampilan</span>
                <div class="theme-switcher" role="radiogroup">
                    <button type="button" class="theme-btn" data-set-theme="light" title="Mode Terang">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </button>
                    <button type="button" class="theme-btn" data-set-theme="dark" title="Mode Gelap">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <button type="button" class="theme-btn active" data-set-theme="system" title="Mengikuti Sistem">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </button>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                @auth
                    <a href="{{ route('account.show') }}"><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-user"/></svg>Profil</a>
                @else
                    <a href="{{ route('login') }}"><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-user"/></svg>Masuk</a>
                @endauth
                <button type="button" data-show-wishlist><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-heart"/></svg><span data-i18n="wishlist_aria">Favorit</span> <span class="wishlist-count">0</span></button>
            </div>
        </div>
        <button class="text-link light mobile-concierge" type="button" data-open-concierge data-i18n="rendezvous_btn">Atur rendez-vous <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
    </div>

    <main id="main-content">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-media">
                <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="Editorial mode ZAFIRA bernuansa klasik" fetchpriority="high" width="2000" height="2500">
            </div>
            <div class="hero-overlay"></div>
            <div class="hero-edition">Édition No. 01<br>Jakarta · Paris · Firenze</div>
            <div class="hero-content reveal is-visible">
                <span class="eyebrow light" data-i18n="hero_eyebrow">La Collection Première · 2026</span>
                <h1 id="hero-title" data-i18n="hero_title">Lahir dari Cahaya,<br>Dibentuk oleh Keanggunan</h1>
                <p data-i18n="hero_subtitle">Keanggunan Paris bertemu keahlian Italia dalam setiap kreasi yang memancarkan jiwa Anda.</p>
                <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                    <a class="button button-light" href="#new-arrivals" data-i18n="hero_cta_explore">Jelajahi koleksi <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                    @unless(app()->environment('production'))
                    <a class="button button-sapphire" href="/prototype/" style="border:1px solid rgba(182,154,100,0.6);" data-i18n="hero_cta_studio">Studio Kriya &amp; Savoir-Faire <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                    @endunless
                </div>
            </div>
            <a class="hero-scroll" href="#collection" aria-label="Gulir ke koleksi"><span></span><span data-i18n="hero_scroll">Découvrir</span></a>
        </section>

        <section class="marquee" aria-label="Nilai ZAFIRA">
            <div class="marquee-track">
                <span>L’Esprit Parisien</span><span class="marquee-bullet">·</span><span>La Mano Italiana</span><span class="marquee-bullet">·</span><span>Jiwa Nusantara</span><span class="marquee-bullet">·</span><span>Depuis Jakarta</span><span class="marquee-bullet">·</span>
                <span>L’Esprit Parisien</span><span class="marquee-bullet">·</span><span>La Mano Italiana</span><span class="marquee-bullet">·</span><span>Jiwa Nusantara</span><span class="marquee-bullet">·</span><span>Depuis Jakarta</span><span class="marquee-bullet">·</span>
            </div>
        </section>

        <section class="category-section section-shell" id="collection">
            <div class="section-heading reveal">
                <div><span class="eyebrow" data-i18n="category_eyebrow">L’Univers ZAFIRA</span><h2 data-i18n="category_title">Temukan dunia Anda</h2></div>
                <p data-i18n="category_desc">Setiap koleksi dirancang sebagai percakapan antara warisan dan modernitas.</p>
            </div>
            <div class="category-grid">
                <a class="category-card category-card-tall reveal" href="#new-arrivals" data-category-link="Warisan Nusantara">
                    <img src="{{ asset('prototype/assets/chemise_riviere.jpg') }}" alt="Koleksi Warisan Nusantara ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">01</span>
                    <div><span data-i18n="cat_nusantara">Warisan Nusantara</span><h3>Indonesia</h3><span class="discover"><span data-i18n="hero_scroll">Découvrir</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="#new-arrivals" data-category-link="Tas Tangan">
                    <img src="{{ asset('prototype/assets/minaudiere_saphir.jpg') }}" alt="Koleksi tas tangan ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">02</span>
                    <div><span>Les Icônes</span><h3 data-i18n="cat_bags">Tas Tangan</h3><span class="discover"><span data-i18n="hero_scroll">Découvrir</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="#new-arrivals" data-category-link="Koleksi Kapsul">
                    <img src="{{ asset('prototype/assets/robe_elan.jpg') }}" alt="Koleksi kapsul songket dan sutra ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">03</span>
                    <div><span>Deux Rives</span><h3 data-i18n="cat_capsule">Koleksi Kapsul</h3><span class="discover"><span data-i18n="hero_scroll">Découvrir</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
            </div>
        </section>

        <section class="products-section" id="new-arrivals">
            <div class="section-shell">
                <div class="products-heading reveal">
                    <div><span class="eyebrow" data-i18n="products_eyebrow">Les Nouveautés</span><h2 data-i18n="products_title">Objek-objek penuh pesona</h2></div>
                    <p class="result-count" role="status" aria-live="polite"><span data-product-count>{{ count($products) }}</span> <span data-i18n="products_count_suffix">kreasi</span></p>
                </div>

                <!-- Live Catalog Control Bar (Shneiderman / Nielsen) -->
                <div style="margin: 20px 0 24px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                        <div class="filter-bar" role="group" aria-label="Filter kategori produk">
                            <button type="button" class="filter-pill active" data-filter="Semua" aria-pressed="true" data-i18n="filter_all">Semua</button>
                            @foreach (($categories ?? collect()) as $category)
                                <button type="button" class="filter-pill" data-filter="{{ $category->cat_title }}" aria-pressed="false">{{ $category->cat_title }}</button>
                            @endforeach
                        </div>
                        <form style="position:relative; min-width:260px; flex:1; max-width:340px;" role="search" aria-label="Cari kreasi dalam katalog" onsubmit="event.preventDefault()">
                            <label for="live-catalog-search-input" class="sr-only">Cari kreasi dalam katalog</label>
                            <input type="search" id="live-catalog-search-input" placeholder="Cari kreasi kriya, tas, sutra..." data-i18n-placeholder="search_placeholder"
                                   style="width:100%; padding:9px 16px 9px 36px; border:1px solid rgba(32,32,29,0.18); border-radius:100px; font-size:12.5px; background:rgba(255,255,255,0.85); outline:none;"
                                   autocomplete="off" spellcheck="false" name="cari-katalog">
                            <svg aria-hidden="true" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:14px; height:14px; stroke:var(--taupe); fill:none;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </form>
                    </div>
                    <div id="welcome-feedback-strip" role="status" aria-live="polite" style="display:none; font-size:11.5px; color:var(--muted); background:rgba(182,154,100,0.1); border-left:3px solid var(--gold); padding:6px 14px; border-radius:4px;">
                        Menampilkan hasil kurasi instan
                    </div>
                </div>

                <div class="product-grid" data-product-grid>
                    @foreach ($products as $index => $product)
                        @php
                            $imgSrc = $localAssets[$product['id']] ?? $product['image'];
                        @endphp
                        <article class="product-card reveal" data-product-card
                            data-id="{{ $product['id'] }}"
                            data-name="{{ $product['name'] }}"
                            data-category="{{ $product['category'] }}"
                            data-price="{{ $product['price'] }}"
                            data-price-label="{{ $product['price_label'] }}"
                            data-image="{{ $imgSrc }}"
                            data-description="{{ $product['description'] }}"
                            data-color="{{ $product['color'] }}"
                            data-material="{{ $product['material'] }}"
                            data-origin="{{ $product['origin'] }}">
                            <div class="product-media">
                                <img src="{{ $imgSrc }}" alt="{{ $product['alt'] }}" loading="lazy" width="800" height="1000">
                                @if ($product['badge'])<span class="product-badge">{{ $product['badge'] }}</span>@endif
                                <button class="wishlist-button" type="button" data-toggle-wishlist aria-label="Tambahkan {{ $product['name'] }} ke favorit" aria-pressed="false">
                                    <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                                </button>
                                <button class="quick-view-button" type="button" data-quick-view>Lihat cepat</button>
                            </div>
                            <div class="product-info">
                                <span>{{ $product['category'] }}</span>
                                <h3><button type="button" data-quick-view>{{ $product['name'] }}</button></h3>
                                <p translate="no">{{ $product['price_label'] }}</p>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                    <div class="product-color"><i style="--swatch: {{ $product['swatch'] }}"></i>{{ $product['color'] }}</div>
                                    @unless(app()->environment('production'))
                                    <a href="/prototype/?p={{ $product['id'] }}#studio-view" class="text-link" style="font-size:8.5px; color:var(--gold); display:inline-flex; align-items:center; gap:4px;" title="Kustomisasi di Studio Kriya"><span>Studio Z</span> <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg></a>
                                    @endunless
                                </div>
                                <button class="product-add" type="button" data-card-add aria-label="Tambahkan {{ $product['name'] }} ke tas"><svg aria-hidden="true"><use href="#icon-bag"/></svg><span>Tambah</span></button>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="empty-products" hidden>Tidak ada kreasi yang sesuai dengan pilihan Anda.</p>
            </div>
        </section>

        <section class="maison-story" id="maison">
            <div class="story-image reveal">
                <img src="{{ asset('prototype/assets/manteau_firenze.jpg') }}" alt="Suasana atelier mode klasik" loading="lazy" width="1400" height="1750">
                <span>Depuis 2026</span>
            </div>
            <div class="story-content reveal">
                <span class="eyebrow light" data-i18n="maison_eyebrow">L’Histoire de la Maison</span>
                <h2 data-i18n="maison_title">Seperti safir,<br>keindahan sejati<br>lahir dalam proses.</h2>
                <p data-i18n="maison_body">ZAFIRA lahir dari keyakinan bahwa kemewahan tidak perlu bersuara keras. Ia hadir dalam ketepatan jahitan, sentuhan material, dan rasa percaya diri yang tertinggal lama setelah Anda meninggalkan ruangan.</p>
                <p class="story-signature" data-i18n="footer_motto">Parisian in spirit. Italian by craft. Indonesian at heart.</p>
                <a href="#atelier" class="text-link light"><span data-i18n="maison_link">Kisah Maison</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="heritage-section section-shell" id="heritage" aria-labelledby="heritage-title">
            <div class="section-heading reveal">
                <div><span class="eyebrow" data-i18n="heritage_eyebrow">Trois Héritages · Tiga Warisan</span><h2 id="heritage-title" data-i18n="heritage_title">Tiga tanah, satu Maison</h2></div>
                <p data-i18n="heritage_subtitle">Paris memberi siluet. Italia memberi tangan. Indonesia memberi jiwa dan materialnya.</p>
            </div>
            <div class="heritage-grid">
                <article class="heritage-card reveal">
                    <span class="heritage-number">I</span>
                    <h3 data-i18n="heritage_1_title">L’Esprit Parisien</h3>
                    <p data-i18n="heritage_1_desc">Siluet, drapery, dan keberanian tampil tenang — digambar di Paris, dikenakan di mana saja.</p>
                    <span class="heritage-proof" data-i18n="heritage_1_proof">Kain Lyon &amp; Biella · potongan bias-cut</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">II</span>
                    <h3 data-i18n="heritage_2_title">La Mano Italiana</h3>
                    <p data-i18n="heritage_2_desc">Kulit Santa Croce, jahitan saddle-stitch, sepatu Blake-stitched — keahlian yang bisa disol ulang.</p>
                    <span class="heritage-proof" data-i18n="heritage_2_proof">Kulit Toskana &amp; Marche · finishing tangan</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">III</span>
                    <h3 data-i18n="heritage_3_title">Jiwa Nusantara</h3>
                    <p data-i18n="heritage_3_desc">Batik tulis Solo, songket Palembang, tenun Flores, bordir Kebayoran — warisan hidup, bukan ornamen.</p>
                    <span class="heritage-proof" data-i18n="heritage_3_proof">Pewarna alam · tenun kayu · borongan adil</span>
                </article>
            </div>
        </section>

        <section class="atelier-section section-shell" id="atelier">
            <div class="atelier-copy reveal">
                <span class="eyebrow" data-i18n="atelier_eyebrow">Atelier Z · Personnalisation</span>
                <h2 data-i18n="atelier_title">Jadikan ia milik Anda,<br>sepenuhnya.</h2>
                <p data-i18n="atelier_desc">Terinspirasi layanan bespoke rumah mode Eropa, Atelier Z menghadirkan monogram pribadi pada kreasi kulit pilihan—dikerjakan tanpa biaya tambahan.</p>
                <ul>
                    <li><span>01</span><span data-i18n="atelier_step_1">Pilih hingga tiga inisial huruf kapital</span></li>
                    <li><span>02</span><span data-i18n="atelier_step_2">Tentukan nuansa foil emas, perak, atau safir</span></li>
                    <li><span>03</span><span data-i18n="atelier_step_3">Bordir tangan complimentary oleh artisan ZAFIRA</span></li>
                </ul>
                <button class="button button-dark" type="button" data-open-concierge><span data-i18n="atelier_cta">Personalisasi Kreasi Anda</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
            </div>
            <div class="monogram-studio reveal">
                <div class="monogram-product">
                    <div class="monogram-tag">
                        <span class="monogram-preview" id="monogram-preview-display" data-monogram-preview>ZA</span>
                    </div>
                    <span class="bag-handle"></span>
                    <div class="bag-body"><span class="bag-lock">Z</span></div>
                    <p>Pratinjau konseptual</p>
                </div>
                <div class="monogram-controls">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label for="monogram-input" style="margin:0;">Inisial Anda</label>
                        <span id="welcome-monogram-counter" style="font-family:'JetBrains Mono', monospace; font-size:10px; font-weight:700; color:var(--gold); background:rgba(182,154,100,0.1); padding:2px 8px; border-radius:10px;">[ 2 / 3 ]</span>
                    </div>
                    <input id="monogram-input" name="monogram" type="text" maxlength="3" value="ZA" autocomplete="off" spellcheck="false" inputmode="text" aria-describedby="monogram-hint"
                           style="margin-top:6px; letter-spacing:0.25em; text-transform:uppercase; font-family:'Bodoni Moda', serif; font-size:16px;">
                    <small id="monogram-hint">Maksimal 3 inisial A–Z · Pratinjau konsep, konsultasikan pesanan dengan concierge.</small>
                    <span class="control-label" style="margin-top:14px; display:block;">Warna foil</span>
                    <div class="foil-options" role="group" aria-label="Pilih warna foil">
                        <button type="button" class="foil active" style="--foil:#c7a665" data-foil="#c7a665" aria-label="Emas" aria-pressed="true"></button>
                        <button type="button" class="foil" style="--foil:#d7d4ce" data-foil="#d7d4ce" aria-label="Perak" aria-pressed="false"></button>
                        <button type="button" class="foil" style="--foil:#263a5d" data-foil="#263a5d" aria-label="Safir" aria-pressed="false"></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Signature Unboxing & Packaging Experience (L'Art d'Offrir ZAFIRA) -->
        <section class="packaging-section" id="packaging-section" aria-labelledby="packaging-heading">
            <div class="packaging-inner">
                <div class="reveal">
                    <span class="eyebrow" data-i18n="packaging_eyebrow">L'Art d'Offrir ZAFIRA</span>
                    <h2 id="packaging-heading" style="font-family:'Bodoni Moda', serif; font-size:clamp(28px, 4vw, 40px); margin:8px 0 16px; color:var(--sapphire-midnight);" data-i18n="packaging_title">Kemasan Kado Ikonik &amp; Segel Lilin Emas</h2>
                    <p style="color:var(--muted); line-height:1.8; margin-bottom:20px; font-size:15px;" data-i18n="packaging_desc">
                        Setiap kreasi dihantarkan dalam kotak kaku safir pekat yang dibuat dengan tangan, diikat pita sutra twill Lyon, dan dilindungi kartu kaligrafi bertanda stempel segel lilin carmine-emas Maison ZAFIRA.
                    </p>
                    <div style="display:flex; gap:16px; align-items:center; margin-bottom:24px; flex-wrap:wrap;">
                        <label style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); letter-spacing:0.1em;" data-i18n="packaging_ribbon_label">Pilihan Pita Sutra:</label>
                        <button type="button" class="button" id="btn-ribbon-gold" style="min-height:36px; padding:0 18px; background:#b89858; color:#0f1c33; font-weight:700; border-color:#b89858; font-size:10px;" onclick="setWelcomeRibbon('gold')" data-i18n="packaging_ribbon_gold">Pita Emas (Or)</button>
                        <button type="button" class="button" id="btn-ribbon-sapphire" style="min-height:36px; padding:0 18px; background:#0f1c33; color:#fff; border-color:#0f1c33; font-size:10px;" onclick="setWelcomeRibbon('sapphire')" data-i18n="packaging_ribbon_sapphire">Pita Safir (Saphir)</button>
                    </div>
                    <div style="font-size:12px; color:var(--muted); display:flex; align-items:center; gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span data-i18n="packaging_complimentary">Layanan kemasan kado adibusana ini diberikan secara cuma-cuma (complimentary).</span>
                    </div>
                </div>

                <div class="unboxing-card reveal">
                    <div class="box-preview-visual">
                        <div class="ribbon-simulation" id="welcome-ribbon-band" style="background:#b89858;"></div>
                        <div style="position:relative; z-index:2; margin-bottom:12px;">
                            <span style="font-family:'Bodoni Moda', serif; font-size:26px; letter-spacing:0.25em; display:block;">ZAFIRA</span>
                            <small style="font-size:9px; letter-spacing:0.35em; text-transform:uppercase; color:var(--gold-light);">Atelier Jakarta</small>
                        </div>
                        <div class="wax-seal-badge" id="welcome-wax-seal" onclick="stampWelcomeWaxSeal()" title="Pratinjau segel lilin" role="button" tabindex="0" aria-label="Segel lilin ZAFIRA">
                            Z
                        </div>
                        <p style="font-size:10.5px; color:var(--gold-light); margin-top:14px; position:relative; z-index:2; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <span class="indicator-dot gold" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--gold);"></span> Sentuh stempel untuk melihat pratinjau segel lilin
                        </p>
                    </div>
                    <div style="font-size:12.5px; color:var(--ink); font-style:italic; background:var(--ivory); padding:16px; border-radius:var(--radius-xs); border:1px solid var(--line);">
                        “Untuk Anda, dengan penghormatan atas kecintaan pada adibusana Nusantara.”
                    </div>
                </div>
            </div>
        </section>

        <section class="editorial-banner">
            <img src="{{ asset('prototype/assets/robe_aurore.jpg') }}" alt="Perempuan dengan busana editorial klasik" loading="lazy" width="2000" height="2666">
            <div class="editorial-content reveal">
                <span class="eyebrow light" data-i18n="editorial_eyebrow">Le Journal · Koleksi Kapsul</span>
                <h2 data-i18n="editorial_title">Songket &amp; Sutra,<br>Deux Rives.</h2>
                <p class="editorial-lede" data-i18n="editorial_desc">Palembang bertemu Lyon dalam selendang dan gaun kapsul — tenun kayu, benang emas, pewarna alam.</p>
                <a href="#heritage" class="button button-light"><span data-i18n="editorial_cta">Temui pengrajin</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="services-section section-shell" id="services">
            <div class="services-heading reveal">
                <span class="eyebrow" data-i18n="services_eyebrow">Les Services ZAFIRA</span>
                <h2 data-i18n="services_title">Lebih dari sebuah kreasi,<br>sebuah pengalaman.</h2>
            </div>
            <div class="services-grid">
                <button class="service-card reveal" type="button" data-open-concierge>
                    <span class="service-icon">01</span><h3 data-i18n="service_1_title">Private Styling</h3><p data-i18n="service_1_desc">Sesi kurasi personal bersama ZAFIRA Client Advisor, secara privat di boutique atau virtual.</p><span class="text-link"><span data-i18n="service_1_link">Atur rendez-vous</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </button>
                <article class="service-card reveal">
                    <span class="service-icon">02</span><h3 data-i18n="service_2_title">Transparansi Penuh</h3><p data-i18n="service_2_desc">Setiap kreasi mencantumkan material, komposisi, asal, dan siapa yang mengerjakannya.</p><span class="text-link"><span data-i18n="service_2_link">Lihat di halaman produk</span> <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">03</span><h3 data-i18n="service_3_title">Care for Life</h3><p data-i18n="service_3_desc">Perawatan artisan untuk menjaga bentuk, warna, dan kisah kreasi ZAFIRA Anda sepanjang masa.</p><span class="text-link"><span data-i18n="service_3_link">Hubungi concierge</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">04</span><h3 data-i18n="service_4_title">Kartu Kriya</h3><p data-i18n="service_4_desc">Setiap kreasi dihantarkan bersama kartu kriya yang mencantumkan material, asal, dan pengrajin yang mengerjakannya.</p><span class="text-link"><span data-i18n="service_4_link">Lihat di halaman produk</span> <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
            </div>
        </section>

        <section class="newsletter-section">
            <div class="newsletter-gem" aria-hidden="true"><i></i></div>
            <div class="reveal">
                <span class="eyebrow light" data-i18n="newsletter_eyebrow">La Lettre ZAFIRA</span>
                <h2 data-i18n="newsletter_title">Masuk ke dunia Maison.</h2>
                <p data-i18n="newsletter_desc">Terima cerita atelier, undangan privat, dan akses pertama ke koleksi terbaru.</p>
                <form class="newsletter-form" action="{{ route('newsletter.store') }}" method="POST" data-ajax-form data-success-reset>
                    @csrf
                    <label class="sr-only" for="newsletter-email">Alamat email</label>
                    <input id="newsletter-email" type="email" name="email" placeholder="nama@contoh.id…" data-i18n-placeholder="newsletter_placeholder" required autocomplete="email" spellcheck="false">
                    <button type="submit" aria-label="Berlangganan"><svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                </form>
                <small data-i18n="newsletter_privacy">Dengan berlangganan, Anda menyetujui kebijakan privasi ZAFIRA.</small>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-brand"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><p data-i18n="footer_motto">Parisian in spirit.<br>Italian by craft.<br>Indonesian at heart.</p></div>
        <div class="footer-links"><h3 data-i18n="footer_col_collection">Koleksi</h3><a href="#new-arrivals" data-i18n="footer_women">Wanita</a><a href="#new-arrivals" data-i18n="footer_men">Pria</a><a href="#new-arrivals" data-i18n="footer_bags">Tas Tangan</a><a href="#new-arrivals" data-i18n="footer_accessories">Aksesori</a></div>
        <div class="footer-links"><h3 data-i18n="footer_col_maison">La Maison</h3><a href="#maison" data-i18n="footer_story">Kisah Kami</a><a href="#atelier" data-i18n="footer_atelier_z">Atelier Z</a><a href="#services" data-i18n="footer_services">Layanan</a><button type="button" data-open-concierge data-i18n="footer_rendezvous">Rendez-vous</button></div>
        <!-- Canonical Luxury Footer International Preferences Bar (Hermès & Chanel ATM) -->
        <div class="footer-preferences-bar">
            <div class="footer-pref-item">
                <label class="footer-pref-label" for="welcome-footer-lang-select" data-i18n="footer_lang_label">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span data-i18n="footer_lang_label">Wilayah &amp; Bahasa</span>
                </label>
                <select id="welcome-footer-lang-select" class="zafira-lang-select" aria-label="Pilih Bahasa / Select Language">
                    <option value="id">Indonesia · Bahasa Indonesia (IDR Rp)</option>
                    <option value="en">International · English</option>
                    <option value="fr">France · Français</option>
                    <option value="it">Italia · Italiano</option>
                </select>
            </div>
            <div class="footer-pref-item">
                <label class="footer-pref-label" for="welcome-footer-theme-select" data-i18n="footer_theme_label">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <span data-i18n="footer_theme_label">Pilihan Tampilan</span>
                </label>
                <select id="welcome-footer-theme-select" class="zafira-theme-select" aria-label="Mode Tampilan / Display Mode">
                    <option value="light">Mode Terang (Clair)</option>
                    <option value="dark">Mode Gelap (Sombre)</option>
                    <option value="system">Sesuai Perangkat (Système)</option>
                </select>
            </div>
        </div>

        <div class="footer-bottom"><span data-i18n="footer_legal_rights">© {{ date('Y') }} ZAFIRA Maison. Seluruh hak dilindungi.</span><div><a href="#services" data-i18n="footer_terms">Ketentuan</a><a href="#main-content">Indonesia / ID</a><a href="#main-content" aria-label="Instagram ZAFIRA"><svg aria-hidden="true"><use href="#icon-instagram"/></svg></a></div></div>
    </footer>

    <div class="overlay" data-overlay></div>

    <aside class="drawer cart-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="cart-title" tabindex="-1">
        <div class="drawer-header"><div><span class="eyebrow">Votre Sélection</span><h2 id="cart-title"><span data-i18n="cart_aria">Tas Belanja</span> <small class="cart-count-text">(0)</small></h2></div><button class="icon-button" type="button" data-close-cart aria-label="Tutup tas belanja"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
        <!-- Undo Banner (Shneiderman Rule 6 / Nielsen Heuristic 3) -->
        <div id="welcome-undo-banner" style="display:none; padding:10px 16px; background:#eff6ff; border-bottom:1px solid #bfdbfe; font-size:12px; color:#1e40af; justify-content:space-between; align-items:center;">
            <span id="welcome-undo-text">Kreasi dihapus dari tas belanja.</span>
            <button type="button" id="welcome-undo-btn" style="background:none; border:none; color:#1d4ed8; font-weight:700; text-decoration:underline; cursor:pointer;">Batalkan (Undo)</button>
        </div>
        <div class="cart-body" data-cart-items></div>
        <div class="cart-empty" data-cart-empty><span class="empty-gem"></span><h3 data-i18n="cart_empty_title">Tas Anda masih kosong</h3><p data-i18n="cart_empty_desc">Temukan kreasi yang akan menjadi bagian dari kisah Anda.</p><button class="button button-dark" type="button" data-close-cart data-i18n="cart_empty_cta">Jelajahi koleksi</button></div>
        <div class="cart-footer" data-cart-footer hidden><div class="cart-service-note"><svg aria-hidden="true"><use href="#icon-check"/></svg><span data-i18n="cart_service_note">Signature wrapping &amp; pengiriman complimentary</span></div><div class="cart-total"><span data-i18n="cart_total_label">Subtotal</span><strong data-cart-total translate="no">Rp0</strong></div><p data-i18n="cart_shipping_calc">Biaya pengiriman dihitung pada langkah berikutnya.</p><button class="button button-sapphire" type="button" data-checkout><span data-i18n="cart_checkout_btn">Lanjut bersama Client Advisor</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button></div>
    </aside>

    <!-- VIP Client Profile & Atelier Salon Preferences Drawer (Alan Cooper Polite Software & Don Norman Gulf of Evaluation) -->
    <aside class="drawer profile-drawer" id="welcome-profile-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="welcome-profile-heading" tabindex="-1">
        <div class="drawer-header">
            <div>
                <span class="profile-eyebrow" data-i18n="profile_drawer_eyebrow">Espace Klien Privilégié</span>
                <h3 id="welcome-profile-heading" data-i18n="profile_drawer_title" style="margin:2px 0 0; font-family:'Bodoni Moda', serif; font-size:22px;">Profil &amp; Pilihan Salon</h3>
            </div>
            <button class="icon-button" type="button" data-close-profile aria-label="Tutup Profil">
                <svg aria-hidden="true"><use href="#icon-close"/></svg>
            </button>
        </div>

        <div class="drawer-body profile-drawer-body">
            <!-- 1. VIP Client Identity Card -->
            <div class="profile-vic-card">
                <div class="profile-vic-avatar">
                    <span>AT</span>
                </div>
                <div class="profile-vic-info">
                    <h4 class="profile-vic-name">{{ auth()->user()->name ?? 'Tamu ZAFIRA' }}</h4>
                    <p class="profile-vic-email">{{ auth()->user()->email ?? 'Masuk untuk melihat permintaan Anda' }}</p>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="profile-quick-actions">
                <button type="button" class="profile-action-pill" data-open-concierge>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span data-i18n="profile_action_fitting">Jadwalkan Fitting</span>
                </button>
                <a href="{{ route('account.show') }}" class="profile-action-pill" style="text-decoration:none;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span data-i18n="profile_action_orders">Riwayat Pesanan</span>
                </a>
            </div>

            <!-- 4. Salon & Experience Preferences (Harmonized Settings) -->
            <div class="profile-section-card profile-settings-group">
                <div class="profile-section-header">
                    <span class="profile-section-title" data-i18n="profile_settings_title">Pilihan Pengalaman Salon</span>
                </div>

                <!-- Mode Tampilan (Appearance: Light, Dark, System) -->
                <div class="profile-setting-row">
                    <div class="setting-info">
                        <span class="setting-name" data-i18n="profile_theme_label">Mode Tampilan Salon</span>
                        <span class="setting-desc" data-i18n="profile_theme_desc">Pilih pencahayaan salon</span>
                    </div>
                    <div class="profile-theme-segmented" role="radiogroup" aria-label="Mode Tampilan">
                        <button type="button" class="theme-seg-btn" data-set-theme="light" title="Mode Terang (Clair)">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <span>Clair</span>
                        </button>
                        <button type="button" class="theme-seg-btn" data-set-theme="dark" title="Mode Gelap (Sombre)">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                            <span>Sombre</span>
                        </button>
                        <button type="button" class="theme-seg-btn" data-set-theme="system" title="Mengikuti Sistem">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <span>Auto</span>
                        </button>
                    </div>
                </div>

                <!-- Pilihan Bahasa & Mata Uang -->
                <div class="profile-setting-row">
                    <div class="setting-info">
                        <span class="setting-name" data-i18n="profile_lang_label">Bahasa</span>
                        <span class="setting-desc" data-i18n="profile_lang_desc">Pilih edisi bahasa katalog</span>
                    </div>
                    <select class="zafira-lang-select profile-lang-select" aria-label="Pilih Bahasa">
                        <option value="id">Bahasa Indonesia</option>
                        <option value="en">English</option>
                        <option value="fr">Français</option>
                        <option value="it">Italiano</option>
                    </select>
                </div>

                <!-- Suara Ambient Atelier (Acoustics) -->
                <div class="profile-setting-row">
                    <div class="setting-info">
                        <span class="setting-name" data-i18n="profile_sound_label">Suasana Akustik Atelier</span>
                        <span class="setting-desc" data-i18n="profile_sound_desc">Umpan balik audio lembut</span>
                    </div>
                    <button type="button" class="sound-toggle-pill" id="profile-sound-btn" onclick="if(window.zafiraCore) window.zafiraCore.toggleSound()">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>
                        <span id="profile-sound-label">Aktif</span>
                    </button>
                </div>
            </div>

            <!-- 5. Navigation Links & Portal Bridge -->
            <div class="profile-footer-links" style="margin-top:20px;">
                <a href="{{ route('account.show') }}" class="button button-gold" style="width:100%; justify-content:center; padding:12px; display:flex; text-decoration:none; background:var(--gold, #b69a64); color:#fff; border-radius:8px; font-weight:600; font-size:12px;" data-i18n="profile_full_portal">
                    Buka Portal Klien Lengkap (/akun)
                </a>
            </div>
        </div>
    </aside>

    <section class="search-panel" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="search-title" tabindex="-1">
        <div class="search-top"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><button class="icon-button" type="button" data-close-search aria-label="Tutup pencarian"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
        <div class="search-inner"><span class="eyebrow">Recherche</span><h2 id="search-title">Apa yang Anda cari?</h2><form class="search-field" role="search" aria-label="Cari produk" onsubmit="event.preventDefault()"><svg aria-hidden="true"><use href="#icon-search"/></svg><label class="sr-only" for="search-input">Cari produk</label><input id="search-input" type="search" data-search-input placeholder="Cari tas, busana, aksesori…" data-i18n-placeholder="search_placeholder" autocomplete="off" spellcheck="false" name="q"><button type="button" data-clear-search>Hapus</button></form><div class="search-suggestions"><span>Pencarian populer</span><button type="button" data-search-term="Tas Tangan">Tas ikonis</button><button type="button" data-search-term="Saphir">Saphir</button><button type="button" data-search-term="Baru">Koleksi terbaru</button></div><div class="search-results" data-search-results></div></div>
    </section>

    <section class="quick-view-modal modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="quick-view-title" tabindex="-1">
        <button class="modal-close icon-button" type="button" data-close-modal aria-label="Tutup"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        <div class="quick-view-media"><img data-quick-image src="" alt=""></div>
        <div class="quick-view-content"><span class="eyebrow" data-quick-category></span><h2 id="quick-view-title" data-quick-name></h2><p class="quick-price" data-quick-price translate="no"></p><p class="quick-description" data-quick-description></p><div class="quick-detail"><span>Warna</span><strong data-quick-color></strong></div><div class="quick-detail" data-quick-material-row hidden><span>Material</span><strong data-quick-material></strong></div><div class="quick-detail" data-quick-origin-row hidden><span>Asal</span><strong data-quick-origin></strong></div><div class="quick-detail"><span>Ketersediaan</span><strong><i class="status-dot"></i>Tersedia</strong></div><button class="button button-sapphire" type="button" data-quick-add>Tambahkan ke tas <svg aria-hidden="true"><use href="#icon-bag"/></svg></button><button class="text-link" type="button" data-open-concierge>Konsultasikan dengan advisor <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button><div class="assurance"><span><svg aria-hidden="true"><use href="#icon-check"/></svg>Pengiriman complimentary</span><span><svg aria-hidden="true"><use href="#icon-check"/></svg>Signature wrapping</span></div></div>
    </section>

    <section class="concierge-modal modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="concierge-title" tabindex="-1">
        <button class="modal-close icon-button" type="button" data-close-modal aria-label="Tutup"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        <div class="concierge-intro"><span class="eyebrow light">ZAFIRA Concierge</span><h2 id="concierge-title">Sebuah waktu,<br>khusus untuk Anda.</h2><p>Client Advisor kami akan menyiapkan pilihan yang disesuaikan dengan kebutuhan dan momen istimewa Anda.</p><span class="concierge-hours">Senin–Sabtu · 10.00–19.00 WIB</span></div>
        <div class="concierge-order-summary" data-checkout-summary hidden><h3>Ringkasan pesanan Anda</h3><ul data-checkout-lines></ul><strong class="cart-total" data-checkout-total></strong></div>
        <form class="concierge-form" action="{{ route('concierge.store') }}" method="POST" data-ajax-form data-success-reset>
            @csrf
            <input type="hidden" name="order_items" value="" data-order-items>
            <div class="field"><label for="concierge-name">Nama lengkap</label><input id="concierge-name" name="name" type="text" required autocomplete="name" value="{{ auth()->user()->name ?? '' }}"></div>
            <div class="form-row"><div class="field"><label for="concierge-email">Email</label><input id="concierge-email" name="email" type="email" required autocomplete="email" spellcheck="false" inputmode="email" value="{{ auth()->user()->email ?? '' }}"></div><div class="field"><label for="concierge-phone">Nomor WhatsApp</label><input id="concierge-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" value="{{ auth()->user()->mobile ?? '' }}"></div></div>
            <div class="field"><label for="concierge-service">Pilih pengalaman</label><select id="concierge-service" name="service" required><option value="private-styling">Private styling di boutique</option><option value="virtual-consultation">Konsultasi virtual</option><option value="gifting">Konsultasi hadiah</option><option value="atelier">Personalisasi Atelier Z</option></select></div>
            <div class="form-row schedule-row"><div class="field"><label for="concierge-date">Tanggal pilihan</label><input id="concierge-date" name="preferred_date" type="date" min="{{ now()->toDateString() }}"></div><div class="field"><label for="concierge-time">Waktu</label><select id="concierge-time" name="preferred_time"><option value="10:00">10.00 WIB</option><option value="12:00">12.00 WIB</option><option value="14:00">14.00 WIB</option><option value="16:00">16.00 WIB</option><option value="18:00">18.00 WIB</option></select></div></div>
            <div class="field"><label for="concierge-notes">Ceritakan kebutuhan Anda <span>Opsional</span></label><textarea id="concierge-notes" name="notes" rows="2" placeholder="Contoh: gaun untuk resepsi malam…"></textarea></div>
            <button class="button button-sapphire" type="submit">Konfirmasi permintaan <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
        </form>
    </section>

    <!-- Floating Luxury Navigation Dock (Norman, Cooper, Krug) -->
    <nav class="floating-luxury-dock" aria-label="Navigasi Cepat ZAFIRA">
        <div class="dock-badge">
            <span class="dock-brand">ZAFIRA</span>
            <span class="dock-ver">v4.1 · Multi-Lang</span>
        </div>
        <button type="button" class="dock-btn active" onclick="window.scrollTo({top:0, behavior:'smooth'})" title="Storefront Utama">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            <span>Storefront</span>
        </button>
        @unless(app()->environment('production'))
        <a class="dock-btn" href="/prototype/#studio-view" title="Studio Kustomisasi Monogram">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            <span>Studio Z</span>
        </a>
        @endunless
        <button type="button" class="dock-btn" data-open-profile title="Portal Akun Klien VIP">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>Akun VIP</span>
        </button>
        <a class="dock-btn" href="/admin" title="Konsol Admin Atelier">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
            <span>Admin Atelier</span>
        </a>
        <button type="button" class="dock-btn" data-open-shortcuts aria-label="Buka panduan pintasan keyboard" title="Pintasan Keyboard (Tekan ?)">
            <kbd style="font-family:'JetBrains Mono', monospace; font-size:10px; background:rgba(255,255,255,0.2); padding:1px 5px; border-radius:3px; color:#fff;">?</kbd>
            <span>Pintasan</span>
        </button>
    </nav>

    <!-- Keyboard Shortcuts Accelerator Modal (Nielsen Heuristic 7 / Shneiderman Rule 2) -->
    <div class="modal-backdrop" id="welcome-shortcuts-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="shortcuts-title" tabindex="-1" style="display:none; position:fixed; inset:0; background:rgba(8,16,29,0.72); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center;" data-close-shortcuts>
        <div class="modal-window" style="background:var(--surface, #fff); max-width:440px; width:90%; padding:28px; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.3); border:1px solid var(--line);" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 id="shortcuts-title" style="font-family:'Bodoni Moda', serif; font-size:20px; color:var(--ink, #17263f); margin:0;" data-i18n="shortcuts_title">Pintasan Keyboard ZAFIRA</h3>
                <button type="button" data-close-shortcuts style="background:none; border:none; cursor:pointer; color:var(--muted); display:flex; align-items:center;" aria-label="Tutup panduan pintasan"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <p style="font-size:12px; color:var(--muted); margin-bottom:18px;" data-i18n="shortcuts_desc">Akselerator navigasi efisiensi tinggi untuk menjelajahi seluruh ruang salon digital.</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory, #f3efe7); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink, #17263f);">Tutup Modal / Drawer Aktif</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">ESC</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory, #f3efe7); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink, #17263f);">Buka Tas Belanja</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">C</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory, #f3efe7); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink, #17263f);">Fokus ke Pencarian Koleksi</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">/</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory, #f3efe7); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink, #17263f);">Lompat ke Studio Monogram</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">M</kbd>
                </div>

            </div>
            <div style="margin-top:20px; text-align:right;">
                <button type="button" data-close-shortcuts style="background:var(--gold, #b69a64); color:#fff; border:none; padding:8px 20px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;" data-i18n="shortcuts_close">Tutup Panduan</button>
            </div>
        </div>
    </div>

    <div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>

    <script>
        // Enhanced Client-Side Interactions (Live Search, Monogram, Keyboard Shortcuts)
        // L'Art d'Offrir Interactive Unboxing Handlers (Norman & Shneiderman)
        function setWelcomeRibbon(color) {
            var band = document.getElementById('welcome-ribbon-band');
            var btnGold = document.getElementById('btn-ribbon-gold');
            var btnSapphire = document.getElementById('btn-ribbon-sapphire');
            if (band) {
                band.style.background = (color === 'gold') ? '#b89858' : '#0f1c33';
            }
            if (btnGold && btnSapphire) {
                if (color === 'gold') {
                    btnGold.style.background = '#b89858';
                    btnGold.style.color = '#0f1c33';
                    btnSapphire.style.background = 'transparent';
                    btnSapphire.style.color = 'var(--sapphire-midnight)';
                    btnSapphire.style.borderColor = 'var(--line-strong)';
                } else {
                    btnSapphire.style.background = '#0f1c33';
                    btnSapphire.style.color = '#fff';
                    btnGold.style.background = 'transparent';
                    btnGold.style.color = 'var(--sapphire-midnight)';
                    btnGold.style.borderColor = 'var(--line-strong)';
                }
            }
        }

        function stampWelcomeWaxSeal() {
            var seal = document.getElementById('welcome-wax-seal');
            if (seal) {
                seal.style.transform = 'scale(1.12) rotate(4deg)';
                setTimeout(function() { seal.style.transform = 'scale(1)'; }, 250);
                var toast = document.querySelector('.toast');
                var msg = document.querySelector('[data-toast-message]');
                if (toast && msg) {
                    msg.textContent = 'Segel lilin khas Maison ZAFIRA telah disematkan.';
                    toast.classList.add('show');
                    setTimeout(function() { toast.classList.remove('show'); }, 3500);
                }
            }
        }

        // Live Monogram Real-Time Interaction (Norman Constraints & Signifiers)
        document.addEventListener('DOMContentLoaded', function() {
            var monogramInput = document.getElementById('monogram-input');
            var previewDisplay = document.getElementById('monogram-preview-display');
            var counterDisplay = document.getElementById('welcome-monogram-counter');

            if (monogramInput && previewDisplay) {
                monogramInput.addEventListener('input', function() {
                    var val = this.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3);
                    this.value = val;
                    previewDisplay.textContent = val || 'Z';
                    if (counterDisplay) {
                        counterDisplay.textContent = '[ ' + val.length + ' / 3 ]';
                        if (val.length === 3) {
                            counterDisplay.style.background = '#d97706';
                            counterDisplay.style.color = '#fff';
                        } else {
                            counterDisplay.style.background = 'rgba(182,154,100,0.1)';
                            counterDisplay.style.color = 'var(--gold)';
                        }
                    }
                });
            }

            // Foil selection
            var foils = document.querySelectorAll('.foil-options .foil');
            foils.forEach(function(f) {
                f.addEventListener('click', function() {
                    foils.forEach(function(b) { b.classList.remove('active'); b.setAttribute('aria-pressed', 'false'); });
                    this.classList.add('active');
                    this.setAttribute('aria-pressed', 'true');
                    var color = this.getAttribute('data-foil') || '#c7a665';
                    if (previewDisplay) {
                        previewDisplay.style.color = color;
                    }
                });
            });

            var searchInput = document.getElementById('live-catalog-search-input');

            // Keyboard Shortcuts Listener
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey || e.altKey || document.querySelector('.open[role="dialog"], .mobile-menu.open') || e.target?.isContentEditable || (e.target && ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName))) {
                    return;
                }
                if (e.key === '?' || (e.shiftKey && e.key === '/')) {
                    e.preventDefault();
                    document.querySelector('[data-open-shortcuts]')?.click();
                } else if (e.key === 'c' || e.key === 'C') {
                    e.preventDefault();
                    var cartBtn = document.querySelector('[data-open-cart]');
                    if (cartBtn) { cartBtn.click(); }
                } else if (e.key === 'u' || e.key === 'U') {
                    e.preventDefault();
                    document.querySelector('[data-open-profile]')?.click();
                } else if (e.key === '/') {
                    e.preventDefault();
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (e.key === 'm' || e.key === 'M') {
                    e.preventDefault();
                    var at = document.getElementById('atelier');
                    if (at) { at.scrollIntoView({ behavior: 'smooth' }); }
                }
            });
        });
    </script>
</body>
</html>
