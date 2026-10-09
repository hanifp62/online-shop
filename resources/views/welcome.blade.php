@php
    $products = $products ?? [];
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
    <title>ZAFIRA: La Maison de Lumière</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <script type="application/ld+json">
    {!! $jsonLd !!}
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="preload" as="image" href="https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=2000&q=90" fetchpriority="high">
    <link href="https://fonts.bunny.net/css?family=bodoni-moda:400,400i,500,500i,600&family=manrope:300,400,500,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront">
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
        <p>Complimentary signature wrapping & pengiriman untuk seluruh Indonesia</p>
        <button type="button" data-open-concierge>Rendez-vous privat</button>
    </div>

    <header class="site-header" id="site-header">
        <button class="icon-button mobile-menu-button" type="button" aria-label="Buka menu" aria-controls="mobile-menu" aria-expanded="false">
            <svg aria-hidden="true"><use href="#icon-menu"/></svg>
        </button>

        <nav class="desktop-nav" aria-label="Navigasi utama">
            <a href="#collection">Koleksi</a>
            <a href="#new-arrivals">Terbaru</a>
            <a href="#heritage">Warisan</a>
            <a href="#maison">La Maison</a>
        </nav>

        <a class="brand" href="{{ route('home') }}" aria-label="ZAFIRA: kembali ke beranda">
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
            <a href="#collection"><span>01</span>Koleksi</a>
            <a href="#new-arrivals"><span>02</span>Terbaru</a>
            <a href="#heritage"><span>03</span>Warisan</a>
            <a href="#maison"><span>04</span>La Maison</a>
            <a href="#services"><span>05</span>Layanan</a>
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

    <main id="main-content">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-media">
                <img src="https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=2000&q=90" alt="Editorial mode ZAFIRA bernuansa klasik" fetchpriority="high" width="2000" height="2500">
            </div>
            <div class="hero-overlay"></div>
            <div class="hero-edition">Édition No. 01<br>Jakarta · Paris · Firenze</div>
            <div class="hero-content reveal is-visible">
                <span class="eyebrow light">La Collection Première · 2026</span>
                <h1 id="hero-title">Lahir dari Cahaya,<br><em>Dibentuk oleh Keanggunan</em></h1>
                <p>Keanggunan Paris bertemu keahlian Italia dalam setiap kreasi yang memancarkan jiwa Anda.</p>
                <a class="button button-light" href="#new-arrivals">Jelajahi koleksi <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
            <a class="hero-scroll" href="#collection" aria-label="Gulir ke koleksi"><span></span>Découvrir</a>
        </section>

        <section class="marquee" aria-label="Nilai ZAFIRA">
            <div class="marquee-track">
                <span>L’Esprit Parisien</span><i>◆</i><span>La Mano Italiana</span><i>◆</i><span>Jiwa Nusantara</span><i>◆</i><span>Depuis Jakarta</span><i>◆</i>
                <span>L’Esprit Parisien</span><i>◆</i><span>La Mano Italiana</span><i>◆</i><span>Jiwa Nusantara</span><i>◆</i><span>Depuis Jakarta</span><i>◆</i>
            </div>
        </section>

        <section class="category-section section-shell" id="collection">
            <div class="section-heading reveal">
                <div><span class="eyebrow">L’Univers ZAFIRA</span><h2>Temukan dunia Anda</h2></div>
                <p>Setiap koleksi dirancang sebagai percakapan antara warisan dan modernitas.</p>
            </div>
            <div class="category-grid">
                <a class="category-card category-card-tall reveal" href="#new-arrivals" data-category-link="Warisan Nusantara">
                    <img src="https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=1200&q=88" alt="Koleksi Warisan Nusantara ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">01</span>
                    <div><span>Warisan Nusantara</span><h3>Indonesia</h3><span class="discover">Découvrir <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="#new-arrivals" data-category-link="Tas Tangan">
                    <img src="https://images.unsplash.com/photo-1594223274512-ad4803739b7c?auto=format&fit=crop&w=1200&q=88" alt="Koleksi tas tangan ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">02</span>
                    <div><span>Les Icônes</span><h3>Tas Tangan</h3><span class="discover">Découvrir <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
                <a class="category-card reveal" href="#new-arrivals" data-category-link="Koleksi Kapsul">
                    <img src="https://images.unsplash.com/photo-1583743814966-8936f5b7fdf1?auto=format&fit=crop&w=1200&q=88" alt="Koleksi kapsul songket dan sutra ZAFIRA" loading="lazy" width="1200" height="1500">
                    <span class="category-number">03</span>
                    <div><span>Deux Rives</span><h3>Koleksi Kapsul</h3><span class="discover">Découvrir <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span></div>
                </a>
            </div>
        </section>

        <section class="products-section" id="new-arrivals">
            <div class="section-shell">
                <div class="products-heading reveal">
                    <div><span class="eyebrow">Les Nouveautés</span><h2>Objek-objek penuh pesona</h2></div>
                    <p class="result-count"><span data-product-count>{{ count($products) }}</span> kreasi</p>
                </div>
                <div class="filter-bar" role="group" aria-label="Filter kategori produk">
                    <button type="button" class="filter-pill active" data-filter="Semua">Semua</button>
                    @foreach (($categories ?? collect()) as $category)
                        <button type="button" class="filter-pill" data-filter="{{ $category->cat_title }}">{{ $category->cat_title }}</button>
                    @endforeach
                </div>
                <div class="product-grid" data-product-grid>
                    @foreach ($products as $index => $product)
                        <article class="product-card reveal" data-product-card
                            data-id="{{ $product['id'] }}"
                            data-name="{{ $product['name'] }}"
                            data-category="{{ $product['category'] }}"
                            data-price="{{ $product['price'] }}"
                            data-price-label="{{ $product['price_label'] }}"
                            data-image="{{ $product['image'] }}"
                            data-description="{{ $product['description'] }}"
                            data-color="{{ $product['color'] }}"
                            data-material="{{ $product['material'] }}"
                            data-origin="{{ $product['origin'] }}">
                            <div class="product-media">
                                <img src="{{ $product['image'] }}" alt="{{ $product['alt'] }}" loading="lazy" width="800" height="1000">
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
                                <div class="product-color"><i style="--swatch: {{ $product['swatch'] }}"></i>{{ $product['color'] }}</div>
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
                <img src="https://images.unsplash.com/photo-1558769132-cb1aea458c5e?auto=format&fit=crop&w=1400&q=88" alt="Suasana atelier mode klasik" loading="lazy" width="1400" height="1750">
                <span>Depuis 2026</span>
            </div>
            <div class="story-content reveal">
                <span class="eyebrow light">L’Histoire de la Maison</span>
                <h2>Seperti safir,<br><em>keindahan sejati</em><br>lahir dalam proses.</h2>
                <p>ZAFIRA lahir dari keyakinan bahwa kemewahan tidak perlu bersuara keras. Ia hadir dalam ketepatan jahitan, sentuhan material, dan rasa percaya diri yang tertinggal lama setelah Anda meninggalkan ruangan.</p>
                <p class="story-signature">Parisian in spirit. Italian by craft. Indonesian at heart.</p>
                <a href="#atelier" class="text-link light">Kisah Maison <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="heritage-section section-shell" id="heritage" aria-labelledby="heritage-title">
            <div class="section-heading reveal">
                <div><span class="eyebrow">Trois Héritages · Tiga Warisan</span><h2 id="heritage-title">Tiga tanah, satu Maison</h2></div>
                <p>Paris memberi siluet. Italia memberi tangan. Indonesia memberi jiwa dan materialnya.</p>
            </div>
            <div class="heritage-grid">
                <article class="heritage-card reveal">
                    <span class="heritage-number">I</span>
                    <h3>L’Esprit Parisien</h3>
                    <p>Siluet, drapery, dan keberanian tampil tenang — digambar di Paris, dikenakan di mana saja.</p>
                    <span class="heritage-proof">Kain Lyon &amp; Biella · potongan bias-cut</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">II</span>
                    <h3>La Mano Italiana</h3>
                    <p>Kulit Santa Croce, jahitan saddle-stitch, sepatu Blake-stitched — keahlian yang bisa disol ulang.</p>
                    <span class="heritage-proof">Kulit Toskana &amp; Marche · finishing tangan</span>
                </article>
                <article class="heritage-card reveal">
                    <span class="heritage-number">III</span>
                    <h3>Jiwa Nusantara</h3>
                    <p>Batik tulis Solo, songket Palembang, tenun Flores, bordir Kebayoran — warisan hidup, bukan ornamen.</p>
                    <span class="heritage-proof">Pewarna alam · tenun kayu · borongan adil</span>
                </article>
            </div>
        </section>

        <section class="atelier-section section-shell" id="atelier">
            <div class="atelier-copy reveal">
                <span class="eyebrow">Atelier Z · Personnalisation</span>
                <h2>Jadikan ia milik Anda,<br><em>sepenuhnya.</em></h2>
                <p>Terinspirasi layanan bespoke rumah mode Eropa, Atelier Z menghadirkan monogram pribadi pada kreasi kulit pilihan—dikerjakan tanpa biaya tambahan.</p>
                <ul>
                    <li><span>01</span>Pilih hingga tiga inisial</li>
                    <li><span>02</span>Tentukan nuansa foil</li>
                    <li><span>03</span>Dikerjakan artisan dalam 3–5 hari</li>
                </ul>
                <button class="button button-dark" type="button" data-open-concierge>Konsultasikan kreasi <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
            </div>
            <div class="monogram-studio reveal">
                <div class="monogram-product">
                    <div class="monogram-tag">
                        <span class="monogram-preview" data-monogram-preview>ZA</span>
                    </div>
                    <span class="bag-handle"></span>
                    <div class="bag-body"><span class="bag-lock">Z</span></div>
                    <p>Pratinjau konseptual</p>
                </div>
                <div class="monogram-controls">
                    <label for="monogram-input">Inisial Anda</label>
                    <input id="monogram-input" name="monogram" type="text" maxlength="3" value="ZA" autocomplete="off" spellcheck="false" inputmode="text" aria-describedby="monogram-hint">
                    <small id="monogram-hint">Maksimal 3 karakter</small>
                    <span class="control-label">Warna foil</span>
                    <div class="foil-options" role="group" aria-label="Pilih warna foil">
                        <button type="button" class="foil active" style="--foil:#c7a665" data-foil="#c7a665" aria-label="Emas" aria-pressed="true"></button>
                        <button type="button" class="foil" style="--foil:#d7d4ce" data-foil="#d7d4ce" aria-label="Perak" aria-pressed="false"></button>
                        <button type="button" class="foil" style="--foil:#263a5d" data-foil="#263a5d" aria-label="Safir" aria-pressed="false"></button>
                    </div>
                </div>
            </div>
        </section>

        <section class="editorial-banner">
            <img src="https://images.unsplash.com/photo-1485230895905-ec40ba36b9bc?auto=format&fit=crop&w=2000&q=88" alt="Perempuan dengan busana editorial klasik" loading="lazy" width="2000" height="2666">
            <div class="editorial-content reveal">
                <span class="eyebrow light">Le Journal · Koleksi Kapsul</span>
                <h2>Songket &amp; Sutra,<br><em>Deux Rives.</em></h2>
                <p class="editorial-lede">Palembang bertemu Lyon dalam selendang dan gaun kapsul — tenun kayu, benang emas, pewarna alam.</p>
                <a href="#heritage" class="button button-light">Temui pengrajin <svg aria-hidden="true"><use href="#icon-arrow"/></svg></a>
            </div>
        </section>

        <section class="services-section section-shell" id="services">
            <div class="services-heading reveal">
                <span class="eyebrow">Les Services ZAFIRA</span>
                <h2>Lebih dari sebuah kreasi,<br>sebuah pengalaman.</h2>
            </div>
            <div class="services-grid">
                <button class="service-card reveal" type="button" data-open-concierge>
                    <span class="service-icon">01</span><h3>Private Styling</h3><p>Sesi kurasi personal bersama ZAFIRA Client Advisor, secara privat di boutique atau virtual.</p><span class="text-link">Atur rendez-vous <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </button>
                <article class="service-card reveal">
                    <span class="service-icon">02</span><h3>Transparansi Penuh</h3><p>Setiap kreasi mencantumkan material, komposisi, asal, dan siapa yang mengerjakannya.</p><span class="text-link">Lihat di halaman produk <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">03</span><h3>Care for Life</h3><p>Perawatan artisan untuk menjaga bentuk, warna, dan kisah kreasi ZAFIRA Anda sepanjang masa.</p><span class="text-link">Hubungi concierge <svg aria-hidden="true"><use href="#icon-arrow"/></svg></span>
                </article>
                <article class="service-card reveal">
                    <span class="service-icon">04</span><h3>{{ count($products) }} Kreasi Terkurasi</h3><p>Dari {{ ($categories ?? collect())->count() }} dunia — termasuk Warisan Nusantara dan Koleksi Kapsul.</p><span class="text-link">{{ date('Y') }} · Jakarta <svg aria-hidden="true"><use href="#icon-check"/></svg></span>
                </article>
            </div>
        </section>

        <section class="newsletter-section">
            <div class="newsletter-gem" aria-hidden="true"><i></i></div>
            <div class="reveal">
                <span class="eyebrow light">La Lettre ZAFIRA</span>
                <h2>Masuk ke dunia Maison.</h2>
                <p>Terima cerita atelier, undangan privat, dan akses pertama ke koleksi terbaru.</p>
                <form class="newsletter-form" action="{{ route('newsletter.store') }}" method="POST" data-ajax-form data-success-reset>
                    @csrf
                    <label class="sr-only" for="newsletter-email">Alamat email</label>
                    <input id="newsletter-email" type="email" name="email" placeholder="nama@contoh.id…" required autocomplete="email" spellcheck="false">
                    <button type="submit" aria-label="Berlangganan"><svg aria-hidden="true"><use href="#icon-arrow"/></svg></button>
                </form>
                <small>Dengan berlangganan, Anda menyetujui kebijakan privasi ZAFIRA.</small>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-brand"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><p>Parisian in spirit.<br>Italian by craft.<br>Indonesian at heart.</p></div>
        <div class="footer-links"><h3>Koleksi</h3><a href="#new-arrivals">Wanita</a><a href="#new-arrivals">Pria</a><a href="#new-arrivals">Tas Tangan</a><a href="#new-arrivals">Aksesori</a></div>
        <div class="footer-links"><h3>La Maison</h3><a href="#maison">Kisah Kami</a><a href="#atelier">Atelier Z</a><a href="#services">Layanan</a><button type="button" data-open-concierge>Rendez-vous</button></div>
        <div class="footer-links"><h3>Bantuan</h3><a href="mailto:bonjour@zafira.maison">Hubungi Kami</a><a href="#services">Pengiriman & Retur</a><a href="#services">Perawatan Produk</a><a href="#services">Panduan Ukuran</a></div>
        <div class="footer-bottom"><span>© {{ date('Y') }} <span translate="no">ZAFIRA</span> Maison. Seluruh hak dilindungi.</span><div><a href="#maison">Privasi</a><a href="#services">Ketentuan</a><a href="#main-content">Indonesia / ID</a><a href="#main-content" aria-label="Instagram ZAFIRA"><svg aria-hidden="true"><use href="#icon-instagram"/></svg></a></div></div>
    </footer>

    <div class="overlay" data-overlay></div>

    <aside class="drawer cart-drawer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="cart-title" tabindex="-1">
        <div class="drawer-header"><div><span class="eyebrow">Votre Sélection</span><h2 id="cart-title">Tas Belanja <small class="cart-count-text">(0)</small></h2></div><button class="icon-button" type="button" data-close-cart aria-label="Tutup tas belanja"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
        <div class="cart-body" data-cart-items></div>
        <div class="cart-empty" data-cart-empty><span class="empty-gem"></span><h3>Tas Anda masih kosong</h3><p>Temukan kreasi yang akan menjadi bagian dari kisah Anda.</p><button class="button button-dark" type="button" data-close-cart>Jelajahi koleksi</button></div>
        <div class="cart-footer" data-cart-footer hidden><div class="cart-service-note"><svg aria-hidden="true"><use href="#icon-check"/></svg><span>Signature wrapping & pengiriman complimentary</span></div><div class="cart-total"><span>Subtotal</span><strong data-cart-total translate="no">Rp0</strong></div><p>Biaya pengiriman dihitung pada langkah berikutnya.</p><button class="button button-sapphire" type="button" data-checkout>Lanjut bersama Client Advisor <svg aria-hidden="true"><use href="#icon-arrow"/></svg></button></div>
    </aside>

    <section class="search-panel" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="search-title" tabindex="-1">
        <div class="search-top"><a class="brand brand-horizontal" href="{{ route('home') }}"><x-brand-mark /></a><button class="icon-button" type="button" data-close-search aria-label="Tutup pencarian"><svg aria-hidden="true"><use href="#icon-close"/></svg></button></div>
        <div class="search-inner"><span class="eyebrow">Recherche</span><h2 id="search-title">Apa yang Anda cari?</h2><div class="search-field"><svg aria-hidden="true"><use href="#icon-search"/></svg><label class="sr-only" for="search-input">Cari produk</label><input id="search-input" type="search" data-search-input placeholder="Cari tas, busana, aksesori…" autocomplete="off" spellcheck="false" name="q"><button type="button" data-clear-search>Hapus</button></div><div class="search-suggestions"><span>Pencarian populer</span><button type="button" data-search-term="Tas Tangan">Tas ikonis</button><button type="button" data-search-term="Saphir">Saphir</button><button type="button" data-search-term="Baru">Koleksi terbaru</button></div><div class="search-results" data-search-results></div></div>
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

    <div class="toast" role="status" aria-live="polite" @if(session('success')) data-flash="{{ session('success') }}" @endif><span><svg aria-hidden="true"><use href="#icon-check"/></svg></span><p data-toast-message></p></div>
</body>
</html>
