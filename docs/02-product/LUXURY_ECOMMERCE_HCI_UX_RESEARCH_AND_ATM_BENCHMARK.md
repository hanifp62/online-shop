# RISET & ANALISIS KOMPREHENSIF: HCI/IMK, TREN LUXURY E-COMMERCE 2025–2026, DAN METODOLOGI ATM (AMATI, TIRU, MODIFIKASI)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Fokus:** Human-Computer Interaction (HCI/IMK), Haute Couture UI/UX Architecture, dan Re-Engineering Prototype  
**Tanggal:** 2026-10-10  
**Status:** Dokumen historis; audit 2026-10-11 mengoreksi klaim yang tak terverifikasi. Ini bukan hasil uji pengguna, audit UX kompetitor, atau persetujuan ahli independen. Lihat [register metodologi dan batas akses](../research/RESEARCH_METHODOLOGY.md); sumber UX publik yang berhasil dibaca meliputi Hermès dan Mytheresa, sedangkan sebagian besar situs luxury lain gagal diekstraksi.

---

## Catatan audit klaim dan scope (2026-10-11)

Dokumen ini memuat proposal konseptual, bukan tren industri 2025–2026 yang dibuktikan sampel memadai. The Row, Lemaire, Jacquemus, Chanel, Dior, SSENSE, dan Farfetch tidak memiliki observasi yang mendukung deskripsi spesifik di bawah; URL luxury yang gagal akses tercatat pada metodologi. Akses Hermès/Mytheresa berupa ekstraksi teks, bukan audit layout/interaksi. Klaim "WCAG 2.2 AAA" dan target 44×44px tidak membuktikan conformance; WCAG 2.2 SC 2.5.8 AA minimum 24×24 CSS px dengan pengecualian. Kisah Lyon, Solo, Flores, jam kerja, packaging, wax seal, lokasi Jakarta, WhatsApp, kalender, serta mata uang adalah contoh/ide desain, bukan fakta operasional ZAFIRA. Prinsip Norman, Hick, Fitts, Gestalt dan Nielsen merupakan lensa analisis, bukan review oleh para tokoh atau hasil eksperimen. Scope/requirement kanonis ada di [sintesis](benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md); blueprint pada bagian 5 bukan komitmen implementasi.

## 1. Latar Belakang & Identifikasi Masalah Desain Sebelumnya

Evaluasi kritis terhadap implementasi prototype sebelumnya menunjukkan:
1. **Sindrom Template Generik (*The Cookie-Cutter Retail Trap*):**
   - Tampilan storefront masih mengadopsi struktur e-commerce massal: *banner hero* tunggal diikuti deretan kartu produk seragam dalam grid 3 atau 4 kolom simetris.
   - Desain semacam ini bertentangan dengan esensi rumah mode adibusana (*haute couture*) seperti Hermès, The Row, atau Chanel, di mana setiap kreasi diperlakukan sebagai karya seni kurasi galeri, bukan komoditas rak swalayan.
2. **Ketiadaan Ritme Visual Asimetris (*Pacing & Breathing Space*):**
   - Tidak ada jeda editorial (narasi kurator, kutipan atelier, asal-usul wastra) di sela-sela katalog.
   - Beban kognitif pengguna menjadi monoton karena mata hanya disajikan kotak-kotak berulang dengan proporsi yang identik.
3. **Keterbatasan Kedalaman Pengalaman Produk (Hanya Modal Sembulan):**
   - Pengguna mewah membutuhkan pengalaman imersi mendalam sebelum berkomitmen pada kreasi senilai puluhan juta rupiah.
   - Eksplorasi produk harus menyediakan opsi *Dedicated Split-Screen Atelier View*, simulasi unboxing kemasan kado (*Maison Packaging Experience*), dan pemesanan janji temu privat dengan kalender interaktif.

---

## 2. Riset Tren Global Luxury E-Commerce (2025–2026)

Berdasarkan riset industri dan analisis komparatif rumah mode terkemuka dunia, tren antarmuka e-commerce mewah 2025–2026 bertumpu pada 4 pilar utama:

### 2.1 "Quiet Luxury" & Minimalisme Ekstrem (The Row & Lemaire)
- **The Product as Hero:** Menghilangkan dekorasi antarmuka yang berisik (*banners, flash sales, countdown badges, neon accents*). Fotografi resolusi tinggi dengan pencahayaan natural dibiarkan bernapas di atas kanvas monokromatik hangat (*Linen Ivory* dan *Parchment*).
- **Tipografi sebagai Otoritas:** Judul editorial menggunakan font serif dengan proporsi klasik (*Bodoni Moda*) yang dipadukan dengan tipografi sans-serif geometris berkontras tinggi (*Manrope*) untuk metadata teknis.
- **Hairline Borders & Negative Space:** Pemisah visual menggunakan garis rambut 1px dengan opasitas rendah (10–15%), bukan drop shadow tebal yang membebani mata.

