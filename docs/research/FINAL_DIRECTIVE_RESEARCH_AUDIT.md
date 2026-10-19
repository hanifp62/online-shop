# Audit riset benchmark ZAFIRA — temuan dan batas bukti

**Tanggal audit dan akses sumber:** 2026-10-11. **Cakupan:** enam dokumen `docs/research/`, PRD, journey, sintesis benchmark kanonis, matriks traceability, serta bukti uji dan render yang dirujuk. Audit dokumentasi ini tidak menjalankan ulang aplikasi atau tes.

## Ringkasan keputusan

- [FACT] S1–S8 kini diperiksa sebagai konten, bukan sekadar status HTTP. Ekstrak, tanggal, URL dan batas inferensi ada di [source-excerpts](../execution/evidence/2026-10-11/phase4/completion-research/source-excerpts.md).
- [FACT] Ranking Similarweb, estimasi GMV ECDB, observasi teks storefront dan studi pengguna NN/g adalah empat jenis bukti berbeda. Tidak satu pun membuktikan conversion lift atau performa UX ZAFIRA.
- [FACT] Bukti 2026 untuk kompetitor berasal dari muatan halaman terbatas. CAPTCHA, security check, Cloudflare, denied access, 404 dan timeout tetap `BLOCKED`; alternatif bantuan eBay hanya membuktikan keberadaan pusat bantuan, bukan perilaku storefront.
- [INTERPRETATION] Jangan membuat ranking kualitas dari scorecard atau mengubah status menjadi `PASS` hanya karena test file ada. Untuk kebutuhan yang belum dibuktikan, status tepat adalah `NOT RUN` atau `BLOCKED`, bukan klaim sukses.
- [RECOMMENDATION] Pertahankan keputusan produk yang sudah tercatat; tangani konflik acceptance dan kekurangan pengujian melalui perubahan requirement terpisah, bukan dengan mengubah makna diam-diam pada audit ini.

## Temuan audit

### R-01 — Kelas bukti pasar tidak setara

[FACT] S1 mencatat ranking domain global kategori Ecommerce & Shopping September 2026; S2 mencatat ranking Indonesia kategori yang sama; S3 melaporkan estimasi GMV perusahaan untuk 2025. Unit dan metrik tidak sebanding. S3 juga mencakup perusahaan/grup seperti Alibaba dan ByteDance, bukan hanya domain toko konsumen.

[INTERPRETATION] Angka ini mendukung konteks ukuran pasar dan pemilihan sampel, bukan preferensi pembeli Indonesia, keberhasilan UX, atau prioritas fitur ZAFIRA. Klaim tersebut tidak boleh digunakan sebagai benchmark conversion atau kualitas.

### R-02 — Benchmark Baymard adalah sumber metode, bukan skor audit lokal

[FACT] S4 menyebut benchmark lintas 344 studi kasus dan penilaian pada banyak elemen UX. S6 menyebut “more than 8”, “8 leading” dan metodologi “9 luxury sites” pada bagian berbeda. S5 menyebut rerata abandonment 70.22% dari 50 studi, terakhir diperbarui 22 September 2025.

[INTERPRETATION] Denominasi S6 tidak konsisten di halaman sumber dan tidak boleh dipadatkan menjadi angka sampel tunggal. S5 tidak dapat dipakai sebagai proyeksi kerugian atau target ZAFIRA: transaksi concierge dan populasi studi tidak identik. Scorecard ZAFIRA tetap rubrik prioritas berbasis observasi, bukan ranking performa.

### R-03 — NN/g 2022 memberikan contoh peserta, bukan audit terkini

[FACT] S7 adalah artikel Kate Moran tanggal 26 Juni 2022. Studi menyebut peserta yang kesulitan menilai ukuran tas Fendi tanpa foto konteks; satu peserta mencari 12 menit di tiga situs. Artikel juga melaporkan friksi hover harga Bulgari, locator Tom Ford/Bulgari yang rusak, interupsi survei Cartier, keterbacaan Rolex menurut peserta, sejarah Bamboo Gucci, digital showroom Gucci, dan opsi perbaikan Louis Vuitton.

[INTERPRETATION] Temuan historis memberi alasan untuk mempertimbangkan konteks produk, harga jelas, locator yang andal, minim interupsi dan layanan manusia. Temuan itu bukan inspeksi storefront 2026, bukan bukti bahwa setiap halaman kini sama, dan bukan pengukuran kontras WCAG.

