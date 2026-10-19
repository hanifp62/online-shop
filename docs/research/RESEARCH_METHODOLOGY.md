# Metodologi dan register bukti benchmark ZAFIRA

Tanggal penelitian dan seluruh akses URL: **2026-10-11**. Status: riset sekunder dan ekstraksi teks halaman publik; **bukan audit browser, transaksi, atau penelitian pengguna**.

## Peran dokumen

[FACT — dokumen lokal dibaca 2026-10-11] [Sintesis kanonis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md) sudah memiliki keputusan MVP dan requirement FR-201–204 serta NFR-201–203. Paket ini menambah register bukti, cakupan sampel, dan penilaian keterterapan; tidak mengganti sintesis atau memvalidasi ulang status implementasinya.

- [Global](GLOBAL_ECOMMERCE_BENCHMARK.md): sepuluh perusahaan menurut sumber GMV yang konsisten.
- [Indonesia](INDONESIAN_ECOMMERCE_BENCHMARK.md): empat marketplace dan satu pembanding fashion.
- [Luxury](LUXURY_FASHION_BENCHMARK.md): tujuh sampel wajib dan Hermès sebagai pembanding tambahan yang dapat diakses.
- [Scorecard](COMPARATIVE_SCORECARD.md): rubrik bukti dan prioritas pola, bukan liga kualitas situs.
- [Diferensiasi](ZAFIRA_DIFFERENTIATION.md): keputusan dan acceptance criteria tertaut requirement kanonis.

## Label dan aturan inferensi

| Label | Makna |
|---|---|
| FACT | Pernyataan sumber primer atau standar; klaim pemasaran tetap diatribusikan kepada penerbit, bukan dianggap hasil audit layanan. |
| THIRD-PARTY METRIC | Estimasi/pengukuran pihak ketiga; selalu sebut periode, unit, wilayah dan entitas. |
| OBSERVATION | Teks benar-benar dikembalikan alat pada URL tertentu; tidak membuktikan tata letak, interaksi atau aksesibilitas. |
| INTERPRETATION | Kesimpulan analitis atau batas pengetahuan; bukan fakta implementasi kompetitor. |
| RECOMMENDATION | Usulan untuk ZAFIRA; bukan fitur yang sudah ada atau bukti peningkatan konversi. |

[INTERPRETATION] `BLOCKED` berarti bukti yang dicari tidak berhasil diperoleh. Substatus membedakan HTTP 403, timeout, security check, hasil kosong dan respons nonhalaman. Timeout tidak membuktikan anti-bot. HTTP 200 tanpa inspeksi konten hanya `HTTP-ONLY`; tidak dihitung sebagai keberhasilan observasi UX. `NOT TESTED` berbeda dari fitur tidak ada.

[INTERPRETATION] GMV adalah nilai perdagangan melalui platform menurut definisi sumber; bukan revenue perusahaan atau laba. Traffic adalah kunjungan domain, bukan jumlah pembeli atau aktivitas aplikasi. Rank web, GMV, revenue, unduhan dan preferensi pengguna tidak boleh dicampur. Perusahaan induk, merek, domain dan kanal aplikasi adalah unit berbeda.

## Sumber inti

Seluruh URL berikut diakses **2026-10-11**. ID sumber pada dokumen lain merujuk tabel ini beserta tanggal aksesnya.

| ID | URL | Status dan batas penggunaan |
|---|---|---|
| S1 | https://www.similarweb.com/top-websites/e-commerce-and-shopping/ | SUCCESS, teks penuh; ranking domain global kategori Ecommerce & Shopping September 2026. |
| S2 | https://www.similarweb.com/top-websites/indonesia/e-commerce-and-shopping/ | SUCCESS, teks penuh; kategori sama, Indonesia September 2026; lima posisi terbuka. |
| S3 | https://ecdb.com/blog/the-top-companies-in-e-commerce-worldwide/5223 | SUCCESS, teks penuh; artikel 10 Juli 2026, estimasi GMV perusahaan 2025. |
| S4 | https://baymard.com/ux-benchmark | SUCCESS pada penelitian awal; cek ulang HTTP 200; kerangka benchmark, bukan skor kompetitor yang kami miliki. |
| S5 | https://baymard.com/lists/cart-abandonment-rate | SUCCESS pada penelitian awal; cek ulang HTTP 200; agregat lintas studi, bukan baseline ZAFIRA. |
| S6 | https://baymard.com/research/luxury-goods | SUCCESS pada penelitian awal dan hasil pencarian; cek ulang HTTP 200. Ringkasan publik menyebut 8 situs di satu bagian dan 9 pada metodologi; tidak dipakai sebagai angka sampel tunggal. |
| S7 | https://www.nngroup.com/articles/luxury-principles-ecommerce-design/ | SUCCESS, teks artikel; terbit 26 Juni 2022, bukan audit kondisi semua brand tahun 2026. |
| S8 | https://www.w3.org/TR/WCAG22/ | SUCCESS; teks dibaca ulang pada audit 2026-10-11 (Recommendation 12 Desember 2024). Standar, bukan sertifikasi situs. |

[INTERPRETATION] Angka agregat Baymard tidak diturunkan menjadi estimasi kehilangan penjualan ZAFIRA. Dataset, kondisi checkout dan populasi berbeda. Angka yang tidak dibutuhkan keputusan sengaja tidak disalin.

## Register akses storefront

Seluruh percobaan: **2026-10-11**, tanpa login. Region pada URL dicatat; lokasi jaringan, viewport, cookie/geolokasi dan eksperimen A/B tidak dikontrol. Ekstraksi bukan render desktop/mobile.

