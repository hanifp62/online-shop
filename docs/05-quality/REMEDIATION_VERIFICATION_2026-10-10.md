# Verifikasi Remediasi ZAFIRA — 10 Oktober 2026

## Bukti browser lokal

- Target: `http://127.0.0.1:8000`, server lokal PHP yang sudah berjalan.
- Browser: Chromium headless cached build 1228 melalui Playwright 1.64.0.
- Home: harga tampil IDR; tidak ditemukan `USD`, `EUR`, atau `$` pada body.
- Storefront: item `Trench Céleste` masuk tas dan tetap terlihat di drawer.
- Search: pencarian `Saphir` menghasilkan produk.
- Filter: kategori `Tas Tangan` menampilkan dua produk.
- Kombinasi: kategori `Tas Tangan` + pencarian `Saphir` menghasilkan satu produk; istilah tanpa hasil menghasilkan nol produk, lalu katalog pulih setelah input dikosongkan.
- PDP: panduan ukuran terbuka dan tertutup dengan `Escape`; monogram dibatasi tiga huruf. Dua item dengan ukuran dan monogram berbeda tetap terpisah setelah reload.
- Overlay: tas, pencarian, concierge, profil, dan panduan ukuran menerima fokus, mempertahankan Tab di dalam, menutup dengan `Escape`, lalu mengembalikan fokus ke pemicu.
- Siklus Tab / Shift+Tab / Escape lengkap: quick view desktop via tombol judul, drawer tas, panel pencarian, dan menu mobile pada 390×844 terverifikasi menahan Tab dan Shift+Tab di dalam dialog, menutup via Escape, serta mengembalikan fokus ke pembuka asalnya.
- Quick view modal: fokus masuk ke elemen fokus pertama melalui retry loop terikat (50ms interval, maks 10 kali) yang menunggu transisi CSS selesai, bukan timeout tunggal yang rentan race.
- Mobile: viewport 390×844 tidak menghasilkan overflow horizontal (`scrollWidth === innerWidth === 390px` pada homepage dan PDP). Reflow pada 640×450 lulus; zoom browser asli belum diuji.
- Prototype: `/prototype/?p=9` dan `/prototype/?p=kebaya-lumiere` membuka studio sesuai produk. Handler menerima ID numerik maupun slug storefront.
- Console/page errors: tidak ada.

## Perubahan kode

- `prototype/app.js`: parameter `?p=` kini memeriksa `item.slug === requestedProductId || item.id === numericId` sebelum memanggil `openProductStudio`.
- `resources/js/app.js`: `openElement` menggunakan retry loop terikat (`focusInside`) saat elemen target belum menerima fokus karena transisi CSS, mencegah hilangnya fokus pada dialog seperti quick view.
- `resources/css/app.css`: aturan mobile untuk `.hero-content` dan `.footer-brand`/`.site-footer` diselaraskan agar tidak memicu overflow horizontal (`scrollWidth` 419px → 390px).
- `resources/views/welcome.blade.php`: reset positioning mobile `.hero-content` memastikan konsistensi layout dengan `app.css`.
- `tests/task3.test.mjs`: regression test memastikan slug storefront diterima prototype router.
- `tests/task4-focus.test.mjs`: regression tests memastikan retry loop fokus berjalan hingga target menerima fokus dan tetap terikat maksimal bila target tidak dapat menerima fokus.

## Batasan operasional

- Staging dan production environment tidak tersedia di mesin lokal ini.
- Driver deployment, proxy, queue worker, health check, backup/restore, dan rollback dicatat sebagai belum diverifikasi di staging nyata.
- Prosedur database: migrasi `2026_09_14_000004_create_getcat_procedure.php` hanya dieksekusi pada MySQL/MariaDB. SQLite pada test runner dilewati secara sengaja; aplikasi fallback ke `Category::getById($cid)`.

## Ketertelusuran kebutuhan

- FR-201: browser memverifikasi dua varian ukuran/monogram dan persistensi reload; payload backend diuji suite PHP, bukan POST ke database pengguna.
- FR-202: browser memverifikasi panduan ukuran PDP busana. Validitas seluruh klaim material belum diaudit ulang.
- FR-203/FR-204: suite PHP lulus; pengiriman concierge dan ownership akun tidak diuji ulang melalui browser database pengguna.
- NFR-201–NFR-203: bukti parsial dari suite lokal dan pemeriksaan browser di atas; bukan pernyataan semua acceptance criteria selesai.

## Hasil otomasi dan blocker

Baseline: 62 test PHP, 17 test Node. Sesudah perubahan: 63 test PHP (247 assertions), 22 test Node (termasuk 3 regression tests focus/retry baru), build Vite, dan Pint lulus.

## Verifikasi trusted proxy (10 Oktober 2026)

- `vendor/bin/phpunit tests/Feature/TrustProxyTest.php`: 4 passed, 16 assertions. Coverage proves empty configuration ignores spoofed forwarded IP/proto headers; explicit proxy IP (`10.0.0.5`) and CIDR (`10.0.0.0/24`) honor forwarded client IP and HTTPS proto, including HTTPS URL generation.
- Wiring uses Laravel's `TrustProxies` global middleware, which reads `trustedproxy.proxies`; `config/trustedproxy.php` always returns an array, defaulting to `[]`. No wildcard default is used.
- `php artisan test`: 63 passed, 247 assertions (62 → 63 karena satu kasus CIDR baru pada `TrustProxyTest`). `node --test tests/*.mjs`: 22 passed. `npm run build`: passed. `vendor/bin/pint --test`: passed.
- These are local suite results, not evidence from staging or a real TLS proxy.

Belum lengkap: zoom browser asli, serta pemeriksaan semua tautan produksi melalui browser environment production. (Kombinasi search+kategori simultan, Shift+Tab, siklus fokus quick view, dan menu mobile 390×844 kini telah terverifikasi via Playwright).

Prototype masih memiliki produk storefront yang tidak tersedia dalam katalog demo (misalnya sac-seraphine), selector mata uang dan klaim ledger/sertifikat/angka kriya/persona yang belum diaudit tuntas. Keberhasilan slug Kebaya tidak membuktikan seluruh pemetaan katalog. Toast fallback rendezvous kini menyatakan simulasi lokal; data localStorage demo bukan pesanan server. Tidak ada klaim siap rilis.

Migrasi getcat menangkap seluruh Throwable pada pembuatan/penghapusan procedure MySQL/MariaDB. Migrasi dapat sukses meskipun procedure tidak tersedia; pemanggil SQL langsung harus memeriksa keberadaannya. Category::getById merupakan jalur portable, bukan pemulihan procedure otomatis.
- Dokumen riset kompetitif `docs/02-product/benchmark/*.md` tetap ditandai sebagai riset sekunder/kualitatif awal, bukan hasil audit browser pihak ketiga.
