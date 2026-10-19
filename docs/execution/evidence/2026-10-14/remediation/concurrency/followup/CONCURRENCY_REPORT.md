# Followup bukti konkurensi checkout — 2026-10-14

## Ruang lingkup dan isolasi

Hanya salinan harness `../checkout-sqlite.php` dan direktori ini yang diubah. `tests/Concurrency/checkout-sqlite.php` dan `app/Services/CheckoutService.php` tidak diedit oleh lane ini. JSON historis `../red.json` dan `../green-v2.json` dipertahankan.

Sebelum eksekusi: inspeksi harness, konfigurasi SQLite, bootstrap, provider, migrasi, dan observer pengguna. Harness membuat SQLite baru pada direktori acak di temp OpenCode, menggunakan konfigurasi testing, menolak config cache, serta memilih nama berkas environment yang tidak ada. Tidak membaca `.env` operasional, menjalankan server, atau memakai port 8015/8016/8017. Migrasi hanya dijalankan pada database disposable setelah verifikasi konfigurasi resolved. Data factory sintetis; tidak ada kredensial dicatat. Temp database tidak dihapus agar bukti dapat diperiksa.

## Perintah dan jumlah eksekusi

Dari akar repositori:

```sh
php -l docs/execution/evidence/2026-10-14/remediation/concurrency/checkout-sqlite.php
php -m | grep -iE '^(pcntl|pdo_sqlite)$'
php docs/execution/evidence/2026-10-14/remediation/concurrency/checkout-sqlite.php > docs/execution/evidence/2026-10-14/remediation/concurrency/followup/green-v3.json 2> docs/execution/evidence/2026-10-14/remediation/concurrency/followup/green-v3.stderr.txt
```

Harness dijalankan **dua kali**, masing-masing exit 0, 11 skenario, 22 worker. Eksekusi kedua menambahkan hash sumber framework/observer; hasil pertama tidak dipublikasikan sebagai bukti final. Total 22 eksekusi skenario, 44 worker. `green-v3.json` adalah hasil eksekusi kedua; stderr kosong. Tidak ada eksekusi MySQL, browser, atau database operasional.

## Parameter terverifikasi

| Parameter | Nilai |
|---|---|
| PHP | 8.5.11 |
| Laravel | 12.69.1 |
| SQLite | 3.53.4 |
| Journal runtime | wal |
| Busy timeout runtime | 50 ms |
| Transaction mode config | DEFERRED |

Nilai runtime berasal dari `sqlite_version()`, `PRAGMA journal_mode`, dan `PRAGMA busy_timeout`. Parameter ini khusus harness, bukan bukti pengaturan produksi.

## Barrier dan hasil final

Listener kueri menahan worker setelah SELECT awal `orders` pada transaction level 0. Kedua lookup selesai sebelum salah satu keluar dari barrier. Barrier kedua menahan keduanya setelah SELECT produk di transaksi sebelum penulisan. Pemeriksaan timestamp `max(initial_lookup) <= min(initial_lookup_passed)` dan kehadiran kedua timestamp transaction-read menjadi syarat PASS. Ini menghindari replay serial yang menyamar sebagai balapan.

| Skenario | Status terurut | Orders/items | Stok akhir | Voucher used |
|---|---|---|---|---|
| same-key | 200,201 | 1/1 | 9 | 0 |
| changed-address | 201,409 | 1/1 | 9 | 0 |
| distinct-voucher | 201,422 | 1/1 | 9 | 1 |
| distinct-key | 201,201 | 2/2 | 8 | 0 |
| exact-one-stock | 201,422 | 1/1 | 0 | 0 |
| same-key-stock-one | 200,201 | 1/1 | 0 | 0 |
| voucher-last-per-user-one | 201,422 | 1/1 | 9 | 1 |
| same-key-voucher-last | 200,201 | 1/1 | 9 | 1 |
| combined-same-key | 200,201 | 1/1 | 0 | 1 |
| combined-distinct-key | 201,422 | 1/1 | 0 | 1 |
| double-cancel | 200,409 | 1/1 | 10 | 0 |

