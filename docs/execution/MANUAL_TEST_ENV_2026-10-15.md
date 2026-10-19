# Lingkungan Uji Manual Laravel 13 — 2026-10-15

## Ringkasan

| Area | Hasil | Bukti |
|---|---|---|
| Main app `:8000` | PASS untuk halaman utama | `manual-main-results.json`, screenshot `manual-main-*.png` |
| Database main | `mysql/ecommerce` terverifikasi | `php artisan config:show` |
| Produk main | 12 produk, semua `stock=0` | query MariaDB |
| Out-of-stock catalog | PASS, 8 label yang dirender: `Stok: 0` | `manual-main-results.json` |
| PDP | PASS, JSON-LD `OutOfStock` | `manual-main-results.json` |
| Add-to-cart main | PASS ditolak HTTP 422, tanpa row operasional baru | response `Stok produk tidak mencukupi atau produk tidak aktif.` |
| Console/page errors main | Tidak ada page error; dua console error berasal dari response 422 yang disengaja | `manual-main-results.json` |
| Synthetic DB | PASS, MariaDB `zafira_upgrade13_synth_20261015_0410`, migrations=24 | `migrate:status`, query count |
| Synthetic flow | PASS login → tambah tas → quantity 2 → pending → cancel | `evidence/ui-browser-results.json` |
| Stock synthetic | 5 → 3 saat pending → 5 setelah cancel | `evidence/ui-browser-results.json` |
| Admin PDF | PASS, 8,001 bytes, signature `%PDF-` | `evidence/ui-admin-report.pdf` |
| Backup | PASS checksum penuh dan hash dibandingkan sumber | `~/Backups/zafira/2026-10-15-laravel13/SHA256SUMS` |

## URL aktif

- Main: http://127.0.0.1:8000/
- Koleksi: http://127.0.0.1:8000/koleksi
- PDP: http://127.0.0.1:8000/produk/sac-seraphine
- Login: http://127.0.0.1:8000/login
- Synthetic manual: http://127.0.0.1:8031/

Main server dimulai setelah `lsof` memastikan tidak ada listener lama di port 8000. Parent PID artisan: `16324`; listener child: `16373`; cwd: `/Users/hanifpriyambodo/Documents/online-shop`. Synthetic parent PID: `18088`; listener child: `18198`; cwd listener: synthetic root `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13/public`.

Kedua server dibiarkan berjalan.

## Synthetic environment

Root terisolasi:

```text
/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13
```

Konfigurasi efektif:

- `default=mysql`
- database `zafira_upgrade13_synth_20261015_0410`
- session `file`
- cache `file`
- queue `sync`
- mail `array`
- port `8031`

Credential file, mode 0600:

```text
/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13/credentials.json
```

Jangan tampilkan nilainya di chat atau log.

Start:

```sh
cd /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13
./start.sh
```

`start.sh` menolak start jika default bukan `mysql` atau database bukan synthetic database.

Stop:

```sh
cd /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13
./stop.sh
```

`stop.sh` memeriksa listener port 8031 dan cwd sebelum kill.

Source root dibuat saat pemeriksaan. Bila source berubah, sinkronkan hanya direktori source berikut; **jangan gunakan `--delete` pada root sintetis** karena dapat menghapus `credentials.json`, skrip server, dan bukti pengujian:

```sh
SRC=/Users/hanifpriyambodo/Documents/online-shop
DST=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13
for dir in app config resources routes; do rsync -a "$SRC/$dir/" "$DST/$dir/"; done
rsync -a "$SRC/public/build/" "$DST/public/build/"
```

Jangan salin `.env`, credentials, `vendor`, `node_modules`, storage, atau cache konfigurasi. Jalankan `php artisan config:show database.default` dan `php artisan config:show database.connections.mysql.database` dari `$DST` sebelum mencoba alur manual. Sinkronisasi tanpa `--delete` tidak menghapus file source lama; tinjau perbedaan source jika ada file yang dihapus dari repo.

## Bukti browser

Script dan hasil berada di evidence root:

```text
/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-upgrade13-20261015-033109/
```

Synthetic evidence berada di:

```text
/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13/evidence/
```

Normal GET membuat session/cache file. Tidak ada penulisan ke database operasional `ecommerce` selain aktivitas normal framework yang diizinkan. Add-to-cart main ditolak sebelum cart disimpan.

## Backup permanen

Lokasi:

```text
~/Backups/zafira/2026-10-15-laravel13/
```

Direktori mode 0700. File backup mode 0600. Backup mencakup:

- `ecommerce-pre-migration.sql`
- `source-checkpoint.tar.gz`
- baseline `composer.json` dan `composer.lock`
- semua upgrade log yang ada di evidence root
- salinan current upgrade-related files di `upgrade-current/`
- `SHA256SUMS`

Verifikasi yang dijalankan:

```sh
cd ~/Backups/zafira/2026-10-15-laravel13
shasum -a 256 -c SHA256SUMS
```

Semua entri `OK`. Hash `ecommerce-pre-migration.sql` juga sama dengan hash original evidence.

File upgrade-related yang disalin:

```text
composer.json
composer.lock
tests/TestCase.php
tests/Feature/RescueBackendAuditTest.php
tests/Feature/AmadisOrderTest.php
tests/Feature/AdminOrderReportTest.php
tests/Unit/TestDatabaseGuardTest.php
tests/Support/guard-probe.php
```

