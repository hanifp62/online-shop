# Panduan Perintah Cepat (Command Cheatsheet) — ZAFIRA

Dokumen ini berisi kumpulan perintah terminal PowerShell sehari-hari untuk menjalankan, mengecek, me-*refresh*, dan menguji proyek e-commerce **ZAFIRA** di Windows agar tidak perlu menghafal perintah satu per satu.

> **Tips Cepat di VS Code / Windows:**
> - Tekan **`Ctrl + Shift + V`** saat membuka file ini di VS Code untuk tampilan pratinjau yang rapi.
> - Anda tidak perlu mengetik perintah panjang: cukup ketik **`.\zafira.cmd`** di terminal (atau klik dua kali file `zafira.cmd` di File Explorer) untuk membuka **Menu Angka Interaktif `[1]`–`[9]`**!

---

## 1. Menjalankan Server Website Lokal

Selalu gunakan alamat **`http://127.0.0.1:8000`** (bukan `localhost:8000`) agar cocok persis dengan konfigurasi Google OAuth Redirect URI dan cookie sesi.

```powershell
# Menjalankan server utama ZAFIRA di port 8000
php artisan serve --host=127.0.0.1 --port=8000
```

Jika Anda baru saja mengubah kode tampilan CSS atau komponen React (`.jsx` / `.css`), jalankan build aset frontend terlebih dahulu:

```powershell
# Mem-build ulang tampilan CSS & React ke folder public/build
npm run build
```

*(Atau gunakan mode live-reload Vite saat sedang aktif mengedit desain UI — jangan lupa matikan dengan `Ctrl + C` lalu jalankan `npm run build` setelah selesai):*

```powershell
npm run dev
```

---

## 2. Mengecek Siapa yang Sudah Login & Status Google OAuth

Untuk melihat daftar akun terdaftar, metode masuk (`Google` / `Password`), serta siapa yang **sedang aktif login saat ini**:

```powershell
# Menampilkan semua pengguna beserta metode login & status sesi (AKTIF / Offline)
php artisan auth:users
```

```powershell
# Menampilkan khusus pengguna yang login / tertaut dengan akun Google
php artisan auth:users --google
```

Contoh tampilan output di terminal:

```text
+----+---------------+--------------------+--------------+----------------------------+------------------+
| ID | Nama          | Email              | Metode Login | Status Sesi                | Terdaftar        |
+----+---------------+--------------------+--------------+----------------------------+------------------+
| 2  | Klien ZAFIRA  | client@example.com | Google       | AKTIF · baru saja          | 2026-11-14 01:14 |
+----+---------------+--------------------+--------------+----------------------------+------------------+
```

---

## 2B. Cara Login Admin Atelier (`http://127.0.0.1:8000/admin/login`)

Demi keamanan (*Zero Hardcoded Password* pada `AdminSeeder`), akun bawaan **`admin@gmail.com`** dibuat dengan kata sandi acak 32 karakter saat *seeding* awal sehingga tidak ada password bawaan statis seperti `admin123`.

Untuk melihat daftar akun admin dan mengatur password pilihan Anda sendiri (ketikan tersembunyi & di-hash Bcrypt):

```powershell
# 1. Lihat daftar akun Admin Atelier yang terdaftar
php artisan auth:admins

# 2. Atur / reset password untuk admin@gmail.com (ketik password baru Anda saat diminta)
php artisan admin:password
```

Setelah menjalankan `php artisan admin:password`, buka **`http://127.0.0.1:8000/admin/login`** lalu masuk menggunakan:
- **Email:** `admin@gmail.com`
- **Password:** *(password yang baru saja Anda buat di terminal)*

*(Jika Anda ingin membuat akun admin baru yang langsung dikunci dengan 2FA Google Authenticator / TOTP, gunakan `php artisan admin:totp-provision emailbaru@domain.com`.)*

---

## 3. Mengatasi Tampilan Tidak Berubah atau `.env` Baru Diubah

Jika Anda baru mengedit file `.env` (misalnya mengganti API Key Gemini atau Google OAuth) atau merasa tampilan web masih memuat versi lama:

```powershell
# 1. Bersihkan cache konfigurasi dan cache tampilan Blade
php artisan config:clear
php artisan view:clear

# 2. Build ulang aset CSS & React
npm run build
```

Lalu di browser Chrome/Edge, tekan **`Ctrl + Shift + R`** (*Hard Refresh*) pada halaman `http://127.0.0.1:8000`.

---

## 4. Operasi Database & Backup yang Aman

Database lokal tersimpan di `database/database.sqlite` (bisa dibuka secara visual di VS Code menggunakan ekstensi **SQLite Viewer**).

```powershell
# Mengecek status migrasi tabel database (aman, tanpa menghapus data)
php artisan migrate:status

# Menjalankan migrasi tabel baru saja (aman untuk data lama)
php artisan migrate

# Membuat cadangan (backup) database SQLite sekaligus verifikasi otomatis
php artisan db:backup --verify

# Memangkas keranjang belanja tamu (guest cart) yang sudah kedaluwarsa
php artisan carts:prune
```

> **PERINGATAN PENTING:** Jangan pernah menjalankan `php artisan migrate:fresh` atau `php artisan db:wipe` kecuali Anda benar-benar ingin menghapus seluruh akun, produk, dan pesanan di database lokal.

---

## 5. Menjalankan Pengujian Otomatis (Testing)

```powershell
# Menjalankan seluruh pengujian komponen frontend React (Vitest)
npm run test:react

# Menjalankan seluruh pengujian backend Laravel (PHPUnit)
php artisan test

# Menjalankan pengujian spesifik (contoh: hanya tes Login Google)
php artisan test tests/Feature/SocialLoginTest.php
```

---

## 6. Daftar Alamat (URL) Penting Lokal

| Halaman | Alamat URL (Wajib `127.0.0.1:8000`) | Keterangan |
| :--- | :--- | :--- |
| **Beranda Utama** | `http://127.0.0.1:8000` | Halaman depan storefront ZAFIRA + AI Concierge |
| **Katalog Koleksi** | `http://127.0.0.1:8000/koleksi` | Eksplorasi katalog lengkap, filter, dan pencarian |
| **Keranjang & Checkout** | `http://127.0.0.1:8000/belanja` | Keranjang belanja & *Lazy Authenticator* saat checkout |
| **Login Pelanggan** | `http://127.0.0.1:8000/login` | Login email/sandi & tombol **Lanjutkan dengan Google** |
| **Registrasi Pelanggan** | `http://127.0.0.1:8000/register` | Daftar akun baru atau daftar cepat via Google |
| **Profil & Akun Saya** | `http://127.0.0.1:8000/akun` | Detail akun login, tautan Google, dan 2FA opsional |
| **Login Admin Atelier** | `http://127.0.0.1:8000/admin/login` | Pintu masuk pengelola / admin ZAFIRA |
| **Dashboard Admin** | `http://127.0.0.1:8000/admin` | Manajemen pesanan, katalog, laporan MSI, & stok |
| **Audit Keamanan Auth** | `http://127.0.0.1:8000/admin/auth-events` | Log audit riwayat login & rotasi sesi pengguna |
