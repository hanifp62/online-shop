# Task 3 — Kejujuran UI & Personalisasi

**Status:** DONE_WITH_CONCERNS
**Tanggal:** 2026-10-10
**Branch:** `fix/audit-benchmark-remediation`
**Commit implementasi:** `e28b2cf7ac6d61cbf870df0bea5a10509b22f65a`

## Perubahan

- `bookConcierge` memvalidasi `order_items.*.size` (`nullable|string|max:50`) dan `order_items.*.monogram` (`nullable|string|regex:/^[A-Z]{1,3}$/`). Nilai nonkosong disimpan sebagai `Ukuran` dan `Monogram` pada ringkasan `notes`.
- Keranjang membawa ukuran dan monogram melalui payload, localStorage, drawer, ringkasan checkout, dan input tersembunyi `order_items`. Kunci komposit memisahkan variasi produk yang berbeda.
- PDP menyediakan pilihan ukuran untuk busana dan panduan ukuran; input monogram membatasi A–Z sebanyak tiga karakter.
- Klaim sertifikat, SHA-256, telemetri, 24k, profil/advisor/ukuran fiktif, dan stepper fiktif dihapus dari tampilan terkait. MSI diganti dengan Admin Atelier.
- Navigasi `/prototype/` disembunyikan pada production; referensi aset tetap tersedia. Pemetaan aset kebaya yang keliru dan tautan privasi dihapus.
- Status akun memakai label Indonesia untuk `new`, `contacted`, `confirmed`, `completed`, dan `cancelled`. Label mata uang USD/EUR di storefront dihapus.

## Bukti verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test` | 62 passed, 243 assertions; baseline 59 test tetap hijau |
| `vendor/bin/pint --test app/Http/Controllers/StorefrontController.php tests/Feature/Task3RemediationTest.php` | Passed |
| `npm run build` | Passed, Vite 8.2.2; 4 modules |
| Pemeriksaan tujuh tautan prototype pada Blade | Seluruhnya berada dalam `@unless(app()->environment('production'))` |
| Pencarian `MSI`, `SHA-256`, `24k`, `Jean-Luc`, `USD` pada views | Tidak ditemukan |
| Output `public/build` | Diabaikan Git; tidak dikomit |

## Batasan dan risiko

- Empat file Blade sudah memiliki perubahan sebelum pekerjaan Task 3. Commit menyertakan perubahan bawaan tersebut, terutama diff besar `welcome.blade.php`; pemisahan historis tidak tersedia dalam commit ini.
- Perubahan lain di CSS, halaman autentikasi, prototype, dokumentasi, dan direktori skill tidak disertakan.
- Verifikasi browser interaktif belum dijalankan. Test PHP dan build tidak membuktikan seluruh interaksi keranjang, persistensi localStorage, maupun tampilan responsif.
- `public/prototype/app.js` masih memiliki logika USD/EUR tersendiri di luar cakupan tugas.
- Drawer profil welcome masih memiliki inisial avatar `AT` dan eyebrow `Espace Klien Privilégié`; normalisasi bahasa serta identitas belum sepenuhnya selesai.
- Parameter `?p=` hanya digunakan pada tautan yang digerbangkan untuk lingkungan non-production.
- Tidak ada model baru atau perubahan skema destruktif.
