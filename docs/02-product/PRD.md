# Product Requirements Document (PRD): ZAFIRA

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Versi:** 1.0 (MVP Formal Specification)  
**Status:** Approved for Implementation & Prototype Verification  
**Bahasa:** Bahasa Indonesia  
**Tanggal:** 2026-10-10  

---

## 1. Product Overview & Objectives

ZAFIRA adalah platform e-commerce dan butik digital adibusana mewah yang mengintegrasikan eksplorasi karya busana bernilai seni tinggi, transparansi material kriya Nusantara dan Eropa, serta layanan personalisasi pesanan melalui penasihat butik (*Client Advisor*).

### Measurable Objectives
1. Menyediakan penjelajahan katalog mewah dengan response time < 100ms (didukung LRU cache).
2. Menyediakan transparansi kriya (provenance, material, keahlian tangan) pada seluruh kreasi.
3. Menyediakan alur belanja yang mulus: pemilihan produk → personalisasi monogram → tas belanja → checkout concierge dalam < 4 langkah.
4. Menyediakan antarmuka administrasi atelier terpadu bagi tim pengelola untuk memantau metrik, mengelola katalog, dan memperbarui status pesanan klien secara real-time.

---

## 2. Target Users & Roles

1. **Role `Guest` (Pengunjung Publik):**
   - Menjelajahi beranda editorial, melihat koleksi dan detail produk, mencari kreasi, mencoba simulasi monogram di tas belanja, mendaftar buletin *La Lettre*, atau membuat janji temu concierge.
2. **Role `Customer` (Pelanggan Terdaftar — Guard `web`):**
   - Melakukan registrasi dengan verifikasi password kuat, login, mengelola profil (/akun), melihat riwayat janji temu dan pesanan, serta pre-fill otomatis formulir checkout.
3. **Role `Admin` (Atelier Director / Client Advisor — Guard `admin`):**
   - Mengelola katalog produk, kategori, data pelanggan, pemantauan arsip audit pendaftaran, dan pembaruan alur status pesanan klien.

---

## 3. User Stories (US)

- **US-001 (Customer Catalog):** Sebagai pelanggan, saya ingin menelusuri kreasi busana berdasarkan kategori dan filter dinamis sehingga saya dapat menemukan busana yang sesuai dengan preferensi gaya saya.
- **US-002 (Product Craftsmanship):** Sebagai pelanggan, saya ingin melihat asal bahan (*provenance*), teknik pembuatan (*craft*), dan panduan perawatan (*care*) agar saya yakin akan kualitas dan keaslian karya yang saya beli.
- **US-003 (Bespoke Cart & Monogram):** Sebagai pelanggan, saya ingin menambahkan produk ke tas belanja dan menambahkan inisial monogram personal agar karya yang saya beli memiliki sentuhan eksklusif.
- **US-004 (Concierge Checkout):** Sebagai pelanggan, saya ingin mengirimkan permintaan pesanan langsung kepada Client Advisor beserta rincian produk agar pesanan saya dikonfirmasi secara personal.
- **US-005 (Customer Account):** Sebagai pelanggan, saya ingin memiliki halaman akun pribadi untuk memantau status pesanan dan konsultasi saya secara transparan.
- **US-006 (Admin KPI Overview):** Sebagai admin atelier, saya ingin melihat ringkasan metrik (jumlah produk, kategori, pengguna terdaftar, pesanan concierge, subscriber) di satu dashboard yang tertata rapi.
- **US-007 (Admin Catalog Management):** Sebagai admin atelier, saya ingin menambah dan memperbarui data produk (termasuk harga, foto, kategori, dan narasi kriya) dengan antarmuka formulir yang jelas dan intuitif.
- **US-008 (Admin Order Processing):** Sebagai admin atelier, saya ingin memperbarui status pesanan klien (`new` → `contacted` → `confirmed` → `completed` → `cancelled`) dan status tersebut langsung terlihat di akun klien.

---

