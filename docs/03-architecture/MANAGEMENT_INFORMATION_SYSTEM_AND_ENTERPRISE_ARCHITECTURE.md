# Manajemen Sistem Informasi (MSI) & Arsitektur Enterprise ZAFIRA

**Project:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Domain:** Luxury E-Commerce & Haute Couture Atelier Management  
**Standar Keilmuan:** Anthony's Hierarchy, Kenneth & Jane Laudon (MIS Framework), Goldratt Theory of Constraints, ISO/IEC 27001, UU PDP No. 27/2022  
**Tanggal Terbit:** 10 Oktober 2026  
**Status:** Dokumen Resmi Sistem Informasi & Arsitektur Enterprise  

---

## 1. Pendahuluan & Latar Belakang MSI

Manajemen Sistem Informasi (MSI) pada rumah mode adibusana (*haute couture*) dan barang mewah (*luxury goods*) memiliki karakteristik yang sangat berbeda dibandingkan e-commerce komoditas masal (*fast fashion*):

1. **Volume Rendah, Nilai Ekstrem (Low Volume, High Margin):** Nilai transaksi per pesanan berkisar antara puluhan juta rupiah, menuntut nol kesalahan (*zero-error tolerance*) dalam pemenuhan pesanan (*order fulfillment*).
2. **Keterbatasan Kapasitas Pengrajin (Artisan Constrained Capacity):** Produksi adibusana dibatasi oleh jam kerja manusiawi pengrajin senior (*master artisans*), bukan mesin pabrik otomatis.
3. **Kerahasiaan Klien Tingkat Tinggi (High Net-Worth Individual / VIC Privacy):** Data ukuran tubuh, preferensi gaya, dan jadwal kunjungan privat adalah aset reputasi yang sangat sensitif.
4. **Keaslian & Ketertelusuran Mutlak (Provenance & Serialized Authenticity):** Setiap helai busana harus memiliki sertifikat keaslian dan nomor seri terdaftar (*traceability lot*).

Oleh karena itu, sistem ZAFIRA mengadopsi kerangka kerja MSI terpadu yang memadukan tiga lapisan piramida hierarki manajemen Robert N. Anthony:

```
                            ▲
                           / \
                          /   \
                         / EIS \       Level Strategis (Maison Executive / Creative Director)
                        /-------\      - GMV, AOV, Gross Margin, Performa Runway, Rekomendasi Ekspansi
                       /   MIS   \
                      /     &     \    Level Taktis/Manajerial (Atelier Manager & Store Director)
                     /     DSS     \   - Kapasitas WIP, Bottleneck, Stok Bahan Langka, SLA Lead-Time
                    /---------------\
                   /       TPS       \ Level Operasional (Client Advisors & Master Artisans)
                  /                   \ - Order Capture, Fitting Scheduler, Serialized Certificate, Work Order
                 ───────────────────────
```

---

## 2. Dekomposisi Sistem Berdasarkan Piramida Anthony

### A. Level Operasional: Transaction Processing System (TPS)
Level ini menangani pemrosesan transaksi harian dengan integritas ACID (*Atomicity, Consistency, Isolation, Durability*):

1. **Front-Desk Order & Appointment Capture:**
   - Pencatatan pesanan produk katalog dan pesanan kriya *bespoke* dengan inisial *custom monogram*.
   - Penjadwalan *rendezvous* di Atelier Kebayoran Baru dengan slot waktu eksklusif (pencegahan *double-booking*).
2. **Atelier Work Order Issuance:**
   - Penerbitan surat perintah kerja kriya digital yang merinci ukuran tubuh klien, spesifikasi material, dan instruksi khusus bordir.
3. **Digital Certificate of Authenticity (Sertifikat Keaslian Digital):**
   - Penerbitan nomor seri unik (Format: `ZFR-2026-XXXX`) yang dilengkapi kode hash integritas untuk menjamin asal-usul karya di pasar sekunder atau koleksi keluarga.
4. **Artisan Assignment:**
   - Alokasi pesanan kepada pengrajin ahli berdasarkan spesifikasi keahlian (contoh: Brokat Prancis ditugaskan ke Madame Hélène; Bordir Tambal-Tangan ditugaskan ke Ibu Siti).

### B. Level Taktis: Management Information System (MIS) & Decision Support System (DSS)
Level ini mengolah data operasional menjadi laporan terstruktur dan alat bantu pengambilan keputusan manajer:

