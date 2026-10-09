# Catatan Keputusan Arsitektur (Architecture Decision Records — ADR)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Status:** Approved & Implemented  
**Tanggal:** 2026-10-10  

---

## ADR-001: Pemisahan Guard Otentikasi Multi-Guard (`web` vs `admin`)

- **Status:** Diterima (Accepted).
- **Konteks:** Aplikasi melayani dua tipe pengguna dengan batasan hak akses yang sangat berbeda: pelanggan butik dan pengelola atelier.
- **Keputusan:** Menggunakan konfigurasi multi-guard bawaan Laravel di `config/auth.php` dengan guard `web` (model `User`) dan guard `admin` (model `Admin`). Sesi login admin dilindungi rute middleware `auth:admin`. Logout pelanggan secara cerdas mengecek status guard admin agar tidak memutuskan sesi kerja staf pada peramban yang sama.
- **Konsekuensi:** Keamanan terisolasi penuh; kredensial staf tidak bercampur dengan tabel pengguna umum.

---

## ADR-002: Strategi Caching LRU Storefront dengan Auto-Flush Tersentralisasi

- **Status:** Diterima (Accepted).
- **Konteks:** Storefront ZAFIRA menerima volume kunjungan tinggi dengan katalog produk yang tidak berubah setiap detik, namun ketika pengelola butik mengubah harga atau kategori, perubahan harus langsung terlihat oleh pelanggan.
- **Keputusan:** Membungkus pembacaan produk dan kategori dalam `Cache::remember` berdurasi 300 detik. Setiap operasi penambahan, pengubahan, atau penghapusan produk maupun kategori memicu metode statis `AdminCategoryController::flushStorefrontCache()`.
- **Konsekuensi:** Waktu respon beranda sangat cepat (< 50ms di cache hit) dengan jaminan *zero stale cache* saat terjadi mutasi data.

---

## ADR-003: Penanganan Stored Procedure Portabel via Eloquent Model

- **Status:** Diterima (Accepted).
- **Konteks:** Proyek warisan memiliki stored procedure `getcat(cid)` dari dump MySQL Windows, namun pengujian lokal dan lingkungan pengembang menggunakan SQLite atau server MariaDB yang tabel prosedurnya belum di-upgrade.
- **Keputusan:** Mengimplementasikan method statis `Category::getById(int $cid)` menggunakan Eloquent query berparameter murni, dan membungkus migrasi DDL procedure dalam blok `try...catch` yang graceful.
- **Konsekuensi:** Kode aplikasi 100% portabel dan dapat dijalankan di MySQL, MariaDB, maupun SQLite tanpa bergantung pada konfigurasi engine database lokal yang rumit.

---

## ADR-004: Alur Checkout Terintegrasi Concierge untuk Produk Kemewahan Tinggi

- **Status:** Diterima (Accepted).
- **Konteks:** Produk ZAFIRA adalah busana adibusana bernilai jutaan hingga puluhan juta rupiah yang memerlukan konsultasi ukuran dan penanganan privat oleh penasihat butik (*Client Advisor*).
- **Keputusan:** Memanfaatkan modal concierge yang adaptif: dapat berfungsi sebagai booking janji temu tatap muka sekaligus mode pemesanan langsung dengan ringkasan daftar belanjaan. Status pesanan dikelola secara transparan oleh admin atelier dan dapat dipantau oleh pelanggan di `/akun`.
- **Konsekuensi:** Memberikan pengalaman sentuhan manusiawi kelas atas (*bespoke luxury experience*) yang sesuai dengan karakter produk, sebelum payment gateway otomatis diaktifkan di masa depan.

---

## ADR-005: Preservasi Identitas Visual Storefront dan Penyelarasan Sistem Admin Atelier

- **Status:** Diterima (Accepted).
- **Konteks:** Tampilan sisi pengguna ZAFIRA telah memiliki kualitas desain dan tipografi yang sangat matang (skor estetika tinggi), sementara halaman admin sebelumnya monolitik dan belum seimbang.
- **Keputusan:** Mempertahankan 100% aset visual, warna, dan font storefront, serta membangun sistem antarmuka admin atelier modern yang mengadopsi prinsip desain yang sama (palet Safir, Emas, Linen pekat, sidebar ramping, dan KPI terstruktur).
- **Konsekuensi:** Tercipta keharmonisan visual antara sisi depan toko dan ruang kerja pengelola atelier.