### 2.2 Editorial-Led & Experiential Commerce (Hermès & Jacquemus)
- Toko digital tidak lagi diposisikan sebagai sekadar katalog transaksi, melainkan **majalah seni interaktif (*living editorial*)**.
- Narasi pembuatan (*savoir-faire*) dan asal material (*provenance*) diintegrasikan langsung ke dalam alur penjelajahan.
- Pembeli diajak memahami perjalanan sutra dari Lyon Prancis atau canting batik malam dari Solo Jawa Tengah sebelum melihat harga.

### 2.3 Sentuhan Fisik Digital (*Digital-Physical Tactility*) & Unboxing
- Menjembatani jurang antara pengalaman butik fisik dan layar kaca melalui representasi visual kemasan mewah (*Complimentary Signature Packaging*):
  - Kotak kaku (*rigid box*) biru safir berfaset emas.
  - Pita sutra murbei yang diikat tangan.
  - Surat kaligrafi personal yang disegel stempel lilin emas (*wax seal*).

### 2.4 Aksesibilitas Eksklusif (WCAG 2.2 Level AAA)
- Rumah mode mewah modern memandang kepatuhan aksesibilitas bukan beban regulasi, melainkan **wujud kesopanan tertinggi (*the ultimate courtesy*)**.
- Kontras warna teks $\ge 7:1$, target sentuh $\ge 44 \times 44\text{px}$, navigasi keyboard penuh dengan indikator fokus emas elegan, dan tombol pintas `Esc` untuk menutup semua dialog.

---

## 3. Landasan Teori Human-Computer Interaction (HCI / IMK)

