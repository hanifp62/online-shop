# Verifikasi Remediasi ZAFIRA — 10 Oktober 2026

## Bukti browser lokal

- Target: `http://127.0.0.1:8000`, server lokal PHP yang sudah berjalan.
- Browser: Chromium headless cached build 1228 melalui Playwright 1.64.0.
- Home: harga tampil IDR; tidak ditemukan `USD`, `EUR`, atau `$` pada body.
- Storefront: item `Trench Céleste` masuk tas dan tetap terlihat di drawer.
- Search: pencarian `Saphir` menghasilkan produk.
- Filter: kategori `Tas Tangan` menampilkan dua produk.
- PDP: panduan ukuran terbuka dan tertutup dengan `Escape`; monogram dibatasi tiga huruf. Dua item dengan ukuran dan monogram berbeda tetap terpisah setelah reload.
- Overlay: tas, pencarian, concierge, profil, dan panduan ukuran menerima fokus, mempertahankan Tab di dalam, menutup dengan `Escape`, lalu mengembalikan fokus ke pemicu.
- Mobile: viewport 390×844 tidak menghasilkan overflow horizontal. Reflow pada 640×450 lulus; zoom browser asli belum diuji.
- Prototype: `/prototype/?p=9` dan `/prototype/?p=kebaya-lumiere` membuka studio sesuai produk. Handler menerima ID numerik maupun slug storefront.
- Console/page errors: tidak ada.

## Perubahan kode

- `prototype/app.js`: parameter `?p=` kini memeriksa `item.slug === requestedProductId || item.id === numericId` sebelum memanggil `openProductStudio`.
- `tests/task3.test.mjs`: regression test memastikan slug storefront diterima prototype router.

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

Baseline: 62 test PHP, 17 test Node. Sesudah perubahan: 62 test PHP (243 assertions), 19 test Node, build Vite, dan Pint lulus.

## Verifikasi trusted proxy (10 Oktober 2026)

- `vendor/bin/phpunit tests/Feature/TrustProxyTest.php`: 4 passed, 16 assertions. Coverage proves empty configuration ignores spoofed forwarded IP/proto headers; explicit proxy IP (`10.0.0.5`) and CIDR (`10.0.0.0/24`) honor forwarded client IP and HTTPS proto, including HTTPS URL generation.
- Wiring uses Laravel's `TrustProxies` global middleware, which reads `trustedproxy.proxies`; `config/trustedproxy.php` always returns an array, defaulting to `[]`. No wildcard default is used.
- `php artisan test`: 63 passed, 247 assertions (62 → 63 karena satu kasus CIDR baru pada `TrustProxyTest`). `node --test tests/*.mjs`: 19 passed. `npm run build`: passed. `vendor/bin/pint --test`: passed.
- These are local suite results, not evidence from staging or a real TLS proxy.

Belum lengkap: kombinasi search+kategori secara simultan, Shift+Tab, seluruh overlay termasuk menu mobile/quick view, zoom browser asli, serta pemeriksaan semua tautan produksi melalui browser environment production.

Prototype masih memiliki produk storefront yang tidak tersedia dalam katalog demo (misalnya sac-seraphine), selector mata uang dan klaim ledger/sertifikat/angka kriya/persona yang belum diaudit tuntas. Keberhasilan slug Kebaya tidak membuktikan seluruh pemetaan katalog. Toast fallback rendezvous kini menyatakan simulasi lokal; data localStorage demo bukan pesanan server. Tidak ada klaim siap rilis.

Migrasi getcat menangkap seluruh Throwable pada pembuatan/penghapusan procedure MySQL/MariaDB. Migrasi dapat sukses meskipun procedure tidak tersedia; pemanggil SQL langsung harus memeriksa keberadaannya. Category::getById merupakan jalur portable, bukan pemulihan procedure otomatis.
- Dokumen riset kompetitif `docs/02-product/benchmark/*.md` tetap ditandai sebagai riset sekunder/kualitatif awal, bukan hasil audit browser pihak ketiga.
