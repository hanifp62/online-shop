# Benchmark e-commerce global: skala ≠ desain

Tanggal akses seluruh URL: **2026-10-11**. Metode, ID URL dan status percobaan: [metodologi](RESEARCH_METHODOLOGY.md). Dokumen ini melengkapi, bukan mengulang, [retail global](../02-product/benchmark/BENCHMARK_GLOBAL_RETAIL.md) dan [marketplace](../02-product/benchmark/BENCHMARK_MARKETPLACES.md).

## Dua lensa yang tidak boleh digabungkan

- [THIRD-PARTY METRIC — S3 https://ecdb.com/blog/the-top-companies-in-e-commerce-worldwide/5223; akses 2026-10-11] Daftar di bawah adalah **estimasi GMV perusahaan/grup selama 2025** oleh ECDB, bukan GMV masing-masing situs dan bukan revenue akuntansi. Satu grup dapat menaungi beberapa layanan; definisi dan model 1P/3P berbeda.
- [THIRD-PARTY METRIC — S1 https://www.similarweb.com/top-websites/e-commerce-and-shopping/; akses 2026-10-11] Similarweb mengurutkan **domain web** kategori Ecommerce & Shopping secara global pada **September 2026**: amazon.com #1, temu.com #2, ebay.com #3, ozon.ru #4, walmart.com #5. Ini bukan daftar perusahaan terbesar, bukan survei favorit, bukan pengguna unik dan tidak menghitung seluruh aktivitas aplikasi.
- [INTERPRETATION — S1, S3; akses 2026-10-11] Alibaba paling besar menurut GMV grup di S3 tetapi amazon.com tertinggi menurut ranking domain di S1; keduanya dapat benar. PDD ≠ temu.com saja; ByteDance ≠ shop.tiktok.com saja; Sea ≠ shopee.co.id saja.

## Sampel: sepuluh grup ECDB, bukan sepuluh situs UX teratas

Semua baris GMV bersumber **S3** (URL di atas, akses 2026-10-11); semua status homepage mengacu register G di [metodologi](RESEARCH_METHODOLOGY.md) (URL dan tanggal yang sama). Semua keputusan adalah hipotesis desain, bukan kesimpulan performa kompetitor.

| Grup / surface contoh | GMV grup 2025, estimasi ECDB [THIRD-PARTY METRIC] | Bukti permukaan [OBSERVATION / INTERPRETATION] | Implikasi [RECOMMENDATION] |
|---|---:|---|---|
| Alibaba / Taobao, Tmall; Alibaba.com berbeda | US$1,26 triliun | G2/G3 HTTP-ONLY. S1 menempatkan taobao.com #27 dan alibaba.com #37 domain global Sept 2026; bukan ranking Tmall [THIRD-PARTY METRIC — S1]. | Jangan mengasumsikan B2B Alibaba.com sama dengan konsumen Taobao; investigasi katalog/varian sebelum adaptasi. |
| PDD Holdings / Pinduoduo, Temu | US$873,0 miliar | G4 BLOCKED; temu.com #2 di S1, bukan bukti PDP Pinduoduo [THIRD-PARTY METRIC — S1]. | Tolak urgensi yang tidak bisa dibuktikan; tidak perlu meniru promosi berlapis. |
| Amazon / amazon.com | US$851,9 miliar | G1 BLOCKED; amazon.com #1 di S1 [THIRD-PARTY METRIC]. | Bandingkan kejelasan pilihan dan status, bukan skala OMS. |
| ByteDance / TikTok Shop, Douyin | US$672,1 miliar | G9 BLOCKED security check; shop.tiktok.com #35 di S1, bukan seluruh Douyin/app [THIRD-PARTY METRIC]. | Jangan menganggap livestream sebagai kebutuhan MVP. |
| JD.com / jd.com | US$555,7 miliar | G5 HTTP-ONLY; belum ada audit UX. | Investigasi fulfillment hanya jika kebutuhan logistik terukur. |
| Walmart / walmart.com | US$270,2 miliar | G6: teks “Pickup or delivery?”, Departments, Services, reorder, harga dan deals [OBSERVATION — https://www.walmart.com/]. | Adaptasi hanya kejelasan pilihan layanan; bukan promo atau janji same-day. |
| Chengdu Kuaigou Technology / Kuaishou | US$220,0 miliar | G10 respons JSON, bukan katalog; belum ada audit jual-beli. | Tidak mengambil keputusan IA dari API response. |
| Sea Ltd / Shopee | US$127,4 miliar | I1 BLOCKED (shopee.co.id); shopee.co.id #31 global S1 [THIRD-PARTY METRIC]. | Perlu uji Indonesia terpisah; GMV global tidak membuktikan UX ID. |
| eBay / ebay.com | US$80,7 miliar | G7 BLOCKED HTTP 403; ebay.com #3 di S1 [THIRD-PARTY METRIC]. | Jangan menyimpulkan format listing atau trust flow dari ranking. |
| MercadoLibre / Mercado Libre | US$65,0 miliar | G8 hanya daftar negara Argentina sampai Venezuela; S1 mengurutkan mercadolivre.com.br #15, domain terpisah dari pemilih negara [THIRD-PARTY METRIC]. | Lokalisasi perlu harga/ongkir/janji nyata; jangan salin multi-negara tanpa operasi. |

[INTERPRETATION — S3; akses 2026-10-11] Nilai di tabel adalah angka perkiraan ECDB yang dilaporkan pada satu artikel; bahkan artikel ECDB lain dengan unit retailer/domain dapat menghasilkan angka berbeda. Jangan menjumlahkan nilai grup ke marketplace masing-masing atau memakai GMV untuk menghitung conversion rate ZAFIRA.

## Catatan HCI dan MIS yang sah

- [OBSERVATION — G6 https://www.walmart.com/; akses 2026-10-11] Copy homepage menempatkan pilihan pickup/delivery dan akses reorder sebelum daftar deals. [INTERPRETATION — sumber sama] Pengurutan teks bukan bukti urutan visual pada semua viewport atau kesuksesan tugas. [RECOMMENDATION — sumber sama] Uji apakah pengguna ZAFIRA paham bahwa tas hanya menyiapkan permintaan concierge, bukan checkout.
- [OBSERVATION — G8 https://www.mercadolibre.com/; akses 2026-10-11] Pengunjung mendapat daftar negara. [INTERPRETATION] Ini hanya entry point; tidak membuktikan opsi bahasa, checkout, mata uang ataupun estimasi pengiriman untuk setiap negara. [RECOMMENDATION] Pertahankan janji layanan sesuai wilayah yang benar-benar dilayani ZAFIRA.
- [FACT — S5 https://baymard.com/lists/cart-abandonment-rate; akses 2026-10-11] Baymard mengompilasi studi cart abandonment (sekitar 70,22% pada halaman saat riset). [INTERPRETATION] Sampel dan definisi checkout umum tidak mewakili alur permintaan concierge. [RECOMMENDATION] Jangan jadikan angka ini target atau estimasi abandonment ZAFIRA.

## Celah

[INTERPRETATION — register G1–G10; akses 2026-10-11] Ranking tidak memberi bukti search, filter, detail PDP, varian, stok, pencegahan kesalahan, aksesibilitas, checkout, OMS, service recovery atau algoritme rekomendasi pada 10 grup. Tampilan homepage Walmart tidak membuktikan fulfillment sesungguhnya. Uji browser/tugas dan sumber resmi seller/layanan dibutuhkan sebelum menilai mekanisme internal.
