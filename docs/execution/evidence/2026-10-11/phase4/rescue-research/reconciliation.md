# Rekonsiliasi riset dan i18n — 2026-10-11

## Batas metode

Bukti sumber sekunder adalah register `docs/research/RESEARCH_METHODOLOGY.md` (S1–S8) dan dokumen global/Indonesia/luxury/scorecard/diferensiasi. Cek ulang delapan URL di `source-recheck-2009.txt` hanya merekam HTTP 200; tidak menguji isi mutakhir, akurasi angka, ranking, atau UX. Klaim metrik dan cuplikan pada dokumen riset tetap bergantung ekstraksi awal yang dicatat register, bukan delapan status HTTP baru.

Bukti render `docs/execution/evidence/2026-10-11/phase2-competitor.md` mencatat 10 halaman terender sebagian, plus 9 URL terhambat. Render tidak mengganti status ekstraksi awal; itu jalur observasi lain. Render Amazon, Taobao, Alibaba.com, JD, Tokopedia, Gucci dan dua halaman bantuan yang sebelumnya kosong/timeout/403 hanya mengonfirmasi teks muat pada percobaan lain. Homepage NET-A-PORTER dan Farfetch tetap terblokir meski halaman bantuan masing-masing terender. Shopee CAPTCHA, TikTok Shop Security Check, SSENSE Cloudflare, NET-A-PORTER/Farfetch homepage, LV, Dior, eBay, Temu tidak dijadikan observasi UX. Tidak ada bypass, checkout, penelitian pengguna, pengukuran aksesibilitas/konversi, atau ranking kualitas.

Tabel `COMPARATIVE_SCORECARD.md` hanya menskor tujuh ekstraksi teks storefront awal dan tidak dinaikkan menjadi skor browser bagi halaman terender belakangan. D-01–08 di `ZAFIRA_DIFFERENTIATION.md` dan FR-201–204/NFR-201–203 di `docs/02-product/REQUIREMENTS_TRACEABILITY.md` tetap rantai keputusan; S1/S2 ranking domain dan S3 estimasi GMV grup tidak dibandingkan sebagai satu peringkat. Keputusan investigasi bukan backlog terimplementasi.

## Temuan i18n aktual

Laporan awal `i18n-static-check.txt` melaporkan editorial_eyebrow/title/desc/cta hilang di semua bahasa. Pemeriksaan pertama terhadap objek yang diekspor `window.Zafira.I18N` membuktikan empat kunci itu memang tidak ada, lalu tes baru `tests/zafira-i18n.test.mjs` gagal merah. Setelah penambahan empat terjemahan pada `public/js/zafira-core.js`, tes hijau. Pemeriksaan terstruktur dan hasilnya ada di `i18n-structural-check.txt`: 118 kunci atribut i18n di `welcome.blade.php` hadir di semua empat kamus. Jumlah union 207; ID/FR/IT masing-masing tidak punya `studio_monogram_desc` yang hanya dipakai di `prototype/app.js` dengan fallback. Paritas 9/8/9/1 dari parser kasar tidak benar. Kualitas semantik terjemahan, halaman lain, dan runtime browser tidak diuji.

## Jejak verifikasi

`node --test tests/zafira-i18n.test.mjs`: 1/1 lulus. `node --test tests/*.test.mjs`: 47/47 lulus pada saat pemeriksaan. Tidak menjalankan browser, build, PHP, database, maupun sinkronisasi prototype. Status implementasi requirement lain tidak dinaikkan dari laporan sebelumnya.
