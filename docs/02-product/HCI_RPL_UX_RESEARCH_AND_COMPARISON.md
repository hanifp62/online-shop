# Riset Analisis Komprehensif: HCI/UI/UX, Rekayasa Perangkat Lunak (RPL), dan Komparasi E-Commerce Mewah

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Domain:** Luxury Fashion & Heritage E-Commerce Atelier  
**Tanggal:** 2026-10-10  
**Audit catatan 2026-10-11:** Dokumen historis 2026-10-10. Judul "Komprehensif" dan daftar penyusun di bawah adalah metadata tidak terverifikasi — anggap dokumen internal tanpa klaim review ahli independen. Klaim kompetitor yang tidak punya URL diperlakukan sebagai hipotesis, bukan fakta benchmark. Bukti akses primer: [metodologi](../research/RESEARCH_METHODOLOGY.md); hanya Hermès (L8) dan Mytheresa (L2) teksnya berhasil diambil; NET-A-PORTER, Farfetch, SSENSE, Louis Vuitton, Dior gagal (HTTP 403) dan Gucci timeout. Prinsip ahli (Norman, Nielsen, dll.) dipakai sebagai lensa analisis, bukan sebagai review individu oleh para ahli tersebut.

---

## 1. Analisis Komparatif UI/UX (Benchmarking)

Untuk memahami posisi ZAFIRA dalam ekosistem digital, kami membandingkannya dengan dua kutub industri: **Rumah Mode Mewah Global** (*Global Haute Couture*) dan **Butik Wastra Adibusana Indonesia** (*Indonesian Heritage Couture*).

| Parameter Evaluasi | ZAFIRA (Kondisi Saat Ini) | Global Luxury (Hermès / Chanel / Cartier) | Indonesian Couture (Iwan Tirta / Danar Hadi) | Rekomendasi Pengembangan ZAFIRA |
|---|---|---|---|---|
| **Tone & Visual Atmosphere** | Editorial klasik, palet Biru Safir Pekat (`#17263f`) & Emas Champagne (`#b69a64`), tipografi *Bodoni Moda* & *Manrope*. | Hermès terobservasi; klaim umum "Global Luxury" lainnya tak bersumber. | Klaim detil Indonesia tak bersumber. | **Pertahankan & Tajamkan (hipotesis):** Perpaduan siluet Prancis dan kriya Nusantara sebagai diferensiasi yang diklaim internal. Klaim "USP" belum didukung riset pengguna; tidak ada riset preferensi yang tercatat. |
| **Katalog & Penemuan Produk** | Filter kategori lokal; status interaksi lintas viewport perlu tes. | Detail filter kompetitor tidak diuji. | Detail kategori kompetitor tidak diuji. | **Investigate:** Uji kebutuhan filter karakter wastra sebelum membuat requirement. |
| **Transparansi Kriya (*Savoir-Faire*)** | Field kriya/perawatan ada di source; akurasi isi perlu validasi data. | Jam kerja, sertifikat, dan edisi terbatas belum terverifikasi. | Profil pengrajin dan filosofi motif belum terverifikasi. | **Investigate:** Jangan tampilkan jam kerja atau sertifikat tanpa data terverifikasi. |
| **Tas Belanja & Personalisasi** | Cart drawer dan monogram tersedia di source; cek browser diperlukan. | Harga atau ketersediaan monogram/hadiah belum diverifikasi. | Ukuran kustom dan kontak manual belum diverifikasi. | **Investigate:** Preview visual memerlukan evaluasi manfaat dan sumber data. |
| **Model Checkout / Transaksi** | Form concierge membuat permintaan, bukan transaksi pembayaran. | Metode pembayaran/appointment lintas brand belum diverifikasi. | Kanal WhatsApp kompetitor belum diverifikasi pada merek yang disebut. | **Pertahankan:** Alur concierge FR-203; pembayaran instan dan OMS di luar scope MVP kanonis. |
| **Antarmuka Pengelola (Admin)** | Modul admin lokal perlu uji browser. | Sistem internal kompetitor tidak dapat disimpulkan dari storefront publik. | CMS internal kompetitor tidak terverifikasi. | **Investigate:** Ukur alur kerja atelier sebelum redesign admin. |

---

## 2. Kajian Mendalam HCI (Human-Computer Interaction) & UX

**Koreksi audit 2026-10-11:** Bagian ini mencatat interpretasi dan target desain historis, bukan hasil uji pengguna atau sertifikasi. Angka kontras 11.4:1, seluruh target 44×44px, durasi toast, jumlah filter, dan perilaku fokus belum diukur ulang. WCAG 2.2 AA SC 2.5.8 menetapkan 24×24px dengan pengecualian; 44×44px adalah target proyek yang lebih ketat. Klaim AAA memerlukan pemeriksaan semua kriteria relevan, bukan satu pasangan warna.

Kolom kompetitor pada tabel sebelumnya tidak membuktikan CMS internal, pembayaran 3D-Secure, sertifikat, jam kriya, atau monogram gratis. Klaim tersebut ditarik sebagai fakta dan hanya menjadi pertanyaan riset. Rekomendasi pembayaran/OMS bukan scope MVP; [sintesis kanonis](benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md) mengungguli roadmap historis ini. Diagram arsitektur konseptual bukan bukti konfigurasi database aktif, kebijakan LRU, atau sinkronisasi real-time.

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
| **10. Bantuan & Dokumentasi** | Dokumentasi panduan operasional tersedia lengkap. | Tersedia dokumen panduan interaktif [panduan prototype](../06-prototype/PROTOTYPE_GUIDE.md). |

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
