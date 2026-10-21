<div class="announcement-bar">
    <nav class="lang-switcher" aria-label="{{ __('shop_ui.header.language_selector') }}">
        @foreach (['id' => 'ID', 'en' => 'EN', 'fr' => 'FR', 'it' => 'IT', 'zh' => 'ZH'] as $code => $label)
            <a href="{{ request()->fullUrlWithQuery(['locale' => $code]) }}" hreflang="{{ $code }}" @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <p>{{ __('shop_ui.header.announcement') }}</p>
    <button type="button" data-open-concierge>Rendez-vous privé</button>
</div>

<header class="site-header is-fixed" id="site-header">
    <button class="icon-button mobile-menu-button" type="button" aria-label="{{ __('shop_ui.header.menu_open') }}" aria-controls="mobile-menu" aria-expanded="false">
        <svg aria-hidden="true"><use href="#icon-menu"/></svg>
    </button>

    <nav class="desktop-nav" aria-label="{{ __('shop_ui.header.nav_label') }}">
        <a href="{{ route('home') }}#collection">{{ __('shop_ui.header.nav_collection') }}</a>
        <a href="{{ route('home') }}#new-arrivals">{{ __('shop_ui.header.nav_new') }}</a>
        <a href="{{ route('home') }}#heritage">{{ __('shop_ui.header.nav_heritage') }}</a>
        <a href="{{ route('home') }}#maison">La Maison</a>
    </nav>

    <a class="brand" href="{{ route('home') }}" aria-label="{{ __('shop_ui.header.home_aria') }}">
        <x-brand-mark />
    </a>

    <div class="header-actions">
        <button class="icon-button" type="button" data-open-search aria-label="{{ __('shop_ui.header.search') }}">
            <svg aria-hidden="true"><use href="#icon-search"/></svg>
        </button>
        @auth
            <div class="account-wrap">
                <button class="icon-button account-button" type="button" aria-label="{{ __('shop_ui.header.auth_account_aria', ['name' => auth()->user()->name]) }}" aria-expanded="false">
                    <svg aria-hidden="true"><use href="#icon-user"/></svg>
                </button>
                <div class="account-menu">
                    <span>{{ __('shop_ui.header.auth_greeting', ['name' => auth()->user()->name]) }}</span>
                    <a href="{{ route('account.show') }}">{{ __('shop_ui.header.auth_profile') }}</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('shop_ui.header.auth_logout') }}</button></form>
                </div>
            </div>
        @else
            <a class="icon-button" href="{{ route('login') }}" aria-label="{{ __('shop_ui.header.login_aria') }}">
                <svg aria-hidden="true"><use href="#icon-user"/></svg>
            </a>
        @endauth
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
        <a href="{{ route('home') }}#collection"><span class="mobile-menu-ordinal" aria-hidden="true">01</span>{{ __('shop_ui.header.nav_collection') }}</a>
        <a href="{{ route('home') }}#new-arrivals"><span class="mobile-menu-ordinal" aria-hidden="true">02</span>{{ __('shop_ui.header.nav_new') }}</a>
        <a href="{{ route('home') }}#heritage"><span class="mobile-menu-ordinal" aria-hidden="true">03</span>{{ __('shop_ui.header.nav_heritage') }}</a>
        <a href="{{ route('home') }}#maison"><span class="mobile-menu-ordinal" aria-hidden="true">04</span>La Maison</a>
    </nav>
    <div class="mobile-utilities">
        @auth
            <a href="{{ route('account.show') }}"><svg aria-hidden="true"><use href="#icon-user"/></svg>{{ __('shop_ui.header.auth_profile') }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><svg aria-hidden="true"><use href="#icon-user"/></svg>{{ __('shop_ui.header.auth_logout_full') }}</button></form>
        @else
            <a href="{{ route('login') }}"><svg aria-hidden="true"><use href="#icon-user"/></svg>{{ __('shop_ui.header.login_aria') }}</a>
        @endauth
        <button type="button" data-show-wishlist><svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('shop_ui.header.favorites') }} <span class="wishlist-count">0</span></button>
    </div>
    <button class="text-link light mobile-concierge" type="button" data-open-concierge>{{ __('shop_ui.header.arrange') }} <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
</div>

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