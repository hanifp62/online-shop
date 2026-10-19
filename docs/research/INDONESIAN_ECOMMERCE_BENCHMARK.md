# Benchmark e-commerce Indonesia: kebiasaan belanja vs ketenangan atelier

Tanggal akses seluruh URL: **2026-10-11**. Metode dan ID URL: [metodologi](RESEARCH_METHODOLOGY.md). Dokumen ini melengkapi [marketplace](../02-product/benchmark/BENCHMARK_MARKETPLACES.md) tanpa menyalin ulang bagian Amazon dan AliExpress.

## Ranking web Similarweb (September 2026)

[THIRD-PARTY METRIC — S2 https://www.similarweb.com/top-websites/indonesia/e-commerce-and-shopping/; akses 2026-10-11] Kategori Ecommerce & Shopping di Indonesia:
1. `shopee.co.id`
2. `tokopedia.com`
3. `lazada.co.id`
4. `blibli.com`
5. `olx.co.id` (classifieds)

[INTERPRETATION — S2; akses 2026-10-11] Tabel publik hanya membuka lima besar tanpa angka visits per domain. Ini peringkat domain web, bukan jumlah transaksi, GMV atau pemakaian aplikasi. Tokopedia web berbeda dari TikTok Shop in-app; Zalora berada di luar lima besar kategori umum namun relevan sebagai perbandingan fashion/lifestyle.

## Ringkasan lima platform

Semua baris mematuhi label bukti; homepage I mengacu register di [metodologi](RESEARCH_METHODOLOGY.md) (akses 2026-10-11).

| Platform / domain | Bukti ranking dan teks publik | Bukti HCI / layanan dari teks terambil | Batas bukti dan keputusan ZAFIRA |
|---|---|---|---|
| Shopee Indonesia / `shopee.co.id` | #1 di S2 [THIRD-PARTY METRIC]. I1 BLOCKED (ekstraksi kosong). Dokumen seller diuji pada benchmark lokal: pilihan variasi dan pesanan seller [FACT — SINTESIS kanonis]. | Fitur pembeli live belum terbukti dari respons web teks ini. | Jangan mengklaim kebiasaan pembeli Shopee dari ranking; adaptasi hanya kejelasan varian tanpa flash-sale atau gamifikasi. |
| Tokopedia / `tokopedia.com` | #2 di S2 [THIRD-PARTY METRIC]. I2 BLOCKED (timeout). Dokumen seller: pengelolaan stok/harga [FACT — SINTESIS kanonis]. | Interaksi pembeli web/app belum terobservasi langsung pada 2026-10-11. | Tolak sinkronisasi lintas kanal yang belum ada; batasi admin pada status concierge yang benar-benar tersimpan. |
| Lazada Indonesia / `lazada.co.id` | #3 di S2 [THIRD-PARTY METRIC]. I3 SUCCESS [OBSERVATION — https://www.lazada.co.id/]. | Homepage memuat: LazMall 100% original, Flash Sale, Lacak Pesanan, live chat 24 jam, metode pembayaran termasuk COD, dorongan download app. | Tolak urgensi Flash Sale; adaptasi hanya kejelasan garansi keaslian bahan dan jalur bantuan yang jujur. |
| Blibli / `blibli.com` | #4 di S2 [THIRD-PARTY METRIC]. I4 SUCCESS [OBSERVATION — https://www.blibli.com/]. | Homepage memuat: “Pasti ORI”, “Retur alasan apa pun”, “Jaminan tepat waktu”, BlibliCare (telepon, WhatsApp, email), integrasi Tiket.com, cicilan, Click & Collect. | Pelajari kejelasan janji keaslian dan nomor bantuan; tolak ekosistem poin/rewards dan janji retur tanpa batas yang tidak realistis untuk atelier. |
| Zalora Indonesia / `zalora.co.id` | Pembanding fashion/lifestyle. I5 SUCCESS [OBSERVATION — https://www.zalora.co.id/]. | Homepage memuat: navigasi Wanita/Pria/Sports/Anak/Luxury/Beauty, klaim 100% asli, gratis 30 hari pengembalian, brand list termasuk luxury/designer. | Adaptasi navigasi kategori yang tenang dan deskripsi bahan; tolak janji retur pakaian custom/monogram. |

## Konteks lokal: ekspektasi pembeli vs batas MVP

- [OBSERVATION — I3, I4; akses 2026-10-11] Lazada dan Blibli mencantumkan transfer bank, cicilan, COD dan e-wallet. [INTERPRETATION] Konsumen Indonesia terbiasa dengan pembayaran instan (QRIS/VA). [RECOMMENDATION] Karena ZAFIRA tidak memproses pembayaran pada MVP, microcopy formulir concierge harus secara eksplisit menyatakan: *"Ini adalah permintaan konsultasi & reservasi karya, bukan pembayaran langsung"* (Nielsen H2 & Krug: eliminasi ekspektasi yang salah).
- [OBSERVATION — I3, I4; akses 2026-10-11] Fitur pelacakan pesanan dan estimasi waktu tampil menonjol. [INTERPRETATION] Konsumen sensitif terhadap kejelasan pengiriman dan ongkos kirim. [RECOMMENDATION] Jangan menampilkan widget pelacakan fiktif atau estimasi pengiriman otomatis jika belum terintegrasi dengan kurir nyata. Komunikasi jadwal karya dilakukan oleh artisan via concierge.
- [OBSERVATION — I4; akses 2026-10-11] Blibli menyediakan kanal bantuan WhatsApp resmi selain telepon dan email. [INTERPRETATION] Kanal chat/WhatsApp adalah media komunikasi yang sangat lazim bagi konsumen Indonesia untuk konsultasi pra-beli. [RECOMMENDATION] Menghubungkan CTA concierge ke jalur WhatsApp bisnis terverifikasi atau formulir yang ditindaklanjuti secara personal sangat selaras dengan model mental konsumen lokal tanpa memerlukan modul chat kustom yang berat.

## Evaluasi HCI: kebisingan massal vs ketenangan atelier

- **Norman (Affordance & Emotional Design):** Marketplace massal dipenuhi banner diskon berkedip, countdown timer, dan lencana promosi berlapis yang menciptakan *cognitive overload* dan kecemasan. Bagi ZAFIRA, kejelasan visual (*visceral* kemewahan) harus diutamakan dengan tipografi elegan (*Bodoni Moda* & *Manrope*), ruang negatif luas, dan fokus penuh pada keindahan visual karya kriya.
- **Nielsen (Visibility of System Status & Error Prevention):** Pada marketplace, pengguna sering dihadapkan pada syarat voucher tersembunyi yang gagal saat checkout. ZAFIRA harus menerapkan pencegahan kesalahan di muka: validasi inisial monogram maksimal 3 karakter secara real-time dengan counter `[2/3]`, serta umpan balik pengiriman concierge hanya setelah server merespons sukses.
- **Cooper (Eliminating Excise):** Menghilangkan keharusan login/registrasi sebelum dapat berkonsultasi (guest concierge). Memaksa pembuatan akun untuk sekadar menanyakan ketersediaan karya menambah friksi administratif (*excise*) yang tidak perlu.
- **Krug (Don't Make Me Think):** Alur concierge harus mandiri dan jelas tujuannya. Hilangkan istilah jargon internal database; gunakan label yang lugas (*"Kirim Permintaan Konsultasi"*).

## Celah verifikasi

[INTERPRETATION — register I1–I5; akses 2026-10-11] Tidak ada data langsung mengenai checkout flow, tingkat konversi, performa mobile app, atau efektivitas penanganan keluhan dari ekstraksi teks ini. Evaluasi UX mendalam memerlukan audit interaksi browser berbasis skenario tugas nyata.
