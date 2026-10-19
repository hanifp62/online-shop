# Log Riset Produk & Teknis (Research Log)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Tanggal:** 2026-10-10  
**Status (diaudit 2026-10-11):** Log historis 2026-10-10. Beberapa butir **tidak memiliki URL atau salinan sumber** dan diberi label di bawah. Bukti akses sumber primer: [metodologi](../research/RESEARCH_METHODOLOGY.md). Label: `[TERVERIFIKASI]` = ada sumber/URL terdaftar; `[TIDAK TERSOURCE]` = tidak ada bukti akses di repo, perlakukan sebagai hipotesis; `[LOKAL]` = dari kode repo.

> Batas: Chanel, Cartier, Goyard, Shopify Polaris, Linear, dan Tailwind UI **tidak tercatat pernah diakses**. NET-A-PORTER, Farfetch, SSENSE, Louis Vuitton, Dior (HTTP 403) dan Gucci (timeout) gagal diekstraksi pada 2026-10-11; hanya Hermès dan Mytheresa berhasil. Tidak ada klaim "bukti resmi" untuk butir tak bersumber.

---

## 1. Riset UX & Pola Luxury E-Commerce

### Sumber 1: Praktik Butik Mewah Internasional (Hermès, Chanel, Cartier, Goyard) — `[TIDAK TERSOURCE]` kecuali Hermès (lihat metodologi L8)
- **Catatan koreksi:** temuan 1–3 di bawah belum punya URL; hanya navigasi Hermès yang tercatat terobservasi. Klaim "Cartier dan Hermès menyediakan 'Hubungi Penasihat Butik'" dan asal kain "Lyon/Biella" untuk kompetitor tidak terverifikasi. Prinsip yang sah dipakai: [NN/g luxury principles](https://www.nngroup.com/articles/luxury-principles-ecommerce-design/) (artikel 2022, bukan audit 2026).
- **Topik:** Pola navigasi, presentasi kriya, dan model pemesanan produk bernilai tinggi.
- **Tanggal Analisis:** 2026-10-10.
- **Temuan Utama:**
  1. Brand adibusana tidak menggunakan gaya visual ramai (*no discount banners, no garish countdowns, no generic badges*). Desain didominasi ruang bernapas yang lapang (*generous whitespace*), tipografi serif klasik dipadu sans-serif modern, dan fotografi berskala besar.
  2. Produk bernilai tinggi memerlukan *Bespoke Service* atau *Private Appointment*. Cartier dan Hermès menyediakan opsi "Hubungi Penasihat Butik" berdampingan dengan opsi "Masukkan ke Kantong".
  3. Detail produk menonjolkan *Provenance* (asal kain dari Lyon atau Biella) dan *Savoir-Faire* (jam pengerjaan tangan, teknik kelim rolled-hem, jahitan tangan).
- **Dampak pada ZAFIRA:**
  - Tetap mempertahankan tipografi *Bodoni Moda* & *Manrope*, palet safir/emas/linen, dan menyajikan atribut `provenance`, `craft`, dan `care` pada setiap halaman produk.
  - Alur checkout modal concierge yang sudah ada dipertahankan dan disempurnakan menjadi alur pesanan terstruktur.

### Sumber 2: Pola Desain Antarmuka Administrasi & Workspace Modern — `[TIDAK TERSOURCE]`
- **Catatan koreksi:** tidak ada URL dokumentasi Polaris/Linear/Tailwind UI di repo. Anggap sebagai preferensi desain internal, bukan temuan riset. Semantik warna badge adalah keputusan desain, bukan standar industri.
- **Topik:** Shopify Polaris, Linear Design System, dan Tailwind UI e-commerce patterns.
- **Tanggal Analisis:** 2026-10-10.
- **Temuan Utama:**
  1. Panel admin modern memisahkan layout global (Sidebar + Top Bar) dari konten halaman spesifik, menghindari tumpukan halaman monolitik yang membingungkan.
  2. Input formulir kompleks (seperti katalog produk dengan belasan atribut kriya) lebih optimal menggunakan pola *Drawer Slide-Over* atau tab terfokus daripada tag accordion `<details>` tersembunyi.
  3. Status transaksi harus menggunakan visual badge dengan semantik warna yang konsisten (Biru = Baru, Kuning = Dihubungi, Hijau = Terkonfirmasi, Ungu = Selesai, Merah = Batal).
- **Dampak pada ZAFIRA:**
  - Menjadi landasan utama redesign antarmuka admin atelier pada Fase 5 dan prototype Fase 6.

---

## 2. Riset Teknis Framework & Keamanan

### Sumber 1: Dokumentasi Resmi Laravel 12 (laravel.com/docs/12.x)
- **Topik:** Multi-guard authentication, closure bootstrap, routing rate limits, dan cache invalidation.
- **Tanggal Analisis:** 2026-10-10.
- **Temuan Utama:**
  1. Pemisahan guard `web` (pelanggan) dan `admin` (pengelola atelier) di `config/auth.php` menjamin keamanan tingkat tinggi tanpa kebocoran sesi.
  2. Pada pemanggilan `Auth::guard('web')->logout()`, sesi aplikasi tidak boleh di-invalidate jika `Auth::guard('admin')->check()` bernilai true, untuk mencegah admin terlempar keluar saat menguji akun pelanggan di peramban yang sama.
  3. Caching storefront menggunakan `Cache::remember` dengan durasi 300 detik (5 menit) mengurangi beban query database secara drastis, dengan syarat dipicu `Cache::flush()` setiap kali admin memutasi kategori atau produk.
- **Dampak pada ZAFIRA:**
  - `[LOKAL]` Pola ini diimplementasikan dan dicakup `LegacyPatchTest`; test **NOT RUN** pada audit dokumen 2026-10-11, jadi kata "terbukti sukses" hanya klaim historis.
  - Koreksi: kode storefront memakai `Cache::remember('storefront:products:v1', 300, …)` dengan invalidasi key tertentu (komentar di `StorefrontController::index`), bukan `Cache::flush()` global. URL dokumentasi Laravel tidak tercatat; `[TIDAK TERSOURCE]` untuk butir 1–3 sebagai kutipan dokumentasi.

### Sumber 2: Standar Keamanan OWASP Top 10 & Web Interface Guidelines
- **Topik:** Proteksi XSS, CSRF, Password Policy, dan Accessibility.
- **Tanggal Analisis:** 2026-10-10.
- **Temuan Utama:**
  1. Render data keranjang client-side rentan stored XSS jika data nama produk atau catatan disisipkan via `innerHTML` tanpa sanitasi. Fungsi `escapeHtml()` wajib diterapkan pada setiap injeksi string dinamis.
  2. Kebijakan kata sandi minimal 8 karakter dengan kombinasi huruf besar, kecil, angka, dan simbol mencegah kompromi kredensial akun admin.
  3. Header keamanan `X-Content-Type-Options: nosniff` dan `X-Frame-Options: SAMEORIGIN` mencegah serangan MIME-sniffing dan clickjacking.
- **Dampak pada ZAFIRA:**
  - Kode middleware `SecurityHeaders` dan helper client-side di `app.js` mengimplementasikan sebagian praktik ini. Klaim "memenuhi standar OWASP" tidak didukung audit; tidak ada pengujian keamanan independen dijalankan di sini. Referensi: OWASP Top 10 (URL tidak tercatat saat riset awal).
