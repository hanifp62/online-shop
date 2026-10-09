# Dokumen Pemahaman Konteks Proyek (Project Context)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Lokasi Direktori:** `/Users/hanifpriyambodo/Documents/online-shop`  
**Tanggal:** 2026-10-10  
**Status:** Terverifikasi berbasis bukti kode dan data aktual  

---

## 1. Identitas & Tujuan Aplikasi

Aplikasi **ZAFIRA** (*La Maison de Lumière*) adalah platform butik dan rumah mode digital mewah (*luxury e-commerce & bespoke atelier*) yang memadukan keanggunan siluet adibusana Paris, presisi kerajinan kulit Italia, dan kriya luhur Nusantara (Batik tulis Solo, Brokat Calais kombinasi bordir tangan Kebayoran, Songket Palembang benang emas, dan Tenun Ikat pewarna alam Maumere).

Tujuan aplikasi:
- Memberikan pengalaman belanja digital yang memancarkan estetika *haute couture* kelas dunia.
- Menyediakan transparansi kriya (*craft & provenance*) untuk setiap kreasi busana dan aksesoris.
- Menyediakan layanan personal *Concierge* dan *Bespoke Styling* yang menghubungkan pelanggan dengan penasihat busana (*Client Advisor*).
- Memberikan alat kelola bagi pengelola (*Atelier Admin*) untuk mengatur katalog, kategori, kriya produk, permintaan janji temu, dan data pelanggan.

---

## 2. Segmentasi Pengguna (User Personas)

1. **Pelanggan Utama (The Luxury Connoisseur & Patron):**
   - Mengapresiasi karya seni busana, material berkualitas tinggi, dan nilai warisan kriya.
   - Menginginkan pengalaman belanja privat, elegan, tanpa distraksi visual generik.
   - Menggunakan fitur penjelajahan katalog, detail kreasi, tas belanja, pemesanan monogram, dan janji temu privat (*rendez-vous*).
2. **Pengelola Maison (Atelier Administrator / Client Advisor):**
   - Bertanggung jawab atas ketersediaan katalog, peluncuran koleksi kapsul/warisan nusantara, dan pembaruan kategori.
   - Memantau janji temu concierge dan pesanan masuk dari klien.
   - Menghubungi pelanggan untuk konfirmasi ukuran, jadwal konsultasi, atau pengiriman pesanan.

---

## 3. Workflow Bisnis yang Teridentifikasi

1. **Penjelajahan Koleksi & Cerita Kriya:**
   - Pelanggan menelusuri produk berdasarkan kategori (Busana Wanita, Mantel Pria, Tas Pesta, Sepatu Kulit, Syal Sutra, Arloji, Warisan Nusantara, Koleksi Kapsul).
   - Pelanggan membuka detail kreasi untuk melihat asal material (*provenance*), teknik jahitan tangan (*craft*), dan instruksi perawatan (*care*).
2. **Tas Belanja & Personalisasi Monogram:**
   - Pelanggan menambahkan kreasi ke tas belanja, memilih opsi personalisasi monogram (2-3 huruf inisial), dan meninjau subtotal.
3. **Pemesanan Melalui Client Advisor (Concierge Checkout):**
   - Karena sifat produk bernilai tinggi (*high-ticket luxury items* dengan harga belasan hingga puluhan juta rupiah), alur checkout saat ini difasilitasi melalui konsultasi penasihat klien.
   - Pelanggan mengirimkan daftar pesanan beserta kontak mereka, yang kemudian diproses oleh tim concierge.
4. **Manajemen Atelier & Sinkronisasi Real-Time:**
   - Admin atelier dapat menambah kreasi baru, mengubah kategori produk, dan mengupdate status pesanan (`new` → `contacted` → `confirmed` → `completed` → `cancelled`).
   - Setiap perubahan status langsung tercermin di halaman `/akun` pelanggan secara real-time.

---

## 4. Aset yang Harus Dipertahankan vs Bagian yang Perlu Ditingkatkan

### Aset yang Harus Dipertahankan (Do Not Break)
- Desain visual storefront yang anggun dan berkarakter (palet Safir, Emas, Linen, Tipografi Bodoni Moda & Manrope).
- Struktur cache performa tinggi (LRU caching di `StorefrontController` dengan auto-flush).
- Seluruh 49 automated test yang sudah ada.
- Skema audit log otomatis pendaftaran via `UserObserver` dan `UserInfoBackup`.
- Isolasi keamanan multi-guard (`web` vs `admin`) dan middleware `SecurityHeaders`.

### Bagian yang Perlu Ditingkatkan (Improvement Areas)
- **Fondasi Eksekusi macOS:** Memberikan izin eksekusi (`+x`) pada binary tooling frontend dan linter (`vendor/bin`, `node_modules/.bin`).
- **Koneksi Database Lokal:** Menstabilkan lingkungan database lokal pada Mac (konfigurasi MariaDB XAMPP lokal dan opsi fallback SQLite terisi penuh).
- **Redesain Antarmuka Admin Atelier:** Mengubah antarmuka admin dari monolitik satu halaman panjang menjadi sistem dasbor terpadu dengan sidebar, KPI modern, dan form drawer yang konsisten dengan estetika Maison ZAFIRA.
- **Model Transaksi E-Commerce Terstruktur:** Merancang skema tabel `orders` dan `order_items` relasional untuk melengkapi alur concierge yang ada saat ini.

---

## 5. Asumsi & Ruang Lingkup (Scope Boundaries)

- **Asumsi Transaksi:** Metode pembayaran pada tahap ini adalah pemesanan terkonfirmasi melalui Client Advisor dan simulasi checkout aman, bukan gateway pembayaran kartu kredit produksi riil.
- **Data Historis:** Mengingat database MySQL Windows tidak terbawa, data awal menggunakan data katalog otentik dari dump SQL `zafira_ecommerce_20260921_clean.sql` dan seeder resmi; tidak ada data transaksi riil fiktif yang diklaim sebagai data nyata.
- **Definisi Selesai:** Proyek berhasil jika fondasi teknis di Mac stabil, PRD dan arsitektur terdokumentasi, serta prototype interaktif berbasis kode nyata dapat dibuka dan diuji secara mulus di browser.
