<div class="announcement-bar">
    <p>Complimentary signature wrapping & pengiriman untuk seluruh Indonesia</p>
    <button type="button" data-open-concierge>Rendez-vous privat</button>
</div>

<header class="site-header is-fixed" id="site-header">
    <button class="icon-button mobile-menu-button" type="button" aria-label="Buka menu" aria-controls="mobile-menu" aria-expanded="false">
        <svg aria-hidden="true"><use href="#icon-menu"/></svg>
    </button>

    <nav class="desktop-nav" aria-label="Navigasi utama">
        <a href="{{ route('home') }}#collection">Koleksi</a>
        <a href="{{ route('home') }}#new-arrivals">Terbaru</a>
        <a href="{{ route('home') }}#heritage">Warisan</a>
        <a href="{{ route('home') }}#maison">La Maison</a>
    </nav>

    <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA — kembali ke beranda">
        <x-brand-mark />
    </a>

    <div class="header-actions">
        <button class="icon-button" type="button" data-open-search aria-label="Cari">
            <svg aria-hidden="true"><use href="#icon-search"/></svg>
        </button>
        @auth
            <div class="account-wrap">
                <button class="icon-button account-button" type="button" aria-label="Akun {{ auth()->user()->name }}" aria-expanded="false">
                    <svg aria-hidden="true"><use href="#icon-user"/></svg>
                </button>
                <div class="account-menu">
                    <span>Bonjour, {{ auth()->user()->name }}</span>
                    <a href="{{ route('account.show') }}">Profil saya</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form>
                </div>
            </div>
        @else
            <a class="icon-button" href="{{ route('login') }}" aria-label="Masuk ke akun">
                <svg aria-hidden="true"><use href="#icon-user"/></svg>
            </a>
        @endauth
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
        <a href="{{ route('home') }}#collection"><span>01</span>Koleksi</a>
        <a href="{{ route('home') }}#new-arrivals"><span>02</span>Terbaru</a>
        <a href="{{ route('home') }}#heritage"><span>03</span>Warisan</a>
        <a href="{{ route('home') }}#maison"><span>04</span>La Maison</a>
    </nav>
    <div class="mobile-utilities">
        @auth
            <a href="{{ route('account.show') }}"><svg aria-hidden="true"><use href="#icon-user"/></svg>Profil saya</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><svg aria-hidden="true"><use href="#icon-user"/></svg>Keluar dari akun</button></form>
        @else
            <a href="{{ route('login') }}"><svg aria-hidden="true"><use href="#icon-user"/></svg>Masuk ke akun</a>
        @endauth
        <button type="button" data-show-wishlist><svg aria-hidden="true"><use href="#icon-heart"/></svg>Favorit <span class="wishlist-count">0</span></button>
    </div>
    <button class="text-link light mobile-concierge" type="button" data-open-concierge>Atur rendez-vous <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
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