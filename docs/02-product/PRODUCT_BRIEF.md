# Product Brief: ZAFIRA (La Maison de Lumière)

**Proyek:** ZAFIRA (`online-shop`)  
**Fase:** Fase 4 — Product Discovery & Strategy  
**Penyusun:** Product Management & Engineering Architecture Team  
**Tanggal:** 2026-10-10  

---

## 1. Product Vision (Visi Produk)

Menjadikan **ZAFIRA** sebagai *The Premier Digital Haute Couture Maison* dari Indonesia yang meredefinisi kemewahan adibusana: mempertemukan ketepatan siluet tailoring Prancis, kualitas material Italia, dan keagungan wastra tradisional Nusantara (Batik Solo, Songket Palembang, Tenun Flores, Kebaya Brokat) dalam sebuah ekosistem digital yang privat, intim, dan tanpa kompromi estetika.

---

## 2. Problem Statement (Rumusan Masalah)

1. **Kompleksitas Pembelian Produk Bernilai Tinggi (High-Ticket Luxury):**
   - Busana adibusana bernilai Rp5.000.000 hingga Rp30.000.000 tidak dapat dijual dengan pola *self-service cart* murahan. Pelanggan memerlukan keyakinan akan ukuran yang presisi, asal usul material (*provenance*), teknik kriya (*craftsmanship*), serta sentuhan personalisasi (seperti monogram inisial).
2. **Ketiadaan Transparansi Kriya pada E-Commerce Tradisional:**
   - Konsumen kelas atas menginginkan cerita di balik kreasi: siapa penenunnya, dari atelier mana kain sutranya diproduksi, dan bagaimana instruksi perawatan spesifiknya.
3. **Kesenjangan Antarmuka Pengelola Toko:**
   - Platform backend butik mewah sering kali kaku, lambat, atau terpisah dari konteks pelanggan, menyulitkan penasihat busana (*Client Advisor*) untuk memberikan layanan kelas dunia secara cepat dan tanggap.

---

## 3. Target Users & Persona

### Persona A: Madame / Monsieur Patron (Pelanggan Utama)
- **Profil:** Kolektor fashion, eksekutif, dan penikmat seni kriya berusia 28–55 tahun.
- **Karakteristik:** Menghargai eksklusivitas, privasi, material alami berkualitas terbaik (sutra murbei, wol kasmir, katun primisima), dan layanan personal.
- **Kebutuhan Utama:** Kemudahan eksplorasi detail bahan, layanan pemesanan janji temu privat (*rendez-vous*), opsi monogram, serta pelacakan pesanan yang transparan.

### Persona B: Atelier Director & Client Advisor (Tim Pengelola Butik)
- **Profil:** Tim pengelola produk, perancang atelier, dan customer advisor ZAFIRA.
- **Karakteristik:** Membutuhkan antarmuka administrasi yang cepat, bersih, berkelas, dan informatif untuk mengelola katalog, kategori, janji temu, dan data klien.
- **Kebutuhan Utama:** Pemantauan metrik secara visual, penambahan produk beserta atribut kriya tanpa hambatan, pembaruan status pesanan yang sinkron ke akun pelanggan, serta pencarian data klien yang responsif.

---

## 4. Value Proposition (Proposisi Nilai)

1. **Digital Atelier Experience:** Tampilan storefront yang memancarkan estetika rumah mode Paris dengan tipografi klasik Bodoni Moda, palet Safir, dan visual resolusi tinggi.
2. **Provenance & Craftsmanship Transparency:** Setiap kreasi mencantumkan asal kain, atelier penjahitan, jam kerja tangan per helai, dan panduan perawatan.
3. **Bespoke Concierge & Checkout Synergy:** Transisi mulus antara belanja mandiri dan konsultasi personal bersama Client Advisor.
4. **Unified Atelier Admin Workspace:** Sistem pengelola terpadu yang memadukan keindahan visual dengan efisiensi pengelolaan katalog dan transaksi.

---

## 5. Cakupan MVP vs Post-MVP

### Lingkup MVP (Minimum Viable Product — Sasaran Saat Ini)
- **Storefront:** Beranda editorial, filter kategori dinamis, halaman detail produk lengkap dengan atribut kriya, modal quick-view, tas belanja interaktif dengan monogram, dan mode checkout concierge.
- **Customer Portal:** Registrasi/login aman dengan password policy ketat, profil pelanggan, dan riwayat pesanan/concierge.
- **Atelier Admin System:** Dashboard metrik KPI terpadu, manajemen katalog produk komprehensif, manajemen kategori dinamis dengan auto-flush cache, manajemen status pesanan/concierge, dan manajemen data klien.
- **Fondasi Teknis:** Kompatibilitas 100% macOS, caching performa tinggi, security headers, dan test suite 100% lulus.

### Lingkup Post-MVP (Pengembangan Masa Depan)
- Integrasi Payment Gateway resmi (Xendit / Midtrans / Stripe) untuk pembayaran kartu kredit instan dan virtual account.
- Sinkronisasi pelacakan kurir logistik (JNE / Lion Parcel / Paxel) secara otomatis via API webhook.
- Virtual Styling 3D / AR fitting preview.
- Program loyalitas *Cercle Privé ZAFIRA* (tier membership khusus kolektor).

---

## 6. Metrik Keberhasilan (Success Metrics)

1. **Performa & Kualitas Teknis:**
   - 0 error fatal pada log runtime macOS.
   - Waktu muat awal storefront < 1.2 detik (First Contentful Paint didukung caching LRU).
   - 100% passing pada automated test suite.
2. **Kualitas Pengalaman Pengguna (UX):**
   - Alur pemesanan produk hingga konfirmasi concierge dapat diselesaikan dalam < 4 langkah intuitif.
   - Antarmuka admin atelier konsisten secara visual, bebas dari elemen monolitik yang membingungkan.
3. **Integritas Data:**
   - 0 data corruption atau bypass pada pengubahan kategori dan pemesanan.
