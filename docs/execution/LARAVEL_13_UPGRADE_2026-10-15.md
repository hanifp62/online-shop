# Upgrade Laravel 13 — 15 Oktober 2026

## Status akhir: verifikasi lokal lulus; bukan persetujuan produksi

Framework lokal berubah dari Laravel 12.69.1 menjadi **13.36.0** (CLI PHP 8.5.11, MariaDB 10.4.28). Listener utama `127.0.0.1:8000` PID 16373 melayani `ecommerce`; synthetic `:8031` PID 18198. Suite akhir MySQL guarded dan Node lulus. Dua tes retry SQLite dilewati dengan alasan engine-specific; tiga tes MySQL baru benar-benar dijalankan. Pekerjaan uncommitted pengguna dipertahankan. Tidak ada commit, push, deploy, pembayaran, atau email nyata. Bagian riwayat di bawah bukan status akhir; hasil final berada pada bagian “Handoff round2 final”.

## Requirement dan bukti penerimaan

| ID | Kriteria penerimaan | Hasil |
|---|---|---|
| NFR-UP13-001 | Pertahankan pekerjaan pengguna dan checkpoint sumber | 4.644 file dicatat; hash baseline aman; composer.json (^8.4.1), composer.lock, RescueBackendAuditTest.php, tests/TestCase.php, tests/Feature/AmadisOrderTest.php, dan tests/Feature/AdminOrderReportTest.php disesuaikan terarah; 0 file baseline hilang |
| NFR-UP13-002 | Backup diverifikasi sebelum migrasi biasa ecommerce | Dump dipulihkan ke database baru; 15 tabel cocok berdasarkan jumlah, checksum, dan struktur |
| FR-UP13-003 | Terapkan tujuh migrasi tertunda tanpa mengarang stok | 24 migrasi tercatat; 12 produk tetap stock=0 dan is_active=1; orders=0 |
| NFR-UP13-004 | Upgrade terarah, Dompdf tepat 3.1.6, PHP ^8.4.1 | Laravel 13.36.0, Dompdf tepat 3.1.6, PHPUnit 12.5.39, collision 8.9.5, tinker 3.0.2; composer.json diselaraskan ke `^8.4.1` (mengikuti floor Symfony 8.1.x); `check-platform-reqs` lulus 100% |
| FR-UP13-005 | Alur browser sintetis dan PDF nyata | UI login, tambah tas, quantity 2, checkout pending, cancel, admin login, unduh PDF lulus; stok kembali 5; PDF 8.014 byte dengan signature %PDF-. |
| NFR-UP13-006 | Tes MySQL guarded, Node, build dan platform | MySQL PHPUnit: 204 tes, 201 lulus, 3 skipped, 1.450 assertions; Node: 92/92 lulus; Vite build dan platform reqs lulus. Tidak menjalankan suite SQLite. |
| FR-UP13-007 | Home ecommerce lewat browser port 8000 | HTTP 200; judul ZAFIRA: La Maison de Lumière; zero page errors; screenshot `home-8000-l13.png` tersimpan |
| NFR-UP13-008 | Review independen | Selesai: sesi `ses_ec3d6b3aeffeoN4XU75HzEcsQV` (MEDIUM floor PHP, hardening cache, LOW origin CSRF test) dan review lanjutan `independent-review-followup.log` (tanpa blocker; dua tes retry khusus SQLite bukan regresi MySQL; matcher dan harness memadai) |

## Fase dan runtime

1. Checkpoint sumber, inspeksi database, dump dan restore verifikasi.
2. Review `migrate --pretend`, kemudian `migrate --force` pada ecommerce.
3. Baseline Laravel 12 dan suite SQLite.
4. Dry-run Composer, update terarah dengan hooks aktif, perbaikan referensi middleware CSRF pada tes.
5. Suite SQLite, build, pemeriksaan platform, browser MySQL sintetis, pemeriksaan MySQL Feature.
6. Server akhir ecommerce dan hash pembanding.

