# Baseline Lingkungan & Status Migrasi (Environment Baseline)

**Proyek:** ZAFIRA (`online-shop`)  
**Lokasi Direktori:** `/Users/hanifpriyambodo/Documents/online-shop`  
**Tanggal Pengujian:** 2026-10-10  
**Status Audit:** Terverifikasi via Antigravity CLI & Subagent Inspection

---

## 1. Spesifikasi Hardware & Sistem Operasi

| Komponen | Spesifikasi Terdeteksi | Bukti Perintah |
|---|---|---|
| **Sistem Operasi** | macOS 27.0.1 (Build 26A434) | `sw_vers` |
| **Arsitektur CPU** | Apple Silicon (`arm64`) | `uname -m` |
| **User & Direktori Kerja** | `hanifpriyambodo` di `/Users/hanifpriyambodo/Documents/online-shop` | `pwd` |
| **Version Control** | Awalnya tidak ada; kini diinisialisasi Git lokal (`.git`) dengan baseline commit | `git status` |

---

## 2. Runtime & Tooling Stack

| Software | Versi Terdeteksi | Lokasi Binary | Status Kompatibilitas |
|---|---|---|---|
| **PHP (CLI)** | `PHP 8.5.11 (cli) (built: Sep 22 2026)` dengan Zend OPcache v8.5.11 | `/opt/homebrew/bin/php` | Kompatibel dengan Laravel ^13.0 (lock v13.36.0); 49 test passing adalah angka historis |
| **Composer** | `Composer 2.10.3 (2026-08-27)` | `/opt/homebrew/bin/composer` | Kompatibel |
| **Laravel Framework** | `Laravel ^13.0` (lock `v13.36.0`) | `artisan` / `composer.lock` | Framework aktif |
| **Node.js** | `v24.21.0` | `/opt/homebrew/opt/node@24/bin/node` | Kompatibel dengan Vite 8 & Tailwind v4 |
| **npm** | `11.19.0` | `/opt/homebrew/opt/node@24/bin/npm` | Kompatibel |
| **Frontend Bundler** | `Vite 8.2.2` + `@tailwindcss/vite 4.0.0` | `node_modules/.bin/vite` | Membutuhkan bit eksekusi `chmod +x` |

---

## 3. Ekstensi PHP Terpasang

Berdasarkan output `php -m`:
- **Database:** `pdo_mysql`, `pdo_sqlite`, `mysqli`, `sqlite3`
- **String & Internationalization:** `mbstring`, `intl`, `iconv`
- **Grafis & Media:** `gd`, `exif`
- **Keamanan & Kriptografi:** `openssl`, `sodium`, `hash`
- **Kompresi & Parser:** `zip`, `zlib`, `bz2`, `xml`, `SimpleXML`, `dom`
- **Performa:** `Zend OPcache`

*Catatan:* Semua ekstensi fundamental yang disyaratkan oleh Laravel 13 telah aktif secara native di lingkungan Homebrew PHP 8.5.11.

---

## 4. Status Database

1. **MySQL / MariaDB Service:**
   - Server MariaDB versi `10.4.28` terdeteksi di `/Applications/XAMPP/xamppfiles/` dan sedang berjalan pada port TCP `3306` (socket: `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`).
   - Akses via PHP PDO `mysql:host=127.0.0.1;port=3306;user=root` berhasil terhubung tanpa sandi.
   - Database `ecommerce` belum ada di instance MariaDB lokal tersebut (database yang ada: `information_schema`, `mysql`, `performance_schema`, `phpmyadmin`, `test`).
2. **SQLite Database:**
   - File `database/database.sqlite` (94 KB) telah tersedia di workspace.
   - PHPUnit test suite menggunakan SQLite in-memory (`:memory:`) dan berhasil menjalankan 49 feature/unit tests.
3. **Artefak SQL:**
   - Tersedia dump skema dan data bersih: `database/zafira_ecommerce_20260921_clean.sql` (28.7 KB), `database/zafira_ecommerce_20260921.sql` (131 KB), dan seeder PHP lengkap (`CategorySeeder`, `ProductSeeder`, `AdminSeeder`).

---

## 5. Isu Kritis Migrasi Windows → macOS

1. **Kehilangan Bit Eksekusi Unix (`Permission Denied`):**
   - File binary di `vendor/bin/*` dan `node_modules/.bin/*` tidak dapat dieksekusi secara langsung.
   - Solusi: Eksekusi `chmod +x vendor/bin/* node_modules/.bin/*` pada Fase 3.
2. **Koneksi Database `.env` Default:**
   - Menjalankan `php artisan migrate:status` gagal karena database `ecommerce` belum dibuat pada server MariaDB lokal.
   - Solusi: Membuat database `ecommerce` di MariaDB lokal atau mengarahkan ke SQLite lokal untuk mode isolasi, tanpa modifikasi destruktif.
3. **Penyimpanan Kode Tanpa Kontrol Versi:**
   - Proyek sebelumnya tidak memiliki folder `.git`.
   - Solusi: Telah diinisialisasi Git lokal dan commit baseline telah diamankan pada Fase 0.
