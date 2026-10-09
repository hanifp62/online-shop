# Matriks Inventarisasi Fitur Aktual (Feature Inventory)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Metodologi:** Audit kode sumber langsung, penelusuran rute, dan verifikasi test suite  
**Tanggal:** 2026-10-10  

---

## 1. Matriks Fitur Storefront & Customer

| Feature ID | Nama Fitur | File Terkait | Status Aktual | Bukti | Dependency | Masalah / Keterbatasan | Prioritas | Rekomendasi |
|---|---|---|---|---|---|---|---|---|
| **FEAT-001** | Beranda & Hero Brand ZAFIRA | `welcome.blade.php`, `StorefrontController.php` | Implemented and verified | Rute `/` status 200, lulus test `storefront displays zafira brand` | Blade, Cache | Tidak ada masalah mendasar | Tinggi (Pertahankan) | Pertahankan estetika dan performa cache LRU |
| **FEAT-002** | Filter Kategori Dinamis | `welcome.blade.php`, `app.js`, `Category.php` | Implemented and verified | Lulus test `home renders dynamic category pills` | Database `categories`, JS filter | Tidak ada | Sedang | Pertahankan |
| **FEAT-003** | Halaman Detail Produk & Related Items | `products/show.blade.php`, `StorefrontController.php` | Implemented and verified | Rute `/produk/{slug}` 200, slug salah 404, lulus test `product detail page is accessible` | Model `Product`, `Category` | Gambar eksternal bergantung Unsplash | Tinggi (Pertahankan) | Pertahankan data provenance & craft |
| **FEAT-004** | Tas Belanja (Cart Drawer) & Storage | `app.js`, `welcome.blade.php` | Implemented and verified | JS renderCart, escapeHtml, sync localStorage v1 | LocalStorage, DOM | Client-side only; belum sinkron ke database user | Tinggi | Tambahkan sinkronisasi keranjang ke database saat login |
| **FEAT-005** | Quick View Modal Produk | `welcome.blade.php`, `app.js` | Implemented and verified | Interaksi klik quick-view membuka modal | JS state, DOM | Data atribut dibaca dari kartu DOM | Rendah | Pertahankan |
| **FEAT-006** | Custom Monogram Kreasi | `app.js`, `welcome.blade.php` | Implemented and verified | Sanitasi input monogram `[a-zA-Z]` di cart | LocalStorage | Hanya data teks di keranjang | Rendah | Pertahankan |
| **FEAT-007** | Mode Checkout via Concierge | `welcome.blade.php`, `app.js`, `StorefrontController.php` | Partially implemented | Lulus test `checkout request without schedule is valid` | `concierge_requests` table | Pesanan disimpan sebagai string ringkasan teks di kolom `notes` | Kritis | Rancang domain `Order` dan `OrderItem` terstruktur |
| **FEAT-008** | Booking Konsultasi Concierge | `StorefrontController.php`, `ConciergeRequest.php` | Implemented and verified | Lulus test `concierge request can be created` | DB table, `Mail::queue` | Pengiriman email bergantung queue worker aktif | Sedang | Pertahankan fungsionalitas konsultasi privat |
| **FEAT-009** | Berlangganan Newsletter (La Lettre) | `StorefrontController.php`, `NewsletterSubscriber.php` | Implemented and verified | Lulus test `newsletter subscription is idempotent` | DB table, `Mail::queue` | Memerlukan queue worker | Rendah | Pertahankan |
| **FEAT-010** | Registrasi & Login Pelanggan | `AuthController.php`, `auth/login.blade.php`, `auth/register.blade.php` | Implemented and verified | Lulus seluruh test `AuthTest` (policy password, auto backup) | Multi-guard `web` | Tampilan auth belum sepenuhnya menyamai keanggunan storefront | Sedang | Selaraskan styling formulir auth |
| **FEAT-011** | Halaman Profil Pelanggan & Riwayat | `ProfileController.php`, `account/show.blade.php` | Implemented and verified | Lulus test `user can view and update profile` | Model `User`, `ConciergeRequest` | Riwayat hanya membaca tabel `concierge_requests` | Sedang | Hubungkan dengan riwayat pesanan formal |
| **FEAT-012** | SEO, OpenGraph, JSON-LD, Sitemap | `StorefrontController.php`, `sitemap.blade.php`, `robots.txt` | Implemented and verified | Lulus test `sitemap is accessible`, JSON-LD valid | Blade, XML Response | Tidak ada | Tinggi | Pertahankan |
| **FEAT-013** | PWA & Offline Caching Dasar | `manifest.webmanifest`, `sw.js`, `app.js` | Implemented and verified | File ada di `public/` dan teregistrasi | Service Worker | Cache statis minimal | Rendah | Pertahankan |

