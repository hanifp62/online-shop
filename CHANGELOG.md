# CHANGELOG — ZAFIRA

Semua pembaruan pada 7 September 2026. Status akhir: **14 test hijau (39 assertions)**, pint pass, build OK.

## Keamanan (CRITICAL)
- `.env` & `.env.example` — `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `BCRYPT_ROUNDS=12`, kredensial MySQL `ecommerce`
- `app/Http/Middleware/SecurityHeaders.php` — **baru**: `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, `X-XSS-Protection`
- `bootstrap/app.php` — daftarkan middleware keamanan global + `trustProxies(at: '*')`
- `routes/web.php` — rate limiting: login 10/menit, register 5/jam, concierge 3/jam, newsletter 5/hari
- `app/Http/Controllers/AuthController.php` — password policy (`Password::min(8)->letters()->mixedCase()->numbers()->symbols()`)
- `resources/js/app.js` — escape HTML anti-XSS pada render keranjang & hasil pencarian

## Database & Produk (CRITICAL)
- Migration **baru**: `create_categories_table`, `create_products_table`, `add_product_details_columns` (`product_slug`, `product_badge`, `product_color`, `product_alt`, `product_description`)
- Seeder: 6 kategori & 8 produk — data real dari DB, hardcode di view dihapus
- `app/Http/Controllers/StorefrontController.php` — `index()` query DB (bukan array statis), `show()` detail produk, `sitemap()`
- `resources/views/products/show.blade.php` + `partials/product-header` & `product-footer` — **halaman detail produk baru**, route `/produk/{slug}`, related products, 404 untuk slug tak dikenal
- `app/Models/Product.php` — helper `imageWidth()`

## Checkout & UX (client user)
- Mode **checkout** pada modal concierge: ringkasan pesanan (produk × qty + total), kolom jadwal tersembunyi, tombol "Kirim permintaan pesanan", cart dikosongkan setelah sukses
- Migration `make_concierge_schedule_nullable` — `preferred_date`/`preferred_time` nullable utk alur checkout tanpa jadwal
- Item keranjang jadi link ke halaman produk; markup modal konsisten antara `welcome` dan `product-footer`

## SEO (HIGH)
- Route + view `resources/views/sitemap.blade.php` → `GET /sitemap.xml`
- `public/robots.txt` — Allow/Disallow + referensi `Sitemap:`
- JSON-LD `Organization` (home) & `Product`/`Offer`/`Brand` (detail) — dibangun di controller agar valid (menghindari kompilasi `@context` oleh Blade)
- Canonical, OGP, Twitter card, meta description per produk

## Email Notifications (HIGH)
- `app/Mail/ConciergeRequested.php`, `app/Mail/NewsletterWelcome.php` — **baru**, `ShouldQueue`
- `resources/views/mail/concierge-requested.blade.php`, `newsletter-welcome.blade.php` — template markdown
- Dikirim via `Mail::queue()` pada concierge request & subscribe baru

## Performansi & Polish
- `public/.htaccess` — HTTP cache headers (asset hashed immutable 1 tahun) + mod_deflate
- `public/manifest.webmanifest` + `public/sw.js` — **PWA** (baru), registrasi di `app.js`, `<link rel="manifest">` di view
- Semua `<img>` diberi `width`/`height` (anti CLS); gambar produk dipacu per konteks (grid 800 / detail 1600 / social 1200)

## Pengujian
- `tests/Feature/AuthTest.php` — **baru** (register/login/logout, weak password ditolak)
- `tests/Feature/StorefrontTest.php` — diperluas (brand, detail, sitemap, 404, concierge, checkout tanpa jadwal, newsletter idempotent) + seeding di `setUp()`
- `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` — RefreshDatabase

## Dokumentasi
- `RESULT.md` — rincian lengkap per fase, verifikasi, rekomendasi
- `RESUME.md` — ringkasan status final & kategori pekerjaan