## 4. Functional Requirements (FR)

### Storefront & Pelanggan
- **FR-001 (Storefront Rendering):** Sistem harus menampilkan beranda ZAFIRA lengkap dengan hero banner, galeri koleksi, cerita warisan kriya, dan daftar produk dinamis dari database.
  - *Acceptance Criteria:* Rute `/` menghasilkan HTTP 200, menampilkan judul brand ZAFIRA, dan memuat produk dari database dengan LRU cache 300 detik.
- **FR-002 (Dynamic Category Pills):** Sistem harus menampilkan filter pill kategori secara dinamis sesuai data di database, dan memfilter kartu produk di client-side tanpa reload halaman.
  - *Acceptance Criteria:* Mengklik pill kategori memfilter tampilan produk secara instan; pill aktif diberi penanda visual kontras.
- **FR-003 (Product Detail Page):** Sistem harus menyediakan halaman khusus `/produk/{slug}` yang menampilkan foto resolusi tinggi, harga terformat rupiah, badge kreasi, detail material, provenance, craft, care, dan 3 related products dari kategori yang sama.
  - *Acceptance Criteria:* Slug valid merender `products/show.blade.php`; slug tidak terdaftar menghasilkan HTTP 404; JSON-LD Product valid tersisip di `<head>`.
- **FR-004 (Interactive Cart Drawer):** Sistem harus menyediakan tas belanja interaktif yang dapat dibuka/tutup, menampilkan daftar item, tombol ubah qty, tombol hapus, input monogram opsional (hanya huruf A-Z, maks 3 karakter), dan subtotal harga.
  - *Acceptance Criteria:* Data tas belanja tersimpan di localStorage berversi `v1`; perubahan kuantitas langsung memperbarui subtotal secara real-time; nama produk di-escape anti-XSS.
- **FR-005 (Bespoke Concierge & Checkout Flow):** Sistem harus menyediakan modal concierge yang memiliki dua mode: konsultasi janji temu (*rendez-vous*) dan mode checkout pesanan (menampilkan ringkasan produk di tas belanja).
  - *Acceptance Criteria:* Pengiriman form checkout memvalidasi nama, email (dinormalisasi ke lowercase), nomor HP, dan catatan; data disimpan ke `concierge_requests`; setelah sukses tas belanja dikosongkan dan modal kembali ke mode awal.
- **FR-006 (Newsletter Subscription):** Sistem harus menyediakan formulir pendaftaran buletin *La Lettre* di footer.
  - *Acceptance Criteria:* Pendaftaran bersifat idempoten; email baru memicu pengiriman email selamat datang via antrean; email yang sudah ada menerima konfirmasi tanpa error duplikasi.
- **FR-007 (Customer Authentication):** Sistem harus menyediakan halaman `/login` dan `/register` dengan penegakan kebijakan password ketat (min 8 karakter, huruf besar & kecil, angka, simbol).
  - *Acceptance Criteria:* Registrasi otomatis mencatat snapshot profil ke `user_info_backups` via `UserObserver`; sesi customer diisolasi dari guard admin.
- **FR-008 (Customer Portal `/akun`):** Sistem harus menampilkan data profil pelanggan dan riwayat janji temu/pesanan concierge berdasarkan relasi user ID atau kecocokan email.
  - *Acceptance Criteria:* Rute `/akun` dilindungi middleware `auth`; pelanggan dapat memperbarui nama, nomor HP, dan alamat; daftar riwayat menampilkan status aktual secara real-time.

### Administrasi Atelier (Admin System)
- **FR-009 (Admin Authentication):** Sistem harus menyediakan login khusus di `/admin/login` menggunakan guard `admin` dengan proteksi rate limit (10 percobaan/menit).
  - *Acceptance Criteria:* Kredensial valid mengarahkan ke `/admin`; kredensial salah mengembalikan error; logout admin tidak memutuskan sesi customer jika aktif di browser yang sama.