**11/11 PASS**, 10/10 barrier checkout PASS; cancellation menggunakan barrier stale-order terpisah. Distribusi final: 200=5, 201=11, 409=2, 422=4, 500=0. Skenario voucher terakhir memakai quota=1, used awal=0, per_user_limit=1. Combined memakai stok awal=1 serta parameter voucher tersebut. Pemeriksaan tambahan menghitung order terkait voucher. Order cancelled tetap menyimpan voucher_id tetapi used dikembalikan ke 0.

## Integritas dan sumber berubah bersama lane lain

Hash harness final: `a27bc9acbe5b5879399439ea735456817f3ea84fb3a0dd215e9238aa7592dcb3`.

Hash service saat run final: `4e27e486d16518e7cb90289e82fb6eaad27f7c7ba62f64a918772a29b6012f90`.

Hash JSON final: `34a4c51259eed35524ddbc9a6638b5d0bbf334219d9c703c0840dda8a8cdf13f`.

Hash historis green-v2: `2f7d48ba38cca49ba519f0d1b09b97b418ec53326091fd337ca75d22db6ffc48`.

Hash historis red: `7af5e856403111f7a2571aa36df14110bbf242734d9e00488263813eed060327`.

`green-v3.json.source_sha256` memuat sembilan hash sumber aktual, termasuk model, konfigurasi, migrasi voucher, observer, dan framework. Hash sumber saat pemeriksaan pasca-run cocok dengan manifest. Service berubah oleh lane lain selama sesi: hash awal `b1b88e542d59e4d55009007cf99f4120b8cce0fb3bf7a2a6fc56a063b58df90f`; hasil final hanya berlaku bagi hash service final di atas. Tidak mengklaim sumber awal dan final identik.

## Inspeksi read-only retry classifier

Referensi `app/Services/CheckoutService.php:97-150`, `vendor/laravel/framework/src/Illuminate/Database/ConcurrencyErrorDetector.php:18-37`.

- **Keterbatasan terverifikasi:** transient retry hanya mengenali SQLite driver code 5/6. MySQL deadlock 1213/timeout 1205 dan SQLSTATE 40001 tidak ditangani classifier ini. Menangkap `DeadlockException` saja tidak mengubah hasil classifier; implementasi tetap membutuhkan previous QueryException berkode SQLite. MySQL tidak diuji.
- **Risiko kode extended SQLite:** pencocokan tepat 5/6 tidak mengenali extended code seperti 517 apabila PDO mengembalikannya. Belum terbukti pada runtime ini; jangan menyebutnya kegagalan hasil harness.
- **Keterbatasan wrapping:** `errorInfo()` hanya menelusuri satu previous dan membutuhkan QueryException, bukan PDOException generik. Wrapper lebih dalam tidak diklasifikasi.
- **Klasifikasi constraint terlalu luas:** SQLite 19 mencakup semua constraint, bukan hanya UNIQUE. Service mencoba lookup replay untuk semua constraint 19; jika tidak ada order, galat dilempar kembali. Jalur ini belum diuji khusus.
- **Jalur pemulihan belum terlindungi retry:** SELECT order di catch berada di luar try transaksi; galat lock pada SELECT itu dapat keluar tanpa retry. Belum direproduksi.
- **Batas waktu:** maksimal empat attempt, tiga sleep 25/50/100 ms plus jitter 0–5 ms masing-masing. Total sleep 175–190 ms, bukan total durasi request; busy timeout dan durasi transaksi menambah waktu. Exhaustion 503 belum diuji.

Tidak mengubah classifier. Tidak menganggap fallback berbasis pesan framework otomatis lebih tepat untuk semua driver. Temuan di atas adalah inspeksi/keterbatasan, bukan kegagalan 11 skenario final.

## Batas bukti

Hasil berupa status yang dipetakan harness dari return/exception service, bukan respons HTTP server. Tidak membuktikan browser, beban tinggi, MySQL, konfigurasi SQLite produksi, atau perilaku extended driver codes. Voucher quota dan per-user limit keduanya bernilai 1 pada skenario terakhir; penyebab penolakan spesifik tidak dipisahkan di skenario tersebut. Skenario distinct-voucher historis tetap menguji per-user limit=1 dengan quota=10.
