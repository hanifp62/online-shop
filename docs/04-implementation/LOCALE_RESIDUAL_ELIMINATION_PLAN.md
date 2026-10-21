# Rencana Aksi Eliminasi Sisa Literal Storefront & Admin ZAFIRA

**Tujuan:** Menyelesaikan task locale (`6632663a-34b3-4fda-a868-25d56ae7eafa`) dengan memindahkan sisa string literal yang terdeteksi pada audit independen ke katalog terjemahan lima bahasa (`id`, `en`, `fr`, `it`, `zh`), menjaga status tes 100% hijau, dan memenuhi kriteria acceptance tanpa regresi.

---

### Irisan 1: Storefront Core & Hero (`welcome.blade.php`, `products/show.blade.php`, Partials)
- **Files:**
  - `resources/views/welcome.blade.php`
  - `resources/views/products/show.blade.php`
  - `resources/views/partials/product-header.blade.php`
  - `resources/views/partials/product-footer.blade.php`
  - `lang/{id,en,fr,it,zh}/shop_ui.php`
- **Tindakan:**
  1. Ganti hardcoded nav aria, labels, button texts, dan modal copy (Concierge, Shortcuts, Packaging, Monogram foil labels) menggunakan `__('shop_ui....')`.
  2. Perbaiki typo "Rendez-vous privat" menjadi "Rendez-vous privé" (sesuai pengecualian merek Prancis).
  3. Pindahkan label "Guide des Tailles", "Signature wrapping", "Care for Life", dan "À Découvrir" ke katalog `shop_ui.pdp`.
  4. Tambahkan kunci yang hilang di seluruh 5 bahasa dengan paritas rekursif 100%.

### Irisan 2: Email & Notifikasi (`app/Notifications/PasswordChanged.php`, Mails)
- **Files:**
  - `app/Notifications/PasswordChanged.php`
  - `lang/{id,en,fr,it,zh}/customer_messages.php` atau `mail_ui.php`
  - `tests/Feature/CustomerMessagesLocaleTest.php`
- **Tindakan:**
  1. Lokalisasi subjek dan isi notifikasi pergantian kata sandi mengikuti locale user target (`$notifiable->locale ?? app()->getLocale()`).
  2. Tambahkan assertion tes pergantian bahasa notifikasi.

### Irisan 3: Admin Flash & Dashboard Copy
- **Files:**
  - `app/Http/Controllers/AdminTotpController.php`
  - `app/Http/Controllers/Admin/AdminOrderController.php` (atau path aktual)
  - `resources/views/admin/dashboard.blade.php`
  - `resources/views/admin/auth-events.blade.php`
  - `resources/views/admin/layouts/app.blade.php`
  - `lang/{id,en,fr,it,zh}/admin_ui.php`
- **Tindakan:**
  1. Alihkan flash message `AdminTotpController` dan `AdminOrderController` ke `admin_ui.flash.*`.
  2. Bungkus literal dashboard dan auth-events dengan `__('admin_ui....')`.

### Irisan 4: Verifikasi, Residual Audit Ulang, & Final Browser Check
- **Files:**
  - `tests/Feature/ResidualLiteralLocaleTest.php`
  - Dokumentasi `docs/04-implementation/LOCALE_AND_TRANSLATION.md`
- **Tindakan:**
  1. Jalankan `php artisan test`, `node --test tests/*.mjs`, `npm run build`, `vendor/bin/pint --test`, dan `git diff --check`.
  2. Jalankan audit independen ulang hingga menghasilkan vonis **MET**.
  3. Finalisasi task NgodingPakeAI.
