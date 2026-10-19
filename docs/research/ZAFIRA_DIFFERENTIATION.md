# Diferensiasi ZAFIRA: keputusan adopt/adapt/reject/investigate

Tanggal akses seluruh URL: **2026-10-11**. ID sumber (S, G, I, L) dan status akses: [metodologi](RESEARCH_METHODOLOGY.md). Bukti pola: [scorecard](COMPARATIVE_SCORECARD.md). Requirement dan acceptance kanonis: [sintesis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md).

## Aturan dokumen

[INTERPRETATION] Dokumen ini **tidak membuat requirement baru**. Setiap keputusan menaut ke FR-201–204 atau NFR-201–203 dan memakai acceptance dari sintesis kanonis. Bila sebuah pola belum punya requirement, statusnya `investigate`, bukan backlog.

[INTERPRETATION] Rantai per item: **prinsip → masalah → solusi → acceptance → verifikasi**. Status implementasi ZAFIRA tidak diaudit ulang di sini; status mengikuti sintesis kanonis.

| Keputusan | Arti |
|---|---|
| Adopt | Pakai pola apa adanya dalam batas MVP. |
| Adapt | Pakai prinsipnya, ubah bentuknya agar sesuai atelier tanpa pembayaran/OMS. |
| Reject | Jangan dipakai; bertentangan dengan prinsip atau tanpa data pendukung. |
| Investigate | Butuh bukti atau keputusan produk sebelum menjadi requirement. |

## Ringkasan

| ID | Pola | Keputusan | Requirement | Bukti utama |
|---|---|---|---|---|
| D-01 | Jalur layanan manusia (concierge) | Adapt | FR-203, FR-204 | I3, I4, L2, L8; S7 |
| D-02 | Material, ukuran, perawatan | Adapt | FR-202 | L8; S7 (Fendi, LV) |
| D-03 | Varian ukuran + monogram melekat pada item | Adopt | FR-201 | Sintesis (Etsy/Shopee); L2 |
| D-04 | Wilayah, mata uang, klaim keaslian jujur | Adapt | NFR-202 | L2, I3, I4, I5 |
| D-05 | Flash sale, countdown, pop-up interupsi | Reject | NFR-202, NFR-203 | I3; S7 (Cartier) |
| D-06 | Kanal WhatsApp untuk concierge | Investigate | — | I4 |
| D-07 | Layanan perawatan/perbaikan pascajual | Investigate | — | L8; S7 (LV) |
| D-08 | Navigasi prototype dan aksesibilitas modal | Adopt | NFR-201, NFR-203 | S8 |

## D-01 — Jalur layanan manusia (Adapt)

