# Scorecard komparatif: rubrik bukti, bukan liga kualitas

Tanggal akses seluruh URL: **2026-10-11**. ID sumber (S, G, I, L) dan status akses: [metodologi](RESEARCH_METHODOLOGY.md). Keputusan MVP tetap di [sintesis kanonis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md).

## Aturan penilaian

[INTERPRETATION] Skor di bawah mengukur **apa yang terlihat di teks halaman yang berhasil diambil**, bukan kualitas UX, konversi, atau kepuasan pengguna. Ekstraksi teks tidak membuktikan tata letak, interaksi, kontras, fokus keyboard atau perilaku mobile. Karena itu aksesibilitas dan konfigurasi varian **tidak diberi skor** untuk satu situs pun.

| Nilai | Arti |
|---|---|
| 2 | Pola teramati eksplisit dan spesifik di teks halaman [OBSERVATION]. |
| 1 | Pola teramati sebagian atau umum (misalnya hanya label menu tanpa detail). |
| — | Tidak teramati pada teks yang terambil. **Bukan bukti bahwa fitur tidak ada.** |
| BT | Belum teruji: akses BLOCKED atau HTTP-ONLY (lihat register). |

Klaim pemasaran (misalnya "100% asli") dicatat sebagai klaim penerbit, bukan hasil verifikasi layanan. Tidak ada total skor; menjumlahkan dimensi berbeda akan menghasilkan peringkat palsu.

## Dimensi

| Kode | Dimensi | Prinsip HCI | Requirement ZAFIRA terkait |
|---|---|---|---|
| A | Janji keaslian / narasi kriya dinyatakan | Norman (reflective), Nielsen H2 | FR-202, NFR-202 |
| B | Jalur layanan manusia terlihat (chat, telepon, butik, appointment) | Cooper (polite software), Nielsen H10 | FR-203 |
| C | Kejelasan wilayah, mata uang, opsi layanan atau retur | Nielsen H1, Krug | NFR-202 |
| D | Edukasi produk: styling, perawatan, perbaikan | Nielsen H2, Shneiderman (details-on-demand) | FR-202 |
| E | Sinyal tekanan atau promosi yang teramati (deskriptif, tidak diskor) | Nielsen H8, anti-dark-pattern | NFR-202 |

## Matriks storefront yang berhasil diakses

| ID | Platform | A | B | C | D | E (teramati) | Bukti [OBSERVATION, akses 2026-10-11] |
|---|---|:---:|:---:|:---:|:---:|---|---|
| G6 | Walmart US | — | 1 | 2 | — | Deals, reorder | Teks "Pickup or delivery?", menu Departments dan Services — https://www.walmart.com/ |
| G8 | MercadoLibre | — | — | 1 | — | — | Hanya pemilih negara — https://www.mercadolibre.com/ |
| I3 | Lazada ID | 2 | 2 | 1 | — | **Flash Sale**, ajakan unduh app | LazMall 100% original, live chat 24 jam, Lacak Pesanan, COD — https://www.lazada.co.id/ |
| I4 | Blibli | 2 | 2 | 2 | — | — | "Pasti ORI", BlibliCare telepon/WhatsApp/email, "Jaminan tepat waktu", Click & Collect — https://www.blibli.com/ |
| I5 | Zalora ID | 2 | — | 1 | — | — | Klaim 100% asli, gratis 30 hari pengembalian — https://www.zalora.co.id/ |
| L2 | Mytheresa | 2 | 2 | 2 | 1 | Kategori Sale di menu | Jaminan autentik, layanan 24/7 dalam 13 bahasa, pengiriman ke Indonesia dalam EUR, edit editorial — https://www.mytheresa.com/int/en/women |
| L8 | Hermès US | 2 | 2 | 1 | 2 | — | "Contemporary artisans since 1837", Made to measure, Find a store, Maintenance and repair, "How to tie your scarf", pengumuman operasional — https://www.hermes.com/us/en/ |

