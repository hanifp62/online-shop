# Panduan Menjalankan & Menguji Prototype (Prototype Guide)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Lokasi Source Code:** `/Users/hanifpriyambodo/Documents/online-shop/prototype/`  
**Akses Web Server:** `http://127.0.0.1:8000/prototype` (atau file langsung `prototype/index.html`)  
**Tanggal:** 2026-10-10  

---

## 1. Cara Menjalankan Prototype di Lingkungan Lokal

Server lokal saat ini telah aktif di latar belakang. Jika ingin menjalankan ulang secara manual di terminal macOS:

```bash
# 1. Masuk ke direktori proyek
cd ~/Documents/online-shop

# 2. Pastikan dependensi frontend sudah terkompilasi
npm run build

# 3. Jalankan server lokal Laravel
php artisan serve --port=8000
```

Buka peramban favorit Anda (Safari / Chrome / Arc) dan akses tautan:  
👉 **`http://127.0.0.1:8000/prototype`**

*Catatan:* Prototype juga dapat dibuka secara langsung tanpa server web dengan membuka file `prototype/index.html` pada browser apa pun.

---

## 2. Navigasi Fitur & Skenario Pengujian Interaktif

Di bagian atas layar prototype, tersedia **Bar Tata Kelola Prototype** untuk beralih mode secara instan:

### Mode 1: Storefront Pelanggan (Customer Experience)
1. **Filter Kategori Dinamis:**
   - Klik pill kategori seperti **"Warisan Nusantara"** atau **"Koleksi Kapsul"**.
   - Perhatikan bagaimana kisi produk terfilter seketika tanpa jeda reload halaman.
2. **Eksplorasi Cerita Kriya (Quick View Modal):**
   - Pada kartu **"Kebaya Lumière"**, klik tombol **"Lihat Kriya"**.
   - Modal akan terbuka menampilkan narasi keahlian tangan (*savoir-faire*), asal bahan (*provenance: Calais & Kebayoran*), dan jam pengerjaan.
3. **Personalisasi Monogram & Tas Belanja:**
   - Di dalam modal kriya, ketik 2–3 huruf inisial pada kolom monogram (contoh: `ZFL`). Input secara otomatis menolak angka/karakter khusus.
   - Klik **"Tambah ke Tas Belanja"**.
   - Tas belanja (*Cart Drawer*) akan bergeser mulus dari kanan, menampilkan kreasi bersangkutan lengkap dengan badge inisial monogram dan kalkulasi subtotal.
4. **Checkout Terintegrasi Concierge:**
   - Di dalam tas belanja, klik **"Lanjut bersama Client Advisor"**.
   - Modal formulir akan terbuka dengan daftar ringkasan pesanan otomatis terisi.
   - Klik **"Kirim Permintaan ke Client Advisor"**. Notifikasi toast emas akan muncul, tas belanja dikosongkan, dan pesanan tercatat di sistem.

### Mode 2: Portal Akun Klien (`/akun`)
- Klik tombol **"Portal Akun (/akun)"** pada bar atas prototype.
- Anda akan melihat profil simulasi Madame Aurelia Tan beserta kartu riwayat pesanan yang baru saja dikirimkan dengan status awal: **NEW**.

### Mode 3: Atelier Admin Workspace (Ruang Kerja Pengelola Butik)
- Klik tombol **"Atelier Admin Workspace"** pada bar atas prototype.
- **Tinjau KPI Cards:** Memantau jumlah produk (12), kategori (8), dan pesanan masuk secara real-time.
- **Pembaruan Status Transaksi:**
  - Pada tabel *Permintaan Pesanan & Konsultasi Terbaru*, ubah dropdown status pesanan dari `new` menjadi `confirmed`.
  - Toast konfirmasi akan muncul.
  - Sekarang klik kembali ke **"Portal Akun (/akun)"**: status pada akun pelanggan telah berubah menjadi **CONFIRMED** secara real-time!
- **Manajemen Kategori & Pencegahan Hapus:**
  - Buka tab **"Kategori Koleksi"** di sidebar admin.
  - Coba klik hapus pada kategori yang masih memiliki produk: sistem akan menolak dengan dialog proteksi referential integrity.
  - Tambah kategori baru melalui form inline atas.
- **Penerbitan Produk Baru:**
  - Klik tombol **"+ Tambah Kreasi Baru"** untuk membuka modal formulir penambahan produk komprehensif.