[CATATAN AUDIT — 2026-10-11] Sampel tujuh storefront pada scorecard berasal dari ekstraksi teks, bukan tujuh audit browser. Render parsial berikutnya dicatat terpisah dalam [metodologi](RESEARCH_METHODOLOGY.md#register-akses-storefront); tidak mengubah skor.

- **Prinsip:** Cooper *polite software*; Nielsen H1 (status sistem) dan H10 (bantuan); Shneiderman *closure*.
- **Masalah:**
  - [OBSERVATION — I3 https://www.lazada.co.id/, I4 https://www.blibli.com/, L2 https://www.mytheresa.com/int/en/women, L8 https://www.hermes.com/us/en/; akses 2026-10-11] Empat dari tujuh storefront yang berhasil diakses menampilkan jalur ke manusia secara eksplisit (Walmart hanya sebagian, lihat [scorecard](COMPARATIVE_SCORECARD.md)): live chat 24 jam (Lazada), BlibliCare telepon/WhatsApp/email, layanan 24/7 dalam 13 bahasa (Mytheresa), Find a store dan Customer Service (Hermès).
  - [FACT — S7 https://www.nngroup.com/articles/luxury-principles-ecommerce-design/; akses 2026-10-11] Studi NN/g 2022 mencatat store locator rusak di Bulgari dan Tom Ford; jalur layanan yang gagal merusak niat kunjungan.
  - [INTERPRETATION] Risiko ZAFIRA: konfirmasi palsu lebih merusak daripada tidak ada kanal sama sekali.
- **Solusi:** [RECOMMENDATION] Concierge tetap formulir tamu tanpa akun. Konfirmasi hanya muncul setelah server menerima. Akun hanya menampilkan permintaan milik pengguna dengan status yang benar-benar tersimpan. Tidak menyebut permintaan sebagai pesanan berbayar.
- **Acceptance (kanonis):**
  - FR-203: kegagalan POST tidak menampilkan keberhasilan; sukses memberi tindak lanjut yang benar-benar didukung.
  - FR-204: email yang sama tanpa ownership tidak membuka riwayat; stepper tidak mengklaim proses produksi fiktif.
- **Verifikasi:** Feature test POST `/concierge` untuk sukses dan gagal validasi. Uji browser dengan jaringan diputus: UI tidak menampilkan sukses. Feature test akun dengan dua pengguna berbeda.
- **Tidak diambil:** live chat 24 jam dan janji 24/7. [INTERPRETATION] Tidak ada bukti kapasitas staf ZAFIRA untuk janji itu.

## D-02 — Material, ukuran, perawatan (Adapt)

- **Prinsip:** Shneiderman *details-on-demand*; Nielsen H2 (dunia nyata); Norman *gulf of evaluation*.
- **Masalah:**
  - [FACT — S7; akses 2026-10-11] NN/g 2022: halaman Fendi tanpa foto saat dipakai; partisipan butuh 12 menit di tiga situs untuk memahami ukuran tas. Filter "Iconic" Louis Vuitton memakai nama lini tanpa penjelasan.
  - [OBSERVATION — L8; akses 2026-10-11] Hermès menampilkan "How to tie your scarf" dan "Styled in Silk".
  - [OBSERVATION — L2; akses 2026-10-11] Mytheresa mencantumkan ukuran tersedia per produk.
- **Solusi:** [RECOMMENDATION] PDP menampilkan dimensi, bahan dan perawatan **hanya bila data produk ada**. Panduan ukuran tampil hanya untuk produk yang memiliki ukuran. Foto konteks dipakai hanya bila foto itu benar-benar tersedia; jangan buat ilustrasi palsu.
- **Acceptance (kanonis, FR-202):** produk tanpa ukuran tidak menawarkan size chart generik; klaim tanpa sumber data tidak tampil.
- **Verifikasi:** Feature test PDP untuk produk dengan dan tanpa ukuran. Pencarian teks UI untuk klaim material hard-coded.
- **Tidak diambil:** rekomendasi ukuran ML, avatar 3D, profil tubuh. [FACT — sintesis] NFR-202 menghapus profil tubuh.

## D-03 — Varian ukuran + monogram melekat pada item (Adopt)

- **Prinsip:** Norman *signifier* dan *constraint*; Nielsen H6 (*recognition rather than recall*) dan H5 (pencegahan kesalahan).
- **Masalah:**
  - [FACT — sintesis kanonis, baseline historis pra-remediasi; status terkini di [matriks](../02-product/REQUIREMENTS_TRACEABILITY.md#rantai-bukti-keputusan-prioritas)] PDP belum meneruskan ukuran dan monogram ke tas. Batas tiga huruf adalah kontrak ZAFIRA, bukan aturan Etsy.
  - [OBSERVATION — L8; akses 2026-10-11] Hermès menampilkan "Made to measure" di navigasi; ini bukti bahwa personalisasi diposisikan sebagai layanan, bukan detail teknis konfigurasi.
  - [INTERPRETATION] Ekstraksi teks tidak membuktikan cara kompetitor menyimpan varian. Konfigurasi varian tidak diskor di scorecard.
- **Solusi:** [RECOMMENDATION] Ukuran dan monogram valid tersimpan per item tas dan ikut payload concierge. Dua kombinasi berbeda menjadi dua item. Ringkasan tas menampilkan pilihan itu. Harga dihitung dari database.
- **Acceptance (kanonis, FR-201):** dua pilihan ukuran/monogram menghasilkan dua item; backend mengabaikan harga browser dan menyimpan harga database.
- **Verifikasi:** Uji JS untuk penggabungan item tas. Feature test concierge dengan harga palsu dari browser. Uji browser: ringkasan tas menampilkan inisial terpilih.

## D-04 — Wilayah, mata uang, klaim keaslian jujur (Adapt)

- **Prinsip:** Nielsen H1 dan H2; Krug *don't make me think*; Norman *reflective* (kepercayaan).
- **Masalah:**
  - [OBSERVATION — L2; akses 2026-10-11] Mytheresa menyatakan pengiriman ke Indonesia dalam EUR dan "Authentic products guaranteed".
  - [OBSERVATION — I3, I4, I5; akses 2026-10-11] Lazada "LazMall 100% original", Blibli "Pasti ORI", Zalora klaim 100% asli. [INTERPRETATION] Semua klaim ini adalah klaim penerbit; dokumen ini tidak memverifikasi layanan di baliknya.
  - [FACT — sintesis kanonis] ZAFIRA memiliki klaim palsu: telemetri, ledger, sertifikat, profil advisor, dan valuta selain IDR.
- **Solusi:** [RECOMMENDATION] Tampilkan harga hanya dalam IDR. Sebut wilayah layanan yang benar-benar dilayani. Klaim keaslian hanya dalam bentuk yang dapat dibuktikan (misalnya asal atelier dan bahan dari data produk). Hapus sertifikat, ledger dan advisor sintetis.
- **Acceptance (kanonis, NFR-202):** pencarian UI dan uji halaman tidak menemukan klaim hard-coded atau selector USD/EUR.
- **Verifikasi:** `grep` pada `resources/views` dan `resources/js` untuk `USD`, `EUR`, `ledger`, `sertifikat`, `blockchain`. Uji browser halaman publik utama.
- **Tidak diambil:** retur gratis 30 hari dan "retur alasan apa pun". [INTERPRETATION] Karya monogram bersifat personal; janji retur butuh keputusan bisnis, bukan salinan benchmark.

## D-05 — Flash sale, countdown, pop-up interupsi (Reject)

- **Prinsip:** Nielsen H8 (estetika minimalis); Norman *visceral*; anti-dark-pattern; Shneiderman *keep users in control*.
- **Masalah:**
  - [OBSERVATION — I3; akses 2026-10-11] Lazada menampilkan Flash Sale dengan countdown, voucher, dan ajakan unduh app.
  - [OBSERVATION — L2; akses 2026-10-11] Mytheresa memuat kategori Sale di menu, tanpa tekanan waktu yang teramati.
  - [FACT — S7; akses 2026-10-11] NN/g 2022: overlay permintaan feedback menginterupsi partisipan yang sedang melihat gelang Cartier US$4.000.
- **Solusi:** [RECOMMENDATION] Tidak ada countdown, stok-langka palsu, diskon berkedip, atau pop-up yang muncul tanpa tindakan pengguna. Modal hanya terbuka atas aksi pengguna dan bisa ditutup.
- **Acceptance (kanonis):**
  - NFR-202: tidak ada klaim hard-coded tanpa data.
  - NFR-203: modal memenuhi keyboard, nama aksesibel, fokus, dan Escape.
- **Verifikasi:** `grep` untuk `countdown`, `setInterval` pada elemen promosi, dan modal yang terbuka otomatis. Uji browser: muat halaman tanpa interaksi selama 30 detik, catat overlay yang muncul.

## D-06 — Kanal WhatsApp untuk concierge (Investigate)

- **Prinsip:** Cooper *eliminating excise*; Brown *viability*.
- **Bukti:** [OBSERVATION — I4; akses 2026-10-11] BlibliCare menyediakan WhatsApp selain telepon dan email. [INTERPRETATION] Satu observasi tidak membuktikan preferensi pelanggan luxury ZAFIRA.
- **Pertanyaan terbuka:** Apakah atelier punya nomor bisnis terverifikasi dan kapasitas membalas? Bagaimana data permintaan dari WhatsApp masuk ke admin tanpa duplikasi dengan formulir?
- **Syarat naik menjadi requirement:** keputusan pemilik bisnis, kebijakan privasi untuk data chat, dan acceptance yang dapat diuji. Sampai saat itu formulir FR-203 tetap satu-satunya jalur.

## D-07 — Perawatan/perbaikan pascajual (Investigate)

- **Prinsip:** Norman *reflective* (nilai warisan); Shneiderman *closure*.
- **Bukti:**
  - [OBSERVATION — L8; akses 2026-10-11] Hermès menampilkan "Maintenance and repair" di navigasi.
  - [FACT — S7; akses 2026-10-11] NN/g 2022 mencatat proses perbaikan online Louis Vuitton dengan video chat.
- **Pertanyaan terbuka:** Apakah atelier menawarkan perbaikan? Berapa biaya dan jangka waktunya?
- **Syarat naik menjadi requirement:** layanan nyata ada. [INTERPRETATION] Sintesis kanonis tidak menemukan alasan berbasis bukti untuk menambah modul baru di MVP. Halaman perawatan statis hanya boleh memuat instruksi yang didukung data (lihat D-02).

## D-08 — Navigasi prototype dan aksesibilitas modal (Adopt)

- **Prinsip:** Krug *trunk test*; Nielsen H3 (*emergency exits*); WCAG 2.2 AA.
- **Masalah:**
  - [FACT — sintesis kanonis, baseline historis; uji Node fokus/Escape kemudian lulus, audit browser/WCAG penuh belum dijalankan — lihat [matriks](../02-product/REQUIREMENTS_TRACEABILITY.md#rantai-bukti-keputusan-prioritas)] Modal belum terbukti memenuhi keyboard/fokus/Escape. Navigasi prototype tidak boleh tampil di produksi.
  - [FACT — S8 https://www.w3.org/TR/WCAG22/; akses 2026-10-11] SC 2.4.7 (fokus terlihat), SC 2.5.8 (target minimal 24×24px), SC 4.1.2 (nama, peran, nilai).
  - [INTERPRETATION] Aksesibilitas kompetitor tidak diskor; tidak ada klaim bahwa ZAFIRA lebih aksesibel dari kompetitor.
- **Solusi:** [RECOMMENDATION] Sembunyikan navigasi prototype di produksi. Semua modal dan drawer: fokus masuk saat buka, terjebak di dalam, kembali ke pemicu saat tutup, Escape menutup, tombol ikon punya nama aksesibel.
- **Acceptance (kanonis):**
  - NFR-201: uji lingkungan produksi tidak menemukan link/demo trigger; gambar dan login tetap termuat.
  - NFR-203: browser menguji Tab, Escape, fokus kembali, zoom/mobile; WCAG 2.2 AA.
- **Verifikasi:** Feature test dengan `APP_ENV=production`. Uji browser keyboard-only dan axe-core per halaman publik.

## Pola yang ditolak tanpa item terpisah

[RECOMMENDATION — dasar: [sintesis kanonis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md#belum-dapat-disimpulkan) dan [benchmark luxury](../02-product/benchmark/BENCHMARK_LUXURY_FASHION.md)] Tidak diambil pada MVP: livestream commerce (G9), loyalty/poin (I4), OMS dan pelacakan kurir (I3, I4), pembayaran/COD/cicilan (I3, I4), tier VIP berbayar, AR/AI. Alasan: tidak ada requirement, tidak ada backend, dan tidak ada bukti dampak untuk ZAFIRA.

[INTERPRETATION] Benchmark luxury lama mengusulkan booking appointment dan waitlist (A4, A5). Sintesis kanonis yang lebih baru menyatakan kalender appointment tidak terbukti di backend dan tidak menaikkannya menjadi requirement. Dokumen ini mengikuti sintesis; kedua pola berstatus investigate.

## Batas bukti

- [INTERPRETATION] Bukti kompetitor berasal dari teks homepage 7 storefront dan satu studi pengguna 2022. Tidak ada audit browser, transaksi, atau uji pengguna.
- [INTERPRETATION] Klaim pemasaran kompetitor tidak diverifikasi.
- [INTERPRETATION] Tidak ada baseline konversi, waktu tugas, atau kepuasan ZAFIRA. Tidak ada klaim bahwa keputusan di atas menaikkan konversi.

## Protokol audit browser lanjutan

[RECOMMENDATION — S4, S6, S8; akses 2026-10-11] Ikuti [protokol metodologi](RESEARCH_METHODOLOGY.md#protokol-lanjutan). Untuk ZAFIRA, jalankan tugas yang sama pada prototype:

1. Buka katalog, filter kategori, buka PDP.
2. Pilih ukuran dan monogram; tambahkan dua kombinasi berbeda ke tas (D-03).
3. Buka tas; pastikan dua item dan pilihan masing-masing terlihat.
4. Kirim concierge dengan input tidak valid, lalu valid; ulangi saat jaringan diputus (D-01).
5. Ulangi langkah 1–4 hanya dengan keyboard; uji Escape dan fokus kembali di setiap modal (D-08).
6. Ulangi di viewport 375px dan zoom 200%.
7. Catat URL, tanggal, viewport, screenshot, langkah yang berhasil dan gagal.

## Lensa analitis (bukan persetujuan independen)

Lensa berikut adalah kerangka analisis internal, bukan penilaian atau endorsement dari tokoh tersebut. Daftar awal tercatat dalam [`COUNCIL_AUDIT_REPORT.md`](../05-quality/COUNCIL_AUDIT_REPORT.md); file itu berada di luar cakupan edit.

| Lensa | Penggunaan dalam audit |
|---|---|
| Norman, Nielsen, Shneiderman, Cooper, Engelbart, Brown, Krug | Affordance, status sistem, kontrol pengguna, efisiensi, pemahaman dan tujuan pengguna. |
| Léonie Watson | Semantik, nama aksesibel dan aksesibilitas interaksi; audit WCAG aktual tetap belum dijalankan. |
| Cagan, Fowler, Evans, Beck | Nilai produk, batas domain, desain evolutif dan umpan balik pengujian. |
| Kleppmann, Stonebraker, Vogels, Hightower, Lamport | Model data, storage, sistem terdistribusi, operabilitas dan konsistensi. |
| Forsgren, Treynor Sloss, Majors, Gregg | Pengiriman perangkat lunak, reliabilitas, observabilitas dan kinerja. |
| Schneier, Shostack, Janca | Pemodelan ancaman, keamanan dan privasi. |
| Berners-Lee, Cerf, Knuth | Standar web, interoperabilitas dan ketepatan algoritmik. |
| Onno W. Purbo | Konteks jaringan dan akses internet di Indonesia; tidak ada klaim persetujuan. |
| Hunt | Identitas ambigu (misalnya Troy Hunt atau Andrew Hunt); tidak diasumsikan. |
| Hudson | Identitas ambigu (misalnya Paul Hudson); tidak diasumsikan. |

Referensi primer tokoh tidak diakses dalam audit lokal ini; atribusi bibliografis belum diverifikasi. Lensa tidak menggantikan bukti source, test, atau penelitian pengguna.