| ID | URL | Hasil |
|---|---|---|
| G1 | https://www.amazon.com/ | BLOCKED — hasil ekstraksi kosong. |
| G2 | https://www.taobao.com/ | HTTP-ONLY — 200; konten belum diaudit. |
| G3 | https://www.alibaba.com/ | HTTP-ONLY — 200; tidak mewakili Taobao/Tmall. |
| G4 | https://www.temu.com/ | BLOCKED — curl 000 setelah batas waktu 15 detik. |
| G5 | https://www.jd.com/ | HTTP-ONLY — 200; konten belum diaudit. |
| G6 | https://www.walmart.com/ | SUCCESS — teks homepage US. |
| G7 | https://www.ebay.com/ | BLOCKED — HTTP 403. |
| G8 | https://www.mercadolibre.com/ | SUCCESS — pemilih negara, bukan katalog lokal. |
| G9 | https://shop.tiktok.com/ | BLOCKED — teks “Security Check”, meskipun cek HTTP 200. |
| G10 | https://www.kuaishou.com/ | BLOCKED — JSON result:2, bukan halaman belanja, meskipun cek HTTP 200. |
| I1 | https://shopee.co.id/ | BLOCKED — hasil ekstraksi kosong. |
| I2 | https://www.tokopedia.com/ | BLOCKED — timeout. |
| I3 | https://www.lazada.co.id/ | SUCCESS — teks homepage ID. |
| I4 | https://www.blibli.com/ | SUCCESS — teks homepage ID. |
| I5 | https://www.zalora.co.id/ | SUCCESS — teks homepage ID. |
| L1 | https://www.net-a-porter.com/en-us/ | BLOCKED — HTTP 403. |
| L2 | https://www.mytheresa.com/int/en/women | SUCCESS — women; halaman menyatakan pengiriman Indonesia dan EUR. |
| L3 | https://www.farfetch.com/ | BLOCKED — HTTP 403. |
| L4 | https://www.ssense.com/en-us | BLOCKED — HTTP 403. |
| L5 | https://www.louisvuitton.com/eng-e1/homepage | BLOCKED — HTTP 403. |
| L6 | https://www.dior.com/en_int | BLOCKED — HTTP 403. |
| L7 | https://www.gucci.com/us/en/ | BLOCKED — timeout pada fetch dan cek ulang curl. |
| L8 | https://www.hermes.com/us/en/ | SUCCESS — teks homepage US. |
| L9 | https://www.farfetch.com/faqs | BLOCKED — timeout. |
| L10 | https://www.net-a-porter.com/en-us/content/help/contact-us/ | BLOCKED — HTTP 403. |
| L11 | https://www.ssense.com/en-us/customer-service | BLOCKED — HTTP 403. |

[OBSERVATION — catatan audit 2026-10-11] Register di atas mencatat ekstraksi teks/HTTP. Render Chrome headless berikutnya pada tanggal sama berhasil memuat L7 Gucci, I2 Tokopedia, G1 Amazon, G2 Taobao, G3 Alibaba, G5 JD, L9 FAQ Farfetch, dan L10 kontak NET-A-PORTER; lihat [bukti render kompetitor](../execution/evidence/2026-10-11/phase2-competitor.md). Hasil render itu tidak mengubah skor atau keputusan pada dokumen riset ini dan bukan audit UX penuh.

[OBSERVATION — cek ulang 2026-10-11, bukti [`source-recheck-2009.txt`](../execution/evidence/2026-10-11/phase4/rescue-research/source-recheck-2009.txt)] Delapan URL sumber inti (S1–S8) mengembalikan kode 200 pada cek ulang. Berkas itu hanya mencatat kode status; isi halaman tidak diekstraksi ulang, sehingga statusnya `HTTP-ONLY` dan tidak menambah observasi isi, skor, atau peringkat apa pun. Rekonsiliasi batas seluruh bukti ada di [catatan rekonsiliasi](../execution/evidence/2026-10-11/phase4/rescue-research/reconciliation.md).

[OBSERVATION — render Chrome headless 2026-10-11, lihat [bukti render kompetitor](../execution/evidence/2026-10-11/phase2-competitor.md)] Hambatan pada render berikutnya tetap BLOCKED dengan substatus yang berbeda dari tabel di atas: I1 Shopee redirect ke verifikasi CAPTCHA, G9 TikTok Shop halaman Security Check, L4 SSENSE verifikasi Cloudflare, L1/L3/L5/L6 akses ditolak, G7 eBay halaman error, G4 Temu `net::ERR_CERT_COMMON_NAME_INVALID` (sebelumnya timeout curl). Tidak ada kontrol akses yang dilewati dan tidak ada hasil yang dikarang untuk situs ini.

[OBSERVATION] URL percobaan https://www.nngroup.com/articles/luxury-principles/ dan https://www.similarweb.com/top-websites/ecommerce-shopping/ mengembalikan 404; diganti S7 dan S1, bukan dijadikan sumber isi. Tanggal akses: 2026-10-11.

## Protokol lanjutan

[RECOMMENDATION — S4, S6, S8; akses 2026-10-11] Audit browser berikutnya harus memakai tugas identik: temukan produk, filter ukuran, buka PDP, pilih varian, ubah tas, buka layanan, picu validasi, lalu keluar tanpa transaksi. Rekam URL, region, login, viewport, tanggal, screenshot dan langkah yang benar-benar berhasil. Uji keyboard, fokus, Escape, pembaca layar, reflow dan reduced motion secara terpisah. Jangan melewati kontrol akses atau membuat transaksi untuk melengkapi tabel.

[INTERPRETATION] Baseline performa, conversion rate, kepuasan, waktu penyelesaian tugas, OMS internal dan aksesibilitas kompetitor belum tersedia. Tidak ada klaim kausal bahwa pola populer menghasilkan pengalaman terbaik.
