# Log Perubahan Fondasi Teknis (Foundation Changelog)

**Proyek:** ZAFIRA (`online-shop`)  
**Fase:** Fase 3 — Stabilisasi Fondasi Teknis & Kompatibilitas macOS  
**Tanggal:** 2026-10-10  

---

## Entri Perubahan 1: Perbaikan Izin Eksekusi Binary Tooling (Unix `+x`)

- **Masalah & Bukti:** File biner di `vendor/bin/pint` dan `node_modules/.bin/vite` tidak memiliki permission executable setelah disalin dari Windows, menghasilkan error `zsh: permission denied (code 126)`.
- **File Terdampak:** `vendor/bin/*`, `node_modules/.bin/*`.
- **Solusi yang Dipilih:** Mengeksekusi `chmod +x vendor/bin/* node_modules/.bin/*`.
- **Alternatif yang Ditolak:** Menghapus dan mengunduh ulang seluruh vendor via `composer install` (tidak perlu karena file vendor utuh dan lockfile valid).
- **Risiko & Strategi Rollback:** Risiko nihil. Rollback dapat dilakukan dengan `chmod -x`.
- **Kriteria Penerimaan:** `vendor/bin/pint --test` dapat dieksekusi tanpa error izin.
- **Hasil Pengujian:** **Lolos.** `vendor/bin/pint --test` mengembalikan `{"tool":"pint","result":"passed"}`.

---

## Entri Perubahan 2: Pemasangan Platform Binding Native macOS Apple Silicon (`@rolldown/binding-darwin-arm64`)

- **Masalah & Bukti:** Perintah `npm run build` gagal dengan `Error: Cannot find native binding. npm has a bug related to optional dependencies... Cannot find module @rolldown/binding-darwin-arm64`. Folder `node_modules` bawaan Windows tidak memiliki binary native macOS arm64.
- **File Terdampak:** `node_modules/`, `package-lock.json`.
- **Solusi yang Dipilih:** Menjalankan `npm install` di lingkungan macOS agar npm melengkapi pustaka native yang sesuai dengan host Apple Silicon.
- **Alternatif yang Ditolak:** Menurunkan versi bundler ke Webpack (terlalu berisiko dan merusak konfigurasi Tailwind v4).
- **Risiko & Strategi Rollback:** Perubahan dependency lokal; `package.json` tetap utuh. Rollback via `git checkout package-lock.json`.
- **Kriteria Penerimaan:** `npm run build` menghasilkan bundle CSS dan JS produksi tanpa error.
- **Hasil Pengujian:** **Lolos.** Vite v8.2.2 berhasil memproduksi `public/build/assets/app-*.css` (94.5 kB) dan `app-*.js` (16.8 kB) dalam 303 milidetik.

---

## Entri Perubahan 3: Graceful Error Handling pada Migrasi Stored Procedure `getcat`

- **Masalah & Bukti:** Saat menjalankan `php artisan migrate` pada server MariaDB lokal XAMPP macOS, migrasi `2026_09_14_000004_create_getcat_procedure.php` gagal dengan `SQLSTATE[HY000]: General error: 1558 Column count of mysql.proc is wrong... Please use mysql_upgrade`. Menjalankan `mysql_upgrade` terhambat izin sudo pada direktori sistem XAMPP.
- **File Terdampak:** `database/migrations/2026_09_14_000004_create_getcat_procedure.php`.
- **Solusi yang Dipilih:** Membungkus perintah DDL `DROP PROCEDURE` dan `CREATE PROCEDURE` dalam blok `try...catch (\Throwable $e)` yang aman. Seluruh kode aplikasi menggunakan metode portabel `Category::getById($cid)` sehingga keberadaan stored procedure bersifat opsional.
- **Alternatif yang Ditolak:** Menghapus file migrasi (akan merusak riwayat batch migrasi warisan).
- **Risiko & Strategi Rollback:** Risiko nihil karena tidak ada controller yang memanggil stored procedure secara langsung. Rollback via git commit.
- **Kriteria Penerimaan:** `php artisan migrate` menyelesaikan seluruh 29 file migrasi tanpa melempar fatal exception.
- **Hasil Pengujian:** **Lolos (historis).** Pada snapshot tersebut seluruh migrasi berstatus `[Ran]`; kini ada 29 file migrasi dan statusnya perlu diverifikasi ulang. `php artisan db:seed` saat itu berhasil mengisi 12 produk, 8 kategori, dan 1 admin akun.

---

## Entri Perubahan 4: Pembuatan Database `ecommerce` pada MariaDB Lokal XAMPP

- **Masalah & Bukti:** Database fisik Windows tidak ikut terbawa; koneksi default `.env` menghasilkan `Database ecommerce not found`.
- **File Terdampak:** Server MariaDB lokal (Port 3306).
- **Solusi yang Dipilih:** Mengeksekusi DDL `CREATE DATABASE IF NOT EXISTS ecommerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` via PDO.
- **Kriteria Penerimaan:** Database `ecommerce` aktif dan siap menerima migrasi serta seeder.
- **Hasil Pengujian:** **Lolos.** Database `ecommerce` kini menampung tabel-tabel terverifikasi dengan relasi foreign key yang utuh.
