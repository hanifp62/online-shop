# Audit Keamanan & Kualitas Kode (Security and Quality Audit)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Metodologi:** Static analysis, code review, OWASP Top 10 checklist, dan automated assertion analysis  
**Tanggal:** 2026-10-10  

---

## 1. Audit Keamanan Aplikasi (OWASP Alignment)

| Kontrol Keamanan | Status | Bukti Implementasi pada Kode Sumber | Penilaian Risiko |
|---|---|---|---|
| **Otentikasi & Multi-Guard** | Kuat (Hardened) | `config/auth.php` memisahkan guard `web` (provider `users`) dan guard `admin` (provider `admins`). Logout user tidak menghapus sesi admin secara tak sengaja (`AuthController.php:84-96`). | Rendah |
| **Password Policy & Hashing** | Kuat (Hardened) | Diatur dengan `Password::min(8)->letters()->mixedCase()->numbers()->symbols()` di `AuthController.php` dan `AdminDashboardController.php`. Hashing menggunakan Bcrypt dengan cost round 12 (`.env: BCRYPT_ROUNDS=12`). | Sangat Rendah |
| **Proteksi Cross-Site Scripting (XSS)** | Kuat (Hardened) | Blade auto-escaping (`{{ }}`). Pada client-side `resources/js/app.js`, fungsi `escapeHtml()` diterapkan saat render item keranjang dan pencarian produk dinamis. Header `X-Content-Type-Options: nosniff` aktif. | Rendah |
| **Proteksi CSRF** | Kuat (Hardened) | Middleware `VerifyCsrfToken` bawaan Laravel aktif di grup `web`. Seluruh formulir POST/PATCH/DELETE menyertakan direktif `@csrf` dan meta tag `csrf-token`. | Sangat Rendah |
| **SQL Injection** | Kuat (Hardened) | Seluruh interaksi database menggunakan Eloquent ORM berparameter (`Product::where(...)`, `Category::create(...)`). Migrasi `getcat` stored procedure menggunakan prepared query. | Sangat Rendah |
| **Mass Assignment Protection** | Terkendali | Model mendefinisikan `$fillable` secara eksplisit (`User`, `Product`, `ConciergeRequest`). Seluruh input divalidasi via `request->validate(...)` sebelum masuk ke model. | Rendah |
| **Rate Limiting (Anti Brute-Force & Spam)** | Kuat (Hardened) | Dikonfigurasi di `routes/web.php` per IP: `login` (10/menit), `register` (5/jam), `concierge` (3/jam), `newsletter` (5/hari). | Rendah |
| **HTTP Security Headers** | Aktif | Diimplementasikan melalui middleware kustom `SecurityHeaders` yang didaftarkan secara global pada `bootstrap/app.php`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`. | Rendah |

---

## 2. Kualitas Kode & Hasil Test Suite

### Automated Test Coverage (`php artisan test`)
- **Total Test:** 49 test passed (168 assertions).
- **Rincian Feature Tests:**
  - `AdminManagementTest`: 5 test (admin create product, delete product, delete subscriber, concierge checkout notes integration, user backup audit).
  - `AuthTest`: 5 test (register form, login form, registration flow, rejection of weak passwords, logout).
  - `BrandHeritageTest`: 8 test (three brand heritages, nusantara & capsule categories, provenance, craft, JSON-LD material, admin quality fields).
  - `LegacyPatchTest`: 22 test (legacy profile fields, seeder integrity, procedure fallback, profile mutation, prefilled checkout, admin filters, category cache flush, sitemap isolation).
  - `StorefrontTest`: 7 test (brand title, product detail 200/404, sitemap 200, concierge appointment, checkout without schedule, newsletter idempotency).
  - `ExampleTest` & `Unit/ExampleTest`: 2 test baseline.

### Standar Gaya Kode (Linting / Pint)
- Konfigurasi `laravel/pint` tersedia di `composer.json`.
- Eksekusi sebelumnya terhambat oleh permission Unix pada `vendor/bin/pint` (dijadwalkan diperbaiki pada Fase 3).

---

## 3. Temuan Kelemahan & Rekomendasi Peningkatan

1. **Struktur Relasional Pesanan E-Commerce:**
   - *Temuan:* Pesanan saat ini disimpan dalam kolom teks `notes` pada tabel `concierge_requests`.
   - *Rekomendasi:* Buat tabel relasional `orders` dan `order_items` dengan integritas foreign key dan snapshot harga transaksi.
2. **Ketergantungan Eksternal Gambar (Unsplash CDN):**
   - *Temuan:* Seluruh gambar produk mengarah ke URL eksternal Unsplash.
   - *Rekomendasi:* Siapkan fallback visual lokal atau caching aset jika koneksi internet terputus di lingkungan pengembangan.
3. **Penyelarasan Desain Form Autentikasi:**
   - *Temuan:* Form `/login` dan `/register` belum sepenuhnya mengadopsi kemewahan visual storefront utama.
   - *Rekomendasi:* Poles tampilan form auth agar selaras dengan tema Maison ZAFIRA.
