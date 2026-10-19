# Laporan Verifikasi Keamanan Akhir (Sanitized)

Tanggal audit: 11 Oktober 2026  
Ruang lingkup: Controller terkait (`StorefrontController`, `AdminDashboardController`, `AuthController`, `AdminAuthController`, `Controller`), template Blade `resources/views/admin/dashboard.blade.php`, dan rangkaian uji `tests/Feature/BackendPermissionAuditTest.php`.  
Batasan lingkup: Audit ini berfokus spesifik pada mitigasi XSS (JSON-LD context escaping dan inline JS event handlers), bukan jaminan ketiadaan XSS menyeluruh di seluruh aplikasi. Tanpa modifikasi produksi dan tanpa pembacaan rahasia/kredensial database.

---

## 1. Verifikasi Lingkungan Uji & Isolasi Database

- **Konfigurasi `phpunit.xml`:**  
  - `DB_CONNECTION = sqlite`
  - `DB_DATABASE = :memory:`
  - `DB_URL = ""`
  - `CACHE_STORE = array`, `SESSION_DRIVER = array`, `QUEUE_CONNECTION = sync`
  - Tidak ada berkas `bootstrap/cache/config.php` aktif yang menimpa konfigurasi env pengujian.
- **Eksekusi Pengujian Terfokus:**
  - Perintah: `php artisan test --filter=BackendPermissionAuditTest`
  - Hasil: **12 passed (141 assertions)** dalam 0.19s - 0.24s (exit code 0).
  - Klaim penambahan tes terverifikasi presisi: 12 test dan 141 assertions.

---

## 2. Analisis Keamanan Encoding JSON-LD

### Sinks & Sumber
- Sink: `resources/views/welcome.blade.php` (baris 30–32) dan `resources/views/products/show.blade.php` (baris 16–18) menggunakan `{!! $jsonLd !!}` di dalam `<script type="application/ld+json">`.
- Sumber data: `StorefrontController::index()` (Organization) dan `StorefrontController::show()` (Product dengan `name`, `description`, `material`, `color`).

### Evaluasi Perubahan (`JSON_HEX_TAG`)
- Controller kini menerapkan kombinasi flag:
  `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG`
- Karakter `<` dan `>` dikonversi menjadi representasi heksadesimal Unicode:
  - `<` -> `\u003C`
  - `>` -> `\u003E`
- **Ketahanan Konteks HTML:**
  - Tag penutup skrip seperti `</script>` tidak pernah muncul dalam bentuk literal HTML parser token, melainkan `\u003C/script\u003E`.
  - HTML parser tidak dapat memecah elemen blok `<script>` sebelum waktu yang ditentukan.
  - JSON parser JavaScript tetap menafsirkan `\u003C` sebagai `<` secara valid dan transparan, menjaga kepatuhan Schema.org.

---

## 3. Analisis Event Handler Inline Admin Dashboard

### Sinks & Interpolasi
File: `resources/views/admin/dashboard.blade.php`
- Baris 122: Form hapus kategori
  `onsubmit="return confirm(@js('Hapus kategori '.$category->cat_title.'?'))"`
- Baris 186: Form hapus produk
  `onsubmit="return confirm(@js('Hapus produk '.$product->product_title.'?'))"`
- Baris 312: Form hapus subscriber
  `onsubmit="return confirm(@js('Hapus subscriber '.$subscriber->email.'?'))"`

### Evaluasi Mekanisme `@js(...)`
- Directive `@js(...)` dikompilasi oleh Blade menjadi `\Illuminate\Support\Js::from(...)->toHtml()`.
- Framework menerapkan flag wajib:
  `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR`
- Perilaku pada konteks atribut ganda `onsubmit="..."`:
  1. **Kutipan tunggal (`'`)** diubah menjadi `\u0027`: payload penutup string JS gagal melepaskan diri dari fungsi `confirm(...)`.
  2. **Kutipan ganda (`"`)** diubah menjadi `\u0022`: payload penutup atribut HTML gagal melepaskan diri dari atribut `onsubmit`.
  3. **Ampersand (`&`)** diubah menjadi `\u0026`: mencegah browser menafsirkan entitas seperti `&#39;` sebelum eksekusi script.
  4. **Kurung siku `< >`** diubah menjadi `\u003C \u003E`: mencegah injeksi tag penutup/pembuka HTML.
  5. Backslash, karakter kontrol baris baru, serta terminator paragraf (`\u2028`, `\u2029`) dikelola aman tanpa error sintaks JS.

### Audit Seluruh Interpolasi Admin Dashboard
- Seluruh 62 kemunculan ekspresi Blade di `resources/views/admin/dashboard.blade.php` telah diaudit:
  - **Teks isi tabel & formulir:** Semua menggunakan `{{ ... }}` (Blade default `htmlspecialchars` dengan flag `ENT_QUOTES`).
  - **Atribut interaktif (event handler):** Tepat 3 lokasi menggunakan `@js(...)` di dalam `onsubmit`. Tidak ada `onclick`, `onmouseover`, atau `href="javascript:..."` dengan interpolasi variabel dinamis.
  - **Flash toast:** Baris 371 menggunakan `@if(session('success')) data-flash="{{ session('success') }}" @endif`. Script pendukung `resources/js/app.js` membaca via `dataset.flash` dan menetapkan isi modal menggunakan `msg.textContent = message;` (aman dari penafsiran HTML).
  - Tidak ada penggunaan directive unescaped `{!! ... !!}` di seluruh view admin dashboard.

---

## 4. Evaluasi Kualitas & Kesenjangan Pengujian Regresi

Rangkaian tes `BackendPermissionAuditTest` telah diperbarui dengan pengujian struktural positif:
1. `test_product_json_ld_escapes_html_tags_but_stays_valid_json()`:
   - Memastikan tidak ada literal `</script>` yang bocor ke HTML.
   - Mengambil tag skrip via XPath `//script[@type="application/ld+json"]`.
   - Menjalankan `json_decode` dengan `JSON_THROW_ON_ERROR` untuk membuktikan dokumen JSON-LD tetap valid dan integritas data terjaga.
2. `test_admin_dashboard_does_not_inject_stored_names_into_inline_javascript()`:
   - Menggunakan payload gabungan: `\');alert(1);// "kutipan" & \ </script><script>alert(9)</script>`.
   - Menguji ketiga entitas secara komprehensif (Kategori, Produk, dan Subscriber).
   - Menggunakan DOM Document untuk memeriksa atribut `onsubmit` pada ketiga formulir penghapusan.
   - Memvalidasi kehadiran escape sequence aman (`\u0027`, `\u0022`, `\u0026`, `\\\\`, `\u003C\/script\u003E`) dan memastikan ketiadaan literal payload yang tidak aman.

### Kesenjangan Pengujian (Test Gaps & Residual Risks)
1. **Validasi Runtime Headless Browser:** Pengujian saat ini berbasis HTTP string/DOM parsing Laravel, bukan evaluasi DOM interaktif pada mesin browser nyata (Playwright/Chrome headless).
2. **Karakter Non-UTF8:** Pengujian belum secara eksplisit menyertakan input byte malformed non-UTF8 yang berpotensi memicu substitusi fallback pada json encoder, meskipun validasi controller mensyaratkan tipe string standar.
3. **Cakupan Lingkup:** Verifikasi terbatas pada controller dan view yang diaudit. View storefront lainnya tetap perlu ditinjau jika mengadopsi pola event handler inline di masa mendatang.
