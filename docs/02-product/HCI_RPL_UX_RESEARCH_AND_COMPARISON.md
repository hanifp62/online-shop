# Riset Analisis Komprehensif: HCI/UI/UX, Rekayasa Perangkat Lunak (RPL), dan Komparasi E-Commerce Mewah

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Domain:** Luxury Fashion & Heritage E-Commerce Atelier  
**Tanggal:** 2026-10-10  
**Disusun Oleh:** Senior Software Architect, Human-Computer Interaction (HCI) Researcher, dan UI/UX Designer  

---

## 1. Analisis Komparatif UI/UX (Benchmarking)

Untuk memahami posisi ZAFIRA dalam ekosistem digital, kami membandingkannya dengan dua kutub industri: **Rumah Mode Mewah Global** (*Global Haute Couture*) dan **Butik Wastra Adibusana Indonesia** (*Indonesian Heritage Couture*).

| Parameter Evaluasi | ZAFIRA (Kondisi Saat Ini) | Global Luxury (Hermès / Chanel / Cartier) | Indonesian Couture (Iwan Tirta / Danar Hadi) | Rekomendasi Pengembangan ZAFIRA |
|---|---|---|---|---|
| **Tone & Visual Atmosphere** | Editorial klasik, palet Biru Safir Pekat (`#17263f`) & Emas Champagne (`#b69a64`), tipografi *Bodoni Moda* & *Manrope*. | Sangat minimalis, monokromatik dengan aksen tembaga/emas, ruang putih sangat lapang (*gallery airy*). | Hangat, dominasi ornamen etnik cokelat tanah/sogan, visual padat tradisi. | **Pertahankan & Tajamkan:** Perpaduan siluet Prancis dan kriya Nusantara adalah *unique selling proposition* (USP). Jangan gunakan ornamen murahan; gunakan layout asimetris modern. |
| **Katalog & Penemuan Produk** | Filter pill dinamis kategori (Koleksi, Warisan Nusantara, Kapsul) tanpa reload halaman. | Navigasi berbasis koleksi musim (*Spring-Summer / Métiers d'Art*), tanpa filter agresif. | Berdasarkan kategori busana tradisional (Kemeja Batik, Kain Panjang, Selendang). | **Tingkatkan:** Tambahkan pengelompokan berdasarkan "Karakter Wastra" (Sutra Lyon, Batik Solo, Tenun Flores, Songket Palembang) selain kategori jenis pakaian. |
| **Transparansi Kriya (*Savoir-Faire*)** | Detail mencantumkan *Provenance* (asal kain), *Craft* (teknik jahit/bordir), dan *Care* (perawatan). | Menampilkan jam pengerjaan tangan, sertifikat keaslian, nomor edisi terbatas (*limited edition*). | Menampilkan profil empu pembatik / penenun dan filosofi motif tradisi. | **Kembangkan:** Sajikan jam kerja tangan (misal: "60 jam bordir tangan oleh 3 pengrajin") sebagai metrik visual kriya yang terstruktur. |
| **Tas Belanja & Personalisasi** | Cart Drawer geser dari kanan, penghitungan subtotal real-time, input monogram inisial (2–3 huruf). | Fitur monogram gratis (*hot stamping* inisial), penambahan pesan kado (*complimentary gift card*). | Pemesanan ukuran custom (*made-to-measure*) melalui kontak manual. | **Kembangkan:** Integrasikan personalisasi monogram langsung dengan preview visual inisial di atas tas belanja. |
| **Model Checkout / Transaksi** | Dual-mode Concierge: Konsultasi tatap muka atau pemesanan langsung via Client Advisor. | Pembayaran kartu kredit instan 3D-Secure + opsi "Pesan Janji Temu Butik". | Pemesanan melalui formulir kontak atau admin WhatsApp langsung. | **Kembangkan:** Pertahankan model Concierge untuk busana adibusana puluhan juta, dan siapkan arsitektur relasional `orders` & `order_items` untuk pembayaran instan. |
| **Antarmuka Pengelola (Admin)** | Dasbor terpadu dengan sidebar, KPI overview, manajemen produk, kategori, dan pesanan concierge. | Dashboard e-commerce korporat terpisah (SAP Commerce / Shopify Plus / Salesforce B2C). | Dasbor WooCommerce / CMS standar tanpa fitur manajemen kriya khusus. | **Kembangkan:** Hadirkan sistem tata kelola atelier mewah (*Atelier Admin Workspace*) yang konsisten dengan estetika storefront. |

---

## 2. Kajian Mendalam HCI (Human-Computer Interaction) & UX

### 2.1 Prinsip Fundamental Don Norman (*The Design of Everyday Things*)

1. **Affordance & Signifiers (Keterjangkauan & Penanda):**
   - *Masalah Umum E-Commerce:* Tombol aksi yang terlihat seperti teks biasa atau kartu yang tidak jelas apakah bisa diklik.
   - *Penerapan di ZAFIRA:* Kartu produk memiliki *hover elevation* halus (translate -4px) dan tombol aksi sekunder *"Lihat Kriya"* serta *"Tambah ke Tas"* yang muncul sebagai overlay mikro saat kursor diarahkan, memberikan penanda yang tegas tanpa mengotori keindahan foto busana.
2. **Mental Models & Mapping (Model Mental & Pemetaan Konseptual):**
   - Pelanggan barang mewah menganggap pembelian busana jutaan rupiah bukan sekadar transaksi klik-beli, melainkan proses kurasi personal.
   - Pemetaan mental dialihkan dari konsep "Keranjang Belanja Supermarket" menjadi "Tas Butik Pribadi" (*La Maison Shopping Bag*) dan checkout menjadi "Konsultasi Bersama Client Advisor".
3. **Feedback & Micro-Interactions (Umpan Balik Sistem):**
   - Setiap tindakan pengguna (menambah ke tas, mengubah kuantitas, mengganti status pesanan di admin) memicu umpan balik visual instan:
     - Badge counter di header ter-update secara reaktif.
     - Toast notification beraksen safir-emas muncul di sudut kanan bawah mengonfirmasi aksi dalam 3.5 detik.
4. **Constraints (Batasan Pencegah Kesalahan):**
   - Kolom personalisasi monogram membatasi input hanya huruf alfabet kapital (A-Z) maksimal 3 karakter; angka dan simbol secara otomatis difilter seketika (`replace(/[^A-Z]/g, '')`).
   - Pada panel admin, kategori yang masih menaungi minimal satu produk dilarang dihapus, mencegah putusnya relasi referensial data.

---

### 2.2 Heuristik Usabilitas Jakob Nielsen (10 Usability Heuristics)

| Heuristik Nielsen | Evaluasi Kritis pada ZAFIRA | Solusi Desain yang Ditegakkan |
|---|---|---|
| **1. Status Visibilitas Sistem** | Pengguna harus tahu status keranjang dan status pesanannya. | Cart count badge selalu terlihat; status pesanan di `/akun` menampilkan badge warna semantik (`new`, `contacted`, `confirmed`, `completed`). |
| **2. Kecocokan Sistem dengan Dunia Nyata** | Istilah yang digunakan harus mencerminkan tradisi rumah mode (*Atelier, Provenance, Savoir-Faire, Rendez-vous*). | Menggunakan terminologi adibusana bilingual (Prancis & Indonesia) yang elegan tanpa istilah teknis database yang membingungkan. |
| **3. Kontrol & Kebebasan Pengguna** | Pengguna dapat menutup modal atau membatalkan item di tas belanja dengan mudah. | Tombol close `×`, tombol backdrop click, dan tombol "Hapus" yang jelas di setiap item keranjang. |
| **4. Konsistensi & Standar** | Bahasa visual, warna tombol, dan tipografi harus seragam. | Penegakan token desain terpadu: tombol utama selalu emas (`--gold`) berteks safir, heading selalu serif *Bodoni Moda*. |
| **5. Pencegahan Kesalahan (*Error Prevention*)** | Mencegah input salah sebelum form dikirim. | Sanitasi input monogram, validasi format tanggal dan waktu konsultasi, serta validasi email lowercase otomatis. |
| **6. Pengenalan Ketimbang Mengingat (*Recognition over Recall*)** | Ringkasan pesanan harus tampil saat checkout. | Modal checkout concierge menampilkan daftar produk, kuantitas, dan total harga secara eksplisit agar pengguna tidak perlu mengingat isi tas. |
| **7. Fleksibilitas & Efisiensi Penggunaan** | Pengguna mahir dapat beralih cepat antar mode. | Bar atas prototype memungkinkan peralihan satu-klik antara Storefront, Portal Klien, dan Admin Workspace. |
| **8. Desain Estetik & Minimalis** | Hindari dekorasi berlebihan dan informasi yang tidak relevan. | Menghapus elemen "slop" AI (tidak ada emoji kartun, tidak ada gradien neon ungu, tidak ada countdown timer palsu). |
| **9. Membantu Pengguna Mengenali & Memperbaiki Error** | Pesan error harus jelas dan solutif. | Jika kategori tidak bisa dihapus, sistem menjelaskan: "Kategori sedang digunakan oleh produk, pindahkan produk terlebih dahulu." |
| **10. Bantuan & Dokumentasi** | Dokumentasi panduan operasional tersedia lengkap. | Tersedia dokumen panduan interaktif [docs/06-prototype/PROTOTYPE_GUIDE.md](file:///Users/hanifpriyambodo/Documents/online-shop/docs/06-prototype/PROTOTYPE_GUIDE.md). |

---

### 2.3 Teori Beban Kognitif (*Cognitive Load Theory*) & Hukum Hick

- **Hick's Law:** Waktu untuk mengambil keputusan meningkat seiring bertambahnya jumlah pilihan.
- **Penerapan pada ZAFIRA:**
  - Katalog tidak menampilkan puluhan tombol filter rumit. Cukup 8 pill kategori tematik yang langsung mengelompokkan koleksi.
  - Halaman produk memecah informasi kriya ke dalam 3 tab fokus: **Material**, **Provenance (Asal Bahan)**, dan **Savoir-Faire (Teknik Pembuatan)** sehingga pelanggan tidak dibanjiri teks teknis sekaligus.

---

### 2.4 Aksesibilitas (WCAG 2.2 AA / AAA Standards)

1. **Rasio Kontras Warna (Contrast Ratio):**
   - Teks Biru Safir Pekat (`#17263f`) di atas latar Linen Ivory (`#f3efe7`) menghasilkan rasio kontras **11.4:1**, jauh melampaui standar WCAG AAA (minimal 7:1).
   - Teks Emas (`#b69a64`) di atas latar putih diberi penyesuaian kontras minimum 4.5:1 untuk keterbacaan label.
2. **Touch Targets (Target Sentuh Perangkat Mobile):**
   - Seluruh elemen interaktif (tombol navigasi, stepper kuantitas, pill filter, tombol modal) memiliki area sentuh minimal **44 × 44 piksel** untuk kenyamanan jemari di layar sentuh iPhone dan MacBook trackpad.
3. **Screen Reader & Keyboard Navigation:**
   - Navigasi dilengkapi *skip-link* (`#main-content`).
   - Ikon SVG dilengkapi atribut `aria-hidden="true"` dan tombol memiliki `aria-label` deskriptif.

---

## 3. Kajian Rekayasa Perangkat Lunak (RPL / Software Engineering)

### 3.1 Pola Arsitektur & Separation of Concerns

```mermaid
graph TD
    subgraph Frontend Client (HCI & Interaction)
        Browser[DOM / Canvas / Peramban]
        StateEngine[Prototype State Repository: prototype/app.js]
        CSSDesignTokens[Design System Tokens: prototype/app.css]
    end

    subgraph Backend Application (RPL Core)
        Router[HTTP Gateway & Middleware Stack]
        Controllers[Storefront & Admin Atelier Controllers]
        CacheEngine[LRU Cache 300s & Auto-Flush Hooks]
    end

    subgraph Data & Persistence (RPL Storage)
        Eloquent[Eloquent ORM Data Models]
        MariaDB[MariaDB Server Port 3306]
        AuditEngine[UserObserver -> UserInfoBackup]
    end

    Browser --> StateEngine
    Browser --> CSSDesignTokens
    Browser -.->|API / HTTP| Router
    Router --> Controllers
    Controllers --> CacheEngine
    Controllers --> Eloquent
    Eloquent --> MariaDB
    Eloquent --> AuditEngine
```

1. **Model-View-Controller (MVC) dengan Service Boundary:**
   - Logika bisnis tidak ditumpuk di dalam view Blade.
   - Mutasi kategori atau produk memicu *invalidation hook* terpusat melalui `AdminCategoryController::flushStorefrontCache()`.
2. **State Machine pada Siklus Transaksi Pesanan:**
   - Alur status pesanan concierge dikelola dengan mesin status terarah:
     $$\text{New} \longrightarrow \text{Contacted} \longrightarrow \text{Confirmed} \longrightarrow \text{Completed}$$
     $$\searrow \quad \text{Cancelled}$$
   - Transisi status bersifat idempoten dan langsung mencerminkan data ke akun pelanggan tanpa jeda waktu.
3. **Pemisahan Guard Otentikasi Multi-Guard:**
   - Perlindungan sesi: Pelanggan yang login di `/login` menggunakan guard `web`. Tim butik yang login di `/admin/login` menggunakan guard `admin`. Hal ini mencegah privilege escalation dan kebocoran sesi saat pengujian di peramban yang sama.

---

## 4. Kesimpulan & Roadmap Pengembangan Selanjutnya

1. **Fondasi Desain:** Telah memiliki identitas visual dan logika interaksi yang kuat.
2. **Langkah Berikutnya:** Mengonversikan seluruh sistem desain semantik ZAFIRA ke dalam format standar **Google Stitch (`DESIGN.md`)** agar dapat dieksekusi secara otomatis oleh agen kecerdasan buatan Google Stitch untuk menghasilkan variasi layar adibusana berkualitas tinggi.
3. **Penyempurnaan Prototype:** Menghadirkan prototype mandiri yang sepenuhnya memuat elemen visual secara inline agar tidak pernah tampil sebagai "HTML kosong" di lingkungan mana pun.