1. **Atelier WIP (Work-In-Progress) & Bottleneck Management:**
   - Mengadopsi *Theory of Constraints* (TOC) Eliyahu M. Goldratt.
   - Setiap stasiun kerja memiliki batas kapasitas kerja serentak (*WIP Limit*):
     - *Verifikasi Pesanan:* Maksimal 5 pesanan
     - *Pola & Pemotongan:* Maksimal 3 pesanan
     - *Jahit & Bordir Tangan:* Maksimal 4 pesanan
     - *Inspeksi Kualitas (QC):* Maksimal 2 pesanan
   - Jika suatu stasiun mencapai batas maksimum, sistem DSS memicu peringatan *Bottleneck Alert* agar manajer dapat menyeimbangkan beban kerja.
2. **Luxury Raw Material Inventory Control:**
   - Pelacakan stok bahan baku langka bersertifikat asal:
     - Brokat Calais (Prancis)
     - Sutra Organza Lyon murni
     - Benang Emas Palembang (ATBM)
     - Pasta Indigo Alami Laweyan
   - Model inventori menggunakan *Safety Stock* dan *Reorder Point* (ROP):
     $$ROP = (d \times L) + SS$$
     *Di mana $d$ adalah konsumsi harian rata-rata, $L$ adalah lead-time pengiriman bahan dari Eropa/daerah, dan $SS$ adalah stok pengaman.*
3. **Lead-Time Analytics & SLA Tracking:**
   - Pemantauan durasi siklus pengerjaan (*Cycle Time*) dari pesanan diterima hingga diserahkan ke Kurir Diplomatik.
   - Target SLA Adibusana: Maksimal 7 hari kerja. Rata-rata aktual: 5.4 hari kerja.

### C. Level Strategis: Executive Information System (EIS) / Executive Support System (ESS)
Level ini menyediakan kokpit visual ringkas bagi *Creative Director* dan *Maison Owner* untuk menentukan arah bisnis jangka panjang:

1. **Executive Scorecard Metrik Kunci:**
   - **GMV (Gross Merchandise Value):** Total nilai transaksi kotor yang diproses dalam periode berjalan.
   - **AOV (Average Order Value):** Tolok ukur daya beli klien VIP (target ZAFIRA: > Rp12.000.000).
   - **Tingkat Utilisasi Atelier (Atelier Utilization Rate):** Persentase kapasitas pengrajin yang terutilisasi (kisaran sehat: 80%–90%).
   - **Gross Margin Adibusana:** Rasio keuntungan kotor terhadap biaya bahan baku dan kompensasi pengrajin (target: > 70%).
   - **VIP Retention Rate:** Persentase klien berkategori VIC (*Very Important Client*) yang melakukan pemesanan ulang dalam kurun 12 bulan (target: > 85%).
2. **Analisis Kontribusi Koleksi:**
   - Visualisasi distribusi pendapatan antar kategori: *Warisan Nusantara*, *Kapsul Adibusana*, dan *Aksesori Kriya*.
3. **Prescriptive Strategic Recommendation Engine:**
   - Mesin penganalisis tren yang memberikan rekomendasi strategis otomatis berbasis data transaksi:
     - *Rekomendasi Restock Bahan Langka:* Ketika konsumsi sutra brokat meningkat tajam menjelang musim pernikahan.
     - *Rekomendasi Alokasi Pengrajin:* Menambah kuota pemagangan pengrajin muda untuk teknik bordir tangan.
     - *Rekomendasi Penjadwalan Trunk Show:* Waktu optimal peluncuran kapsul privat berdasarkan klaster pemesanan klien.

---

## 3. Data Flow Diagram (DFD) Sistem Informasi ZAFIRA

### DFD Level 0 (Context Diagram)

```
       ┌────────────────────────┐
       │   Klien VIP ZAFIRA     │
       └───────────┬────────────┘
                   │ 1. Transaksi, Monogram & Jadwal Rendez-vous
                   │ 2. Penerimaan Konfirmasi & Sertifikat Keaslian
                   ▼
       ┌────────────────────────────────────────────────────────┐
       │                                                        │
       │    SISTEM INFORMASI TERPADU ZAFIRA ATELIER & COMMERCE  │
       │                                                        │
       └─────┬───────────────────┬────────────────────────┬─────┘
             │                   │                        │
             │ Work Order        │ Laporan WIP & Stok     │ Executive KPI
             ▼                   ▼                        ▼
       ┌──────────────┐    ┌──────────────┐         ┌───────────────┐
       │ Master       │    │ Atelier      │         │ Maison        │
       │ Artisan      │    │ Manager      │         │ Director      │
       └──────────────┘    └──────────────┘         └───────────────┘
```

