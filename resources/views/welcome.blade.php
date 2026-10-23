@php
    $products = $products ?? [];
    $storefrontAppCopy = array_merge(__('shop_ui.app_js'), ['stock_rejection' => __('customer_messages.cart.stock'), 'adjust_stock_rejection' => __('customer_messages.cart.adjust_stock')]);
    $purchaseIds = \App\Models\Product::whereIn('product_slug', collect($products)->pluck('id'))->pluck('product_id', 'product_slug');
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
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-server-locale="{{ app()->getLocale() }}" data-locale-url="{{ route('locale.update') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17263f">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('shop_ui.welcome.meta_description') }}">
    <meta property="og:title" content="ZAFIRA: La Maison de Lumière">
    <meta property="og:description" content="{{ __('shop_ui.welcome.og_description') }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('favicon.svg') }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta name="twitter:card" content="summary_large_image">
    <title>{{ __('shop_ui.welcome.page_title') }}</title>
    <link rel="canonical" href="{{ route('home') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <script type="application/ld+json">
    {!! $jsonLd !!}
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preload" as="image" href="{{ asset('prototype/assets/hero_couture-600.webp') }}" imagesrcset="{{ asset('prototype/assets/hero_couture-600.webp') }} 600w, {{ asset('prototype/assets/hero_couture.webp') }} 1200w" fetchpriority="high">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600,700&family=manrope:300,400,500,600,700&family=jetbrains-mono:400,500,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('prototype/app.css') }}?v=4.0.0">

    <script type="application/json" id="zafira-app-copy">@json($storefrontAppCopy)</script>
    @vite(['resources/css/app.css'])
    @isset($page)
        @viteReactRefresh
        @vite('resources/js/inertia.jsx')
    @else
        @vite('resources/js/app.js')
    @endisset
    <script>
        (function() {
            var theme = 'system', lang = document.documentElement.getAttribute('data-server-locale') || 'id';
            try {
                var read = function(key, legacy, ok) {
                    var v = localStorage.getItem(key);
                    if (v !== null) return ok(v) ? v : null;
                    v = localStorage.getItem(legacy);
                    return ok(v) ? v : null;
                };
                theme = read('zafira_theme', 'zafira-theme', function(v) { return v === 'light' || v === 'dark' || v === 'system'; }) || 'system';
                var queryLocale = new URLSearchParams(window.location.search).get('locale');
                if (queryLocale && ['id', 'en', 'fr', 'it', 'zh'].indexOf(queryLocale) !== -1) {
                    try { localStorage.setItem('zafira_lang', queryLocale); } catch (e) {}
                    lang = queryLocale;
                } else if (!document.documentElement.getAttribute('data-server-locale')) {
                    lang = read('zafira_lang', 'zafira-lang', function(v) { return v === 'id' || v === 'en' || v === 'fr' || v === 'it' || v === 'zh'; }) || 'id';
                }
            } catch (e) {}
            var resolved = theme === 'dark' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', resolved);
            document.documentElement.setAttribute('data-theme-setting', theme);
            document.documentElement.setAttribute('lang', lang);
        })();
    </script>
    @empty($page)
        <script src="{{ asset('js/zafira-core.js') }}"></script>
    @endempty
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
        .hero-content { position: relative; left: auto; right: auto; bottom: auto; z-index: 2; max-width: 800px; text-align: center; padding: 0 20px; color: #fff; margin-top: 40px; }
        .hero-content h1 { font-family: 'Bodoni Moda', serif; font-size: clamp(38px, 6vw, 68px); font-weight: 400; line-height: 1.1; margin: 18px 0 24px; text-shadow: 0 4px 24px rgba(0,0,0,0.4); }
        .hero-content p { font-size: clamp(14px, 1.8vw, 17px); color: rgba(255,255,255,0.88); max-width: 580px; margin: 0 auto 32px; font-weight: 300; }
        .hero-scroll { position: absolute; bottom: 32px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.75); font-size: 11px; letter-spacing: 0.25em; text-transform: uppercase; display: flex; flex-direction: column; align-items: center; gap: 8px; z-index: 2; }
        .hero-scroll span { width: 1px; height: 32px; background: rgba(182, 154, 100, 0.65); }
        
        @media (max-width: 768px) {
            .hero-scroll { display: none !important; }
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
<body class="storefront" data-catalog-url="{{ route('catalog.index') }}" lang="{{ app()->getLocale() }}" @auth data-favorites-url="{{ route('favorites.index') }}" data-favorites-page="{{ route('shop.index') }}#favorit" @endauth>
<x-react-root :page="$page ?? null">
    <div class="film-grain-overlay" aria-hidden="true"></div>
    <a class="skip-link" href="#main-content">{{ __('shop_ui.pdp.skip') }}</a>

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
        <p id="locale-status" role="status" aria-live="polite" aria-atomic="true" style="font-size:11px; color:inherit"></p>
        <p data-i18n="announcement">{{ __('shop_ui.welcome.announcement') }}</p>
        <div class="desktop-only-flex" style="display:flex; align-items:center; gap:16px;">
            <div class="lang-switcher" role="radiogroup" aria-label="{{ __('shop_ui.header.language_selector') }}">
                <button type="button" class="lang-btn active" data-set-lang="id" aria-label="Bahasa Indonesia" role="radio" aria-checked="true">ID</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="en" aria-label="English" role="radio" aria-checked="false">EN</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="fr" aria-label="Français" role="radio" aria-checked="false">FR</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="it" aria-label="Italiano" role="radio" aria-checked="false">IT</button>
                <span class="lang-divider">/</span>
                <button type="button" class="lang-btn" data-set-lang="zh" aria-label="中文" role="radio" aria-checked="false">ZH</button>
            </div>
            <div class="theme-switcher" role="radiogroup" aria-label="{{ __('shop_ui.welcome.theme_selector') }}">
                <button type="button" class="theme-btn" data-set-theme="light" title="{{ __('shop_ui.welcome.theme_light') }}" aria-label="{{ __('shop_ui.welcome.theme_light') }}" data-i18n-title="theme_light" data-i18n-aria-label="theme_light" role="radio" aria-checked="false">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </button>
                <button type="button" class="theme-btn" data-set-theme="dark" title="{{ __('shop_ui.welcome.theme_dark') }}" aria-label="{{ __('shop_ui.welcome.theme_dark') }}" data-i18n-title="theme_dark" data-i18n-aria-label="theme_dark" role="radio" aria-checked="false">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <button type="button" class="theme-btn active" data-set-theme="system" title="{{ __('shop_ui.welcome.theme_system') }}" aria-label="{{ __('shop_ui.welcome.theme_system') }}" data-i18n-title="theme_system" data-i18n-aria-label="theme_system" role="radio" aria-checked="true">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                </button>
            </div>
            <button type="button" data-open-concierge data-i18n="rendezvous_btn">{{ __('shop_ui.welcome.rendezvous_btn') }}</button>
        </div>
    </div>

    <header class="site-header" id="site-header">
        <button class="icon-button mobile-menu-button" type="button" aria-label="{{ __('shop_ui.header.menu_open') }}" aria-controls="mobile-menu" aria-expanded="false">
            <svg aria-hidden="true"><use href="#icon-menu"/></svg>
        </button>

        <nav class="desktop-nav" aria-label="{{ __('shop_ui.header.nav_label') }}">
            <a href="#collection" data-i18n="nav_collection">{{ __('shop_ui.welcome.nav_collection') }}</a>
            <a href="#new-arrivals" data-i18n="nav_arrivals">{{ __('shop_ui.welcome.nav_arrivals') }}</a>
            <a href="#heritage" data-i18n="nav_heritage">{{ __('shop_ui.welcome.nav_heritage') }}</a>
            <a href="#atelier" data-i18n="nav_atelier">{{ __('shop_ui.welcome.nav_atelier') }}</a>
            <a href="#packaging-section" lang="fr" data-i18n="nav_packaging">{{ __('shop_ui.welcome.nav_packaging') }}</a>
            <a href="#services" data-i18n="nav_services">{{ __('shop_ui.welcome.nav_services') }}</a>
        </nav>

        <a class="brand" href="{{ route('home') }}" aria-label="{{ __('shop_ui.header.home_aria') }}">
            <x-brand-mark />
        </a>

        <div class="header-actions">
            <button class="icon-button" type="button" data-open-search aria-label="{{ __('shop_ui.header.search') }}">
                <svg aria-hidden="true"><use href="#icon-search"/></svg>
            </button>
            <button class="icon-button" type="button" id="btn-header-profile" data-open-profile aria-label="{{ __('shop_ui.welcome.profile_aria') }}" title="{{ __('shop_ui.welcome.profile_aria') }}" style="position:relative;">
                <svg aria-hidden="true"><use href="#icon-user"/></svg>
                <span style="position:absolute; top:7px; right:7px; width:7px; height:7px; border-radius:50%; background:var(--gold); box-shadow:0 0 8px var(--gold);"></span>
            </button>
            <button class="icon-button wishlist-trigger" type="button" data-show-wishlist aria-label="{{ __('shop_ui.header.wishlist_show') }}">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg><span class="action-count wishlist-count">0</span>
            </button>
            <button class="icon-button" type="button" data-open-cart aria-label="{{ __('shop_ui.header.cart_open') }}">
                <svg aria-hidden="true"><use href="#icon-bag"/></svg><span class="action-count cart-count">0</span>
            </button>
        </div>
    </header>

    <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
        <div class="mobile-menu-top">
            <a class="brand brand-horizontal" href="{{ route('home') }}" aria-label="{{ __('shop_ui.header.home_short_aria') }}"><x-brand-mark /></a>
            <button class="icon-button" type="button" data-close-menu aria-label="{{ __('shop_ui.header.menu_close') }}"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        </div>
        <nav>
            <a href="#collection"><span class="mobile-menu-ordinal" aria-hidden="true">01</span><span data-i18n="nav_collection">{{ __('shop_ui.welcome.nav_collection') }}</span></a>
            <a href="#new-arrivals"><span class="mobile-menu-ordinal" aria-hidden="true">02</span><span data-i18n="nav_arrivals">{{ __('shop_ui.welcome.nav_arrivals') }}</span></a>
            <a href="#heritage"><span class="mobile-menu-ordinal" aria-hidden="true">03</span><span data-i18n="nav_heritage">{{ __('shop_ui.welcome.nav_heritage') }}</span></a>
            <a href="#atelier"><span class="mobile-menu-ordinal" aria-hidden="true">04</span><span data-i18n="nav_atelier">{{ __('shop_ui.welcome.nav_atelier') }}</span></a>
            <a href="#packaging-section"><span class="mobile-menu-ordinal" aria-hidden="true">05</span><span lang="fr" data-i18n="nav_packaging">{{ __('shop_ui.welcome.nav_packaging') }}</span></a>
            <a href="#services"><span class="mobile-menu-ordinal" aria-hidden="true">06</span><span data-i18n="nav_services">{{ __('shop_ui.welcome.nav_services') }}</span></a>
        </nav>
        <div class="mobile-utilities" style="display:flex; flex-direction:column; gap:14px; padding:18px 0; border-top:1px solid rgba(255,255,255,0.15); border-bottom:1px solid rgba(255,255,255,0.15);">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:10px; text-transform:uppercase; letter-spacing:0.15em; color:var(--gold);" data-i18n="lang_selector">{{ __('shop_ui.welcome.lang_selector') }}</span>
                <div class="lang-switcher" role="radiogroup" aria-label="{{ __('shop_ui.header.language_selector') }}">
                    {{-- aria-checked is server default only; JS must keep it in sync. --}}
                    <button type="button" class="lang-btn active" data-set-lang="id" role="radio" aria-checked="true">ID</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="en" role="radio" aria-checked="false">EN</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="fr" role="radio" aria-checked="false">FR</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="it" role="radio" aria-checked="false">IT</button>
                    <span class="lang-divider">/</span>
                    <button type="button" class="lang-btn" data-set-lang="zh" aria-label="中文" role="radio" aria-checked="false">ZH</button>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:10px; text-transform:uppercase; letter-spacing:0.15em; color:var(--gold);">{{ __('shop_ui.welcome.theme_selector') }}</span>
                <div class="theme-switcher" role="radiogroup" aria-label="{{ __('shop_ui.welcome.theme_selector') }}">
                    <button type="button" class="theme-btn" data-set-theme="light" title="{{ __('shop_ui.welcome.theme_light') }}" data-i18n-title="theme_light" aria-label="{{ __('shop_ui.welcome.theme_light') }}" data-i18n-aria-label="theme_light" role="radio" aria-checked="false">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </button>
                    <button type="button" class="theme-btn" data-set-theme="dark" title="{{ __('shop_ui.welcome.theme_dark') }}" data-i18n-title="theme_dark" aria-label="{{ __('shop_ui.welcome.theme_dark') }}" data-i18n-aria-label="theme_dark" role="radio" aria-checked="false">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </button>
                    <button type="button" class="theme-btn active" data-set-theme="system" title="{{ __('shop_ui.welcome.theme_system') }}" data-i18n-title="theme_system" aria-label="{{ __('shop_ui.welcome.theme_system') }}" data-i18n-aria-label="theme_system" role="radio" aria-checked="true">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    </button>
                </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                @auth
                    <a href="{{ route('account.show') }}"><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-user"/></svg>{{ __('shop_ui.welcome.profile') }}</a>
                @else
                    <a href="{{ route('login') }}"><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-user"/></svg>{{ __('shop_ui.welcome.login') }}</a>
                @endauth
                <button type="button" data-show-wishlist><svg aria-hidden="true" style="width:14px; height:14px;"><use href="#icon-heart"/></svg><span data-i18n="wishlist_aria">{{ __('shop_ui.welcome.wishlist_aria') }}</span> <span class="wishlist-count">0</span></button>
            </div>
        </div>
        <button class="text-link light mobile-concierge" type="button" data-open-concierge data-i18n="rendezvous_btn">{{ __('shop_ui.header.arrange') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
    </div>

    <main id="main-content">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-media">
                <picture>
                    <source type="image/webp" media="(max-width: 640px)" srcset="{{ asset('prototype/assets/hero_couture-600.webp') }}">
                    <source type="image/webp" srcset="{{ asset('prototype/assets/hero_couture.webp') }}">
                    <img src="{{ asset('prototype/assets/hero_couture.jpg') }}" alt="{{ __('shop_ui.welcome.hero_alt') }}" fetchpriority="high" width="896" height="1200" decoding="async">
                </picture>
            </div>
            <div class="hero-overlay"></div>
            <div class="hero-edition"><span lang="fr">Édition No. 01</span><br>Jakarta · Paris · Firenze</div>
            <div class="hero-content reveal is-visible">
                <span class="eyebrow light" lang="fr" data-i18n="hero_eyebrow">{{ __('shop_ui.welcome.hero_eyebrow') }}</span>
                <h1 id="hero-title" data-i18n="hero_title">{{ __('shop_ui.welcome.hero_title_1') }}<br>{{ __('shop_ui.welcome.hero_title_2') }}</h1>
                <p data-i18n="hero_subtitle">{{ __('shop_ui.welcome.hero_subtitle') }}</p>
                <div style="display:flex; justify-content:center; gap:16px; flex-wrap:wrap;">
                    <a class="button button-light" href="#new-arrivals" data-i18n="hero_cta_explore">{{ __('shop_ui.welcome.hero_cta_explore') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
                </div>
            </div>
            <a class="hero-scroll" href="#collection" aria-label="{{ __('shop_ui.welcome.scroll_collection') }}"><span></span><span data-i18n="hero_scroll">{{ __('shop_ui.welcome.hero_scroll') }}</span></a>
        </section>

        <section class="marquee" aria-label="{{ __('shop_ui.welcome.values') }}">
            <div class="marquee-track">
                <span lang="fr">L’Esprit Parisien</span><span class="marquee-bullet">·</span><span>La Mano Italiana</span><span class="marquee-bullet">·</span><span>{{ __('shop_ui.welcome.nusantara_soul') }}</span><span class="marquee-bullet">·</span><span lang="fr">Depuis Jakarta</span><span class="marquee-bullet">·</span>
                <span lang="fr">L’Esprit Parisien</span><span class="marquee-bullet">·</span><span>La Mano Italiana</span><span class="marquee-bullet">·</span><span>{{ __('shop_ui.welcome.nusantara_soul') }}</span><span class="marquee-bullet">·</span><span lang="fr">Depuis Jakarta</span><span class="marquee-bullet">·</span>
            </div>
        </section>

        <section class="category-section section-shell" id="collection">
            <div class="section-heading reveal">
                <div><span class="eyebrow" lang="fr" data-i18n="category_eyebrow">{{ __('shop_ui.welcome.category_eyebrow') }}</span><h2 data-i18n="category_title">{{ __('shop_ui.welcome.category_title') }}</h2></div>
                <p data-i18n="category_desc">{{ __('shop_ui.welcome.category_desc') }}</p>
            </div>
            <div class="category-grid">
                <a class="category-card category-card-tall reveal" href="{{ route('catalog.index', ['kategori' => 'warisan-nusantara']) }}">
                    <img src="{{ asset('prototype/assets/chemise_riviere.jpg') }}" srcset="{{ asset('prototype/assets/chemise_riviere-600.webp') }} 600w, {{ asset('prototype/assets/chemise_riviere.webp') }} 896w" sizes="(max-width: 900px) 100vw, 40vw" decoding="async" alt="{{ __('shop_ui.welcome.heritage_alt') }}" loading="lazy" width="1200" height="1500">
                    <span class="category-number">01</span>
                    <div><span data-i18n="cat_nusantara">{{ __('shop_ui.welcome.cat_nusantara') }}</span><h3>Indonesia</h3><span class="discover"><span data-i18n="hero_scroll">{{ __('shop_ui.welcome.hero_scroll') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="{{ route('catalog.index', ['kategori' => 'tas-tangan']) }}">
                    <img src="{{ asset('prototype/assets/minaudiere_saphir.jpg') }}" srcset="{{ asset('prototype/assets/minaudiere_saphir-600.webp') }} 600w, {{ asset('prototype/assets/minaudiere_saphir.webp') }} 896w" sizes="(max-width: 900px) 100vw, 30vw" decoding="async" alt="{{ __('shop_ui.welcome.bags_alt') }}" loading="lazy" width="1200" height="1500">
                    <span class="category-number">02</span>
                    <div><span lang="fr">Les Icônes</span><h3 data-i18n="cat_bags">{{ __('shop_ui.welcome.cat_bags') }}</h3><span class="discover"><span data-i18n="hero_scroll">{{ __('shop_ui.welcome.hero_scroll') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="{{ route('catalog.index', ['kategori' => 'koleksi-kapsul']) }}">
                    <img src="{{ asset('prototype/assets/robe_elan.jpg') }}" srcset="{{ asset('prototype/assets/robe_elan-600.webp') }} 600w, {{ asset('prototype/assets/robe_elan.webp') }} 896w" sizes="(max-width: 900px) 100vw, 30vw" decoding="async" alt="{{ __('shop_ui.welcome.capsule_alt') }}" loading="lazy" width="1200" height="1500">
                    <span class="category-number">03</span>
                    <div><span lang="fr">Deux Rives</span><h3 data-i18n="cat_capsule">{{ __('shop_ui.welcome.cat_capsule') }}</h3><span class="discover"><span data-i18n="hero_scroll">{{ __('shop_ui.welcome.hero_scroll') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
            </div>
        </section>

        <section class="products-section" id="new-arrivals">
            <div class="section-shell">
                <div class="products-heading reveal">
                    <div><span class="eyebrow" lang="fr" data-i18n="products_eyebrow">{{ __('shop_ui.welcome.products_eyebrow') }}</span><h2 data-i18n="products_title">{{ __('shop_ui.welcome.products_title') }}</h2></div>
                    <p class="result-count" role="status" aria-live="polite"><span data-product-count>{{ count($products) }}</span> <span data-i18n="products_count_suffix">{{ __('shop_ui.welcome.products_count_suffix') }}</span></p>
                </div>

                <p>{{ __('shop_ui.welcome.featured_note') }} <a class="text-link" href="{{ route('catalog.index') }}" data-view-all>{{ __('shop_ui.welcome.view_all') }}</a></p>
                <!-- Category and search controls query the full catalog, not featured cards. -->
                <div style="margin: 20px 0 24px; display:flex; flex-direction:column; gap:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                        <div class="filter-bar" role="group" aria-label="{{ __('shop_ui.welcome.filter_label') }}">
                            <a class="filter-pill" href="{{ route('catalog.index') }}">{{ __('shop_ui.welcome.filter_all') }}</a>
                            @foreach (($categories ?? collect()) as $category)
                                <a class="filter-pill" href="{{ route('catalog.index', ['category' => $category->cat_id]) }}">{{ $category->cat_title }}</a>
                            @endforeach
                        </div>
                        <form id="home-catalog-search" style="position:relative; min-width:260px; flex:1; max-width:340px;" role="search" aria-label="{{ __('shop_ui.welcome.catalog_search') }}" method="get" action="{{ route('catalog.index') }}">
                            <label for="home-catalog-query" class="sr-only">{{ __('shop_ui.welcome.catalog_search') }}</label>
                            <input type="search" id="home-catalog-query" name="q" maxlength="100" placeholder="{{ __('shop_ui.welcome.catalog_placeholder') }}" data-i18n-placeholder="search_placeholder"
                                   style="width:100%; padding:9px 16px 9px 36px; border:1px solid rgba(32,32,29,0.18); border-radius:100px; font-size:12.5px; background:rgba(255,255,255,0.85); outline:none;"
                                   autocomplete="off" spellcheck="false">
                                                               <button type="submit" class="text-link">{{ __('shop_ui.footer.search_submit') }}</button>
                            <svg aria-hidden="true" style="position:absolute; left:12px; top:20px; transform:translateY(-50%); width:14px; height:14px; stroke:var(--taupe); fill:none;" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </form>
                    </div>
                    <div id="welcome-feedback-strip" role="status" aria-live="polite" hidden style="font-size:11.5px; color:var(--muted); background:rgba(182,154,100,0.1); border-left:3px solid var(--gold); padding:6px 14px; border-radius:4px;">
                        Menampilkan hasil kurasi instan
                    </div>
                </div>

                <div class="product-grid" data-product-grid>
                    @foreach ($products as $index => $product)
                        @php
                            $imgSrc = $localAssets[$product['id']] ?? $product['image'];
                            $purchaseId = $purchaseIds[$product['id']] ?? (preg_match('/^product-(\d+)$/', $product['id'], $idMatch) ? $idMatch[1] : '');
                        @endphp
                        <article class="product-card reveal" data-product-card
                            data-id="{{ $product['id'] }}"
                            data-product-id="{{ $purchaseId }}"
                            data-name="{{ $product['name'] }}"
                            data-alt="{{ $product['alt'] }}"
                            data-category="{{ $product['category'] }}"
                            data-price="{{ $product['price'] }}"
                            data-price-label="{{ $product['price_label'] }}"
                            data-stock="{{ max(0, (int) $product['stock']) }}"
                            data-image="{{ $imgSrc }}"
                            data-description="{{ $product['description'] }}"
                            data-color="{{ $product['color'] }}"
                            data-material="{{ $product['material'] }}"
                            data-origin="{{ $product['origin'] }}">
                            <div class="product-media">
                                <img src="{{ $imgSrc }}" @if (isset($localAssets[$product['id']])) srcset="{{ preg_replace('/\.jpg$/', '-600.webp', $imgSrc) }} 600w, {{ preg_replace('/\.jpg$/', '.webp', $imgSrc) }} 896w" sizes="(max-width: 700px) 50vw, 25vw" @endif alt="{{ $product['alt'] }}" loading="lazy" decoding="async" width="800" height="1000">
                                @if ($product['badge'])<span class="product-badge">{{ $product['badge'] }}</span>@endif
                                <button class="wishlist-button" type="button" data-toggle-wishlist aria-label="{{ __('shop_ui.welcome.favorite_add', ['name' => $product['name']]) }}" aria-pressed="false">
                                    <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                                </button>
                                <button class="quick-view-button" type="button" data-quick-view>{{ __('shop_ui.welcome.card_quick_view') }}</button>
                            </div>
                            <div class="product-info">
                                <span>{{ $product['category'] }}</span>
                                <h3><button type="button" data-quick-view>{{ $product['name'] }}</button></h3>
                                <p translate="no">{{ $product['price_label'] }}</p>
                                @if ($product['stock'] <= 0)<p>{{ __('shop_ui.pdp.sold_out') }}</p>@endif
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                    <div class="product-color"><i style="--swatch: {{ $product['swatch'] }}"></i>{{ $product['color'] }}</div>

                                </div>
                                <button class="product-add" type="button" data-card-add @if ($product['stock'] <= 0) disabled @endif aria-label="{{ $product['stock'] <= 0 ? __('shop_ui.pdp.card_sold_out_aria', ['title' => $product['name']]) : __('shop_ui.pdp.card_add_aria', ['title' => $product['name']]) }}"><svg aria-hidden="true"><use href="#icon-bag"/></svg><span>{{ $product['stock'] <= 0 ? __('shop_ui.pdp.sold_out') : __('shop_ui.pdp.card_add') }}</span></button>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="empty-products" @if(count($products) > 0) hidden @endif role="status" aria-live="polite">{{ __('shop_ui.welcome.featured_empty') }} <a class="text-link" href="{{ route('catalog.index') }}">{{ __('shop_ui.welcome.view_all') }}</a></p>
            </div>
        </section>

        <section class="maison-story" id="maison">
            <div class="story-image reveal">
                <img src="{{ asset('prototype/assets/manteau_firenze.jpg') }}" srcset="{{ asset('prototype/assets/manteau_firenze-600.webp') }} 600w, {{ asset('prototype/assets/manteau_firenze.webp') }} 896w" sizes="(max-width: 900px) 100vw, 50vw" decoding="async" alt="{{ __('shop_ui.welcome.maison_alt') }}" loading="lazy" width="1400" height="1750">
                <span lang="fr">Depuis 2026</span>
            </div>
            <div class="story-content reveal">
                <span class="eyebrow light" lang="fr" data-i18n="maison_eyebrow">{{ __('shop_ui.welcome.maison_eyebrow') }}</span>
                <h2 data-i18n="maison_title">{{ __('shop_ui.welcome.maison_title_1') }}<br>{{ __('shop_ui.welcome.maison_title_2') }}<br>{{ __('shop_ui.welcome.maison_title_3') }}</h2>
                <p data-i18n="maison_body">{{ __('shop_ui.welcome.maison_body') }}</p>
                <p class="story-signature" data-i18n="footer_motto">{{ __('shop_ui.footer.motto') }}</p>
                <a href="#atelier" class="text-link light"><span data-i18n="maison_link">{{ __('shop_ui.welcome.maison_link') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="heritage-section section-shell" id="heritage" aria-labelledby="heritage-title">
            <div class="section-heading reveal">
                <div><span class="eyebrow" data-i18n="heritage_eyebrow">{{ __('shop_ui.welcome.heritage_eyebrow') }}</span><h2 id="heritage-title" data-i18n="heritage_title">{{ __('shop_ui.welcome.heritage_title') }}</h2></div>
                <p data-i18n="heritage_subtitle">{{ __('shop_ui.welcome.heritage_subtitle') }}</p>
            </div>
            <div class="heritage-grid">
                <article class="heritage-card reveal">
                    <span class="heritage-number">I</span>
                    <h3 lang="fr" data-i18n="heritage_1_title">{{ __('shop_ui.welcome.heritage_1_title') }}</h3>
                    <p data-i18n="heritage_1_desc">{{ __('shop_ui.welcome.heritage_1_desc') }}</p>
                    <span class="heritage-proof" data-i18n="heritage_1_proof">{{ __('shop_ui.welcome.heritage_1_proof') }}</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">II</span>
                    <h3 data-i18n="heritage_2_title">{{ __('shop_ui.welcome.heritage_2_title') }}</h3>
                    <p data-i18n="heritage_2_desc">{{ __('shop_ui.welcome.heritage_2_desc') }}</p>
                    <span class="heritage-proof" data-i18n="heritage_2_proof">{{ __('shop_ui.welcome.heritage_2_proof') }}</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">III</span>
                    <h3 data-i18n="heritage_3_title">{{ __('shop_ui.welcome.heritage_3_title') }}</h3>
                    <p data-i18n="heritage_3_desc">{{ __('shop_ui.welcome.heritage_3_desc') }}</p>
                    <span class="heritage-proof" data-i18n="heritage_3_proof">{{ __('shop_ui.welcome.heritage_3_proof') }}</span>
                </article>
            </div>
        </section>

        <section class="atelier-section section-shell" id="atelier">
            <div class="atelier-copy reveal">
                <span class="eyebrow" data-i18n="atelier_eyebrow">{{ __('shop_ui.welcome.atelier_eyebrow') }}</span>
                <h2 data-i18n="atelier_title">{{ __('shop_ui.welcome.atelier_title_1') }}<br>{{ __('shop_ui.welcome.atelier_title_2') }}</h2>
                <p data-i18n="atelier_desc">{{ __('shop_ui.welcome.atelier_desc') }}</p>
                <ul>
                    <li><span>01</span><span data-i18n="atelier_step_1">{{ __('shop_ui.welcome.atelier_step_1') }}</span></li>
                    <li><span>02</span><span data-i18n="atelier_step_2">{{ __('shop_ui.welcome.atelier_step_2') }}</span></li>
                    <li><span>03</span><span data-i18n="atelier_step_3">{{ __('shop_ui.welcome.atelier_step_3') }}</span></li>
                </ul>
                <button class="button button-dark" type="button" data-open-concierge><span data-i18n="atelier_cta">{{ __('shop_ui.welcome.atelier_cta') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
            </div>
            <div class="monogram-studio reveal">
                <div class="monogram-product">
                    <div class="monogram-tag">
                        <span class="monogram-preview" id="monogram-preview-display" data-monogram-preview>ZA</span>
                    </div>
                    <span class="bag-handle"></span>
                    <div class="bag-body"><span class="bag-lock">Z</span></div>
                    <p>{{ __('shop_ui.welcome.monogram_preview') }}</p>
                </div>
                <div class="monogram-controls">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label for="monogram-input" style="margin:0;">{{ __('shop_ui.welcome.monogram_initials') }}</label>
                        <span id="welcome-monogram-counter" style="font-family:'JetBrains Mono', monospace; font-size:10px; font-weight:700; color:var(--gold); background:rgba(182,154,100,0.1); padding:2px 8px; border-radius:10px;">[ 2 / 3 ]</span>
                    </div>
                    <input id="monogram-input" name="monogram" type="text" maxlength="3" value="ZA" autocomplete="off" spellcheck="false" inputmode="text" aria-describedby="monogram-hint"
                           style="margin-top:6px; letter-spacing:0.25em; text-transform:uppercase; font-family:'Bodoni Moda', serif; font-size:16px;">
                    <small id="monogram-hint">{{ __('shop_ui.welcome.monogram_hint') }}</small>
                    <span class="control-label" style="margin-top:14px; display:block;">{{ __('shop_ui.welcome.atelier_foil_label') }}</span>
                    <div class="foil-options" role="group" aria-label="{{ __('shop_ui.welcome.foil_choose') }}">
                        <button type="button" class="foil active" style="--foil:#c7a665" data-foil="#c7a665" aria-label="{{ __('shop_ui.welcome.foil_gold') }}" aria-pressed="true"></button>
                        <button type="button" class="foil" style="--foil:#d7d4ce" data-foil="#d7d4ce" aria-label="{{ __('shop_ui.welcome.foil_silver') }}" aria-pressed="false"></button>
                        <button type="button" class="foil" style="--foil:var(--sapphire)" data-foil="var(--sapphire)" aria-label="{{ __('shop_ui.welcome.foil_sapphire') }}" aria-pressed="false"></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Signature Unboxing & Packaging Experience (L'Art d'Offrir ZAFIRA) -->
        <section class="packaging-section" id="packaging-section" aria-labelledby="packaging-heading">
            <div class="packaging-inner">
                <div class="reveal">
                    <span class="eyebrow" lang="fr" data-i18n="packaging_eyebrow">{{ __('shop_ui.welcome.packaging_eyebrow') }}</span>
                    <h2 id="packaging-heading" style="font-family:'Bodoni Moda', serif; font-size:clamp(28px, 4vw, 40px); margin:8px 0 16px; color:var(--sapphire-midnight);" data-i18n="packaging_title">{{ __('shop_ui.welcome.packaging_title') }}</h2>
                    <p style="color:var(--muted); line-height:1.8; margin-bottom:20px; font-size:15px;" data-i18n="packaging_desc">{{ __('shop_ui.welcome.packaging_desc') }}</p>
                    <div style="display:flex; gap:16px; align-items:center; margin-bottom:24px; flex-wrap:wrap;">
                        <label style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--muted); letter-spacing:0.1em;" data-i18n="packaging_ribbon_label">{{ __('shop_ui.welcome.packaging_ribbon_label') }}</label>
                        <button type="button" class="button" id="btn-ribbon-gold" style="min-height:36px; padding:0 18px; background:#b89858; color:#0f1c33; font-weight:700; border-color:#b89858; font-size:10px;" onclick="setWelcomeRibbon('gold')"><span data-i18n="packaging_ribbon_gold">{{ __('shop_ui.welcome.packaging_ribbon_gold') }}</span> (<span lang="fr">Or</span>)</button>
                        <button type="button" class="button" id="btn-ribbon-sapphire" style="min-height:36px; padding:0 18px; background:#0f1c33; color:#fff; border-color:#0f1c33; font-size:10px;" onclick="setWelcomeRibbon('sapphire')"><span data-i18n="packaging_ribbon_sapphire">{{ __('shop_ui.welcome.packaging_ribbon_sapphire') }}</span> (<span lang="fr">Saphir</span>)</button>
                    </div>
                    <div style="font-size:12px; color:var(--muted); display:flex; align-items:center; gap:8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span data-i18n="packaging_complimentary">{{ __('shop_ui.welcome.packaging_complimentary') }}</span>
                    </div>
                </div>

                <div class="unboxing-card reveal">
                    <div class="box-preview-visual">
                        <div class="ribbon-simulation" id="welcome-ribbon-band" style="background:#b89858;"></div>
                        <div style="position:relative; z-index:2; margin-bottom:12px;">
                            <span style="font-family:'Bodoni Moda', serif; font-size:26px; letter-spacing:0.25em; display:block;">ZAFIRA</span>
                            <small style="font-size:9px; letter-spacing:0.35em; text-transform:uppercase; color:var(--gold-light);">Atelier Jakarta</small>
                        </div>
                        <button class="wax-seal-badge" id="welcome-wax-seal" type="button" onclick="stampWelcomeWaxSeal()" title="{{ __('shop_ui.welcome.wax_title') }}" aria-label="{{ __('shop_ui.welcome.wax_aria') }}" data-wax-success="{{ __('shop_ui.welcome.wax_success') }}">
                            Z
                        </button>
                        <p style="font-size:10.5px; color:var(--gold-light); margin-top:14px; position:relative; z-index:2; display:flex; align-items:center; justify-content:center; gap:6px;">
                            <span class="indicator-dot gold" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:var(--gold);"></span> {{ __('shop_ui.welcome.wax_hint') }}
                        </p>
                    </div>
                    <div style="font-size:12.5px; color:var(--ink); font-style:italic; background:var(--ivory); padding:16px; border-radius:var(--radius-xs); border:1px solid var(--line);">
                        “{{ __('shop_ui.welcome.packaging_quote') }}”
                    </div>
                </div>
            </div>
        </section>

        <section class="editorial-banner">
            <img src="{{ asset('prototype/assets/robe_aurore.jpg') }}" srcset="{{ asset('prototype/assets/robe_aurore-600.webp') }} 600w, {{ asset('prototype/assets/robe_aurore.webp') }} 896w" sizes="(max-width: 900px) 100vw, 50vw" decoding="async" alt="{{ __('shop_ui.welcome.editorial_alt') }}" loading="lazy" width="2000" height="2666">
            <div class="editorial-content reveal">
                <span class="eyebrow light" data-i18n="editorial_eyebrow">{{ __('shop_ui.welcome.editorial_eyebrow') }}</span>
                <h2 data-i18n="editorial_title">{{ __('shop_ui.welcome.editorial_title_1') }}<br>{{ __('shop_ui.welcome.editorial_title_2') }}</h2>
                <p class="editorial-lede" data-i18n="editorial_desc">{{ __('shop_ui.welcome.editorial_desc') }}</p>
                <a href="#heritage" class="button button-light"><span data-i18n="editorial_cta">{{ __('shop_ui.welcome.editorial_cta') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="services-section section-shell" id="services">
            <div class="services-heading reveal">
                <span class="eyebrow" data-i18n="services_eyebrow">{{ __('shop_ui.welcome.services_eyebrow') }}</span>
                <h2 data-i18n="services_title">{{ __('shop_ui.welcome.services_title_1') }}<br>{{ __('shop_ui.welcome.services_title_2') }}</h2>
            </div>
            <div class="services-grid">
                <button class="service-card reveal" type="button" data-open-concierge>
                    <span class="service-icon">01</span><h3 data-i18n="service_1_title">{{ __('shop_ui.welcome.service_1_title') }}</h3><p data-i18n="service_1_desc">{{ __('shop_ui.welcome.service_1_desc') }}</p><span class="text-link"><span data-i18n="service_1_link">{{ __('shop_ui.welcome.service_1_link') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </button>
                <article class="service-card reveal">
                    <span class="service-icon">02</span><h3 data-i18n="service_2_title">{{ __('shop_ui.welcome.service_2_title') }}</h3><p data-i18n="service_2_desc">{{ __('shop_ui.welcome.service_2_desc') }}</p><span class="text-link"><span data-i18n="service_2_link">{{ __('shop_ui.welcome.service_2_link') }}</span> <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">03</span><h3 data-i18n="service_3_title">{{ __('shop_ui.welcome.service_3_title') }}</h3><p data-i18n="service_3_desc">{{ __('shop_ui.welcome.service_3_desc') }}</p><span class="text-link"><span data-i18n="service_3_link">{{ __('shop_ui.welcome.service_3_link') }}</span> <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">04</span><h3 data-i18n="service_4_title">{{ __('shop_ui.welcome.service_4_title') }}</h3><p data-i18n="service_4_desc">{{ __('shop_ui.welcome.service_4_desc') }}</p><span class="text-link"><span data-i18n="service_4_link">{{ __('shop_ui.welcome.service_4_link') }}</span> <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
            </div>
        </section>

        <section class="section-shell" id="returns-policy" aria-labelledby="returns-policy-title">
            <h2 id="returns-policy-title" class="sr-only">{{ __('shop_ui.footer.shipping_returns') }}</h2>
            <p class="editorial-lede">{{ __('shop_ui.welcome.returns_policy') }}</p>
        </section>

        <section class="newsletter-section">
            <div class="newsletter-gem" aria-hidden="true"><i></i></div>
            <div class="reveal">
                <span class="eyebrow light" data-i18n="newsletter_eyebrow">{{ __('shop_ui.welcome.newsletter_eyebrow') }}</span>
                <h2 data-i18n="newsletter_title">{{ __('shop_ui.welcome.newsletter_title') }}</h2>
                <p data-i18n="newsletter_desc">{{ __('shop_ui.welcome.newsletter_desc') }}</p>
                <form class="newsletter-form" action="{{ route('newsletter.store') }}" method="POST" data-ajax-form data-success-reset>
                    @csrf
                    <label class="sr-only" for="newsletter-email">{{ __('shop_ui.welcome.email_label') }}</label>
                    <input id="newsletter-email" type="email" name="email" placeholder="{{ __('shop_ui.welcome.newsletter_placeholder') }}" data-i18n-placeholder="newsletter_placeholder" required autocomplete="email" spellcheck="false">
                    <button type="submit" aria-label="{{ __('shop_ui.welcome.newsletter_submit') }}"><svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                </form>
                <small data-i18n="newsletter_privacy">{{ __('shop_ui.welcome.newsletter_privacy') }}</small>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-brand"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><p data-i18n="footer_motto">{{ __('shop_ui.footer.motto') }}</p></div>
        <div class="footer-links"><h3 data-i18n="footer_col_collection">{{ __('shop_ui.welcome.footer_col_collection') }}</h3><a href="#new-arrivals" data-i18n="footer_women">{{ __('shop_ui.welcome.footer_women') }}</a><a href="#new-arrivals" data-i18n="footer_men">{{ __('shop_ui.welcome.footer_men') }}</a><a href="#new-arrivals" data-i18n="footer_bags">{{ __('shop_ui.welcome.footer_bags') }}</a><a href="#new-arrivals" data-i18n="footer_accessories">{{ __('shop_ui.welcome.footer_accessories') }}</a></div>
        <div class="footer-links"><h3 data-i18n="footer_col_maison">{{ __('shop_ui.welcome.footer_col_maison') }}</h3><a href="#maison" data-i18n="footer_story">{{ __('shop_ui.welcome.footer_story') }}</a><a href="#atelier" data-i18n="footer_atelier_z">{{ __('shop_ui.welcome.footer_atelier_z') }}</a><a href="#services" data-i18n="footer_services">{{ __('shop_ui.welcome.footer_services') }}</a><a href="#returns-policy">{{ __('shop_ui.footer.shipping_returns') }}</a><button type="button" data-open-concierge lang="fr" data-i18n="footer_rendezvous">{{ __('shop_ui.welcome.footer_rendezvous') }}</button></div>
        <!-- Canonical Luxury Footer International Preferences Bar (Hermès & Chanel ATM) -->
        <div class="footer-preferences-bar">
            <div class="footer-pref-item">
                <label class="footer-pref-label" for="welcome-footer-lang-select" data-i18n="footer_lang_label">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span data-i18n="footer_lang_label">{{ __('shop_ui.welcome.footer_lang_label') }}</span>
                </label>
                <select id="welcome-footer-lang-select" class="zafira-lang-select" aria-label="{{ __('shop_ui.header.language_selector') }}" data-i18n-aria-label="lang_selector">
                    <option value="id">Indonesia · Bahasa Indonesia (IDR Rp)</option>
                    <option value="en">International · English</option>
                    <option value="fr">France · Français</option>
                    <option value="it">Italia · Italiano</option>
                    <option value="zh">中国 · 中文</option>
                </select>
            </div>
            <div class="footer-pref-item">
                <label class="footer-pref-label" for="welcome-footer-theme-select" data-i18n="footer_theme_label">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <span data-i18n="footer_theme_label">{{ __('shop_ui.welcome.footer_theme_label') }}</span>
                </label>
                <select id="welcome-footer-theme-select" class="zafira-theme-select" aria-label="{{ __('shop_ui.welcome.theme_selector') }}">
                    <option value="light" data-i18n="theme_light">{{ __('shop_ui.welcome.theme_light') }}</option>
                    <option value="dark" data-i18n="theme_dark">{{ __('shop_ui.welcome.theme_dark') }}</option>
                    <option value="system" data-i18n="theme_system">{{ __('shop_ui.welcome.theme_system') }}</option>
                </select>
            </div>
        </div>

        <div class="footer-bottom"><span data-i18n="footer_legal_rights">© {{ date('Y') }} ZAFIRA Maison. {{ __('shop_ui.footer.rights') }}</span><div><button type="button" class="text-link" data-open-shortcuts style="background:none; border:none; color:inherit; font:inherit; cursor:pointer; text-transform:uppercase; letter-spacing:0.12em; font-size:11px;" aria-label="{{ __('shop_ui.welcome.shortcuts_open') }}" title="{{ __('shop_ui.welcome.shortcuts_hint') }}"><span data-i18n="shortcuts_trigger">{{ __('shop_ui.welcome.shortcuts_trigger') }}</span></button><a href="#services" data-i18n="footer_terms">{{ __('shop_ui.welcome.footer_terms') }}</a><a href="#main-content">{{ __('shop_ui.welcome.region_badge') }}</a><a href="#main-content" aria-label="Instagram ZAFIRA"><svg aria-hidden="true"><use href="#icon-instagram"/></svg></a></div></div>
    </footer>

    <div class="overlay" data-overlay></div>

    @include('partials.purchase-cart')

    <!-- VIP Client Profile & Atelier Salon Preferences Drawer (Alan Cooper Polite Software & Don Norman Gulf of Evaluation) -->
    <aside class="drawer profile-drawer" id="welcome-profile-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="welcome-profile-heading" tabindex="-1">
        <div class="drawer-header">
            <div>
                <span class="profile-eyebrow" data-i18n="profile_drawer_eyebrow">{{ __('shop_ui.welcome.profile_drawer_eyebrow') }}</span>
                <h3 id="welcome-profile-heading" data-i18n="profile_drawer_title" style="margin:2px 0 0; font-family:'Bodoni Moda', serif; font-size:22px;">{{ __('shop_ui.welcome.profile_drawer_title') }}</h3>
            </div>
            <button class="icon-button" type="button" data-close-profile aria-label="{{ __('shop_ui.welcome.profile_close') }}">
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
                    <h4 class="profile-vic-name">{{ auth()->user()->name ?? __('shop_ui.welcome.guest') }}</h4>
                    <p class="profile-vic-email">{{ auth()->user()->email ?? __('shop_ui.welcome.guest_hint') }}</p>
                </div>
            </div>

            <!-- Quick Action Buttons -->
            <div class="profile-quick-actions">
                <button type="button" class="profile-action-pill" data-open-concierge>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span data-i18n="profile_action_fitting">{{ __('shop_ui.welcome.profile_action_fitting') }}</span>
                </button>
                <a href="{{ route('account.show') }}" class="profile-action-pill" style="text-decoration:none;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span data-i18n="profile_action_orders">{{ __('shop_ui.welcome.profile_action_orders') }}</span>
                </a>
            </div>

            <!-- 4. Salon & Experience Preferences (Harmonized Settings) -->
            <div class="profile-section-card profile-settings-group">
                <div class="profile-section-header">
                    <span class="profile-section-title" data-i18n="profile_settings_title">{{ __('shop_ui.welcome.profile_settings_title') }}</span>
                </div>

                <!-- Mode Tampilan (Appearance: Light, Dark, System) -->
                <div class="profile-setting-row">
                    <div class="setting-info">
                        <span class="setting-name" data-i18n="profile_theme_label">{{ __('shop_ui.welcome.profile_theme_label') }}</span>
                        <span class="setting-desc" data-i18n="profile_theme_desc">{{ __('shop_ui.welcome.profile_theme_desc') }}</span>
                    </div>
                    <div class="profile-theme-segmented" role="radiogroup" aria-label="{{ __('shop_ui.welcome.theme_selector') }}">
                        {{-- aria-checked is server default only; JS must keep it in sync (zafira-core.js currently toggles .active + aria-pressed). --}}
                        <button type="button" class="theme-seg-btn" data-set-theme="light" title="{{ __('shop_ui.welcome.theme_light') }}" data-i18n-title="theme_light" aria-label="{{ __('shop_ui.welcome.theme_light') }}" data-i18n-aria-label="theme_light" role="radio" aria-checked="false">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <span lang="fr">Clair</span>
                        </button>
                        <button type="button" class="theme-seg-btn" data-set-theme="dark" title="{{ __('shop_ui.welcome.theme_dark') }}" data-i18n-title="theme_dark" aria-label="{{ __('shop_ui.welcome.theme_dark') }}" data-i18n-aria-label="theme_dark" role="radio" aria-checked="false">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                            <span lang="fr">Sombre</span>
                        </button>
                        <button type="button" class="theme-seg-btn" data-set-theme="system" title="{{ __('shop_ui.welcome.theme_system') }}" data-i18n-title="theme_system" aria-label="{{ __('shop_ui.welcome.theme_system') }}" data-i18n-aria-label="theme_system" role="radio" aria-checked="false">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <span>Auto</span>
                        </button>
                    </div>
                </div>

                <!-- Pilihan Bahasa & Mata Uang -->
                <div class="profile-setting-row">
                    <div class="setting-info">
                        <span class="setting-name" data-i18n="profile_lang_label">{{ __('shop_ui.welcome.profile_lang_label') }}</span>
                        <span class="setting-desc" data-i18n="profile_lang_desc">{{ __('shop_ui.welcome.profile_lang_desc') }}</span>
                    </div>
                    <select class="zafira-lang-select profile-lang-select" aria-label="{{ __('shop_ui.header.language_selector') }}" data-i18n-aria-label="lang_selector">
                        <option value="id">Bahasa Indonesia</option>
                        <option value="en">English</option>
                        <option value="fr">Français</option>
                        <option value="it">Italiano</option>
                        <option value="zh">中文</option>
                    </select>
                </div>

                {{-- Removed sound control: the loaded core exports no toggleSound API. --}}
            </div>

            <!-- 5. Navigation Links & Portal Bridge -->
            <div class="profile-footer-links" style="margin-top:20px;">
                <a href="{{ route('account.show') }}" class="button button-gold" style="width:100%; justify-content:center; padding:12px; display:flex; text-decoration:none; background:var(--gold); color:#fff; border-radius:8px; font-weight:600; font-size:12px;" data-i18n="profile_full_portal">{{ __('shop_ui.welcome.profile_full_portal') }}</a>
            </div>
        </div>
    </aside>

    <section class="search-panel" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="search-title" tabindex="-1">
        <div class="search-top"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><button class="icon-button" type="button" data-close-search aria-label="{{ __('shop_ui.footer.search_close') }}"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
        <div class="search-inner"><span class="eyebrow">{{ __('shop_ui.footer.search_eyebrow') }}</span><h2 id="search-title">{{ __('shop_ui.footer.search_title') }}</h2><form id="catalog-header-search" class="search-field" role="search" aria-label="{{ __('shop_ui.footer.search_label') }}" method="get" action="{{ route('catalog.index') }}"><svg aria-hidden="true"><use href="#icon-search"/></svg><label class="sr-only" for="search-input">{{ __('shop_ui.footer.search_label') }}</label><input id="search-input" type="search" data-search-input placeholder="{{ __('shop_ui.footer.search_placeholder') }}" data-i18n-placeholder="search_placeholder" autocomplete="off" spellcheck="false" name="q" maxlength="100"><button type="button" data-clear-search>{{ __('shop_ui.footer.search_clear') }}</button></form><div class="search-suggestions"><span>{{ __('shop_ui.footer.search_popular') }}</span><a href="{{ route('catalog.index', ['kategori' => 'tas-tangan']) }}" data-search-term="Tas Tangan">{{ __('shop_ui.footer.term_bag') }}</a><a href="{{ route('catalog.index', ['q' => 'Saphir']) }}" data-search-term="Saphir">Saphir</a><a href="{{ route('catalog.index', ['urut' => 'terbaru']) }}" data-search-term="Baru">{{ __('shop_ui.footer.term_new') }}</a></div><button class="button button-dark" type="submit" form="catalog-header-search">{{ __('shop_ui.footer.search_submit') }}</button></div>
    </section>

    <section class="quick-view-modal modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="quick-view-title" tabindex="-1">
        <button class="modal-close icon-button" type="button" data-close-modal aria-label="{{ __('shop_ui.footer.close') }}"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        <div class="quick-view-media"><img data-quick-image src="" alt="" width="896" height="1200" decoding="async"></div>
        <div class="quick-view-content"><span class="eyebrow" data-quick-category></span><h2 id="quick-view-title" data-quick-name></h2><p class="quick-price" data-quick-price translate="no"></p><p class="quick-description" data-quick-description></p><div class="quick-detail"><span>{{ __('shop_ui.pdp.color') }}</span><strong data-quick-color></strong></div><div class="quick-detail" data-quick-material-row hidden><span>{{ __('shop_ui.pdp.material') }}</span><strong data-quick-material></strong></div><div class="quick-detail" data-quick-origin-row hidden><span>{{ __('shop_ui.pdp.origin') }}</span><strong data-quick-origin></strong></div><div class="quick-detail"><span>{{ __('shop_ui.footer.quick_availability') }}</span><strong data-quick-availability><i class="status-dot"></i>{{ __('shop_ui.footer.quick_available') }}</strong></div><button class="button button-sapphire" type="button" data-quick-add>{{ __('shop_ui.pdp.add') }} <svg aria-hidden="true"><use href="#icon-bag"/></svg></button><button class="text-link" type="button" data-open-concierge>{{ __('shop_ui.pdp.consult') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button><div class="assurance"><span><svg aria-hidden="true"><use href="#icon-check"/></svg>{{ __('shop_ui.footer.shipping_complimentary') }}</span><span><svg aria-hidden="true"><use href="#icon-check"/></svg>{{ __('shop_ui.pdp.signature_wrapping') }}</span></div></div>
    </section>

    <section class="concierge-modal modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="concierge-title" tabindex="-1">
        <button class="modal-close icon-button" type="button" data-close-modal aria-label="{{ __('shop_ui.footer.close') }}"><svg aria-hidden="true"><use href="#icon-close"/></svg></button>
        <div class="concierge-intro"><span class="eyebrow light">ZAFIRA Concierge</span><h2 id="concierge-title">{{ __('shop_ui.footer.concierge_title_1') }}<br>{{ __('shop_ui.footer.concierge_title_2') }}</h2><p>{{ __('shop_ui.footer.concierge_intro') }}</p><span class="concierge-hours">{{ __('shop_ui.footer.hours') }}</span></div>
        <div class="concierge-order-summary" data-checkout-summary hidden><h3>{{ __('shop_ui.footer.order_summary') }}</h3><ul data-checkout-lines></ul><strong class="cart-total" data-checkout-total></strong></div>
        <form class="concierge-form" action="{{ route('concierge.store') }}" method="POST" data-ajax-form data-success-reset>
            @csrf
            <input type="hidden" name="order_items" value="" data-order-items>
            <div class="field"><label for="concierge-name">{{ __('shop_ui.footer.name') }}</label><input id="concierge-name" name="name" type="text" required autocomplete="name" value="{{ auth()->user()->name ?? '' }}"></div>
            <div class="form-row"><div class="field"><label for="concierge-email">{{ __('shop_ui.footer.email') }}</label><input id="concierge-email" name="email" type="email" required autocomplete="email" spellcheck="false" inputmode="email" value="{{ auth()->user()->email ?? '' }}"></div><div class="field"><label for="concierge-phone">{{ __('shop_ui.footer.phone') }}</label><input id="concierge-phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel" value="{{ auth()->user()->mobile ?? '' }}"></div></div>
            <div class="field"><label for="concierge-service">{{ __('shop_ui.footer.service') }}</label><select id="concierge-service" name="service" required><option value="private-styling">{{ __('shop_ui.footer.svc_styling') }}</option><option value="virtual-consultation">{{ __('shop_ui.footer.svc_virtual') }}</option><option value="gifting">{{ __('shop_ui.footer.svc_gifting') }}</option><option value="atelier">{{ __('shop_ui.footer.svc_atelier') }}</option></select></div>
            <div class="form-row schedule-row"><div class="field"><label for="concierge-date">{{ __('shop_ui.footer.date') }}</label><input id="concierge-date" name="preferred_date" type="date" min="{{ now()->toDateString() }}"></div><div class="field"><label for="concierge-time">{{ __('shop_ui.footer.time') }}</label><select id="concierge-time" name="preferred_time"><option value="10:00">10.00 WIB</option><option value="12:00">12.00 WIB</option><option value="14:00">14.00 WIB</option><option value="16:00">16.00 WIB</option><option value="18:00">18.00 WIB</option></select></div></div>
            <div class="field"><label for="concierge-notes">{{ __('shop_ui.footer.notes') }} <span>{{ __('shop_ui.footer.optional') }}</span></label><textarea id="concierge-notes" name="notes" rows="2" placeholder="{{ __('shop_ui.footer.notes_placeholder') }}"></textarea></div>
            <button class="button button-sapphire" type="submit">{{ __('shop_ui.footer.confirm') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
        </form>
    </section>


    <!-- Keyboard Shortcuts Accelerator Modal (Nielsen Heuristic 7 / Shneiderman Rule 2) -->
    <div class="modal-backdrop" id="welcome-shortcuts-backdrop" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="shortcuts-title" tabindex="-1" style="display:none; position:fixed; inset:0; background:rgba(8,16,29,0.72); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center;" data-close-shortcuts>
        <div class="modal-window" style="background:var(--surface, #fff); max-width:440px; width:90%; padding:28px; border-radius:12px; box-shadow:0 24px 60px rgba(0,0,0,0.3); border:1px solid var(--line);" onclick="event.stopPropagation()">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 id="shortcuts-title" style="font-family:'Bodoni Moda', serif; font-size:20px; color:var(--ink); margin:0;" data-i18n="shortcuts_title">{{ __('shop_ui.welcome.shortcuts_title') }}</h3>
                <button type="button" data-close-shortcuts style="background:none; border:none; cursor:pointer; color:var(--muted); display:flex; align-items:center;" aria-label="{{ __('shop_ui.welcome.shortcuts_dismiss') }}"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            </div>
            <p style="font-size:12px; color:var(--muted); margin-bottom:18px;" data-i18n="shortcuts_desc">{{ __('shop_ui.welcome.shortcuts_desc') }}</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink);">{{ __('shop_ui.welcome.shortcut_close') }}</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">ESC</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink);">{{ __('shop_ui.header.cart_open') }}</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">C</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink);">{{ __('shop_ui.welcome.shortcut_search') }}</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">/</kbd>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:var(--ivory); border-radius:6px; border:1px solid var(--line);">
                    <span style="font-size:12px; font-weight:600; color:var(--ink);">{{ __('shop_ui.welcome.shortcut_monogram') }}</span>
                    <kbd style="padding:2px 8px; background:var(--surface, #fff); border:1px solid var(--line); border-radius:4px; font-size:11px; color:var(--ink);">M</kbd>
                </div>

            </div>
            <div style="margin-top:20px; text-align:right;">
                <button type="button" data-close-shortcuts style="background:var(--gold); color:#fff; border:none; padding:8px 20px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer;" data-i18n="shortcuts_close">{{ __('shop_ui.welcome.shortcuts_close') }}</button>
            </div>
        </div>
    </div>

    <div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>

</x-react-root>
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
                    msg.textContent = seal.dataset.waxSuccess || '';
                    toast.classList.add('show');
                    setTimeout(function() { toast.classList.remove('show'); }, 3500);
                }
            }
        }

        // Live Monogram Real-Time Interaction (Norman Constraints & Signifiers)
        function initWelcomeFallback() {
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

            var searchInput = document.getElementById('home-catalog-query');

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
        }
        @isset($page)
            document.addEventListener('zafira:storefront-fallback-ready', initWelcomeFallback, {once: true});
        @else
            document.addEventListener('DOMContentLoaded', initWelcomeFallback, {once: true});
        @endisset
    </script>
@isset($page)
<script type="module" data-storefront-fallback-loader>
    const root = document.getElementById('app');
    let loading = false;
    let ready = false;
    let claimed = root.dataset.reactBoot === 'ready' || root.dataset.reactBoot === 'failed' || !document.querySelector('script[data-page="app"]');
    let replay = null;
    const start = async () => {
        if (loading || claimed || root.dataset.reactBoot !== 'fallback') return;
        loading = true;
        try {
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = @json(asset('js/zafira-core.js'));
                script.onload = resolve; script.onerror = reject;
                document.head.appendChild(script);
            }).catch(() => {});
            await import(@json(\Illuminate\Support\Facades\Vite::asset('resources/js/app.js')));
            document.dispatchEvent(new Event('zafira:storefront-fallback-ready'));
            root.querySelectorAll('#monogram-input, #pdp-monogram-input').forEach(input => input.dispatchEvent(new Event('input', {bubbles: true})));
            ready = true;
            observer.disconnect(); clearTimeout(timer); root.removeEventListener('click', click, true);
            const intent = replay; replay = null;
            if (intent?.isConnected) intent.click();
        } catch {
            const status = root.querySelector('[data-cart-status]');
            if (status) status.textContent = @json(__('shop_ui.app_js.err_service'));
        }
        replay = null;
    };
    const click = event => {
        const button = event.target.closest('button[type="button"]');
        if (!claimed && !ready && root.dataset.reactBoot === 'fallback' && button && !button.hasAttribute('onclick')) {
            replay ||= button;
            event.preventDefault(); event.stopPropagation();
        }
        void start();
    };
    root.addEventListener('click', click, true);
    const observer = new MutationObserver(() => void start());
    observer.observe(root, {attributes: true, attributeFilter: ['data-react-boot']});
    const timer = setTimeout(() => { if (!claimed && root.dataset.reactBoot === 'pending') root.dataset.reactBoot = 'fallback'; }, 10000);
    root.addEventListener('zafira:react-claim', () => { claimed = true; clearTimeout(timer); observer.disconnect(); root.removeEventListener('click', click, true); }, {once: true});
    void start();
</script>
@endisset
</body>
</html>
