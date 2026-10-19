# Audit akhir database read-only — 11 Oktober 2026

Lingkup: baca saja. Tidak ada tulis ke `ecommerce` atau `zafira_restore_test_20261011`. Tidak ada `mysql_upgrade`, migrasi, atau upgrade. Nilai baris, PII, hash kata sandi, dan rahasia tidak ditampilkan. Hanya jumlah yang dicatat.

Klien: `/Applications/XAMPP/xamppfiles/bin/mysql -uroot -S /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock` (root lokal tanpa kata sandi; tidak ada file `.env` dibuka).

## Perintah dan status

| # | Perintah (ringkas) | Exit | Hasil |
|---|---|---|---|
| 1 | `SELECT VERSION()` | 0 | `10.4.28-MariaDB` |
| 2 | `information_schema.schemata` untuk DB uji | 0 | `zafira_restore_test_20261011` ada |
| 3 | Hitung tabel/indeks/FK/kolom (`information_schema`) | 0 | lihat tabel Skema |
| 4 | Query orphan pertama | 1 | `ERROR 1054`, nama kolom keliru (`category_id`). Query diperbaiki ke `product_cat`/`cat_id` (percobaan 2) |
| 5 | Orphan, jumlah baris, migrasi (dibungkus `SET SESSION TRANSACTION READ ONLY`) | 0 | lihat bawah |
| 6 | `information_schema.routines` | 1 | `ERROR 1558`: `mysql.proc` 20 kolom, server butuh 21. Tidak diperbaiki (butuh `mysql_upgrade` pada server bersama) |
| 7 | Perbandingan per-PK per-kolom (`<=> BINARY`), hanya hitungan | 0 | lihat bawah |
| 8 | Fingerprint `mysqldump ecommerce` (data saja, urut PK) sebelum/sesudah, SHA-256 di memori, tidak dicetak | 0 | **sama** di tiga putaran |

Skrip dijalankan via `python3` dengan heredoc; stdout error ditekan supaya tidak membocorkan isi.

## Skema `ecommerce` vs DB uji

| Pemeriksaan | ecommerce | uji | Selisih |
|---|---|---|---|
| Tabel BASE | 15 | 15 | tabel hanya di satu sisi: 0 / 0 |
| Baris indeks (`statistics`) | 28 | 28 | definisi indeks berbeda: 0 |
| Foreign key | 3 | 3 | sama: `products.product_cat→categories.cat_id`, `concierge_requests.user_id→users.id`, `user_info_backups.user_id→users.id` |
| Kolom | 110 | 110 | kolom hanya di satu sisi: 0 / 0; definisi kolom (tipe, null, default, extra, charset, collation) berbeda: 0 |
| Primary key | — | — | PK berbeda: 0 |

Kesimpulan: skema identik.

## Migrasi (SQL read-only, setara `migrate:status`)

| | ecommerce | uji |
|---|---|---|
| Baris `migrations` | 17 | 17 |
| File di `database/migrations` | 17 | 17 |
| Pending (file tanpa baris) | 0 | 0 |
| Baris tanpa file | 0 | 0 |
| Batch maksimum | 2 | 3 |

Nama migrasi sama di kedua DB. Satu nama berpindah `id`, dan batch beda di 3 baris (urutan restore/migrate berbeda), tidak ada migrasi hilang.

## Orphan FK

Semua 0 di kedua DB: produk tanpa kategori, concierge tanpa user, backup tanpa user.

## Jumlah baris

| Tabel | ecommerce | uji |
|---|---|---|
| admins | 1 | 2 |
| users | 1 | 2 |
| user_info_backups | 0 | 1 |
| categories | 8 | 8 |
| products | 12 | 12 |
| newsletter_subscribers | 1 | 2 |
| concierge_requests | 0 | 1 |
| jobs | 0 | 2 |
| failed_jobs | 0 | 0 |
| migrations | 17 | 17 |
| sessions | 73 | 39 |
| cache | 10 | 16 |

Catatan: `restore-validation.md` mencatat users 1/1 dan admins 1/1. Sekarang DB uji punya admins 2, users 2, user_info_backups 1. DB uji berubah sesudah validasi itu, oleh proses lain (bukan audit ini). Sumber perubahan belum diverifikasi. Newsletter 2, concierge 1, jobs 2 cocok dengan uji POST yang tercatat.

## Selisih isi per kolom (baris dengan PK sama; hanya hitungan)

| Tabel | Hanya ecommerce | Hanya uji | Kolom berbeda (jumlah baris) |
|---|---|---|---|
| admins | 0 | 1 | password 1, remember_token 1, created_at 1, updated_at 1 |
| users | 0 | 1 | first_name, last_name, mobile, address1, address2, email_verified_at, password, remember_token, created_at, updated_at: masing-masing 1 |
| categories | 1 | 1 | cat_title 1, created_at 7, updated_at 7 |
| products | 0 | 0 | product_cat 4, product_title 8, product_description 6, product_origin 12, product_care 2, product_craft 2, created_at 12, updated_at 12 |
| newsletter_subscribers | 0 | 1 | created_at 1, updated_at 1 |
| concierge_requests | 0 | 1 | — |
| user_info_backups | 0 | 1 | — |
| jobs | 0 | 2 | — |
| migrations | 1 | 1 | migration 1, batch 3 |
| sessions | 73 | 39 | — (data volatil) |
| cache | 1 | 7 | value 8, expiration 9 (volatil) |
| cache_locks, failed_jobs, job_batches, password_reset_tokens | 0 | 0 | — |

Kesimpulan: dump 2026-09-21 bukan salinan identik DB aktif. Katalog produk sama jumlahnya, tapi konten beda (judul, deskripsi, asal, kategori 4 produk). Satu kategori ganti `cat_id`. Kredensial admin/user di DB uji beda dari aktif. Nilai sengaja tidak dicetak.

## Prosedur `getcat`

Grep `getcat` di `app/`, `routes/`, `database/` (dump `.sql` dikecualikan):
- `app/Models/Category.php`: hanya komentar. `Category::getById()` = Eloquent `where('cat_id', …)->first()`.
- `database/migrations/2026_09_14_000004_create_getcat_procedure.php`: membuat prosedur di MySQL/MariaDB, menelan error (`try/catch Throwable`).
- `routes/`: 0 hasil. Tidak ada `CALL getcat` di kode PHP.
- Pemakai `getById`: `AdminProductController.php:80` dan `tests/Feature/LegacyPatchTest.php`.

Dampak: aplikasi tidak memanggil prosedur. Gagalnya `getcat` (ERROR 1558) tidak memengaruhi runtime. Risiko: migrasi tercatat `Ran` walau prosedur tidak dibuat. Orang yang mengandalkan `CALL getcat` lewat SQL langsung akan gagal. `information_schema.routines` juga gagal dibaca sampai `mysql.proc` di-upgrade. Perbaikan butuh `mysql_upgrade` terencana (di luar lingkup, butuh persetujuan).

## Batas

- Tidak menjalankan `php artisan migrate:status` (bisa membuka `.env`); diganti SQL langsung.
- Tidak membuka `.env`, tidak mencetak nilai baris, hash, atau token.
- Fingerprint hanya untuk `ecommerce`; DB uji tidak di-fingerprint.
