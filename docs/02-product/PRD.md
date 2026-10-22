# Product Requirements Document (PRD): ZAFIRA

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Versi:** 1.1 — rekonsiliasi kontrak REM-20261021 DOC

**Status:** Spesifikasi; implementasi terlihat pada source, runtime dan review independen tertunda. Bukan persetujuan produksi.

**Bahasa:** Bahasa Indonesia  
**Tanggal dasar:** 2026-10-10; **revisi:** 2026-10-21

## Acuan aktif dan batas bukti

Revisi ini menindaklanjuti DOC-01/02/05 pada [audit 21 Oktober](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md#10-dokumentasi-requirement-dan-klaim-produk). [Kontrak REM](../04-implementation/REMEDIATION_REAL_WORLD_2026-10-21.md) mendefinisikan FR-REM-001–007 dan NFR-REM-001–006; [crosswalk aktif](../04-implementation/REQUIREMENT_TRACEABILITY.md) menghubungkannya ke [acceptance](../04-implementation/ACCEPTANCE_CRITERIA.md), kode, tes, hasil, dan reviewer. Dokumen ini tidak mengubah status plan server.

- **Fakta source:** route/controller cart, checkout, inquiry, akun, pembayaran, retur/refund, shipment, dan ringkasan operasi tersedia. Keberadaan endpoint bukan bukti seluruh layanan end-to-end atau UI operasional siap. Ringkasan operasi dibatasi feature flag dan otorisasi admin.
- **Target/prototype:** personalisasi bukan varian pembelian yang dijamin terpenuhi; layar prototype bukan transaksi produksi. Klaim asal bahan, autentikasi karya, kapasitas atelier, AOV/margin/retensi, dan kepatuhan memerlukan bukti bisnis eksternal; data tampilan bukan sertifikasi.
- **Bukti tertunda:** eksekusi regresi source terbaru, pengiriman mail/provider nyata, engine/deploy/restore, performa dan aksesibilitas menyeluruh. Angka target di bawah bukan hasil ukur. Model volume pada [TPS real-world](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md) adalah proyeksi dengan delta historis, bukan throughput terukur.
- **Riwayat:** label v1.0 “Approved” tidak memiliki bukti persetujuan formal dalam audit 2026-10-11. [Matriks produk 10–12 Oktober](REQUIREMENTS_TRACEABILITY.md) tetap arsip bukti bertanggal, bukan pengganti kontrak aktif ini; khususnya email-match, monogram dalam cart, dan pernyataan verifikasi email belum ada telah tersupersesi.

---

## 1. Product Overview & Objectives

ZAFIRA adalah platform e-commerce dan butik digital adibusana mewah yang mengintegrasikan eksplorasi karya busana bernilai seni tinggi, transparansi material kriya Nusantara dan Eropa, serta layanan personalisasi pesanan melalui penasihat butik (*Client Advisor*).

### Measurable Objectives
1. Menyediakan penjelajahan katalog mewah dengan response time < 100ms (didukung LRU cache).
2. Menyediakan transparansi kriya (provenance, material, keahlian tangan) pada seluruh kreasi.
3. Memisahkan pembelian katalog (pilih produk → tas → checkout order) dari konsultasi/personalisasi melalui concierge. Sasaran pengalaman < 4 langkah tetap target desain, belum hasil uji pengguna.
4. Menyediakan antarmuka administrasi atelier terpadu bagi tim pengelola untuk memantau metrik, mengelola katalog, dan memperbarui status pesanan klien secara real-time.

---

## 2. Target Users & Roles

1. **Role `Guest` (Pengunjung Publik):**
   - Menjelajahi katalog, membeli produk standar melalui cart/guest checkout, mencoba personalisasi sebagai inquiry concierge terpisah, mendaftar buletin *La Lettre*, atau membuat janji temu.
2. **Role `Customer` (Pelanggan Terdaftar — Guard `web`):**
   - Melakukan registrasi dengan verifikasi password kuat, login, mengelola profil (/akun), melihat riwayat janji temu dan pesanan, serta pre-fill otomatis formulir checkout.
3. **Role `Admin` (Atelier Director / Client Advisor — Guard `admin`):**
   - Mengelola katalog produk, kategori, data pelanggan, pemantauan arsip audit pendaftaran, dan pembaruan alur status pesanan klien.

---

## 3. User Stories (US)

- **US-001 (Customer Catalog):** Sebagai pelanggan, saya ingin menelusuri kreasi busana berdasarkan kategori dan filter dinamis sehingga saya dapat menemukan busana yang sesuai dengan preferensi gaya saya.
- **US-002 (Product Craftsmanship):** Sebagai pelanggan, saya ingin melihat asal bahan (*provenance*), teknik pembuatan (*craft*), dan panduan perawatan (*care*) agar saya yakin akan kualitas dan keaslian karya yang saya beli.
- **US-003 (Tas Pembelian Katalog):** Sebagai pelanggan, saya ingin menyimpan produk dan kuantitas di tas lalu checkout dengan harga server, tanpa mengira ukuran/monogram sudah menjadi varian yang dibeli.
- **US-004 (Inquiry Concierge):** Sebagai pelanggan, saya ingin mengirimkan konsultasi atau permintaan personalisasi kepada Client Advisor dan mengetahui bahwa penerimaan inquiry bukan konfirmasi order atau pembayaran.
- **US-005 (Customer Account):** Sebagai pelanggan, saya ingin memiliki halaman akun pribadi untuk memantau status pesanan dan konsultasi saya secara transparan.
- **US-006 (Admin KPI Overview):** Sebagai admin atelier, saya ingin melihat ringkasan metrik (jumlah produk, kategori, pengguna terdaftar, pesanan concierge, subscriber) di satu dashboard yang tertata rapi.
- **US-007 (Admin Catalog Management):** Sebagai admin atelier, saya ingin menambah dan memperbarui data produk (termasuk harga, foto, kategori, dan narasi kriya) dengan antarmuka formulir yang jelas dan intuitif.
- **US-008 (Pemrosesan Inquiry dan Order):** Sebagai admin atelier, saya ingin mengelola status inquiry concierge terpisah dari lifecycle order, dengan transisi yang diizinkan dan riwayat milik pelanggan terkait.

---

## 4. Functional Requirements (FR)

### Storefront & Pelanggan
- **FR-001 (Storefront Rendering):** Sistem harus menampilkan beranda ZAFIRA lengkap dengan hero banner, galeri koleksi, cerita warisan kriya, dan daftar produk dinamis dari database.
  - *Acceptance Criteria:* Rute `/` menghasilkan HTTP 200, menampilkan judul brand ZAFIRA, dan memuat produk dari database dengan LRU cache 300 detik.
- **FR-002 (Dynamic Category Pills):** Sistem harus menampilkan filter pill kategori secara dinamis sesuai data di database, dan memfilter kartu produk di client-side tanpa reload halaman.
  - *Acceptance Criteria:* Mengklik pill kategori memfilter tampilan produk secara instan; pill aktif diberi penanda visual kontras.
- **FR-003 (Product Detail Page):** Sistem harus menyediakan halaman khusus `/produk/{slug}` yang menampilkan foto resolusi tinggi, harga terformat rupiah, badge kreasi, detail material, provenance, craft, care, dan 3 related products dari kategori yang sama.
  - *Acceptance Criteria:* Slug valid merender `products/show.blade.php`; slug tidak terdaftar menghasilkan HTTP 404; JSON-LD Product valid tersisip di `<head>`.
- **FR-004 (Tas Pembelian Katalog):** Tas menyimpan `product_id` dan kuantitas pada `carts`/`cart_items`; harga dihitung ulang oleh server. Ukuran dan monogram tidak menjadi atribut pembelian.
  - *Acceptance Criteria:* Drawer dapat dibuka/tutup, kuantitas/hapus memperbarui subtotal, dan nama produk di-escape anti-XSS. `GET /cart`, `POST /cart/items`, endpoint delta/hapus memperbarui tas persisten; batas server menolak `size`/`monogram`. Data localStorage legacy hanya bahan pemulihan, bukan sumber harga/order. Lihat **AC-REM-CART** dan FR-REM-005.
- **FR-005 (Inquiry Concierge):** `POST /concierge` menerima janji temu atau inquiry personalisasi dengan validasi kontak/catatan dan membuat `concierge_requests`, kemudian mengantrekan email. Inquiry tidak menciptakan order atau pembayaran dan tidak mengosongkan tas pembelian.
  - *Acceptance Criteria:* Ukuran/monogram di UI diarahkan ke catatan inquiry terpisah. Payload kompatibilitas `order_items` hanya diringkas ke catatan, bukan baris `order_items` transaksi. Respons diterima/queued tidak boleh disebut email terkirim. Lihat **AC-REM-INQUIRY**; FR-203 tetap merujuk penerimaan inquiry.
- **Kontrak checkout order — FR-REM-002 (terkait FR-004, US-003):** `POST /checkout` melalui `OrderController`/`GuestCheckoutController` dan `CheckoutService` membuat `orders`, snapshot `order_items`, reservasi stok, serta riwayat status dalam batas transaksi. `GET /checkout` hanya redirect ke `/belanja`.
  - *Acceptance Criteria:* Alamat milik pengguna atau data guest tervalidasi; nominal berasal dari server; idempotency key/payload mencegah order ganda; cart dibersihkan saat order baru berhasil dibuat, bukan saat inquiry sukses. `pending` bukan bukti pembayaran. Lihat **AC-REM-ORDER** dan **AC-REM-F02**.
- **FR-006 (Newsletter Subscription):** Sistem harus menyediakan formulir pendaftaran buletin *La Lettre* di footer.
  - *Acceptance Criteria:* Pendaftaran bersifat idempoten; email baru memicu pengiriman email selamat datang via antrean; email yang sudah ada menerima konfirmasi tanpa error duplikasi.
- **FR-007 (Customer Authentication):** Sistem harus menyediakan halaman `/login` dan `/register` dengan penegakan kebijakan password ketat (min 8 karakter, huruf besar & kecil, angka, simbol).
  - *Acceptance Criteria:* Registrasi otomatis mencatat snapshot profil ke `user_info_backups` via `UserObserver`; sesi customer diisolasi dari guard admin.
- **FR-008 (Portal Pelanggan dan Kepemilikan):** `/akun` menampilkan profil dan riwayat concierge hanya dengan `concierge_requests.user_id = pengguna terautentikasi`. Riwayat order terpisah di `/orders` juga dibatasi `orders.user_id`; kesamaan email tidak memberi hak baca atau klaim kepemilikan.
  - *Acceptance Criteria:* Middleware `auth` wajib; pelanggan dapat memperbarui nama, nomor HP, dan alamat. Inquiry guest (`user_id = NULL`) dan milik akun lain tidak tampil walaupun email sama. Keterikatan `user_id` tetap berlaku bila email berbeda. Status yang tersimpan tampil saat dimuat ulang; bukan janji push real-time. Lihat **AC-REM-OWN**, FR-204, FR-REM-001/004.
  - *Klaim guest terpisah:* Bila kelak disediakan, klaim harus membuktikan kepemilikan record melalui alur verifikasi khusus sebelum menautkan `user_id`; email-match maupun verifikasi email akun saja tidak cukup. Guest tracking dengan token bukan penautan kepemilikan akun. Alur klaim tidak dinyatakan tersedia atau selesai dalam revisi ini.

### Administrasi Atelier (Admin System)
- **FR-009 (Admin Authentication):** Sistem harus menyediakan login khusus di `/admin/login` menggunakan guard `admin` dengan proteksi rate limit (10 percobaan/menit). *[Audit 2026-10-11: `routes/web.php` mendefinisikan limiter `login` 10/menit per IP, dipakai bersama login pelanggan dan admin; perilaku runtime belum diuji di audit ini.]*
  - *Acceptance Criteria:* Kredensial valid mengarahkan ke `/admin`; kredensial salah mengembalikan error; logout admin tidak memutuskan sesi customer jika aktif di browser yang sama.
- **FR-010 (Admin Dashboard Workspace):** Sistem harus menyediakan dashboard terpadu dengan sidebar navigasi, breadcrumbs, dan KPI cards (Total Users, Concierge Orders, Subscribers, Products, Categories).
  - *Acceptance Criteria:* Seluruh metrik bersumber dari data agregasi riil; tidak ada angka dummy fiktif.
- **FR-011 (Admin Product Management):** Sistem harus memungkinkan penambahan kreasi baru dan pembaruan detail produk (kategori, harga, foto, badge, atribut kriya, asal bahan).
  - *Acceptance Criteria:* Penambahan produk otomatis menghasilkan slug unik jika tidak diisi manual; perubahan produk otomatis me-flush cache storefront (`Cache::flush()`). *[Audit 2026-10-11: kode memakai `Cache::forget` per kunci, bukan `Cache::flush()`; acceptance perlu dibaca sebagai invalidasi cache terkait.]*
- **FR-012 (Admin Category Management):** Sistem harus memungkinkan pembuatan, penggantian nama, dan penghapusan kategori.
  - *Acceptance Criteria:* Kategori yang sedang digunakan oleh minimal 1 produk dilarang dihapus (menampilkan pesan error pencegahan referential integrity).
- **FR-013 (Status Inquiry Concierge):** Admin dapat memfilter inquiry menurut status (`new`, `contacted`, `confirmed`, `completed`, `cancelled`) atau nama/email/HP dan memperbaruinya melalui transisi yang diizinkan. Ini bukan state machine order pembelian.
  - *Acceptance Criteria:* `PATCH /admin/concierge/{concierge}` menyimpan status; `/akun` milik owner menampilkan nilai tersimpan saat dimuat. UI membedakan simpan berhasil dari refresh gagal/tertunda (**AC-REM-F05**). Order ditangani terpisah oleh `/admin/orders` dan FR-REM-002.
- **FR-014 (Admin Password Change):** Admin harus dapat mengganti kata sandi dengan memvalidasi kata sandi saat ini (`current_password:admin`) dan konfirmasi kata sandi baru.
  - *Acceptance Criteria:* Kata sandi lama yang salah ditolak; kata sandi baru yang valid diperbarui dan di-hash Bcrypt round 12. *[Audit 2026-10-11: konfigurasi round eksplisit tidak ditemukan; angka 12 belum diverifikasi.]*
- **FR-015 (Sort Tabel Admin):** Tabel dashboard admin mendukung pengurutan server-side berdasarkan kolom dan arah yang diizinkan untuk tabel terkait.
  - *Acceptance Criteria:* Parameter sort/arah di luar allowlist ditolak dengan validasi; baris berubah mengikuti urutan naik/turun yang diminta; header kolom aktif menunjukkan `aria-sort="ascending"` atau `aria-sort="descending"`.
- **FR-016 (Paginasi dan Filter Tabel Admin):** Daftar dashboard admin mendukung paginasi dan filter/pencarian per tabel tanpa menghilangkan state query tabel lain.
  - *Acceptance Criteria:* Nomor halaman di luar batas ditolak; nilai halaman tidak valid tidak mematikan dashboard; tautan paginasi mempertahankan query filter, sort, dan state tabel lain; pencarian string `"0"` tetap diperlakukan sebagai nilai pencarian.
- **FR-017 (Pemuatan Region Tabel Admin):** Dengan JavaScript dan `fetch` tersedia, navigasi tabel memuat dan mengganti region tabel admin tanpa reload halaman; tanpa dukungan `fetch`, tautan dan formulir GET tetap dapat dinavigasikan secara native.
  - *Acceptance Criteria:* Respons yang valid mengganti region tabel dan memperbarui URL; navigasi Back/Forward memuat state URL tanpa menambah entri riwayat; respons gagal atau tidak lengkap mempertahankan tabel lama; permintaan lintas-origin tidak diambil sebagai pengganti navigasi browser; preferensi kerapatan baris per tabel (kunci `localStorage` `admin-table-density:<key>`, default `compact`) diterapkan ulang setelah region diganti, sedangkan seleksi baris hanya hidup di DOM sehingga tidak dipertahankan setelah penggantian region.
- **FR-018 (Peringatan Perubahan Form Belum Disimpan):** Sebelum region tabel diganti, sistem meminta konfirmasi jika kontrol form non-GET di region terkait berbeda dari nilai default.
  - *Acceptance Criteria:* Perubahan input teks, textarea, checkbox, radio, atau select memicu konfirmasi; membatalkan konfirmasi mencegah pemuatan dan pemindahan URL; menerima konfirmasi melanjutkan navigasi; kontrol yang tidak berubah tidak memicu konfirmasi.

---

## 5. Non-Functional Requirements (NFR)

- **NFR-001 (Performance):** Respon server rata-rata untuk storefront publik < 150ms dengan bantuan cache memory/database; aset CSS/JS telah terkompresi Gzip dan berukuran < 120 kB total.
- **NFR-002 (Security):** Seluruh respon HTTP harus menyertakan header keamanan (`nosniff`, `SAMEORIGIN`, `strict-origin-when-cross-origin`). Input formulir dilindungi token CSRF dan rate limiter per IP.
- **NFR-003 (Data Integrity):** Relasi foreign key pada produk, kategori, dan pesanan harus terikat dengan constraint integritas referensial di level database.
- **NFR-004 (Visual Consistency & Aesthetics):** Storefront dan panel admin harus memiliki harmoni estetika tingkat tinggi yang mencerminkan identitas atelier mewah ZAFIRA (Palet Safir, Emas, Linen, Tipografi Bodoni Moda & Manrope).
- **NFR-005 (Portability & Compatibility):** Kode aplikasi harus 100% kompatibel di lingkungan macOS Apple Silicon (arm64) dengan PHP 8.5 dan MariaDB/MySQL.
- **NFR-006 (Aksesibilitas Tabel Admin):** Tabel dashboard admin menyediakan status sort terprogram, nama aksesibel untuk kontrol baris, pengumuman pemuatan, dan pengelolaan fokus setelah penggantian region.
  - *Acceptance Criteria:* Header sort aktif memakai `aria-sort`; tombol aksi per baris dan checkbox pilih baris memiliki `aria-label`; region live `role="status"` mengumumkan pemuatan lalu ringkasan halaman baru; region yang diganti memakai `aria-busy` dan tidak ada yang tertinggal `true`; fokus dipindahkan ke heading region hanya bila fokus sebelumnya berada di dalam tabel; tombol kerapatan memakai `aria-pressed="true"` untuk mode `compact`. Kepatuhan WCAG 2.2 AA penuh, kontras, target sentuh, dan uji screen reader nyata belum termasuk acceptance ini.

---

## 6. Business Rules (BR)

- **BR-001 (Integritas Kategori):** Kategori tidak boleh dihapus jika masih terdapat produk yang terasosiasi dengannya. Produk harus dipindahkan ke kategori lain terlebih dahulu.
- **BR-002 (Normalisasi Email):** Seluruh input email (registrasi, login, newsletter, checkout) harus dinormalisasi menjadi huruf kecil (*lowercase*) sebelum disimpan atau dicocokkan.
- **BR-003 (Pemisahan Status):** Status inquiry concierge adalah `new`, `contacted`, `confirmed`, `completed`, `cancelled`, mengikuti transisi yang diizinkan controller. Order pembelian mengikuti `pending → paid → processing → shipped → completed`; hanya `pending` boleh beralih ke `cancelled`. Pembayaran tepercaya, bukan pilihan manual admin, mengotorisasi `paid`. Pembatalan paid/processing ditolak; kewajiban finansial harus melalui rekonsiliasi/retur/refund, bukan dihapus.
- **BR-004 (Sitemap Public Exclusivity):** Sitemap XML hanya memuat URL halaman publik yang diizinkan untuk mesin pencari (`/` dan `/produk/{slug}`). Rute administratif, login, dan akun pribadi dilarang muncul di sitemap.

## 7. Riwayat klausul yang disupersesi — bukan acceptance aktif

| Klausul lama | Pengganti dan alasan |
|---|---|
| FR-008 v1.0: “relasi user ID atau kecocokan email” | **Dicabut sebagai kontrak keamanan.** Owner `user_id` wajib; klaim guest memerlukan verifikasi terpisah, bukan fallback email. |
| US-003/FR-004/FR-201: monogram melekat pada item tas | Cart katalog hanya produk/kuantitas; personalisasi menjadi inquiry. FR-201 lama pada matriks produk dibaca sebagai riwayat, bukan kewajiban mengembalikan perilaku cart lama. |
| US-004/FR-005: “checkout concierge” mengosongkan tas | Inquiry dan order dipisah pada FR-005 vs FR-REM-002. Dukungan payload legacy tidak menyamakan kedua domain. |
| US-008/FR-013/BR-003: status concierge disebut status pesanan | Status inquiry tidak mengotorisasi pembayaran atau transisi order. |

Target latensi objective `<100ms`, NFR-001 `<150ms`, serta AC-001 historis `<50ms` belum memiliki protokol ukur bersama. Angka dipertahankan sebagai target yang perlu direkonsiliasi pemilik produk (**AC-REM-N01**), bukan dilonggarkan atau dinyatakan tercapai. Batas penerimaan dan hasil terbaru mengikuti crosswalk REM; bukti historis tetap bertanggal.
