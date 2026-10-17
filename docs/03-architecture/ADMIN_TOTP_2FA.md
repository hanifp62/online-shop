# Admin TOTP 2FA — Desain, Bukti, dan Risiko

**Tanggal:** 2026-10-18  
**Status:** Terverifikasi lokal (SQLite `:memory:`). MySQL live **NOT_RUN**. Produksi **belum disetujui**.  
**ADR:** [ADR-011](ARCHITECTURE_DECISIONS.md#adr-011-totp-2fa-admin-bertahap-dengan-anti-replay-compare-and-swap)

## 1. Komponen

| Komponen | Peran |
|---|---|
| `database/migrations/2026_10_17_000001_add_totp_security_to_admins_table.php` | Kolom `totp_secret`, `totp_required_at`, `totp_last_used_at` + tabel `admin_totp_recovery_codes` |
| `app/Services/TotpService.php` | `verifyAndAdvance`, `compareAndSwapTimestamp`, `consumeRecoveryCode` |
| `app/Http/Middleware/EnsureAdminTotp.php` | Gerbang konsol (15 rute) |
| `app/Http/Controllers/AdminTotpController.php` | Challenge GET/POST `admin/totp`, POST `admin/totp/recovery` (rate limit 5/menit per admin\|ip) |
| `app/Console/Commands/AdminTotpProvision.php` | `admin:totp-provision`: membuat secret + 8 kode pemulihan, dicetak sekali |
| `app/Http/Controllers/AdminTotpEnrolController.php` | Enrol mandiri GET/POST `/admin/totp/enrol`, konfirmasi sekali `/admin/totp/enrol/recovery` |
| `app/Console/Commands/AdminTotpRequire.php` | `admin:totp-require --all`: mewajibkan TOTP bila seluruh admin telah memiliki secret |
| `tests/Feature/AdminTotpTest.php` | 46 test perilaku (EXIT=0, 308 assertion) |

Rute terukur setelah enrol: 22 `auth:admin`, terdiri dari 15 konsol bergate `EnsureAdminTotp` dan 7 tanpa gate (logout, tiga challenge, tiga enrol). Dua rute login memakai `guest:admin`. Hitungan ini berasal dari `php artisan route:list --json` (EXIT=0), bukan target angka tetap.

## 2. Kebijakan

### K6 — Satu perangkat terverifikasi terakhir
Verifikasi di perangkat B memajukan `totp_last_used_at`. Sesi perangkat A menyimpan cap lama, jadi middleware melempar A kembali ke challenge. Diuji: `verifying on device b throws device a back to challenge`.

### Kenapa `max(getTimestamp(), old + 1)` pada kode pemulihan
Kode pemulihan tidak membawa cap waktu TOTP. Penanda tetap harus naik agar sesi perangkat lain gugur (K6). `getTimestamp()` telanjang bisa **sama atau lebih kecil** dari nilai lama bila jam server mundur (clock skew/NTP) atau bila TOTP baru saja dipakai pada step yang sama. Akibatnya penanda tidak berubah dan perangkat lain tidak dilempar. `old + 1` menjamin monoton naik.

### Lock = defense-in-depth, bukan bukti keamanan
`lockForUpdate()` ada, tetapi SQLite mengabaikan `FOR UPDATE`. Mekanisme anti-replay yang sebenarnya adalah `UPDATE admins SET totp_last_used_at = :new WHERE id = :id AND totp_last_used_at = :old` (atomik per baris). Hanya pemanggil dengan `affected === 1` yang menang. Kode pemulihan memakai `delete() === 1` dengan prinsip yang sama.

Replay sekuensial ditolak lebih awal oleh `verifyKeyNewer` (alasan `invalid_code`). Cabang `replayed` hanya tercapai pada interleaving baca-lalu-tulis.

### K5 — Flag tanpa secret dialihkan ke enrol + `Log::critical`
Admin bertanda `totp_required_at` tanpa `totp_secret` tidak lagi lolos konsol. Middleware menulis `Log::critical` dan mengalihkan ke `admin.totp.enrol`. Kolom `totp_last_used_at` tetap bernama itu agar tidak mengubah skema eksisting; nilainya adalah *time-step* integer 30 detik dari `verifyKeyNewer`, bukan `DateTime`.

### Rotasi `APP_KEY`
`totp_secret` dienkripsi dengan `APP_KEY`. Bila kunci hilang atau dirotasi, secret tidak terdekripsi (`DecryptException` → `broken_secret`, `Log::critical`). Pemulihan: jalankan ulang `php artisan admin:totp-provision` untuk admin terdampak dan bagikan secret baru lewat kanal aman. Riwayat git memuat 2 commit yang menyentuh string `APP_KEY` (nilai tidak diperiksa/dicetak); rotasi kunci gratis hanya selama belum ada secret TOTP di produksi.

### Ganti kata sandi
Admin ber-TOTP: `totp_last_used_at` = null, kode pemulihan dihapus, `remember_token` dirotasi, sesi admin diakhiri. Sesi guard `web` (pelanggan) tetap hidup.

## 3. Bukti (2026-10-18)

| # | Bukti | Hasil |
|---|---|---|
| 1 | `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate --force` | EXIT=0, `2026_10_17_000001` DONE |
| 2 | Baca raw `DB::table` | `totp_secret` = `eyJpdiI6...` (payload terenkripsi, 256 char); `code_hash` = `$2y$12$...` (bcrypt, 60 char). Test `password reset and totp recovery tokens are not interchangeable` lulus (7 assertion; tabel terpisah) |
| 3 | Anti-lockout legacy | `legacy admin can enter console`, `legacy admin enters via real login without code page` lulus |
| 4 | Admin bertanda tanpa kode | `totp admin without second factor gets no console` lulus |
| 5 | Mutasi M4b (`user('admin')`→`user('web')`) | 9 test merah karena assertion (lihat §4) |
| 6 | Ganti kata sandi | `password change voids totp state session and remember token`, `admin password change does not kill web customer session` lulus |
| 7 | `grep "=== true"` jalur verifikasi (4 berkas) | Kosong (EXIT=1). `StorefrontController.php:105` di luar jalur, sah, tidak diubah |
| 8 | Log tanpa rahasia | `no secret password or code in logs` lulus (listener `MessageLogged`) |

Full suite terbaru setelah enrol (SQLite): **EXIT=0, 242 passed, 4 skipped, 1721 assertions**.

### Enrol mandiri — 11846b4c

- Secret pending dan URI disimpan terenkripsi dalam sesi; belum ada penulisan secret ke DB sebelum kode benar.
- Konfirmasi menyimpan secret terenkripsi, flag wajib TOTP, langkah terakhir, dan 8 hash bcrypt dalam satu transaksi.
- CAS `WHERE totp_secret IS NULL` mencegah request stale menimpa enrol yang sudah menang. Sesi dirotasi setelah kode benar.
- Kode cadangan disimpan terenkripsi dalam sesi dan diambil dengan `pull()` untuk tampilan sekali; respons memakai `Cache-Control: no-store`.
- Admin legacy tetap dapat login dan masuk konsol dengan banner enrol. Admin yang sudah memiliki secret tidak dapat enrol ulang.
- Mutasi penghapusan `whereNull('totp_secret')` membuat `test_stale_enrol_request_cannot_replace_an_existing_secret` merah (EXIT=1). Berkas dipulihkan, MD5 identik, test kembali hijau (EXIT=0).
- Ini membuktikan interleaving dengan model autentikasi stale, **bukan** uji konkurensi MySQL nyata.

### Batas verifikasi — ccb230ef

- Kode `+3` step ditolak sebagai `invalid_code`, dan `totp_last_used_at` tetap null. Mutasi window `1 → 4` membuat test ini merah.
- Nilai mentah DB `totp_last_used_at` sama dengan integer `verifyKeyNewer`. Mutasi `result + 1` membuat 7 test merah.
- Pesan lockout memuat sisa detik sebagai angka 1–60. Mutasi tanpa angka membuat 2 test merah.
- Setiap mutasi dipulihkan. Full suite setelah pemulihan: EXIT=0, 245 passed, 4 skipped, 1739 assertions.

### QR enrol — 3d7884f6

- QR dirender lokal sebagai SVG data URI dari URI provisioning yang sama. Secret tidak dikirim ke layanan QR eksternal.
- Test membandingkan data URI pada respons dengan hasil `qrDataUri()` untuk URI di sesi.
- Full suite setelah QR: EXIT=0, 246 passed, 4 skipped, 1743 assertions.

## 4. Mutasi

| ID | Mutasi | Merah | Revert |
|---|---|---|---|
| M1 | `!== false` → `=== true` | 9 | md5 identik, EXIT=0 |
| M2 | Hapus klausa `where` CAS | 2 | md5 identik, EXIT=0 |
| M3 | Hapus `if ($affected === 1)` | 1 (interleaving saja; replay sekuensial ditolak vendor — dibuktikan assert `reason === 'invalid_code'`) | md5 identik, EXIT=0 |
| M4b | Guard `admin` → `web` di middleware | 9 | md5 identik, EXIT=0 |
| M5 | Hapus cek `delete() !== 1` | 1 | md5 identik, EXIT=0 |

Nama test merah M4b: legacy admin can enter console; legacy admin enters via real login without code page; totp admin without second factor gets no console; full flow rotates session id twice and grants console; recovery code allows console entry anti lockout; flag without secret passes as legacy and alarms; verifying on device b throws device a back to challenge; password change voids totp state session and remember token; legacy password change keeps session and rotates remember token.

## 5. Baseline T1

Worktree bersih di HEAD `8bc5a9a` (tanpa perubahan 2FA): EXIT=1, 4 failed, 64 passed. Status: **baseline known-limp + 4 pre-existing** (contoh: `Task3RemediationTest.php:83` `assertSee('Admin Atelier')`). Satu percobaan, tidak diulang.

## 6. Higiene repo

- Dump `database/zafira_ecommerce_20260921_chat.sql` dan `_clean.sql` memuat DDL tabel `sessions` (0 baris INSERT). Keduanya dilepas dari index (`git rm --cached`) dan `.gitignore` diubah ke `database/zafira_ecommerce_20260921*.sql`. Riwayat git tidak dibersihkan.

## 7. Risiko terbuka

- **Konkurensi implementasi TIDAK TERBUKTI.** Harness paralel menyalin algoritme CAS, tidak memanggil `TotpService`. Test interleaving di proses tunggal membuktikan cabang `replayed`, bukan race nyata.
- **Sesi MySQL live tidak terverifikasi.** Pengujian aplikasi memakai SQLite `:memory:`; harness paralel memakai berkas SQLite sementara. Perilaku `FOR UPDATE` dan isolasi InnoDB NOT_RUN.
- Percobaan skrip bukti awal tanpa override DB mencoba MySQL lokal dan gagal `Connection refused`. Percobaan berikutnya memakai SQLite `:memory:`. Tidak ada bukti query berhasil pada percobaan awal.
- **Tracking BELUM:** `/workspace/tasks.json` dan direktori `/workspace` tidak tersedia di lingkungan ini. Pencarian repo dan Documents tidak menemukan `tasks.json`. Status `3c485b5d` tidak diubah; task berikutnya belum dimulai.
- Belum ada reissue kode pemulihan; admin yang kehabisan kode bergantung pada `admin:totp-provision` oleh operator.
- Cakupan M3 tipis (1 test).
- Commit 2FA tersedia lokal dan belum di-push.