`tests/Unit/TestDatabaseGuardTest.php` dan `tests/Support/guard-probe.php` hanya tersedia sebagai salinan di `upgrade-current/`. Keduanya bukan anggota `source-checkpoint.tar.gz`, karena dibuat setelah checkpoint.

## Panduan rollback selektif

Jangan jalankan rollback otomatis. Jangan gunakan `git checkout`, `git reset`, atau reset database.

1. Buat backup database baru dan minta persetujuan eksplisit.
2. Untuk dependency, salin baseline `composer.json` dan `composer.lock` dari backup secara selektif.
3. Jalankan `composer install` setelah memastikan lockfile dan platform PHP cocok.
4. Untuk test lama yang ada di checkpoint (`tests/TestCase.php`, `RescueBackendAuditTest.php`, `AmadisOrderTest.php`, `AdminOrderReportTest.php`), ekstrak path spesifik dari `source-checkpoint.tar.gz` ke direktori temporary. Jangan ekstrak ke repo.
5. Review diff, lalu copy hanya path test yang diperlukan ke repo. Pertahankan pengaman database sampai ada evaluasi terpisah. Kembalikan perubahan test dependency dan CSRF bersama-sama; jangan kembalikan salah satunya saja.
6. `TestDatabaseGuardTest.php` dan `guard-probe.php` adalah file baru. Jangan menghapus atau memulihkannya dari checkpoint. Putuskan secara eksplisit apakah dipertahankan bersama pengaman.
7. Jangan menyentuh file aplikasi, config, atau database tanpa scope rollback terpisah.
8. Database rollback memerlukan backup baru, approval, dan prosedur restore terverifikasi.

Tidak ada rollback yang dijalankan.

## Catatan risiko

- Main app sengaja memiliki stock nol untuk seluruh 12 produk. Jangan menaikkan stock di `ecommerce`.
- Synthetic test membuat order pending lalu cancel, sehingga stock synthetic kembali ke baseline 5.
- Dua console error main adalah 422 dari percobaan add-to-cart out-of-stock yang diharapkan.
- Bukti browser memuat screenshot dan PDF synthetic, tetapi tidak memuat secret.

## Tidak dilakukan

Tidak dilakukan login ke akun nyata, pembayaran, pengiriman email, deploy, commit, push, migrasi destruktif, atau rollback database.

## Koreksi handoff round2 final

Bagian di atas mencatat run manual sebelumnya. Hasil dan listener akhir setelah sinkronisasi source:

- Main `http://127.0.0.1:8000`: PID 16373, cwd `/Users/hanifpriyambodo/Documents/online-shop/public`. Browser akhir memeriksa home, koleksi, PDP, login, cart, akun. Sebanyak 12 tombol home, 8 tombol katalog, tombol PDP, quick-view add, dan quantity input dinonaktifkan untuk stok nol. Paksa klik menghasilkan 0 POST; 0 page error, console error, request gagal, atau respons HTTP >=400. Bukti: `final-main-results.json`, `final-main-*.png` di root bukti privat.
- Synthetic `http://127.0.0.1:8031`: PID 18198, cwd synthetic root `/public`; customer login, add, ubah quantity menjadi 2, checkout pending, cancel, admin login, dan PDF dilakukan lewat klik UI. Order sintetis #5 cancelled; stok produk 1 kembali ke 5. PDF 8.014 byte, signature `%PDF-`. Bukti `final-ui-browser-results.json`, `final-ui-*.png`, `final-ui-admin-report.pdf`.
- Effective synthetic settings diverifikasi dari root synthetic: mysql / `zafira_upgrade13_synth_20261015_0410`; session=file, cache=file, mail=array, queue=sync. Credential path tetap `.../credentials.json`; isinya tidak ditampilkan.
- Source `app/ config/ resources/ routes/ public/build/` disinkronkan tanpa `--delete`. Seluruh 80 file dalam daftar checksum source cocok pada synthetic. Satu build JS lama yatim tertinggal karena sinkronisasi aman tanpa delete; manifest hanya menunjuk asset build aktif.
- Final commands/logs ada di `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-upgrade13-20261015-033109/`: `mysql-full-final-skips.log` (204; 201 lulus, 3 skipped), `mysql-targeted-final-testdox.log` (8/8), `node-tests-final2.log` (92/92), `npm-build-final2.log`, `composer-platform-final2.log`.
- Snapshot original backup `/Users/hanifpriyambodo/Backups/zafira/2026-10-15-laravel13/SHA256SUMS` diverifikasi ulang; hasil disimpan sebagai `original-backup-final-check.log`. Backup asli tidak diubah.
- Kedua server dibiarkan berjalan. Perintah stop: synthetic `cd /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-mariadb-l13 && ./stop.sh`; main hentikan hanya setelah `lsof -p 16373 -a -d cwd` memastikan cwd `/Users/hanifpriyambodo/Documents/online-shop/public` dan proses milik sesi ini; parent artisan PID 16324. Jangan kill PID berdasarkan port saja.

Batas: deadlock test memakai fault injection sintetis, bukan deadlock dua koneksi nyata; uji origin HTTPS bukan TLS/proxy live.
