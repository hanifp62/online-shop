# Laporan Implementasi: Bahasa Konsisten Lintas Saluran (R-51 / R-52)

**Tanggal:** 2026-10-21  
**Task Ref:** 58d3cb6c  
**Kepatuhan:** AGENTS.md, Engineering Excellence Protocol, HCI Council Protocol

---

## 1. Ringkasan & Ruang Lingkup Masalah

### Persyaratan Utama
- **R-51 (Email & Notifikasi):** Setiap pesan keluar (konfirmasi pesanan, verifikasi email, reset kata sandi, buletin atelier, dan notifikasi status) wajib dirender dalam bahasa penerima yang tersimpan/terpilih, bukan bahasa sesi pengirim atau worker queue Laravel.
- **R-52 (Invois & Dokumen Cetak/PDF):** Slip pesanan (`invoice`) dan dokumen ringkasan untuk pelanggan harus menggunakan bahasa preferensi pemesan (`orders.locale` atau `users.locale`), dan memulihkan kembali locale kerja operator/sistem seketika setelah perenderan selesai.

### Acceptance Criteria
- **AC-51-1:** Buletin memakai bahasa akun ketika query/sesi tidak memilih bahasa; notifikasi autentikasi memakai preferensi penerima.
- **AC-51-2:** Preferensi null menghasilkan fallback `id`, bukan bahasa pengirim; locale runtime dipulihkan setelah notifikasi autentikasi.
- **AC-52-1:** Invois memakai `RecipientLocale::resolve(orders.locale ?? users.locale)`; locale operator dipulihkan.
- **AC-52-2:** Email transisi pesanan tamu memakai `customer_email` dan locale tersimpan, termasuk setelah serialisasi mailable.
- **AC-51-3:** Pesanan tanpa alamat email tidak mengantrekan email dan tidak mengambil slot `order_notifications`.
- Pemetaan: enam test `CrossChannelLocaleTest` mencakup AC di atas serta pilihan bahasa concierge eksplisit. Test tamu membuat order langsung; tidak membuktikan penyimpanan locale melalui checkout HTTP.
- Prioritas pemilihan bahasa HTTP tetap `?locale=` > sesi > `users.locale` > `id`. Ini berbeda dari resolusi penerima untuk pesan tersimpan.

### Masalah Baseline yang Diidentifikasi
1. **Preferensi Bahasa User pada Notifikasi Bawaan Laravel:** Model `User` belum mengimplementasikan antarmuka `Illuminate\Contracts\Translation\HasLocalePreference`. Akibatnya, notifikasi bawaan seperti `VerifyEmail` dan `ResetPassword` meminjam locale aktif runtime alih-alih `users.locale`.
2. **Notifikasi Custom (`PasswordChanged`):** Menggunakan fallback `app()->getLocale()` jika preferensi penerima bernilai null, sehingga worker atau pengguna lain dapat mengontaminasi bahasa notifikasi.
3. **Pendaftaran Buletin (`StorefrontController::newsletter`):** Mengambil locale hanya dari sesi HTTP mentah, mengabaikan preferensi profil user terautentikasi yang login tanpa parameter query sesi.
4. **Pesanan Tamu (`Guest Order`):** Tabel `orders` tidak memiliki kolom untuk mencatat bahasa saat tamu melakukan checkout, dan `CheckoutService::notifyCustomer` mengabaikan `customer_email` pada pesanan tanpa relasi `user`.
5. **Slip Tagihan Cetak (`AdminOrderController::invoice`):** Menggunakan `app()->getLocale()` (bahasa operator admin yang sedang login) alih-alih bahasa pemesan/penerima.
6. **Ketiadaan Berkas Terjemahan Notifikasi Bawaan:** Belum tersedianya berkas JSON terjemahan untuk subjek dan baris standar notifikasi otentikasi Laravel di `lang/fr.json`, `lang/id.json`, `lang/it.json`, dan `lang/zh.json`.

---

## 2. Perubahan Implementasi

