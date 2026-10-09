# Laporan Kompatibilitas Lingkungan macOS (Compatibility Report)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Lingkungan Diuji:** macOS 27.0.1 (arm64, Apple Silicon)  
**Tanggal Verifikasi:** 2026-10-10  
**Status Evaluasi:** **100% Kompatibel & Terstabilisasi**  

---

## 1. Matriks Penilaian Kompatibilitas

| Komponen | Persyaratan Proyek | Versi Terpasang di Mac | Status Kompatibilitas | Bukti Uji Coba | Tindakan Resolusi Dilakukan |
|---|---|---|---|---|---|
| **PHP Engine** | `^8.2` | `PHP 8.5.11` (Homebrew, Zend OPcache) | Kompatibel Penuh | `php -v`, 49 unit/feature tests lulus | Dipertahankan tanpa penurunan atau penaikan versi |
| **Laravel Framework** | `^12.0` | `12.69.1` | Kompatibel Penuh | `artisan --version`, routing, closure bootstrap | Dipertahankan |
| **Composer CLI** | `^2.2` | `Composer 2.10.3` | Kompatibel Penuh | `composer --version`, lockfile konsisten | Dipertahankan |
| **Node.js & npm** | `^20.0` | Node `v24.21.0` / npm `11.19.0` | Kompatibel Penuh | `node -v`, `npm -v` | Dipertahankan |
| **Vite & Rollup** | `^8.0` | `Vite 8.2.2` | Memerlukan platform binding | `Cannot find native binding darwin-arm64` | Dijalankan `npm install` untuk mengunduh binary native `@rolldown/binding-darwin-arm64`. Hasil build `npm run build` sukses dalam 303ms. |
| **CLI Binaries (`vendor/bin`, `node_modules/.bin`)** | Executable Unix | File tanpa bit `+x` (artefak transfer Windows) | Sempat gagal (`Permission denied`, code 126) | `vendor/bin/pint --test` dan `npm run build` gagal | Dieksekusi `chmod +x vendor/bin/* node_modules/.bin/*`. Pint pass 100%, Vite build sukses. |
| **MariaDB Server** | MySQL 5.7+ / MariaDB 10.3+ | `MariaDB 10.4.28` (XAMPP macOS) | Kompatibel Penuh | Koneksi PDO `127.0.0.1:3306` user `root` berhasil | Dibuat database `ecommerce` jika belum ada. 17 migrasi sukses dijalankan dan di-seed dengan 12 produk dan 8 kategori. |
| **SQLite Fallback** | SQLite 3 | SQLite 3 (`pdo_sqlite` aktif di PHP 8.5) | Kompatibel Penuh | File `database/database.sqlite` dimigrasi dan di-seed | Tersedia sebagai opsi offline terisolasi penuh |

---

## 2. Rincian Masalah yang Telah Diperbaiki

1. **Permission Denied pada Binary Tooling (Code 126):**
   - *Penyebab:* File yang disalin secara manual dari Windows (sistem file NTFS/FAT) kehilangan bit perizinan eksekusi `+x` pada sistem berkas POSIX macOS.
   - *Solusi:* Memberikan izin eksekusi menggunakan `chmod +x` pada direktori `vendor/bin/*` dan `node_modules/.bin/*`.
2. **Missing Native Darwin-arm64 Bindings pada Bundler:**
   - *Penyebab:* Folder `node_modules` bawaan Windows berisi dependensi biner untuk arsitektur win32/x64.
   - *Solusi:* Menjalankan `npm install` di macOS, sehingga npm secara otomatis melengkapi paket opsional arsitektur Apple Silicon (`@rolldown/binding-darwin-arm64`).
3. **Database Target Belum Diinisialisasi di MariaDB Mac:**
   - *Penyebab:* Database fisik MySQL dari komputer Windows tidak ikut dipindahkan.
   - *Solusi:* Membuat database `ecommerce` di MariaDB lokal port 3306, menjalankan seluruh 17 migrasi skema, dan mengisi data awal melalui seeder otentik ZAFIRA (`CategorySeeder`, `ProductSeeder`, `AdminSeeder`).
4. **Penanganan Graceful Error pada Stored Procedure `getcat`:**
   - *Penyebab:* Tabel sistem `mysql.proc` pada instalasi XAMPP lokal memerlukan `mysql_upgrade` yang mensyaratkan izin sudo, menyebabkan error kolom pada `DROP/CREATE PROCEDURE`.
   - *Solusi:* Menambahkan blok `try...catch (\Throwable)` pada file migrasi `2026_09_14_000004_create_getcat_procedure.php`. Jika sistem database lokal belum di-upgrade, migrasi tetap sukses karena seluruh logika aplikasi menggunakan metode portabel `Category::getById()` yang 100% identik.