- **FR-010 (Admin Dashboard Workspace):** Sistem harus menyediakan dashboard terpadu dengan sidebar navigasi, breadcrumbs, dan KPI cards (Total Users, Concierge Orders, Subscribers, Products, Categories).
  - *Acceptance Criteria:* Seluruh metrik bersumber dari data agregasi riil; tidak ada angka dummy fiktif.
- **FR-011 (Admin Product Management):** Sistem harus memungkinkan penambahan kreasi baru dan pembaruan detail produk (kategori, harga, foto, badge, atribut kriya, asal bahan).
  - *Acceptance Criteria:* Penambahan produk otomatis menghasilkan slug unik jika tidak diisi manual; perubahan produk otomatis me-flush cache storefront (`Cache::flush()`).
- **FR-012 (Admin Category Management):** Sistem harus memungkinkan pembuatan, penggantian nama, dan penghapusan kategori.
  - *Acceptance Criteria:* Kategori yang sedang digunakan oleh minimal 1 produk dilarang dihapus (menampilkan pesan error pencegahan referential integrity).
- **FR-013 (Admin Order & Concierge Status Update):** Admin harus dapat memfilter daftar permintaan berdasarkan status (`new`, `contacted`, `confirmed`, `completed`, `cancelled`) atau pencarian nama/email/HP, serta memperbarui statusnya.
  - *Acceptance Criteria:* Pembaruan status concierge berhasil tersimpan dan memunculkan toast konfirmasi; status baru langsung tercermin di `/akun` pelanggan.
- **FR-014 (Admin Password Change):** Admin harus dapat mengganti kata sandi dengan memvalidasi kata sandi saat ini (`current_password:admin`) dan konfirmasi kata sandi baru.
  - *Acceptance Criteria:* Kata sandi lama yang salah ditolak; kata sandi baru yang valid diperbarui dan di-hash Bcrypt round 12.

---

## 5. Non-Functional Requirements (NFR)

- **NFR-001 (Performance):** Respon server rata-rata untuk storefront publik < 150ms dengan bantuan cache memory/database; aset CSS/JS telah terkompresi Gzip dan berukuran < 120 kB total.
- **NFR-002 (Security):** Seluruh respon HTTP harus menyertakan header keamanan (`nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin`). Input formulir dilindungi token CSRF dan rate limiter per IP.
- **NFR-003 (Data Integrity):** Relasi foreign key pada produk, kategori, dan pesanan harus terikat dengan constraint integritas referensial di level database.
- **NFR-004 (Visual Consistency & Aesthetics):** Storefront dan panel admin harus memiliki harmoni estetika tingkat tinggi yang mencerminkan identitas atelier mewah ZAFIRA (Palet Safir, Emas, Linen, Tipografi Bodoni Moda & Manrope).
- **NFR-005 (Portability & Compatibility):** Kode aplikasi harus 100% kompatibel di lingkungan macOS Apple Silicon (arm64) dengan PHP 8.5 dan MariaDB/MySQL.

---

## 6. Business Rules (BR)

- **BR-001 (Integritas Kategori):** Kategori tidak boleh dihapus jika masih terdapat produk yang terasosiasi dengannya. Produk harus dipindahkan ke kategori lain terlebih dahulu.
- **BR-002 (Normalisasi Email):** Seluruh input email (registrasi, login, newsletter, checkout) harus dinormalisasi menjadi huruf kecil (*lowercase*) sebelum disimpan atau dicocokkan.
- **BR-003 (Siklus Status Pesanan):** Status pesanan concierge bergerak melalui alur: `new` → `contacted` → `confirmed` → `completed`, atau beralih ke `cancelled`. Setiap transisi dapat diakses oleh admin.
- **BR-004 (Sitemap Public Exclusivity):** Sitemap XML hanya memuat URL halaman publik yang diizinkan untuk mesin pencari (`/` dan `/produk/{slug}`). Rute administratif, login, dan akun pribadi dilarang muncul di sitemap.