### A. Model `User` (`app/Models/User.php`)
- Mengimplementasikan `HasLocalePreference`.
- Menambahkan metode `preferredLocale(): string` yang memvalidasi `$this->locale` lewat `RecipientLocale::resolve($this->locale)`.
- Mengembalikan bahasa default `id` bila locale tidak valid atau null, mencegah kebocoran locale runtime.

### B. Skema Database & Model `Order` (`database/migrations/...`, `app/Models/Order.php`)
- **Migrasi Aditif:** Menambahkan file migrasi `database/migrations/2026_10_21_000020_add_locale_to_orders_table.php` dengan kolom nullable `locale` (`string(10)`, tanpa `after`) pada tabel `orders`. Migrasi bersifat aditif; `down()` hanya menghapus kolom tersebut.
- **Atribut Mass-Assignment:** Menambahkan `'locale'` ke dalam array `$fillable` pada model `Order`.

### C. Alur Checkout & Notifikasi (`app/Services/CheckoutService.php`)
- Pada `checkoutGuest`, mencatat bahasa aktif pemesan (`RecipientLocale::resolve(app()->getLocale())`) pada `extras` `createOrder`.
- Pada `notifyCustomer`, memakai `$order->customer_email` lebih dulu, lalu email akun; tanpa email, tidak mengklaim slot notifikasi.
- Menentukan locale pengiriman pesan keluar melalui urutan prioritas: `$order->locale ?? $order->user?->locale`, divalidasi ke daftar bahasa yang didukung (`RecipientLocale::resolve(...)`).
- Menyesuaikan pencatatan riwayat pelacakan (`trackingEvents`) agar menggunakan bahasa penerima pesanan.

### D. Slip Tagihan Admin (`app/Http/Controllers/AdminOrderController.php`)
- Membungkus proses render slip pesanan (`invoice`) dengan isolasi bahasa penerima pesanan (`$order->locale ?? $order->user?->locale`).
- Menjaga integritas bahasa operator dengan blok `try ... finally` yang mengembalikan `app()->setLocale($operatorLocale)` seketika setelah HTML selesai dibuat.

### E. Notifikasi & Buletin
- **`app/Notifications/PasswordChanged.php`:** Mengeliminasi pemanggilan `app()->getLocale()` saat locale penerima null; murni menggunakan locale notifiable yang divalidasi `RecipientLocale::resolve($notifiable->locale)`.
- **`app/Http/Controllers/StorefrontController.php`:** Menggunakan `app()->getLocale()` (yang sudah diproses oleh middleware `SetLocale` dengan prioritas query > session > user profile > default) saat mengirimkan buletin selamat datang `NewsletterWelcome`.
- **`lang/*.json`:** Menambahkan berkas terjemahan JSON (`lang/fr.json`, `lang/id.json`, `lang/it.json`, `lang/zh.json`) untuk baris-baris standar notifikasi email otentikasi Laravel.

---

## 3. Bukti Pengujian & Verifikasi (TDD)

### Test Khusus Lintas Saluran
File test `tests/Feature/CrossChannelLocaleTest.php` memverifikasi enam skenario batas; dua tambahan menguji query locale concierge dan pesanan tanpa email. Mutation check menghapus guard email sementara: test tanpa email gagal; guard dipulihkan sebelum suite penuh.
1. `test_newsletter_uses_authenticated_recipient_without_session_choice`: Berhasil membuktikan buletin terkirim menggunakan bahasa penerima akun terdaftar tanpa parameter sesi eksplisit.
2. `test_null_notification_locale_never_borrows_sender_locale`: Berhasil membuktikan pengguna tanpa locale tetap menerima bahasa fallback `id` dan tidak meminjam locale pengirim (`fr`).
3. `test_framework_notifications_render_in_recipient_locale_and_restore_worker`: Berhasil membuktikan notifikasi bawaan Laravel (`VerifyEmail` & `ResetPassword`) diterjemahkan penuh ke bahasa penerima (`Vérifiez votre adresse e-mail` & `Réinitialisez votre mot de passe`) dan worker locale dipulihkan ke `id`.
4. `test_guest_order_mail_and_invoice_use_saved_locale_not_operator`: Berhasil membuktikan email transisi pesanan tamu dan dokumen invois admin dirender dalam bahasa yang disimpan pada pesanan (`fr`), sementara locale admin pengakses dipulihkan kembali ke `id`.