Belum teruji (BT) pada 2026-10-11: Amazon (G1), Taobao (G2), Alibaba.com (G3), Temu (G4), JD (G5), eBay (G7), TikTok Shop (G9), Kuaishou (G10), Shopee (I1), Tokopedia (I2), NET-A-PORTER (L1, L10), Farfetch (L3, L9), SSENSE (L4, L11), Louis Vuitton (L5), Dior (L6), Gucci (L7). Tidak ada skor yang ditebak untuk platform ini.

[INTERPRETATION] Pola yang paling konsisten di sampel berhasil adalah **A** (janji keaslian, 5 dari 7 halaman) dan **B** (jalur layanan manusia, 5 dari 7 halaman; Walmart hanya sebagian). Enam dari tujuh halaman menampilkan setidaknya salah satunya. Edukasi produk (**D**) hanya teramati jelas di Hermès. Sinyal tekanan waktu eksplisit hanya teramati di Lazada (Flash Sale).

## Temuan riset sekunder (tidak diskor)

[FACT — S7 Kate Moran, "Applying Luxury Principles to Ecommerce Design", NN/g, 26 Juni 2022, https://www.nngroup.com/articles/luxury-principles-ecommerce-design/; akses 2026-10-11] Temuan studi pengguna 2022 berikut tidak menggambarkan kondisi situs pada 2026 sehingga **tidak dimasukkan ke matriks**:

| Brand | Temuan studi | Dimensi |
|---|---|---|
| Fendi | Halaman produk tanpa foto saat dipakai. Partisipan butuh 12 menit di tiga situs untuk memahami ukuran tas. | D |
| Bulgari | Harga hanya muncul saat hover di halaman daftar; store locator rusak. | C, B |
| Tom Ford | Teks all-caps abu-abu muda sulit dipindai; store locator rusak. | Aksesibilitas, B |
| Cartier | Overlay permintaan feedback menginterupsi partisipan yang melihat gelang US$4.000. | E |
| Rolex | Desain minimalis tetap mudah dibaca menurut partisipan. | Aksesibilitas |
| Louis Vuitton | Filter "Iconic" memakai nama lini tanpa penjelasan; proses perbaikan online dengan video chat. | D, B |
| Gucci | Narasi sejarah tas Bamboo di situs; Digital Showroom (livestream one-on-one, follow-up SMS). | A, B |

## Prioritas pola untuk ZAFIRA

| Pola | Kekuatan bukti | Prioritas | Keputusan rinci |
|---|---|---|---|
| Jalur layanan manusia yang jelas (B) | 5 storefront teramati + temuan NN/g | Tinggi | [Diferensiasi D-01](ZAFIRA_DIFFERENTIATION.md) |
| Detail material, ukuran, perawatan (D) | 1 storefront + temuan Fendi/LV | Tinggi | [D-02](ZAFIRA_DIFFERENTIATION.md) |
| Mata uang dan wilayah jujur (C) | 3 storefront eksplisit | Tinggi | [D-04](ZAFIRA_DIFFERENTIATION.md) |
| Janji keaslian (A) | 5 storefront, semua klaim penerbit | Sedang; hanya bila ada data nyata | [D-04](ZAFIRA_DIFFERENTIATION.md) |
| Flash sale, interupsi pop-up (E) | Lazada teramati + temuan Cartier | Tolak | [D-05](ZAFIRA_DIFFERENTIATION.md) |

## Celah yang tidak bisa ditutup dokumen ini

[INTERPRETATION] ZAFIRA sendiri **tidak diskor** di sini karena dokumen ini tidak menjalankan audit browser atas prototype. Status kekurangan ZAFIRA di bawah adalah baseline historis pra-remediasi dari sintesis kanonis; status terkini ada di [matriks ketertelusuran](../02-product/REQUIREMENTS_TRACEABILITY.md#rantai-bukti-keputusan-prioritas). Baseline: PDP belum meneruskan ukuran dan monogram ke tas (FR-201), dan modal belum terbukti memenuhi keyboard/fokus/Escape (NFR-203). Skor aksesibilitas kompetitor membutuhkan audit browser sesuai protokol di [metodologi](RESEARCH_METHODOLOGY.md#protokol-lanjutan).
