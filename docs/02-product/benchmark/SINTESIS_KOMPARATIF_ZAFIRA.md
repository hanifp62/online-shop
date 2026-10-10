# Sintesis komparatif e-commerce untuk ZAFIRA

Tanggal: 10 Oktober 2026. Status: **riset sekunder dan audit kode; belum audit browser lintas-situs**. Baca bersama [marketplace](BENCHMARK_MARKETPLACES.md), [retail global](BENCHMARK_GLOBAL_RETAIL.md), [luxury/fashion](BENCHMARK_LUXURY_FASHION.md), dan [audit dewan](../../05-quality/COUNCIL_AUDIT_REPORT.md).

## Metode dan batas bukti

Sepuluh **referensi desain**, bukan daftar sepuluh situs paling digemari: Amazon, Shopee Indonesia, Tokopedia/TikTok Shop Indonesia (satu ekosistem seller, dua surface pembeli yang tidak boleh disamakan), AliExpress, Temu, Shein, Etsy, Mytheresa, Hermès, dan Zalando. Farfetch/NET-A-PORTER dalam dokumen luxury hanya pembanding tambahan. Kelompok ini dipilih untuk memadukan penggunaan web skala besar, relevansi Indonesia, personalisasi, dan layanan luxury. Tidak ada survei favorit pengguna yang memvalidasi urutan preferensi. Similarweb/Semrush mengestimasi **kunjungan web**, bukan pengguna unik, pembeli, atau kepuasan; angka berbeda bulan, negara, dan metode sehingga tidak diperingkat silang. Lihat URL dan tanggal per platform dalam tiga benchmark. Data aplikasi sebagian tidak tersedia. Klaim yang hanya ditemukan di snippet, artikel sekunder, atau inferensi **bukan fakta implementasi kompetitor**.

| Kelompok / platform | Bukti/kontribusi yang dapat dipakai | Keputusan ZAFIRA |
|---|---|---|
| Marketplace: Amazon, Shopee, Tokopedia/TikTok Shop, AliExpress | Seller docs membuktikan kebutuhan status permintaan, harga/stok varian, serta kejelasan pilihan; detail state machine internal dan checkout pembeli masih belum diverifikasi. | Adaptasi ringkasan pilihan dan status concierge; jangan salin OMS, reservasi stok, atau checkout pembayaran. |
| Retail: Temu, Shein | Bukti keberadaan pola promosi dan retur berbeda wilayah; sebagian detail UI hanya inferensi. Kritik regulator pada pola tekanan perlu dicek langsung pada sumber primer sebelum dipakai sebagai klaim spesifik. | Tolak urgency, countdown, diskon dan ulasan tanpa bukti. |
| Personalisasi: Etsy | Opsi personalisasi terstruktur dapat dibandingkan dengan monogram; batas tiga huruf berasal dari kontrak ZAFIRA, bukan aturan Etsy. | Simpan ukuran/monogram pada item tas dan permintaan concierge. |
| Luxury/fashion: Mytheresa, Hermès, Zalando | Material, perawatan, size guide, appointment/client service dan discovery fashion memberi referensi; audit aksesibilitas langsung belum dilakukan. | Jaga identitas editorial; jelaskan bahan, ukuran dan jalur konsultasi tanpa klaim provenance fiktif. |

## HCI dan MSI: perbandingan menuju keputusan

- **Norman — signifier/feedback:** pilihan ukuran dan monogram harus terlihat pada ringkasan tas dan permintaan, bukan hanya pratinjau dekoratif. Kegagalan saat ini: PDP tidak meneruskan kedua nilai; lihat `resources/views/products/show.blade.php` dan `resources/js/app.js` dalam audit fitur.
- **Nielsen — status/error prevention:** tampilkan konfirmasi hanya setelah POST `/concierge` sukses. Kesalahan validasi harus menjelaskan apa yang perlu diperbaiki; status akun hanya boleh memetakan nilai yang benar-benar disimpan.
- **Shneiderman — overview/filter/detail dan reversibility:** pertahankan filter ringan dan akses PDP; pertahankan kemampuan mengubah jumlah dan membatalkan penghapusan tas, dengan feedback yang bisa dibaca.
- **Cooper/Krug — hemat langkah, terminologi jujur:** concierge tanpa akun wajib, label sederhana, tidak menyebut permintaan konsultasi sebagai pesanan berbayar; jangan memaksa pengguna memahami model database.
- **MIS:** saat ini admin memiliki CRUD produk, kategori, permintaan concierge, pelanggan newsletter. Dashboard manufaktur, OMS, ledger, kalender appointment, pembayaran, dan pelacakan pengiriman **tidak** terbukti sebagai backend. Jangan tampilkan angka operasional simulasi sebagai fakta; Order/OrderItem tetap pasca-MVP.

## Prioritas yang dapat diuji

| ID | Perubahan | Uji penerimaan | Asal |
|---|---|---|---|
| FR-201 | Ukuran dan monogram yang valid melekat pada item tas dan payload concierge, varian berbeda tidak digabung. | Dua pilihan ukuran/monogram menghasilkan dua item; backend mengabaikan harga browser dan menyimpan harga database. | Audit UX-01/SEC-04; adaptasi Etsy/Shopee. |
| FR-202 | Halaman produk hanya menampilkan panduan ukuran yang relevan dan material/perawatan yang didukung data. | Produk tanpa ukuran tidak menawarkan size chart generik; klaim yang tidak ada sumber datanya tidak tampil. | Audit fitur; pola Mytheresa. |
| FR-203 | Permintaan concierge memberi pesan keberhasilan hanya sesudah backend menerima; tidak diwajibkan login. | Kegagalan POST tidak menampilkan keberhasilan; sukses memberi informasi tindak lanjut yang benar-benar didukung. | Audit prototype; HCI Nielsen. |
| FR-204 | Akun hanya menampilkan permintaan milik pengguna dan status nyata. | Email yang sama tanpa ownership tidak membuka riwayat; stepper tidak mengklaim proses produksi fiktif. | Audit SEC-03; MIS. |
| NFR-201 | Navigasi prototype disembunyikan di produksi; aset yang dipakai storefront tetap ada. | Uji lingkungan produksi memastikan tak ada link/demo trigger; gambar/login masih termuat. | Keputusan pengguna. |
| NFR-202 | Klaim palsu (telemetri, ledger, sertifikat, profil tubuh/advisor, valuta selain IDR) dihapus dari aplikasi nyata. | Pencarian UI dan uji halaman tidak menemukan klaim hard-coded atau selector USD/EUR. | Keputusan pengguna; audit fitur. |
| NFR-203 | Aksi dan modal memenuhi keyboard, nama aksesibel, fokus dan Escape. | Browser menguji Tab, Escape, fokus kembali, zoom/mobile; WCAG 2.2 AA. | Audit dewan; WCAG 2.2. |

## Belum dapat disimpulkan

Popularitas favorit, kualitas UX kompetitor secara kuantitatif, algoritme rekomendasi, aturan reservasi stok, dan dampak konversi ZAFIRA belum terukur. Tidak ada alasan berbasis bukti untuk menambah AI/AR, pembayaran, loyalty, review, OMS, atau dashboard telemetri pada MVP. Audit browser dan pengujian pengguna diperlukan sebelum klaim komparatif yang belum diverifikasi naik menjadi requirement.