---

## 4. Tata Kelola Data, Keamanan, & Kepatuhan Regulasi (Governance)

### A. Role-Based Access Control (RBAC)

| Peran (Role) | Hak Akses Operasional (TPS) | Hak Akses Taktis (MIS/DSS) | Hak Akses Strategis (EIS) |
| :--- | :--- | :--- | :--- |
| **Klien VIP** | Melihat katalog, membuat pesanan, riwayat pribadi, unduh sertifikat keaslian sendiri. | *Tidak ada akses* | *Tidak ada akses* |
| **Client Advisor** | Mengelola pesanan klien, memperbarui status janji temu, mengirim pesan kurasi. | Melihat antrean pesanan & jadwal salon harian. | *Tidak ada akses* |
| **Master Artisan** | Melihat detail lembar kerja kriya (*Work Order*), memperbarui tahapan jahit/bordir. | Melihat utilisasi bahan baku terkait kriya yang dikerjakan. | *Tidak ada akses* |
| **Atelier Manager** | Memperbarui seluruh alur pesanan, menerbitkan nomor seri sertifikat keaslian. | Akses penuh laporan stok, kapasitas WIP, dan peringatan bottleneck. | Melihat rangkuman efisiensi bulanan. |
| **Maison Director** | Melihat audit log komprehensif. | Akses penuh laporan taktis. | Akses penuh Executive Cockpit, GMV, Margin, dan Strategic Engine. |

### B. Audit Trail Imutabel (Integritas Data)
Setiap mutasi data kritis (perubahan status pesanan, penerbitan sertifikat keaslian, perubahan alokasi bahan baku) dicatat secara permanen dalam tabel log audit dengan atribut:
- `timestamp`: Waktu transaksi presisi mikrodetik (WIB).
- `actor_id`: Identitas pengguna pengeksekusi aksi.
- `role`: Peran aktor saat mengeksekusi aksi.
- `module`: Sub-sistem terdampak (TPS, INVENTORY, CONCIERGE).
- `action`: Deskripsi perubahan data (e.g. `ORDER_STATUS_CHANGED: Confirmed -> In_Production`).
- `integrity_hash`: Kode verifikasi SHA-256 untuk mendeteksi manipulasi data (*tamper-evident*).

### C. Perlindungan Privasi Data Pribadi (UU PDP No. 27/2022)
1. **Data Ukuran Tubuh Klien:** Tergolong data pribadi spesifik. Dienkripsi pada level database (*column-level encryption* AES-256-GCM) dan hanya dapat diakses oleh Client Advisor yang ditugaskan.
2. **Hak Menghapus Data (Right to Erasure):** Portal akun klien menyediakan opsi penonaktifan akun dan anonimisasi data transaksi untuk audit kepatuhan perpajakan.

---

## 5. Implementasi pada Antarmuka Prototipe ZAFIRA

Pilar-pilar MSI di atas diwujudkan secara konkret pada antarmuka **Atelier Command Workspace** (`prototype/`):
- **Tab 1: TPS Pipeline & Kanban:** Operasional pemenuhan pesanan, alokasi artisan, dan penerbitan sertifikat keaslian.
- **Tab 2: MIS / DSS Kontrol Taktis:** Monitoring WIP & bottleneck alert, pelacak stok bahan baku langka, dan analytics lead-time.
- **Tab 3: EIS Executive Cockpit:** Dashboard eksekutif GMV, AOV, utilisasi atelier, grafik kontribusi koleksi, dan mesin rekomendasi strategis.
- **Tab 4: Tata Kelola & Audit Trail:** Uji simulasi RBAC (Role-based access switcher) dan pencatatan log audit reaktif ber-hash kriptografis.

Dokumen ini menjadi acuan tunggal bagi tim teknis, analis bisnis, dan desainer antarmuka dalam mengembangkan fungsionalitas backend dan frontend ZAFIRA.