### 3.1 Don Norman’s Design Principles (*The Design of Everyday Things*)

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    PRINSIP HCI DON NORMAN PADA ZAFIRA                        │
├───────────────────┬──────────────────────────────────────────────────────────┤
│ Prinsip           │ Manifestasi pada Antarmuka ZAFIRA v3.0                   │
├───────────────────┼──────────────────────────────────────────────────────────┤
│ 1. Affordance     │ Elemen yang dapat diklik memiliki perubahan elevasi      │
│    & Signifiers   │ mikro, kursor pointer, dan garis bawah halus.            │
├───────────────────┼──────────────────────────────────────────────────────────┤
│ 2. Conceptual     │ Mengadopsi model mental "Kunjungan ke Atelier Pribadi"   │
│    Model          │ alih-alih "Belanja di Supermarket Online".               │
├───────────────────┼──────────────────────────────────────────────────────────┤
│ 3. Mapping        │ Tab kriya dipetakan secara horizontal logis:             │
│                   │ Siluet → Material → Savoir-Faire → Perawatan.            │
├───────────────────┼──────────────────────────────────────────────────────────┤
│ 4. Feedback       │ Tombol :active menekan 0.98x; live preview monogram      │
│                   │ emas berganti karakter seketika saat diketik.            │
├───────────────────┼──────────────────────────────────────────────────────────┤
│ 5. Constraints    │ Input monogram dibatasi 3 huruf kapital A-Z; tanggal     │
│                   │ janji temu memvalidasi hari kerja operasional atelier.   │
└───────────────────┴──────────────────────────────────────────────────────────┘
```

### 3.2 Hukum Hick-Hyman & Teori Beban Kognitif (*Cognitive Load*)
- **Hukum Hick:** Waktu keputusan berbanding lurus dengan logaritma jumlah opsi ($T = b \cdot \log_2(n + 1)$).
- **Solusi Desain:** Menerapkan *Progressive Disclosure*. Detail teknis material, nomor seri, dan instruksi perawatan tidak ditumpuk sekaligus di halaman utama, melainkan disajikan dalam tab interaktif dan panel laci (*sliding drawer*) yang dipanggil sesuai kebutuhan pengguna.

### 3.3 Hukum Fitts (*Target Acquisition Law*)
- Waktu untuk menjangkau target adalah fungsi jarak ke target dibagi lebar target ($MT = a + b \cdot \log_2(2D / W)$).
- **Solusi Desain:**
  - Tombol aksi utama (*Add to Bag*, *Pesan Rendez-vous*) memiliki lebar penuh kontainer atau minimum $48\text{px}$ tinggi.
  - Menghadirkan **Sticky Purchase Smart-Bar** yang melayang di bagian bawah layar ketika pengguna menggulir melewati area produk utama, mendekatkan tombol beli ke zona jemari/kursor tanpa perlu menggulir kembali ke atas.

### 3.4 Prinsip Gestalt dalam Tata Letak Editorial
- **Law of Proximity (Kedekatan):** Asal-usul kain (Lyon/Biella/Solo) dikelompokkan secara intim dengan foto kriya terkait.
- **Law of Asymmetry & Visual Balance:** Menyeimbangkan foto portrait vertikal berukuran besar di sebelah kiri dengan tipografi judul serif dan ruang putih di sebelah kanan, menciptakan daya tarik visual tanpa kekacauan.

---

## 4. Benchmark & Metodologi ATM (Amati, Tiru, Modifikasi)

| Benchmark Rumah Mode | Elemen yang Diamati (Amati) | Pola Interaksi yang Ditiru (Tiru) | Inovasi Khusus ZAFIRA (Modifikasi) |
| :--- | :--- | :--- | :--- |
| **Hermès Paris** | Cerita mendalam tentang pengrajin pelana & sutra, sertifikat asal bahan, kemasan oranye ikonik. | Tab *Provenance & Savoir-Faire* serta simulasi kotak kado. | Menambahkan **Simulasi Stempel Lilin Emas (*Wax Seal*)** interaktif dan narasi perpaduan sutra Lyon dengan batik tulis canting Solo & tenun ikat Flores. |
| **The Row (Olsen Twins)** | Minimalisme radikal, tata letak asimetris majalah mode, tanpa tombol beli mencolok. | Ruang putih lapang (*generous negative space*), tipografi tanpa serif untuk label dan serif untuk judul. | Menghadirkan **Dual View Mode Switcher**: pengguna dapat memilih *"Tampilan Editorial"* (ritme majalah) atau *"Tampilan Galeri"* (tinjauan cepat). |
| **Chanel & Christian Dior** | Opsi pemesanan janji temu privat (*Rendez-vous Boutique*) bersama Client Advisor berdedikasi. | Formulir concierge dengan pemilihan layanan eksklusif. | Menyediakan **Grid Pemilihan Tanggal & Waktu Privat** di Atelier Jakarta (Kebayoran Baru) dengan sinkronisasi langsung ke WhatsApp Client Advisor. |
| **SSENSE & Farfetch** | *Sticky smart bar* saat scroll, micro-cart drawer geser dari kanan, filter tipografis instan. | Drawer keranjang tanpa reload halaman, stepper kuantitas real-time. | Mengintegrasikan **Live Monogram Gold Foil Stamp** yang langsung terlihat di dalam tas belanja dan ringkasan pesanan. |

---

## 5. Blueprint Arsitektur Prototype v3.0 — proposal historis, bukan backlog MVP

**Keputusan yang mengungguli daftar ini:** [Sintesis kanonis](benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md) membatasi MVP pada FR-201–204/NFR-201–203. Dual-view, unboxing/wax seal, kalender tersinkron WhatsApp, mata uang tanpa konversi, Kanban/OMS dan sinkronisasi real-time **bukan scope yang disetujui**. Validasi hari kerja, lokasi atelier, jam kerja kriya dan packaging memerlukan data nyata. Daftar berikut dipertahankan hanya sebagai riwayat ide, tanpa klaim sudah berjalan.

Proposal historis:

1. **Header & Navigation Bar:**
   - Navigasi transparan melayang dengan efek kaca (*liquid glass/backdrop-filter*).
   - Indikator mata uang & bahasa (`IDR · ID` / `EUR · EN`).
   - Tautan langsung ke Concierge Atelier dan Tas Belanja dengan counter dinamis.
2. **Hero Section (Majalah Seni Asimetris):**
   - Split-screen: Kanvas foto adibusana beresolusi tinggi di kiri dengan badge *"Collection Métiers d'Art 2026"*.
   - Tipografi monumental *Bodoni Moda* di kanan dengan manifesto filosofi Maison ZAFIRA dan koordinat atelier.
3. **Dual View Controller:**
   - Tombol toggle: **[Tampilan Editorial]** vs **[Tampilan Galeri Koleksi]**.
4. **Editorial Showcase & Asymmetric Diptychs:**
   - Spotlight kreasi utama (*Kebaya Lumière*, *Chemise Rivière*, *Minaudière Saphir*) dengan kutipan desainer dan jam pengerjaan kriya.
   - Pacing kartu bervariasi (asymmetric portrait, square, dan wide editorial banner).
5. **Dedicated Interactive Product Studio (PDP Split-View):**
   - Bukan sekadar pop-up modal biasa, melainkan layar studio interaktif penuh:
     - Kiri: Galeri multi-foto sudut pandang kriya dengan zoom mikro.
     - Kanan: Panel kurasi melekat (*sticky*) dengan 4 tab kriya (*Siluet*, *Material*, *Savoir-Faire*, *Perawatan*), selektor ukuran atelier (FR 36 s/d FR 42) dengan panduan fitting, dan studio monogram emas real-time.
6. **Simulasi Kemasan Kado & Unboxing Mewah (Maison Packaging Experience):**
   - Modul interaktif yang menampilkan kotak safir eksklusif, pita sutra, dan kartu ucapan tulisan tangan kaligrafi dengan stempel cap lilin emas yang bisa dipersonalisasi.
7. **Jadwal Janji Temu Privat (Concierge Appointment Grid):**
   - Pemilihan tanggal, jam sesi privat, dan jenis layanan fitting di Atelier Jakarta.
8. **Atelier Admin Command Center:**
   - Pipeline Kanban Pesanan (*Baru* $\rightarrow$ *Dihubungi* $\rightarrow$ *Terkonfirmasi* $\rightarrow$ *Selesai*).
   - Manajemen katalog kreasi dan sinkronisasi reaktif dua arah ke portal klien (`/akun`).
