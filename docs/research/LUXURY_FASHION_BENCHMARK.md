# Benchmark luxury fashion e-commerce: kurasi, craft & batasan bukti

Tanggal akses seluruh URL: **2026-10-11**. Metode dan register URL: [metodologi](RESEARCH_METHODOLOGY.md). Dokumen ini melengkapi [benchmark luxury](../02-product/benchmark/BENCHMARK_LUXURY_FASHION.md) dan [sintesis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md).

## Status akses situs luxury (2026-10-11)

Ekstraksi teks atas sejumlah situs luxury tidak berhasil; penyebab spesifik (termasuk apakah ada proteksi bot) belum diverifikasi:
- **Mytheresa** (L2): **SUCCESS** — teks Women’s homepage terambil lengkap; pengiriman ke Indonesia dalam EUR tertera [OBSERVATION — https://www.mytheresa.com/int/en/women].
- **Hermès** (L8): **SUCCESS** — teks US homepage terambil lengkap; navigasi atelier, craft, dan instruksi styling tertera [OBSERVATION — https://www.hermes.com/us/en/].
- **NET-A-PORTER** (L1, L10): **BLOCKED (HTTP 403)** — tidak ada data scraping langsung.
- **FARFETCH** (L3, L9): **BLOCKED (HTTP 403 / Timeout)** — tidak ada data scraping langsung.
- **SSENSE** (L4, L11): **BLOCKED (HTTP 403)** — tidak ada data scraping langsung.
- **Louis Vuitton** (L5): **BLOCKED (HTTP 403)** — tidak ada data scraping langsung.
- **Dior** (L6): **BLOCKED (HTTP 403)** — tidak ada data scraping langsung.
- **Gucci** (L7): **BLOCKED (Timeout)** — tidak ada data scraping langsung.

[INTERPRETATION] Status `BLOCKED` bukan indikasi bahwa situs tersebut tidak berfungsi. Penyebab penolakan atau timeout belum diketahui; proteksi bot hanya salah satu kemungkinan. Prinsip UX dari NN/g S7 dan Baymard S6 adalah rujukan sekunder, bukan bukti perilaku terkini setiap platform yang terblokir.

## Observasi primer dari situs yang berhasil diakses

### 1. Mytheresa (Multi-brand Luxury Curator)
- [OBSERVATION — L2 https://www.mytheresa.com/int/en/women; akses 2026-10-11]
  - *Kurasi dan Navigasi:* Mega menu terstruktur tenang (Women, Men, Kids, LIFE / New Arrivals, Designers, Clothing, Shoes, Bags, Accessories, Jewelry, Vacation, Sale). Menampilkan kurasi runway eksklusif (Saint Laurent, Brunello Cucinelli, Roger Vivier, Chloé, Bottega Veneta).
  - *Transparansi Internasional:* Menyatakan secara eksplisit negara pengiriman aktif (Indonesia) dan mata uang penagihan (EUR €).
  - *Nilai Layanan:* Menegaskan jaminan produk autentik, pengembalian gratis 30 hari, dan layanan pelanggan 24/7 dalam 13 bahasa.
  - *Editorial Styling:* Menggunakan narasi editorial bertema (*"The Knitwear Edit"*, *"Sole Statements"*, *"Spotted in Style"*).
  - *HCI Lens:* Mendukung Norman *reflective* (prestise dan kurasi berselera) serta Shneiderman *overview first* (katalog terorganisir per tema dan desainer).

### 2. Hermès (Maison & Craftsmanship Pure-play)
- [OBSERVATION — L8 https://www.hermes.com/us/en/; akses 2026-10-11]
  - *Narasi Kriya & Warisan:* Identitas ditegaskan sejak awal: *"Contemporary artisans since 1837"*. Fokus pada material kriya (kulit, sutra, kasmir, perhiasan emas/perak, pelana kuda/equestrian).
  - *Edukasi & Styling Nyata:* Menampilkan modul *"How to tie your scarf"* dan *"Styled in Silk"* — memberi panduan praktis cara mengenakan produk tanpa jargon berlebihan.
  - *Layanan Purna Jual & Personalisasi:* Mencantumkan layanan *Made to measure*, *Maintenance and repair*, dan *Find a store* di tingkat navigasi utama.
  - *Transparansi Operasional:* Memasang pengumuman operasional jadwal gudang dan pengiriman secara jujur di banner atas.
  - *HCI Lens:* Mendukung Nielsen *Match between system and real world* (bahasa kriya autentik) dan Brown *Desirability* (keinginan memiliki warisan budaya dan keahlian tangan).

## Pelajaran mendalam dari riset HCI Luxury NN/g (2022)

[FACT — S7 NN/g (Kate Moran), terbit 26 Juni 2022, https://www.nngroup.com/articles/luxury-principles-ecommerce-design/; akses 2026-10-11]
Riset Nielsen Norman Group terhadap situs-situs luxury mengungkapkan empat prinsip utama dan jebakan implementasi digitalnya:

1. **Eksklusivitas vs Hambatan yang Mengganggu:**
   - *Paradoks Kemewahan:* Merek seperti Hermès mempertahankan status Birkin bag dengan membatasi pasokan dan sengaja tidak menjualnya secara online. Namun, membatasi ketersediaan barang berbeda dengan menyembunyikan informasi produk yang membuat frustrasi.
   - *Jebakan Detail PDP:* Partisipan riset mengeluhkan tas Fendi yang tidak menampilkan foto konteks saat dikenakan orang, memaksa pengguna mencari di tiga situs departemen store selama 12 menit hanya untuk mengetahui skala ukuran tas.
   - *Transparansi Harga:* Menyembunyikan harga sepenuhnya (misal: Bulgari yang mengharuskan hover untuk melihat harga) membuat frustrasi bahkan bagi pembeli sangat kaya, karena setiap konsumen memiliki kisaran anggaran (*price range*) dalam benaknya.
   - *Rekomendasi ZAFIRA:* Tampilkan foto karya pada model/manekin untuk skala nyata, sertakan dimensi detail (cm/inci), bahan, dan panduan perawatan. Cantumkan harga atau estimasi rentang harga kriya secara transparan.

2. **Perhatian Terhadap Detail (*Attention to Detail*):**
   - *Anti-Pola Pop-up:* Partisipan riset menyatakan rasa muak ketika sedang melihat gelang Cartier senilai $4.000 tiba-tiba diinterupsi oleh pop-up survei kepuasan situs yang murahan. Hal ini merusak persepsi kemewahan seketika.
   - *Kerusakan Tautan/Fitur:* Tautan pencari butik (*store locator*) yang rusak pada Tom Ford dan Bulgari membuat konsumen membatalkan niat kunjungan offline.
   - *Rekomendasi ZAFIRA:* Bebaskan storefront ZAFIRA dari pop-up agresif, kupon countdown, banner diskon berkedip, atau survei interuptif. Setiap piksel dan transisi harus bersih, tenang, dan berfungsi sempurna.

3. **Gaya Khas vs Kemudahan Membaca (*Distinctive Identity vs Usability*):**
   - *Kegagalan Tipografi:* Situs Tom Ford menggunakan teks huruf kapital berwarna abu-abu muda (*all-caps light gray*) yang sulit dipindai menurut peserta. Artikel tidak melaporkan rasio kontras terukur; pelanggaran WCAG tidak dapat disimpulkan.
   - *Keberhasilan Rolex:* Rolex berhasil menyeimbangkan estetika minimalis mewah dengan tipografi berkontras tinggi dan keterbacaan yang sangat baik.
   - *Rekomendasi ZAFIRA:* Padukan tipografi serif klasik (*Bodoni Moda*) untuk tajuk prestisius dan sans-serif geometris (*Manrope*) berkontras tinggi (memenuhi WCAG 2.2 AA) untuk teks tubuh dan detail teknis.

4. **Hubungan Personal Mendalam (*Deep Personal Connections*):**
   - *Dukungan Digital untuk Perwakilan Kriya:* Konsumen luxury menghargai relasi jangka panjang dengan sales representative atau artisan (contoh di artikel: David Yurman yang merawat perhiasan klien selama bertahun-tahun). Artikel yang sama mencatat Gucci Digital Showroom (livestream one-on-one dengan tindak lanjut SMS) sebagai contoh terpisah.
   - *Rekomendasi ZAFIRA:* Gunakan alur concierge bukan untuk menggantikan interaksi manusia, melainkan sebagai jembatan pembuka janji temu pribadi antara Klien VIP dan artisan atelier.

## Kerangka Aksesibilitas WCAG 2.2 Level AA untuk Luxury

[FACT — S8 W3C WCAG 2.2 Recommendation; akses 2026-10-11]
Situs luxury sering kali gagal dalam aksesibilitas karena mengorbankan fungsi demi tampilan visual redup. ZAFIRA wajib mematuhi kriteria sukses Level AA:
- **Kontras Teks (SC 1.4.3):** Rasio kontras minimal 4.5:1 untuk teks normal dan 3:1 untuk teks besar terhadap latar belakang linen/ivory. Dilarang menggunakan teks abu-abu redup pada latar putih.
- **Kontras Elemen Non-Teks (SC 1.4.11):** Batas input, status pilihan swatch kain, dan indikator tombol memiliki kontras minimal 3:1.
- **Ukuran Target Sentuh (SC 2.5.8):** Target interaktif minimal 24×24px, dengan target yang direkomendasikan 44×44px untuk kenyamanan sentuhan mobile.
- **Indikator Fokus Terlihat (SC 2.4.7):** Setiap elemen interaktif yang dijelajahi dengan keyboard Tab harus memiliki cincin fokus yang tidak ambigu.
- **Nama Aksesibel (SC 4.1.2):** Semua tombol ikon (drawer keranjang, tutup modal, pemilih monogram) harus memiliki `aria-label` deskriptif dalam Bahasa Indonesia.

## Kesimpulan komparatif untuk ZAFIRA

| Dimensi | Pola yang didukung bukti | Label | Arah ZAFIRA |
|---|---|---|---|
| **Akses katalog** | NN/g 2022: harga hanya saat hover (Bulgari) membuat frustrasi; Birkin sengaja tidak dijual online (Hermès) | [FACT — S7] | [RECOMMENDATION] Harga atau rentang harga terlihat tanpa interaksi tambahan |
| **Konteks produk** | NN/g 2022: Fendi tanpa foto saat dipakai, 12 menit mencari ukuran | [FACT — S7] | [RECOMMENDATION] Dimensi akurat; foto konteks hanya bila ada fotonya (FR-202) |
| **Layanan manusia** | Mytheresa layanan 24/7; Hermès Find a store; NN/g: store locator rusak (Bulgari, Tom Ford) | [OBSERVATION — L2, L8], [FACT — S7] | [RECOMMENDATION] Concierge sebagai jalur ke manusia, sukses hanya setelah backend menerima (FR-203) |
| **Personalisasi** | Hermès Made to measure terlihat di navigasi | [OBSERVATION — L8] | [RECOMMENDATION] Monogram melekat pada item (FR-201); batas 3 karakter adalah kontrak ZAFIRA, bukan temuan benchmark |
| **Integritas informasi** | Klaim keaslian di Mytheresa adalah klaim penerbit | [OBSERVATION — L2] | [RECOMMENDATION] Hapus klaim tanpa data (NFR-202) |
| **Gangguan** | NN/g 2022: overlay survei menginterupsi (Cartier) | [FACT — S7] | [RECOMMENDATION] Tanpa pop-up interuptif |
