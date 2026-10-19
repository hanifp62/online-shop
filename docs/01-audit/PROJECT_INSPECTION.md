# Laporan Inspeksi Menyeluruh Proyek (Project Inspection)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Lingkungan:** macOS 27.0.1 (arm64) / Antigravity IDE  
**Tanggal:** 2026-10-10  
**Metode:** Static code analysis, dynamic runtime CLI testing, and cross-layer inspection  

---

## 1. Arsitektur Repository & Struktur File

Aplikasi ini dibangun menggunakan arsitektur Laravel versi 12 dengan struktur modern (bootstrap berbasis closure di `bootstrap/app.php` tanpa `Http/Kernel.php` warisan).

### Komponen Backend Utama
- **Routing:** Didefinisikan di `routes/web.php` (69 baris) dan `routes/console.php`. Rute web mencakup storefront publik, otentikasi pelanggan, profil akun, dan panel manajemen admin dengan multi-guard.
- **Controllers:**
  - `StorefrontController.php`: Menangani halaman beranda (`index`), halaman detail produk (`show`), sitemap XML (`sitemap`), pemesanan concierge/checkout (`bookConcierge`), dan newsletter subscriber (`subscribe`).
  - `AuthController.php`: Otentikasi pelanggan (registrasi dengan password policy ketat, login, dan logout yang aman bagi sesi admin).
  - `ProfileController.php`: Menampilkan dan memperbarui data profil pelanggan (`/akun`) serta riwayat concierge.
  - `AdminAuthController.php`: Login & logout guard terpisah `admin`.
  - `AdminDashboardController.php`: Agregasi metrik KPI, filter pencarian user/concierge, pembaruan status concierge, ganti kata sandi admin, dan hapus subscriber.
  - `AdminProductController.php`: CRUD produk (tambah produk baru dengan auto-slug generator, pemindahan kategori, update atribut kualitas kreasi, dan hapus produk).
  - `AdminCategoryController.php`: CRUD kategori dan pembersihan cache storefront terpusat (`flushStorefrontCache()`).
- **Models:**
  - `Product`: Berelasi `belongsTo(Category)`. Memiliki accessor helper `imageWidth()` dan `swatchHex()`.
  - `Category`: Berelasi `hasMany(Product)`. Memiliki method statis `getById()` yang kompatibel dengan database SQLite maupun MySQL (`getcat`).
  - `User`: Otentikasi pelanggan dengan kolom profil warisan (`first_name`, `last_name`, `mobile`, `address1`, `address2`).
  - `Admin`: Otentikasi guard admin (`auth:admin`).
  - `ConciergeRequest`: Berelasi opsional ke `User`. Menyimpan pemesanan konsultasi privat atau ringkasan pesanan checkout (produk, qty, total harga) dan status pemrosesan (`new`, `contacted`, `confirmed`, `completed`, `cancelled`).
  - `NewsletterSubscriber`: Penyimpanan email pelanggan untuk buletin *La Lettre*.
  - `UserInfoBackup`: Audit trail otomatis dari pendaftaran user melalui `UserObserver`.
- **Middleware:**
  - `SecurityHeaders`: Menginjeksi header `nosniff`, `SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, dan `X-XSS-Protection`.
  - Rate limiting bawaan Laravel: login (10/mnt), register (5/jam), concierge (3/jam), newsletter (5/hari).
  - Multi-guard route middleware: `guest`, `auth`, `guest:admin`, `auth:admin`.

---

## 2. Audit Dependensi & Tooling

### Composer (`composer.json` & `composer.lock`)
- **PHP Engine Requirement:** `^8.2` (Lingkungan macOS saat ini menggunakan `PHP 8.5.11` yang memenuhi constraint).
- **Framework:** `laravel/framework: ^12.0` (Aktual terpasang: `12.69.1`).
- **Tinker:** `laravel/tinker: ^3.0`.
- **Dev Tools:** `phpunit/phpunit: ^11.5.50`, `laravel/pint: ^1.24`, `laravel/sail: ^1.41`, `fakerphp/faker: ^1.23`, `mockery/mockery: ^1.6`.
- **Temuan:** Lockfile valid dan konsisten. Tidak diperlukan `composer update`.

### Node & Frontend Tooling (`package.json`)
- **Vite:** `^8.2.2` dengan `@tailwindcss/vite: ^4.0.0` dan `tailwindcss: ^4.0.0`.
- **Helper Tools:** `concurrently: ^10.0.5`, `axios: ^1.11.0`, `laravel-vite-plugin: ^3.2.0`.
- **Temuan Kritis Migrasi:** File binary di `node_modules/.bin/vite` dan `vendor/bin/pint` kehilangan bit eksekusi Unix (`chmod +x`), menghasilkan `code 126: Permission denied` saat dijalankan di macOS.

---

## 3. Database & Skema Saat Ini

1. **Migration Files (29 Migrasi):**
   - 3 migrasi default Laravel (`users`, `cache`, `jobs`).
   - `concierge_requests` dan `newsletter_subscribers`.
   - `categories` dan `products` (termasuk kolom detail: `product_slug`, `product_badge`, `product_color`, `product_alt`, `product_description`, `product_material`, `product_composition`, `product_origin`, `product_care`, `product_craft`).
   - `admins` dan `user_info_backups`.
   - `getcat_procedure` (hanya aktif pada engine MySQL/MariaDB).
2. **Kondisi Data:**
   - Database MySQL Windows lokal tidak ikut terbawa secara fisik.
   - Dump SQL lengkap (`database/zafira_ecommerce_20260921_clean.sql`) dan Seeder (`AdminSeeder`, `CategorySeeder`, `ProductSeeder`) tersedia dan memuat data otentik katalog ZAFIRA.
   - SQLite `database/database.sqlite` tersedia di direktori `database/`.

---

## 4. Evaluasi Kesesuaian Sistem

| Area | Status | Catatan Teknis |
|---|---|---|
| **Core Laravel & PHP** | Stabil | 49 test passing pada PHP 8.5.11 |
| **CLI & Binary Execution** | Terhambat permission | Perlu `chmod +x` pada `vendor/bin` dan `node_modules/.bin` |
| **Storefront Customer** | Sangat Baik | Desain mewah, responsif, SEO-ready, PWA-enabled |
| **Admin Panel** | Parsial / Monolitik | Tampilan sederhana satu halaman panjang, belum memiliki sistem navigasi modular |
| **E-Commerce Checkout** | Berbasis Teks Concierge | Belum memiliki tabel relasional transaksi pesanan (`orders`, `order_items`) |