### Hasil Eksekusi Perintah Verifikasi
```bash
# 1. Test suite lengkap
php artisan test
# Output: Tests: 4 skipped, 939 passed (25865 assertions)
# Exit Code: 0

# 2. Node, build, formatting
node --test tests/*.test.mjs   # 304 pass, 0 fail, EXIT 0
npm run build                  # EXIT 0
vendor/bin/pint --dirty --test # passed, EXIT 0

# 3. Git Diff Whitespace / Conflict Check
git diff --check
# Output: bersih tanpa trailing whitespace atau conflict markers
# Exit Code: 0
```

---

## 4. Batasan & Catatan Operasional
- **Format Invois:** Slip pesanan admin saat ini berformat HTML (nominal `Rp`), bukan biner PDF. Belum ada aturan `@media print` khusus.
- **Perubahan Semantik Test Lama:** `tests/Feature/AdminResidualLocaleTest.php` (invois) kini mengatur `$order->locale` per bahasa dan meminta dengan `?locale` operator yang berbeda, mencerminkan aturan bahasa penerima, bukan bahasa operator.
- **Bahasa Tamu:** Disuntikkan di `extras` `createOrder` (bukan `$contact`) sehingga `guestHash` dan idempotensi replay tidak berubah.
- **Dukungan Bahasa:** Terbatas pada whitelist `config('app.supported_locales')` (`id`, `en`, `fr`, `it`, `zh`). Kode bahasa yang tidak dikenal secara otomatis dinormalisasi ke `id`.
- **Penyimpanan Pesanan Historis:** Pesanan lama yang dibuat sebelum migrasi ini memiliki nilai `locale = NULL`. Sistem secara otomatis fallback ke `$order->user?->locale`, lalu ke default `id`.

## 5. Continuous Evaluation Record
- **Task Ref / Fitur:** `58d3cb6c` / R-51–R-52, bahasa konsisten lintas saluran.
- **Acceptance Criteria per Gate:** Email memakai penerima tersimpan; locale HTTP tetap query > sesi > user; pesanan tanpa email tidak mengklaim notifikasi; invoice memulihkan locale operator.
- **Baseline:** Guard email sudah ada pada `CheckoutService::notifyCustomer`; regresi missing-email belum terkunci test.
- **Controlled Change:** `tests/Feature/CrossChannelLocaleTest.php` dan laporan task ini.
- **Verification Commands & Exit Status:** `php artisan test` → EXIT 0, 939 passed/4 skipped; `node --test tests/*.test.mjs` → EXIT 0, 304 passed; `npm run build` → EXIT 0; `vendor/bin/pint --dirty --test` → EXIT 0; `git diff --check` → EXIT 0.
- **Manual Review Evidence:** Review kode guard: status harus valid dan recipient email harus truthy sebelum insert; catch menghapus claim saat queue gagal. Locale concierge sengaja tidak diubah: `SetLocale` memprioritaskan query > session > user.
- **Artifact / Log Path:** `/tmp/locale-php.log`, `/tmp/locale-node.log`, `/tmp/locale-build.log`.
- **Comparison to Baseline:** Regression test fails when email guard is removed, then passes after restoration; full suite remains green.
- **Regressions Checked:** Full PHP, all Node tests, production Vite build, Pint, diff whitespace.
- **Reviewer Verification:** Independent reviewer session unavailable in this subagent; focused manual review completed, so independent review remains TIDAK TERBUKTI.
- **Approver:** Main integrator session.
- **Decision:** ACCEPTED locally; final task closure requires main integrator CLI.