Runtime: macOS; CLI `/opt/homebrew/bin/php` 8.5.11; MariaDB 10.4.28 di 127.0.0.1:3306. PHP XAMPP `/Applications/XAMPP/xamppfiles/bin/php` 8.2.4 **tidak kompatibel** dengan floor lock aktual `^8.4.1` (Symfony 8.1.x `>=8.4.1`); Apache XAMPP tidak di-upgrade karena aplikasi dijalankan dengan `php artisan serve`. phpMyAdmin XAMPP (`config.inc.php`: auth config, user root, host bawaan localhost/socket) mengarah ke server MariaDB yang sama: `@@port=3306`, socket `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`, versi 10.4.28. Lock juga membawa Symfony 8.1.x, Guzzle 8.2.0, dan brick/math 1.0.0. Jangan menyamakan kelulusan CLI dengan kompatibilitas Apache XAMPP.

## Perintah dan hasil

Tanggal sesi 15 Oktober 2026 lokal. Sebagian output aplikasi bertanggal 14 Oktober UTC. Timestamp individual fase awal tidak dipertahankan dalam catatan ringkas; tidak direkonstruksi secara spekulatif. Root bukti memakai timestamp `20261015-033109`.

| Perintah / pemeriksaan | Exit / hasil |
|---|---|
| `php artisan migrate --pretend`, lalu `php artisan migrate --force` pada ecommerce | 0; tujuh migrasi biasa |
| `composer update laravel/framework phpunit/phpunit nunomaduro/collision laravel/tinker laravel/pail laravel/sail --with-all-dependencies` (dry-run lalu aktual) | 0, 0; hooks berjalan |
| `php artisan test` SQLite sebelum dan sesudah upgrade | 175 lulus, 1 skipped; run final 1.362 assertions |
| `composer check-platform-reqs` | lulus seluruh requirement |
| `composer validate --no-check-publish` | valid; peringatan exact pin Dompdf yang memang diwajibkan |
| `npm run build` | 0; Vite 8.2.2 |
| Browser sintetis `test-browser.cjs` | 0 pada run akhir |
| MySQL `php artisan test --testsuite=Feature` | 1; 168 lulus, 4 gagal, 1.338 assertions |
| Browser GET-only port 8000 | 0; HTTP 200, pageErrors=[] |
| Perbandingan SHA256 sumber | 0; tiga file berubah, nol hilang sebelum laporan ini |

### Tindak lanjut 2026-10-14T20:50Z–20:58Z UTC

Jurnal mesin: `followup-commands.jsonl` (start/end UTC, exit). Log penuh di root bukti.

| Perintah | Exit / hasil |
|---|---|
| Edit `composer.json` php `^8.3` → `^8.4.1`; `composer update --lock --no-interaction` | 0; "Nothing to modify", hooks berjalan, versi paket tidak berubah |
| MySQL Feature (env `DB_DATABASE=zafira_upgrade13_phpunit_20261015_0420`, `ZAFIRA_SAFE_MYSQL_TESTS=1`) → `mysql-feature-final.log` | 1; 172 tes, 170 lulus, 2 gagal, 1.339 assertions |
| SQLite `php vendor/bin/phpunit` → `sqlite-all-final.log` | 0; 176 tes, 1 skipped, 1.362 assertions |
| `node --test tests/*.mjs` → `node-tests-final.log` | 0; 85 lulus (`npm test` tidak ada di package.json, exit 1) |
| `npm run build` → `node-build-final.log` | 0 |
| `composer check-platform-reqs` / `composer validate --no-check-publish` | 0 / 0 (peringatan pin Dompdf) |
| `opencode run` review independen → `independent-review-followup.log` | 0; tanpa blocker |

Harness MySQL aman (`tests/TestCase.php`, opt-in `ZAFIRA_SAFE_MYSQL_TESTS=1`): hanya untuk DB terisolasi bernama tepat dengan 24 migrasi. Harness menyetel `RefreshDatabaseState::$migrated = true`, sehingga `migrate:fresh` internal tidak dipanggil. Tiap tes tetap memakai transaksi rollback. Perintah `migrate:fresh/refresh/reset` dan `db:wipe` dilempar exception. Setelah run: migrations=24, users=0, orders=0. Tanpa env, perilaku SQLite tidak berubah. Catatan: suite SQLite tetap memakai `RefreshDatabase` bawaan atas `:memory:`, termasuk fresh internal pada DB memori sementara; bukan MySQL dan bukan ecommerce.

Percobaan browser awal gagal karena path Chromium, asumsi cart berupa HTML, dan parameter PDF `period` tidak diberikan. Skrip bukti diperbaiki, bukan kode aplikasi. Cart sebenarnya endpoint JSON. PDF akhir menggunakan `?period=today` dan tidak mengikuti redirect. Checkout/cancel memakai API request Playwright dengan sesi login browser; ini bukan bukti seluruh tombol checkout UI telah diuji.

