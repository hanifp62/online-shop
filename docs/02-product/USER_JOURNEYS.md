# Pemetaan Alur Pengguna (User Journeys)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal:** 2026-10-10  

---

## 1. Journey 1: Eksplorasi Karya Adibusana & Pemesanan Monogram (Pelanggan)

```mermaid
journey
    title Perjalanan Pelanggan Menemukan & Memesan Kreasi ZAFIRA
    section Penemuan
      Buka Beranda ZAFIRA: 5: Pelanggan
      Menikmati Visual Editorial Hero: 5: Pelanggan
      Filter Kategori (Warisan Nusantara): 5: Pelanggan
    section Eksplorasi Detail
      Buka Halaman Detail Produk (/produk/kebaya-lumiere): 5: Pelanggan
      Membaca Asal Bahan (Calais, Prancis) & Bordir Tangan: 5: Pelanggan
      Meninjau Panduan Perawatan Kriya: 4: Pelanggan
    section Personalisasi & Tas Belanja
      Klik Tambah ke Tas: 5: Pelanggan
      Buka Tas Belanja (Cart Drawer): 5: Pelanggan
      Menambahkan Personalisasi Monogram (CTH: 'ZFL'): 5: Pelanggan
    section Pemesanan
      Klik Lanjut bersama Client Advisor: 5: Pelanggan
      Modal Berubah ke Mode Checkout: 5: Pelanggan
      Isi Nama, Email, Nomor WhatsApp, dan Catatan Ukuran: 5: Pelanggan
      Kirim Permintaan Pesanan: 5: Pelanggan
      Menerima Notifikasi Toast Sukses & Email Konfirmasi: 5: Pelanggan
```

---

## 2. Journey 2: Manajemen Katalog & Alur Status Pesanan (Admin Atelier)

```mermaid
journey
    title Perjalanan Pengelola Butik (Atelier Director / Client Advisor)
    section Akses Sistem
      Buka Halaman /admin/login: 4: Admin
      Masuk dengan Guard Admin: 5: Admin
      Tinjau Dashboard & Metrik KPI: 5: Admin
    section Pembaruan Katalog
      Buka Modul Produk: 5: Admin
      Klik Tambah Produk Baru: 4: Admin
      Isi Detail Busana & Cerita Kriya: 4: Admin
      Simpan & Verifikasi Cache Ter-flush Otomatis: 5: Admin
    section Pemrosesan Pesanan
      Buka Modul Pesanan / Concierge: 5: Admin
      Tinjau Rincian Produk & Catatan Monogram: 5: Admin
      Hubungi Pelanggan via WhatsApp: 5: Admin
      Perbarui Status (New -> Contacted -> Confirmed): 5: Admin
      Pelanggan Melihat Status Berubah di /akun: 5: Admin, Pelanggan
```

---

## 3. Journey 3: Pemantauan Profil & Riwayat Pesanan (Pelanggan Terdaftar)

```mermaid
journey
    title Perjalanan Pelanggan Memantau Riwayat Pesanan
    section Akses Akun
      Klik Ikon Akun di Header: 5: Pelanggan
      Login dengan Password Terverifikasi: 5: Pelanggan
      Masuk ke Halaman /akun: 5: Pelanggan
    section Pemantauan & Update
      Melihat Ringkasan Profil: 4: Pelanggan
      Memperbarui Nomor HP / Alamat Pengiriman: 4: Pelanggan
      Melihat Kartu Riwayat Pesanan / Concierge: 5: Pelanggan
      Mengecek Badge Status Real-Time (Misal: Confirmed): 5: Pelanggan
```