### R-04 — Akses kompetitor tetap parsial; kegagalan akses bukan kegagalan produk

[OBSERVATION] Bukti render 2026 ada dalam [phase2-competitor.md](../execution/evidence/2026-10-11/phase2-competitor.md). Halaman yang terender termasuk Mytheresa, Hermès, Gucci, halaman bantuan NET-A-PORTER/FARFETCH, Taobao, Tokopedia, Alibaba, JD, Amazon. Cakupan hanya halaman yang terdaftar dan sesi sesaat.

[OBSERVATION] Shopee memunculkan CAPTCHA; TikTok Shop security check; SSENSE Cloudflare; beberapa situs menolak akses; Temu sertifikat tidak valid. Tidak ada kontrol yang dilewati. eBay Customer Service berhasil dibaca, tetapi itu bukan alternatif bukti untuk homepage atau alur belanja. Shopee Help Center hanya menampilkan label umum. Halaman LVMH yang dicoba memberi 404; tidak digunakan.

[INTERPRETATION] Tidak ada skor kompetitor untuk fitur yang tidak teramati. Label `BLOCKED` hanya menjelaskan keterbatasan pengumpulan bukti.

### R-05 — Keputusan diferensiasi cukup tertaut, tetapi lensa HCI harus spesifik

[FACT] D-01–08 tertaut ke FR/NFR kanonis. Keputusan relevan dapat dijelaskan dengan prinsip spesifik: D-02 kejelasan ukuran/material didukung oleh kasus Fendi/LV di S7; D-05 menghindari gangguan didukung kasus Cartier; D-08 modal keyboard/fokus terkait WCAG 2.2 dan kebutuhan escape/fokus yang memang acceptance ZAFIRA.

[INTERPRETATION] Prinsip Norman (signifier dan umpan balik), Nielsen (status dan kontrol keluar), Shneiderman (reversal), Cooper (mengurangi input berulang), Engelbart (informasi kerja terpadu), Brown (uji prototipe), dan Krug (scanability) adalah lensa internal, bukan endorsement atau sumber validasi. Gunakan hanya ketika keputusan konkret menggunakannya. Atribusi bibliografis tokoh belum diverifikasi pada audit ini; identitas “Hunt” dan “Hudson” tetap ambigu.

### R-06 — Standar WCAG tidak mengesahkan UI secara otomatis

[FACT] S8 adalah W3C Recommendation 12 Desember 2024. Ia menetapkan kriteria yang dapat diuji, termasuk kontras teks AA, reflow, keyboard, fokus tidak tertutup, dan target minimum 24×24 CSS px dengan pengecualian tertentu.

[FACT] Traceability menunjukkan tes Node historis 46/46 lulus untuk sebagian perilaku overlay/fokus/Escape. Itu bukan audit WCAG 2.2 AA: belum ada verifikasi komprehensif pembaca layar, kontras, reflow/zoom, nama aksesibel, seluruh proses dan perangkat.

[INTERPRETATION] Jangan menyimpulkan bahwa warna Tom Ford gagal WCAG dari deskripsi teks NN/g; artikel menyebut sulit dibaca, bukan rasio terukur. Jangan menyatakan ZAFIRA lulus WCAG dari test Node terbatas.

### R-07 — Klaim implementasi dan requirement belum seluruhnya konsisten

[FACT] PRD menulis objective storefront `<100ms`, sementara NFR-001 menulis rata-rata `<150ms`. Tidak ada benchmark latensi audit ini. Bukti ukuran asset historis tidak mengukur waktu server. Klaim LRU pada FR-001 tidak terbukti oleh source evidence yang dirujuk; NFR-003 menuntut relasi pesanan, sedangkan matriks mencatat tidak ada tabel Order dan item pesanan berada di concierge notes.

[FACT] FR-008 menerima pencocokan email, sementara FR-204 membatasi riwayat ke permintaan milik user. FR-011 menyebut `Cache::flush()`, namun catatan audit menyatakan implementasi menggunakan invalidasi kunci terkait. FR-014 menyebut bcrypt round 12 yang belum diverifikasi.

[RECOMMENDATION] Pemilik produk/arsitektur harus menyelesaikan konflik `<100ms`/`<150ms`, definisi kepemilikan FR-008/FR-204, serta model data NFR-003 secara eksplisit. Tetapkan cara ukur untuk LRU/performa dan password hashing. Jangan mengubah requirement kanonis lewat dokumen riset.

### R-08 — Matriks traceability mencatat test lama, bukan status lulus menyeluruh