Seeder DatabaseSeeder sempat dijalankan ulang dan gagal pada email sintetis duplikat. Tidak dijalankan pada ecommerce. Kredensial tes hanya untuk database sintetis; tidak dicantumkan di laporan.

### Penyimpangan keselamatan yang harus dicatat

Agen menjalankan `migrate:fresh --force` pada database tes baru `zafira_upgrade13_phpunit_20261015_0420`. Ini **melanggar larangan perintah fresh pengguna**, walaupun bukan pada ecommerce. Suite RefreshDatabase juga memakai migrasi ulang internal pada database tes tersebut. Tidak ada database yang di-drop. Tidak ada restore atau fresh pada ecommerce. Jangan mengulang tindakan ini tanpa izin eksplisit.

### Kegagalan MySQL — riwayat

Run awal: 168 lulus, 4 gagal.
- Tiga tes AmadisOrderTest menginjeksi kegagalan dengan pencocokan `update "products"`, khusus quoting SQLite. SQL MySQL memakai backtick sehingga injector tidak terpanggil.
- AdminOrderReportTest baris 334 menghitung kueri memakai `from "users"`, juga khusus SQLite.

Perbaikan (hanya tes): matcher memakai `DB::connection()->getQueryGrammar()->wrapTable(...)`, sehingga quoting mengikuti driver aktif. Injector, assertion, dan jumlah percobaan tidak dilemahkan; tidak ada tes dilewati.

Run akhir: 170 lulus, 2 gagal. `test_non_transient_query_error_is_not_retried` dan tes laporan kini lulus di MySQL. Dua tes `test_transient_sqlite_lock_*` menyuntikkan kode SQLite 5 dan mengharapkan retry. `CheckoutService::isTransientLock` sengaja mengembalikan false di luar sqlite, jadi kedua tes tidak berlaku pada MySQL. Kode produksi tidak diubah. Opsi lanjutan, dengan persetujuan: tandai kedua tes khusus sqlite (`requires` driver), atau tambah kebijakan retry deadlock MySQL (1213/1205) beserta tesnya.

## Koreksi reviewer dan verifikasi harness — 2026-10-14T21:05Z–21:06Z UTC

Riwayat kegagalan dan penyimpangan pada bagian atas tetap dipertahankan. Koreksi berikut menutup blocker fail-open dan dua temuan tes; tidak ada `migrate:fresh`, migrate, reset, wipe, atau tes SQLite yang dijalankan pada tindak lanjut ini.

| Perintah | Exit / hasil |
|---|---|
| Probe child-process guard tanpa kernel, `.env`, atau PDO: default kosong, target `ecommerce`, URL MySQL operasional, SQLite file, opt-in hilang, split read/write, SQLite memory, MySQL schema salah/benar, URL override | 0; semua target aman diterima dan operasional ditolak sebelum koneksi |
| `php vendor/bin/phpunit tests/Unit/TestDatabaseGuardTest.php` | 0; 11 tes, 22 assertions |
| `ZAFIRA_SAFE_MYSQL_TESTS=1 DB_CONNECTION=mysql DB_DATABASE=zafira_upgrade13_phpunit_20261015_0420 DB_URL='' php vendor/bin/phpunit --testsuite Feature` | 0; 172 tes, 170 lulus, 2 skipped, 1.336 assertions |
| Perintah sama, tanpa `--testsuite Feature` | 0; 187 tes, 184 lulus, 3 skipped, 1.367 assertions |
| `php -l` pada tiga file PHP yang diubah | 0 |

Harness sekarang menolak target selain `sqlite :memory:` tanpa opt-in; opt-in tepat hanya menerima nama disposable MySQL yang disetujui. Config URL Laravel diurai sebelum koneksi. Target MySQL diverifikasi ulang melalui koneksi efektif dan `select database()`, lalu pemeriksaan read-only memastikan daftar 24 migration identik serta semua tabel InnoDB. State migrated diset dan destructive-command listener dipertahankan sebelum `RefreshDatabase` dijalankan.

Dua tes retry SQLite kini dilewati eksplisit pada driver lain sebelum fixture dibuat. Ini dua skip engine-specific, bukan bukti kompatibilitas seluruh engine. Tes laporan menyimpan order hasil factory, mencari `#<id> |`, dan memastikan offset ditemukan sebelum `substr`.

