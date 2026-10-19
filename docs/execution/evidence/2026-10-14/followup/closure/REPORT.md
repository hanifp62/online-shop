# Bukti penutupan follow-up — 2026-10-15

Semua perubahan aplikasi terbatas pada copy katalog yang menjelaskan kontrak keranjang `SET`; layanan keranjang dan perilaku backend tidak berubah. Perubahan lain hanya dokumen/bukti/paket.

## Hasil verifikasi terisolasi

Checkout baru: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-closure-20261015`, port 8018. `setup.sh` selesai; log lengkap berada di `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-closure-20261015.setup.log`. SQLite lokal saja. Tidak mengakses `.env` operasional/MySQL. Fixture dua kali: exit 0/0. PHPUnit: 176 test, 1362 assertions, 1 skipped, exit 0. Node tests `tests/*.test.mjs`: exit 0. `npm run build`: exit 0. Sumber tracked relevant pre/post identik pada subset pencatatan closure; setup fingerprint menyatakan drift 0. Revisi test baru: 2/2 lulus.

PHPUnit attempt awal exit 1 karena `APP_URL` lokal sementara satu assertion sitemap mengharapkan `http://localhost`; percobaan berikutnya mengatur `APP_URL=http://localhost` pada env testing disposable dan lulus. Kedua log dipertahankan. PHPUnit menggunakan `DB_DATABASE=:memory:`.

Browser targeted, pada server `php artisan serve --host=127.0.0.1 --port=8018`: login sintetis, add home, quick +3 (qty 4), katalog `SET 1` (4→1), feedback, quick view `SET 2` (1→2), status live, link checkout, server cart quantity, tanpa page errors/HTTP >=400. 1 request font eksternal diblokir sesuai isolasi. Exit 0. Kegagalan percobaan selector relatif dan case-sensitive dipertahankan dalam `catalog-browser-attempt0.txt`; bukan kegagalan aplikasi. Server PID milik closure dihentikan, port 8018 terverifikasi bebas.

Jalankan log subprocess: `closure/*.json`, stdout/stderr. `source-pre.json`/`source-post.json`, `profile.json`. Server transcript OpenCode shell ditautkan dalam konteks sesi; tidak disalin karena berisi request log panjang.

## Sanitasi tiga test review

`review-copies/tests/Feature/` menyalin test asli, mengganti tepat satu nilai literal fixture kata sandi dummy (pada array `Admin::create`) menjadi ekspresi konkatenasi PHP dua literal ('sanitized-' dan 'fixture-only'). Tidak ada assertion/flow lain berubah. Hash asli/salinan dan transform di `review-copies/PROVENANCE.json`. Salinan sanitasi sengaja **tidak diuji**; pengujian penuh memakai checkout native terisolasi dan fixture dummy lokal.

## Konkurensi

`green-v3.json`: PASS, 11 skenario SQLite WAL, timeout 50ms, mode DEFERRED, barrier `initial_lookup` diverifikasi. SHA harness dan semua source cocok dengan JSON saat pemeriksaan. Ini pemetaan status/exception service, bukan hasil HTTP. Tidak mengubah retry. MySQL/MariaDB tidak diuji. Legacy localStorage hanya dibaca; tidak ada migrasi/merge otomatis. PDP stok merupakan snapshot render, bukan reservasi atomik.

## Keputusan interpretasi dan batas

Katalog awalnya memakai tombol “Tambahkan ke tas”, padahal service mengganti kuantitas. Kini tombol dan helper per locale (id/en/zh/fr/it) menyatakan set/ganti. Pembaruan pengujian browser lama `catalog-toast-count` tidak diperlukan: feedback yang diuji adalah `#catalog-feedback` dan kuantitas server. Kegagalan historis dalam `browser/RESULT.md` tetap dipertahankan.

Kredensial sintetis tidak dicetak. Jangan menganggap scanner pola terbatas sebagai bukti bebas rahasia. Tidak menguji MySQL, pembayaran nyata, prod, aksesibilitas lintas perangkat, maupun copy sanitasi secara exact-run.