[FACT] Log historis `mandate-regression` mencatat PHPUnit 79 test/428 assertions (exit 0) dan Node 46/46 (exit 0). Test menggunakan `Mail::fake()`; ini menguji dispatch/queue, bukan pengiriman email eksternal nyata. Beberapa fitur hanya memiliki test unit/feature; pengukuran latency, audit visual dan validasi browser/accessibility penuh belum dilakukan.

[FACT] Acceptance PRD seperti tiga produk terkait, JSON-LD valid, newsletter email benar-benar dikirim, isolasi sesi dua guard pada browser, transisi status live, NFR-005 pada seluruh setup macOS/arm64, dan NFR-001 latency belum memiliki bukti spesifik yang diperiksa dalam audit ini. Total suite tidak membuktikan semua ini.

[RECOMMENDATION] Matriks memakai status terstandar pada adendum: `PASS` hanya untuk acceptance yang benar-benar diuji; `FAIL` untuk hasil bertentangan; `BLOCKED` untuk akses/bukti yang terhalang; `NOT RUN` jika tidak ada verifikasi; `NA` bila requirement tidak berlaku. Status yang ada jangan dipromosikan tanpa test evidence khusus.

## Ringkasan status penerimaan pada audit dokumen

Status ini menilai bukti yang tersedia, bukan menyatakan requirement produk selesai.

| Acceptance / kebutuhan | Status audit | Bukti atau alasan |
|---|---|---|
| S1–S3 klaim ranking/GMV yang tercantum | PASS | Konten sumber diperiksa ulang 2026-10-11; definisi dan unit tetap dibatasi. |
| S4–S6 metode, abandonment, luxury sample | PASS | Konten berhasil diperiksa; ketidakkonsistenan S6 dicatat. |
| S7 contoh historis brand | PASS | Konten artikel dibaca; temuan tetap bertanggal 2022. |
| S8 versi, tanggal dan kriteria relevan | PASS | W3C Recommendation dan contoh kriteria diperiksa. |
| Skor peringkat kualitas kompetitor / conversion lift | NOT RUN | Tidak ada data pembanding atau pengukuran konversi. |
| Fitur kompetitor di halaman terblokir | BLOCKED | Tidak menebak fitur; CAPTCHA/denied/Cloudflare/timeout/sertifikat. |
| ZAFIRA NFR-001 latensi `<150ms` dan objective `<100ms` | NOT RUN | Tidak ada benchmark; persyaratan bertentangan. |
| WCAG 2.2 AA end-to-end | NOT RUN | Test Node terbatas bukan audit lengkap. |
| FR-006 pengiriman email eksternal | NOT RUN | Test memakai fake mail; tidak membuktikan delivery. |
| NFR-003 relasi FK pesanan | FAIL | PRD mewajibkan relasi order; bukti matriks menyatakan model Order/relasi item belum ada. |
| FR-014 bcrypt round 12 | NOT RUN | Konfigurasi tidak terbukti dari evidence yang dibaca. |
| Acceptance lain PRD FR-001–014/NFR-001–005 yang tidak dipetakan di tabel ini | NOT RUN | Audit ini tidak menjalankan test baru; lihat matriks untuk catatan historis. |

`PASS` pada baris sumber berarti klaim isi sumber telah dicek, bukan keputusan produk atau implementasi.

## Rantai dan cakupan

- Sumber dan temuan: S1–S8 dalam [metodologi](RESEARCH_METHODOLOGY.md), kutipan dan tanggal di [ekstrak](../execution/evidence/2026-10-11/phase4/completion-research/source-excerpts.md).
- Observasi kompetitor: [render browser parsial](../execution/evidence/2026-10-11/phase2-competitor.md); bukan scorecard.
- Requirement: [PRD](../02-product/PRD.md), [journey](../02-product/USER_JOURNEYS.md), [sintesis kanonis](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md), [diferensiasi](ZAFIRA_DIFFERENTIATION.md), dan matriks traceability.
- Hasil tes historis: [PHPUnit](../execution/evidence/2026-10-11/phase4/mandate-regression/phpunit-random.log), [Node](../execution/evidence/2026-10-11/phase4/mandate-regression/node-test.log). Tanggal run historis; tidak dijalankan kembali pada audit dokumentasi ini.
- Batas: tidak ada uji pengguna baru, transaksional competitor journey, conversion, latency, live email, full WCAG audit, atau validasi deployment. Tidak ada perubahan requirement kanonis.
