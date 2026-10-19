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

## Perbaikan temuan review (belum dikomit)

**Status:** DONE_WITH_CONCERNS

| # | Temuan | Perbaikan |
| --- | --- | --- |
| 1 | Klaim palsu di runtime i18n dan welcome | `service_4_*` menjadi kartu kriya jujur; durasi `atelier_step_3` dihapus; 84 kunci `telemetry_*`, `account_cert*`, `account_step_*`, dan profil fiktif dihapus dari id/en/fr/it; `service_2_desc` tanpa jam kerja; toast rendezvous menjadi "Simulasi" di empat bahasa; `(3–5 hari)` dan kutipan Madame Aurelia dihapus dari welcome. |
| 2 | Kunci keranjang bertabrakan | `cartKey = JSON.stringify([id, size, monogram])` untuk `data-cart-id`, update, dan remove; item localStorage lama dinormalisasi (item invalid dibuang, monogram A–Z maks. 3). |
| 3 | Dua filter katalog berbenturan | Satu `applyFilter` berbasis `hidden`: kategori/favorit ∩ kata kunci (`#live-catalog-search-input`, termasuk material/asal). Skrip filter inline `card.style.display` dihapus. |
| 4 | Drawer profil di luar siklus overlay | Profil masuk `syncOverlay`/`closeAll`/focus trap; buka/tutup via `data-open-profile`/`data-close-profile`; handler Escape inline dan `body.style.overflow` dihapus. Escape global di `app.js` juga menutup modal pintasan. `toggle` overlay/no-scroll memakai `Boolean(...)`. |
| 5 | Tambah cepat melewati ukuran | Tombol kartu dan quick-view untuk kategori `Busana` mengarah ke `/produk/{id}` tanpa menambah item. |

### Bukti

| Pemeriksaan | Hasil |
| --- | --- |
| `node --test tests/task3.test.mjs` sebelum perbaikan | 5 gagal (RED) |
| `node --test tests/*.mjs` | 8 passed, 0 failed |
| `php artisan test` | 62 passed, 243 assertions |
| `npm run build` | Passed, Vite 8.2.2; 4 modules |
| `vendor/bin/pint --test` (welcome, Task3RemediationTest) | Passed |

### File

- `resources/js/app.js`
- `resources/views/welcome.blade.php`
- `public/js/zafira-core.js`
- `tests/task3.test.mjs` (baru)

### Celah tersisa

- `public/js/zafira-core.js` dan `tests/task3.test.mjs` belum dilacak Git; keduanya wajib ikut commit berikutnya.
- `MULTI_PRODUCTS` di `zafira-core.js` masih memuat klaim 60 jam, 3 minggu, 12 jam, 24k, dan nomor seri. `studio_monogram_desc` (emas 18 karat), `toast_wax_sealed` (nomor seri), `dock_atelier` "Komando MSI", `msi_*`, dan `profile_msi_link` juga tetap ada. Semuanya memerlukan verifikasi katalog.
- `welcome.blade.php:367` masih menaut Studio Z ke `/prototype/?p={{ $product['id'] }}#studio-view`. Pengenal `?p=` di `prototype/` belum dipetakan.
- Catatan riset historis di `docs/05-quality/COUNCIL_AUDIT_REPORT.md:100` dan `docs/06-prototype/HCI_UX_COUNCIL_AUDIT.md:302` tidak diubah.
- `public/prototype/app.js` (USD/EUR) dan data Aurelia di `prototype/app.js` di luar cakupan.
- Drawer profil masih menampilkan inisial `AT` dan "Espace Klien Privilégié".
- Belum ada uji browser interaktif. Tes Node memakai DOM tiruan; audit fokus penuh menjadi bagian Task 4.

## Verifikasi runtime lanjutan — 2026-10-10

- `public/js/zafira-core.js` dan `prototype/zafira-core.js` adalah dua file berbeda. `public/prototype` hanya symlink folder ke `../prototype`; perubahan diterapkan pada kedua dictionary runtime.
- `node --test tests/*.mjs`: **9 passed, 0 failed**.
- `php artisan test`: **62 passed, 243 assertions**.
- `npm run build`: **Passed**.
- `node --check public/js/zafira-core.js` dan `node --check prototype/zafira-core.js`: **Passed**.
- `tests/task3.test.mjs` kini memverifikasi empat bahasa dan absennya klaim MSI, telemetry, sertifikat, serial, ukuran tubuh, 18k/24k, serta durasi produksi dari kedua dictionary runtime dan `MULTI_PRODUCTS`.
- `?p=` prototype menerima ID numerik. Tautan storefront mengirim `$product['id']`; pemetaan ID tersebut belum diverifikasi terhadap fixture katalog backend.

### Status celah setelah verifikasi

- Klaim pada `prototype/app.js` tetap di luar cakupan: data Aurelia, serial/certificate flow, MSI UI, ukuran tubuh, durasi craft, dan fallback toast lama.
- Tidak ada perubahan pada `prototype/app.js`, `prototype/app.css`, atau aset.
- Belum ada uji browser interaktif. Risiko utama: fallback string di `prototype/app.js` dapat tetap menampilkan klaim bila kunci i18n hilang.
