# Kerangka Navigasi dan Jejak Konteks Admin — Design Spec

**Tanggal:** 2026-10-18  
**Task:** `baseline-codebase/navigasi-jejak-konteks/tetapkan-informasi-arsitektur-kerangka-navigasi-ko`  
**Requirement:** R-42  
**Tujuan:** Memberi konsol admin satu kerangka navigasi konsisten dan konteks halaman yang jelas tanpa mengubah kontrak endpoint atau perilaku operasi yang sudah ada.

## 1. Fakta Kode yang Terverifikasi

- `admin.dashboard` merender `resources/views/admin/dashboard.blade.php`; halaman ini sudah memiliki navigasi anchor ke delapan panel dalam satu halaman.
- `admin.auth-events.index` merender `resources/views/admin/auth-events.blade.php`; halaman ini menyediakan filter dan paginasi, tetapi memakai dokumen HTML mandiri.
- Kedua halaman berada di belakang `auth:admin` dan `EnsureAdminTotp`.
- Endpoint pesanan (`/admin/orders`) merespons JSON dan belum memiliki halaman Blade. Task ini tidak mengubah response tersebut.
- Halaman login dan enrolment TOTP merupakan alur autentikasi, bukan bagian dari shell kerja admin.

## 2. Keputusan Desain

Buat layout Blade bersama `resources/views/admin/layouts/app.blade.php` khusus halaman kerja admin yang sudah memiliki view: dashboard dan log autentikasi. Layout memuat skip link, navigasi utama, header identitas/operator, breadcrumb, judul halaman, serta slot konten. Navigasi dashboard menautkan ke panel yang tersedia; tautan ke halaman log autentikasi tetap memakai named route. Tandai lokasi aktif secara semantik, bukan hanya warna.

Dashboard tetap satu halaman dan mempertahankan panel, form, route, query, serta aksi saat ini. Halaman log autentikasi menggunakan shell yang sama, mempertahankan filter dan paginator, dan menunjukkan filter aktif sebagai konteks halaman. Jangan menampilkan pencarian global, notifikasi, atau menu yang belum memiliki fungsi nyata.

Layout memakai Blade slots/sections yang sudah didukung Laravel, tanpa dependency baru dan tanpa perubahan database. CSS shell dibatasi pada layout admin dan responsif; gunakan token yang tersedia bila kompatibel, tanpa mengubah identitas storefront.

## 3. Batas dan Perilaku

- Tidak mengubah route, middleware, guard, TOTP, controller JSON, model, atau skema.
- Tidak memecah dashboard menjadi halaman terpisah dalam task ini.
- Tidak menambah menu untuk pelanggan, ulasan, atau pengaturan yang belum memiliki halaman admin nyata.
- Tidak menambah state filter ke session atau browser storage.
- Tetap escape data dinamis melalui Blade; jangan menampilkan data rahasia autentikasi.

## 4. Verifikasi dan Kriteria Penerimaan

1. Admin yang lolos TOTP dapat membuka dashboard dan log autentikasi; pengguna tanpa autentikasi/TOTP tetap ditolak atau diarahkan oleh middleware yang ada.
2. Kedua halaman memakai struktur shell bersama dan menampilkan lokasi halaman aktif serta breadcrumb/judul yang relevan.
3. Skip link keyboard mencapai landmark konten utama; navigasi memiliki label aksesibel dan fokus terlihat.
4. Filter log autentikasi dan pagination mempertahankan query string serta hasil sebelumnya.
5. Dashboard form dan aksi yang ada tetap mengirimkan request ke route yang sama.
6. Navigasi berfungsi pada viewport sempit tanpa menghilangkan akses ke tautan.
7. Tes relevan admin, TOTP, auth-events, dan seluruh suite lulus; catat perintah dan EXIT code.
8. `route:list --json` dibanding baseline tidak menunjukkan route baru atau perubahan route.

## 5. Risiko

- Memindahkan markup ke layout dapat mengubah urutan CSS atau perilaku form bila konten tidak dipertahankan utuh. Mitigasi: tes regresi yang mengunci route, label, filter, dan aksi utama.
- Tes yang memeriksa teks HTML lama dapat memerlukan penyesuaian hanya jika struktur semantik berubah; jangan melemahkan assertion perilaku.
- Dashboard mengandung kontrol dan data sensitif operator. Layout harus mempertahankan middleware TOTP serta escaping Blade.