Root bukti: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-upgrade13-20261015-033109/`. Log `guard-unit.log`, `mysql-feature-guard-fix.log`, `mysql-full-guard-fix.log`, serta jurnal UTC `guard-fix-commands.jsonl`. Status target operasional setelah run: `ecommerce orders=0` (read-only count). Suite penuh hanya menggunakan DB disposable yang disetujui; seluruh write tes di-rollback. Review independen ulang `independent-review-guardfix.log`: tanpa blocker; reviewer hanya membaca kode, tidak menjalankan tes atau menulis database.

## Review kompatibilitas resmi

Sumber: https://laravel.com/docs/13.x/upgrade (dibaca saat sesi).

- CSRF: test menggunakan `PreventRequestForgery`, menggantikan alias `ValidateCsrfToken`. Tidak menghapus middleware atau perlindungan CSRF.
- Session: konfigurasi lama dipertahankan; fallback serialisasi PHP, bukan JSON. Migrasi JSON dapat membatalkan sesi aktif.
- Cache: nama prefix/cookie tidak diganti. Aplikasi meng-cache koleksi Eloquent; opsi `serializable_classes` belum ditambahkan. Perilaku lama dipertahankan, tetapi hardening allow-list/array masih pekerjaan lanjutan, bukan klaim keamanan penuh.
- Eloquent: Laravel 13 memulihkan eager-loaded relations ketika koleksi model diserialisasi. Suite SQLite dan home berkali-kali berjalan, tetapi tidak ada uji khusus semua job produksi.
- MySQL upsert: pencarian tidak menemukan pemanggilan `upsert(` pada app/config/routes/tests/bootstrap/database. Validasi uniqueBy baru tidak memerlukan patch pada jalur yang ditemukan.

## Backup, artefak, dan rollback

Root privat:
`/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-upgrade13-20261015-033109/`

- `source-checkpoint.tar.gz`, SHA256 `babfcd698434274299c1cfc31599016dae1d6297e6438991e77a8515dece8a7f`.
- `ecommerce-pre-migration.sql`, SHA256 `daddf970e1def068357e30fd09fce2bef8f4a781c8472296918191d3d92c2909`.
- `backup-verification.json`, `migration-verification.json`, `source-sha256.json`, `source-sha256-after.json`, `source-comparison.json`.
- `composer.json`, `composer.lock` baseline; `git-status.txt`, `git-diff.patch`.
- `playwright-synthetic-verification.json`, `admin-report-sample.pdf`, `home-8000-l13.png`, `home-l13.html`, `sqlite-final.log`.

Restore copy: `zafira_upgrade13_restore_20261015_0333`. Browser database: `zafira_upgrade13_synth_20261015_0410`. PHPUnit database: `zafira_upgrade13_phpunit_20261015_0420`. Database dan server lama port 8020 tidak ditimpa. Server sintetis 8031 dihentikan; server ecommerce 8000 tetap berjalan.

Rollback kode: hentikan hanya server sesi ini, simpan perubahan pascaupgrade, pulihkan file Composer baseline dan perubahan CSRF terkait secara selektif, lalu `composer install` menggunakan lock baseline. Jangan menimpa seluruh pekerjaan pengguna dengan checkout/reset.

Rollback data memerlukan persetujuan baru, backup kondisi terkini, dan rekonsiliasi data. Validasi dump di database baru sebelum cutover. **Restore tidak dijalankan pada ecommerce.** Jangan mengimpor dump baseline langsung ke database aktif atau menjalankan rollback destruktif otomatis.

### Keadaan akhir terverifikasi (2026-10-14T20:56Z UTC)

- Server: PID 67102, `/opt/homebrew/Cellar/php/8.5.11/bin/php -S 127.0.0.1:8000 .../server.php`, cwd `/Users/hanifpriyambodo/Documents/online-shop/public`, LISTEN 127.0.0.1:8000, `GET /` → 200.
- Driver efektif: cache `database`, session `database`, DB `mysql`/`ecommerce`.
- `ecommerce`: migrations=24, products=12 dengan total stock=0, orders=0.
- Manifest final: `final-manifest.sha256` (composer.json, composer.lock, tiga file tes, laporan ini).
- Verifikasi ulang checksum dump baseline `ecommerce-pre-migration.sql`: OK.
- Log tindak lanjut: `mysql-feature-final.log`, `sqlite-all-final.log`, `node-tests-final.log`, `node-build-final.log`, `composer-platform-final.log`, `composer-validate-final.log`, `independent-review-followup.log`, `followup-commands.jsonl`.

Dump tidak menyertakan routines/events: mysql.proc lokal usang, scheduler nonaktif, dan pemeriksaan menemukan nol routines/events/triggers/views. `mysql_upgrade` tidak dijalankan.

## Risiko dan checklist akhir

- [x] Identitas visual dan sumber storefront tidak ditulis ulang.
- [x] APP_KEY dan .env tidak diubah; ecommerce bukan SQLite.
- [x] Tidak ada pesanan tes pada ecommerce; pemeriksaan terakhir orders=0.
- [x] Stok tidak di-backfill pada ecommerce. Semua 12 produk belum tersedia sampai pemilik mengisi stok nyata.
- [x] Cancel sintetis menghasilkan status cancelled; pemeriksaan database setelah run menunjukkan stock produk 1 kembali 5. Tidak ada pembacaan stok antara checkout dan cancel dalam skrip browser.
- [x] Matcher SQL netral-driver; MySQL dari 4 gagal menjadi 2 gagal tanpa fresh.
- [ ] Semua tes MySQL hijau: 2 tes retry lock khusus SQLite tidak berlaku pada MySQL (lihat atas).
- [x] Review independen: tanpa blocker (dua sesi).
- [x] Floor PHP composer.json `^8.4.1` sesuai lock.
- [x] Hardening cache `serializable_classes`: selesai pada Putaran 2 dengan allow-list eksplisit (lihat bawah).
- [x] Test CSRF origin path: ditambah pada Putaran 2 (`RequestForgeryTest`).
- [x] Checkout/cancel melalui klik tombol UI diuji pada server sintetis 8031. Checkout menghasilkan pending, tombol `Batalkan pesanan` menghasilkan cancelled, dan stok produk 1 pulih dari 3 menjadi 5.
- [x] Admin login dan tombol `Unduh PDF` diuji melalui UI. Download nyata menghasilkan 7.988 byte dengan signature `%PDF-`.
- [x] Browser UI tidak menghasilkan page error; tidak ada pembayaran online atau mail nyata. Artefak tersanitasi: `ui-browser-results.json`, `ui-checkout-pending.png`, `ui-checkout-cancelled.png`, `ui-admin-report.png`, `ui-admin-report.pdf` di root bukti privat.
- [ ] Apache/PHP XAMPP 8.2.4 tidak didukung; gunakan CLI PHP ≥8.4.1.
- [ ] Timestamp dan exit code granular setiap perintah awal tidak lengkap.

`/katalog` bukan rute aplikasi dan menghasilkan 404 pada baseline; katalog yang diuji adalah `/koleksi`. Kelulusan smoke test lokal tidak menyatakan kesiapan pembayaran, email nyata, atau deployment produksi.

### Tindak lanjut browser UI asli — 2026-10-14T20:58:51Z–20:58:54Z UTC

Run sebelumnya (`test-browser.cjs`) memakai API request untuk menambah alamat, cart, checkout, cancel, dan PDF; tidak boleh ditafsirkan sebagai pembuktian klik UI untuk transaksi itu. Run tambahan (`ui-browser.cjs`, `ui-browser-results.json`) memakai Chromium headless pada server PHP terisolasi `127.0.0.1:8031`, dengan `DB_DATABASE=zafira_upgrade13_synth_20261015_0410`, cache `array`, session `file`, mail `array`, dan queue `sync`. Tidak mengakses pesanan atau stok `ecommerce`.

- Login pelanggan: form UI berhasil. Dari `/koleksi`, form produk ID 1 diklik untuk tambah tas; di `/belanja`, jumlah diubah menjadi 2 melalui input dan tombol `Ubah jumlah`. Alamat sintetis yang sudah ada dipilih otomatis; tidak dibuat alamat baru lewat API.
- Tombol `Buat pesanan pending`: respons POST `/checkout` 201, pesanan sintetis #3 berstatus `pending / belum dibayar` terlihat pada halaman. Stok produk ID 1 dibaca langsung dari DB terisolasi: 5 sebelum checkout → 3 setelah checkout.
- Tombol `Batalkan pesanan` pada pesanan #3 dan dialog konfirmasi browser: POST `/orders/3/cancel` 200, halaman menampilkan `Status: cancelled`; stok ID 1 pulih 3 → 5. Pemeriksaan database akhir: pesanan #1–#3 semuanya cancelled, stok ID 1 = 5.
- Login admin lewat form UI, tombol `Unduh PDF` di dashboard: browser menerima download GET `/admin/reports/orders.pdf?period=today` 200, signature `%PDF-`, ukuran 7.988 byte. Tidak ada `pageerror`; output ini membuktikan interaksi UI, bukan pembayaran atau email nyata.
- Bukti privat: `ui-browser-results.json` (ringkasan jaringan tanpa kredensial), `ui-checkout-pending.png`, `ui-checkout-cancelled.png`, `ui-admin-report.png` (alamat dan tabel admin dimask), `ui-admin-report.pdf`. PDF memuat data sintetis, jangan dipublikasikan tanpa pemeriksaan ulang. Tidak ada migrasi, reset database, edit sumber aplikasi, atau interaksi port 8000 dalam run ini.

## Putaran 2 — hardening dan verifikasi (2026-10-14T23:09Z UTC)

- Cache storefront tetap menyimpan Eloquent Collection karena view memakai metode model. `config/cache.php` membatasi kelas serialisasi ke Collection, Product, dan Category; kunci produk, kategori, serta related naik ke `v2`. `StorefrontCacheSerializationTest` menguji render cold/warm pada cache database, eager-load kategori, dan penolakan unserialisasi kelas di luar daftar. PDP sendiri membaca produk segar setiap request agar status stok tidak usang.
- `RequestForgeryTest` menjalankan middleware web asli dengan environment `local` untuk menonaktifkan bypass khusus testing. HTTP tanpa token dan HTTPS cross-site ditolak (419); token valid dan HTTPS same-origin diterima. Ini bukan uji browser atau proxy produksi.
- `CheckoutService` mencoba ulang transaksi saat MySQL/MariaDB melaporkan SQLSTATE `40001` dengan kode driver `1213`; timeout `1205` tidak dicoba ulang. `MysqlCheckoutRetryTest` menguji satu kegagalan sementara, batas percobaan, replay idempotensi, pembatalan, dan ketiadaan penulisan parsial lewat fault injection. Deadlock dua koneksi PDO nyata **belum diuji**: `RefreshDatabase` memakai transaksi fixture tunggal.
- Guard database tes mendapat kasus target hilang dan target terlarang pada `TestDatabaseGuardTest`. Suite MySQL hanya dijalankan dengan `ZAFIRA_SAFE_MYSQL_TESTS=1` pada `zafira_upgrade13_phpunit_20261015_0420`; tidak ada run SQLite tambahan, perintah reset destruktif, transaksi pembayaran, maupun email nyata.
- Bukti privat di direktori `zafira-upgrade13-20261015-033109/`: `round2-commands.jsonl` dan log `*-round2*`. PHPUnit MySQL: **203 tes, 1424 assertion, 3 skipped, 0 gagal**. Skip: satu `ComposerHooksTest` membutuhkan launcher `AMADIS_ROOT`; dua tes lock SQLite pada `AmadisOrderTest` tidak berlaku untuk MySQL. `node --test tests/*.test.mjs`: **85 lulus**; `npm run build` dan `composer check-platform-reqs`: sukses. Semua empat perintah memiliki exit code 0 pada manifest tersebut.
- Pemeriksaan manual diff Putaran 2 tidak menemukan blocker pada cakupan yang ditinjau. **Review independen oleh agent `code-reviewer` belum dilakukan** karena tool subagent tidak tersedia pada sesi ini; jangan menafsirkan pemeriksaan manual sebagai penggantinya. Bukti eksekusi di atas mendahului penambahan bagian dokumentasi ini.

## Handoff round2 final — 2026-10-15

Bagian ini menggantikan angka status lama. Bagian riwayat tetap dipertahankan sebagai jejak audit.

### Perubahan tes terakhir

- `StorefrontCacheSerializationTest` tidak lagi mencari key dengan `LIKE`. Tes memakai key aktif `storefront:products:v3` dan `storefront:related:{cat}:{product}:v3`, ditambah prefix asli dari store cache `database`. Payload warm diperiksa sebagai `EloquentCollection`, `Product`, dan relasi `Category`.
- `sold-out-controls.test.mjs` memeriksa satu tombol katalog yang sama: `disabled` saat `stock <= 0`, label `sold_out`, dan label stok tersedia `set_one`.
- `StorefrontCacheTest` memeriksa key `v3` sudah hangat sebelum invalidasi kategori.
- Tidak ada perubahan fitur dalam tahap handoff ini.

### Hasil akhir

| Perintah | Exit | Hasil | Log |
|---|---:|---|---|
| `python3 run-final.py mysql-full-verbose` | 0 | 204 tes, 201 lulus, 3 skipped, 1.450 assertions | `mysql-full-final-skips.log` |
| PHPUnit terarah MySQL guarded (`MysqlCheckoutRetryTest`, dua tes cache) | 0 | 8 tes, 89 assertions; tiga tes retry MySQL lulus, tidak skip | `mysql-targeted-final-testdox.log` |
| `python3 run-final.py node` | 0 | 92/92 lulus | `node-tests-final2.log` |
| `python3 run-final.py build` | 0 | Vite 8.2.2 lulus | `npm-build-final2.log` |
| `python3 run-final.py platform` | 0 | PHP 8.5.11 memenuhi platform | `composer-platform-final2.log` |
| Browser main `final-main.cjs` | 0 | PASS: home, koleksi, PDP, login, `/belanja`, `/akun` | `final-main-results.json` |
| Browser synthetic `final-ui-browser.cjs` | 0 | PASS: login, add, quantity 2, pending, cancel, admin PDF | `final-ui-browser-results.json` |

Run PHPUnit dan browser memakai MariaDB. Runner final memakai `DB_DATABASE=zafira_upgrade13_phpunit_20261015_0420` dan `ZAFIRA_SAFE_MYSQL_TESTS=1`. Suite SQLite tidak dijalankan pada handoff final.

Skip MySQL:

1. `ComposerHooksTest::test_guard_accepts_the_launcher_root_and_fails_closed_on_a_moved_cache`: harus berjalan melalui `scripts/amadis/launch.sh test`.
2. `AmadisOrderTest::test_transient_sqlite_lock_is_retried_once_and_creates_single_order`: simulasi lock khusus SQLite.
3. `AmadisOrderTest::test_exhausted_sqlite_lock_returns_503_without_partial_state_and_key_stays_usable`: simulasi lock khusus SQLite.

### Bukti browser

Main `:8000` memakai database `ecommerce`. Hasil: 12 tombol home dan 8 tombol katalog dinonaktifkan. Quick view dan input jumlah juga dinonaktifkan. Tombol PDP dinonaktifkan dengan label `Stok habis`; JSON-LD memakai `OutOfStock`. Klik paksa tidak membuat POST. Console error, page error, request gagal, dan HTTP ≥400 berjumlah nol. `/akun` mengarah ke `/login` untuk tamu.

Synthetic `:8031` memakai `zafira_upgrade13_synth_20261015_0410`. UI membuat pesanan sintetis #5 lalu membatalkannya. Stok produk 1: 5 → 3 → 5. Admin mengunduh PDF 8.014 byte dengan signature `%PDF-`. Semua stok synthetic akhir bernilai 5. Tidak ada perubahan stok atau order operasional.

### Review independen

Reviewer read-only membaca semua file yang diminta, termasuk file untracked `tests/Feature/MysqlCheckoutRetryTest.php`. Log: `independent-review-final.log`. Reviewer mengonfirmasi fault 1213 terjadi setelah stok dan voucher berubah, tetapi sebelum row order. Reviewer juga mengonfirmasi exact key cache dan pemeriksaan tipe payload.

Reviewer memberi verdict `BLOCKER` karena tes dapat skip di SQLite default, serta menduga regex katalog gagal. Kedua temuan tidak dipertahankan setelah eksekusi. Run MySQL guarded menjalankan tiga tes retry tanpa skip. Regex memakai `[^\n]*`, sehingga atribut `aria-describedby` diterima, dan tes Node lulus. Review bersifat statis; reviewer tidak menjalankan tes.

### Batasan

- Fault deadlock bersifat sintetis. Tes tidak membuktikan deadlock nyata antara dua koneksi PDO.
- Tes origin HTTPS memakai request Laravel, bukan TLS live, browser HTTPS, atau proxy produksi.
- Smoke lokal bukan persetujuan deployment, pembayaran, atau email nyata.
