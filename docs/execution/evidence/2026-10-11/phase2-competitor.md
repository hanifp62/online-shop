# Phase 2 — Competitor browser audit (partial)

Tanggal: 2026-10-11. Hanya sumber publik. Tidak ada login, tidak ada interaksi dengan CAPTCHA/security check, tidak ada pembacaan `.env`, tidak ada akses database, tidak ada commit.

## Metode

- Chrome headless (rendering penuh dengan JavaScript) memuat URL publik halaman beranda atau halaman bantuan; teks yang terender dicatat sebagai observasi.
- Maksimal tiga percobaan terarah per hambatan. Halaman yang menampilkan CAPTCHA, security check, atau akses ditolak tidak dilewati.
- Jam akses per URL tidak dicatat; hanya tanggal yang valid.
- Daftar URL dan status HTTP-only/curl sebelumnya ada di `docs/research/RESEARCH_METHODOLOGY.md` (bagian G/I/L). Berkas ini hanya menambahkan hasil render browser.

## Batas bukti (wajib dibaca)

- **Tingkat konversi tidak diukur.** Tidak ada data konversi, traffic, pesanan, atau abandonment dari kompetitor yang diperoleh.
- Cakupan parsial: hanya halaman yang tertulis di bawah. Satu muatan halaman = satu observasi sesaat, bukan audit UX penuh.
- Ketiadaan fitur pada teks yang terender **tidak** membuktikan fitur itu tidak ada (konten dapat dimuat lazy, bergantung lokasi, atau di balik interaksi).
- Konten bergantung lokasi/sesi; hasil tidak mewakili semua pengguna.

## Hasil: terender (observasi)

| URL | Hasil | Observasi |
|---|---|---|
| https://www.mytheresa.com/int/en/women | Terender | Lokasi pengiriman Indonesia, harga dalam euro, navigasi kategori, modul editorial. |
| https://www.hermes.com/us/en/ | Terender | Tautan layanan dan kontak; konten Made to measure, perawatan/perbaikan (maintenance/repair), dan konten sutra. |
| https://www.gucci.com/us/en/ | Terender pada percobaan ulang (percobaan pertama `net::ERR_HTTP2_PROTOCOL_ERROR`) | Layanan terlihat: pemesanan janji temu, personalisasi, collect in store, kontak. |
| https://www.net-a-porter.com/en-us/content/help/contact-us/ | Terender | Halaman kontak terbaca; beranda NET-A-PORTER sendiri ditolak (lihat bawah). |
| https://www.farfetch.com/faqs | Terender | Halaman FAQ terbaca; beranda FARFETCH ditolak (lihat bawah). |
| https://www.taobao.com/ | Terender | Beranda terbaca sebagai observasi teks. |
| https://www.tokopedia.com/ | Terender | Beranda terbaca sebagai observasi teks (sebelumnya timeout pada fetch HTTP). |
| https://www.alibaba.com/ | Terender | Beranda terbaca; tidak mewakili Taobao/Tmall. |
| https://www.jd.com/ (JD Global) | Terender | Beranda terbaca sebagai observasi teks. |
| https://www.amazon.com/ | Terender | Beranda terbaca (sebelumnya ekstraksi HTTP kosong). |

Catatan: tabel hanya memuat apa yang terlihat; tidak ada klaim kualitas UX, kecepatan, atau konversi.

## Hasil: terblokir / tidak tersedia (hambatan)

| URL | Status | Tindakan |
|---|---|---|
| https://shopee.co.id/ | Redirect ke verifikasi CAPTCHA | Tidak diinteraksikan/dilewati. |
| https://shop.tiktok.com/ | Halaman "Security Check" | Tidak diinteraksikan/dilewati. |
| https://www.ssense.com/en-us | Verifikasi Cloudflare | Tidak dilewati. |
| https://www.net-a-porter.com/en-us/ | Akses ditolak | Tidak dilewati. |
| https://www.farfetch.com/ | Akses ditolak | Tidak dilewati. |
| https://www.louisvuitton.com/eng-e1/homepage | Akses ditolak/tidak tersedia | Tidak dilewati. |
| https://www.dior.com/en_int | Akses ditolak/tidak tersedia | Tidak dilewati. |
| https://www.ebay.com/ | Halaman error | Tidak dicoba ulang lebih lanjut. |
| https://www.temu.com/ | `net::ERR_CERT_COMMON_NAME_INVALID` | Tidak dicoba ulang lebih lanjut; sertifikat tidak diabaikan. |

## Implikasi untuk ZAFIRA

- [OBSERVATION] Sinyal layanan luxury yang terlihat pada halaman terender (janji temu, personalisasi, collect in store, made-to-measure, perawatan/perbaikan, kontak) konsisten dengan arah concierge ZAFIRA. Ini hanya kesamaan fitur yang terlihat, bukan bukti dampak pada konversi.
- [LIMITATION] Perbandingan Shopee, TikTok Shop, SSENSE, NET-A-PORTER (beranda), FARFETCH (beranda), Louis Vuitton, Dior, eBay, dan Temu tetap belum dilakukan lewat browser. Untuk menutup celah: akses sah dengan izin pemilik situs, atau riset pengguna nyata. Tidak ada hasil yang dikarang untuk situs terblokir.
- Skor di `docs/research/COMPARATIVE_SCORECARD.md` tidak diubah oleh berkas ini dan tetap tunduk pada batasnya sendiri.