---

## 2. Matriks Fitur Administrasi (Admin Atelier)

| Feature ID | Nama Fitur | File Terkait | Status Aktual | Bukti | Dependency | Masalah / Keterbatasan | Prioritas | Rekomendasi |
|---|---|---|---|---|---|---|---|---|
| **ADM-001** | Login & Proteksi Guard Admin | `AdminAuthController.php`, `admin/login.blade.php` | Implemented and verified | Guard `admin`, multi-auth session isolatif | Guard `admin`, rate limiter | Halaman login admin sangat sederhana | Sedang | Berikan sentuhan visual atelier yang konsisten |
| **ADM-002** | Dashboard Metrik & Agregasi KPI | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Partially implemented | Menampilkan count real untuk user, backup, concierge, subscriber, produk | Query Eloquent | Tampilan card metrik kasar, tidak ada grafik atau perbandingan periode | Tinggi | Desain ulang KPI card dengan hierarki modern |
| **ADM-003** | Manajemen Kategori (CRUD) | `AdminCategoryController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `admin can manage categories`, auto flush cache | Model `Category`, Cache | Form inline di dalam tabel satu halaman | Tinggi | Pisahkan ke menu/view navigasi kategori tersendiri |
| **ADM-004** | Manajemen Produk & Detail Kriya (CRUD) | `AdminProductController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `admin can create new product`, `admin can delete product` | Model `Product`, `Category` | Form create tersembunyi di tag `<details>`, edit produk hanya bisa ubah kategori | Kritis | Buat antarmuka katalog produk lengkap (tabel modular, drawer/modal create & edit komprehensif) |
| **ADM-005** | Manajemen Pesanan & Permintaan Concierge | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Partially implemented | Lulus test `admin can update concierge status`, filter status | Model `ConciergeRequest` | Tidak ada tabel pesanan khusus e-commerce (hanya status concierge) | Kritis | Kembangkan modul pesanan formal dengan badge alur status yang jelas |
| **ADM-006** | Manajemen Pengguna & Pencarian Profil | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `admin can search users` | Model `User` | Tabel digabung dalam dashboard monolitik | Sedang | Sediakan view daftar customer tersendiri |
| **ADM-007** | Arsip Audit Registrasi (UserInfoBackup) | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `register with legacy profile fields creates backup` | `UserInfoBackup`, `UserObserver` | Tampilan audit menumpuk di bagian bawah dashboard | Rendah | Buat tab / sub-menu audit log khusus |
| **ADM-008** | Manajemen Buletin Subscriber | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `admin can delete subscriber` | Model `NewsletterSubscriber` | Tampilan tabel sederhana tanpa fitur ekspor CSV | Rendah | Rapikan tabel subscriber |
| **ADM-009** | Penggantian Kata Sandi Admin | `AdminDashboardController.php`, `admin/dashboard.blade.php` | Implemented and verified | Lulus test `admin can change password` | `current_password:admin`, Password rule | Berada di panel terbawah satu halaman | Sedang | Pindahkan ke tab pengaturan profil admin |
