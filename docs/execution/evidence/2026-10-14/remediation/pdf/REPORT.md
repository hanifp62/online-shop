# Verifikasi perbaikan glyph PDF — 2026-10-14

## Ruang lingkup dan hasil

Perbaikan lokal pada `app/Services/PdfDocumentService.php`, regresi pada `tests/Feature/AdminOrderReportTest.php`, dan skrip/bukti di direktori ini. API `render(string, array): string` dipertahankan. Dompdf tetap **v3.1.6** sesuai `composer.lock`; tidak ada instalasi atau upgrade, server/port, transaksi database operasional, ataupun commit.

**Hasil terverifikasi:** PDF sintetis memiliki 3 halaman. Seluruh halaman dirasterisasi melalui PDFKit dan diperiksa secara visual. `Ĳ` dan `Ł` tampil sebagai huruf, bukan kotak glyph; `王小明`, diakritik Latin, pembungkusan nama panjang, serta footer halaman tetap terbaca. Tidak terlihat teks terpotong atau bertumpuk pada ketiga halaman yang diperiksa. Ini inspeksi visual manual, bukan image-diff otomatis.

## Akar masalah dan fix

- Inspeksi cmap dengan FontLib lokal: `NotoSansSC-Regular.ttf` dan `NotoSansSC-VF.ttf` masing-masing memiliki 30.445 pemetaan, tetapi tidak memiliki `Ĳ`, `ĳ`, `Ł`, `ł`.
- Font bawaan vendor Dompdf `DejaVuSans.ttf` memiliki glyph tersebut; tidak memiliki `王`. Karena itu, mengganti Noto sepenuhnya akan merusak CJK.
- CSS memakai `Report,ReportLatin`: Noto tetap utama, DejaVu Sans menjadi fallback per glyph melalui pemetaan font Dompdf.
- Pengukuran lebar sebelum pembungkusan memakai run font yang sama. Ini mencegah pengukuran `.notdef` Noto bagi glyph fallback.
- Registrasi kedua font gagal dengan exception jika font tidak tersedia. Remote fetch, PHP, JavaScript tetap nonaktif; protokol hanya `file://`, chroot dibatasi direktori font dan cache. Closure pemulihan memory limit (`finally`) tetap ada.

## TDD dan verifikasi kode

1. Test baru `test_pdf_service_embeds_latin_fallback_alongside_cjk` gagal sebelum fix karena PDF tidak menyertakan `DejaVuSans`.
2. Setelah fix, test memeriksa embedding dua font serta ekstraksi `Ĳ Ł 王小明` dan `ĳ ł 日本語`.
3. `php artisan test tests/Feature/AdminOrderReportTest.php`: **17 passed, 411 assertions**, 2,33 detik pada run terakhir.
4. `vendor/bin/pint --test app/Services/PdfDocumentService.php tests/Feature/AdminOrderReportTest.php`: **passed**.

Test fitur menggunakan konfigurasi PHPUnit SQLite `:memory:` dan `RefreshDatabase`, bukan database operasional. Test fitur tetap melakukan bootstrap Laravel biasa; tidak diklaim bebas pembacaan `.env` atau bebas penulisan cache storage aplikasi. Isolasi tanpa bootstrap berikut berlaku **hanya untuk generator bukti sintetis**.

## Isolasi generator dan reproduksi

Versi generator sebelumnya melakukan bootstrap kernel aplikasi. Komentar “no app .env sinks” tidak membuktikan isolasi; klaim tersebut dicabut.

`render-synthetic.php` sekarang hanya memuat autoloader dan membuat `Application` kosong untuk helper path. Tidak memuat `bootstrap/app.php`, kernel, `.env`, konfigurasi, provider, atau koneksi database. Storage diarahkan ke root baru sebelum service dipanggil. Root wajib direktori baru `zafira-pdf-*` langsung di bawah `sys_get_temp_dir()`; root yang sudah ada/symlink ditolak. Output wajib file baru langsung di dalam root tersebut. Binding `db`/`config` dan keberadaan environment `APP_KEY` menyebabkan penolakan. Uji pemakaian ulang root menghasilkan **exit 1** dengan pesan `isolated root already exists; refusing to reuse it`.

Contoh reproduksi (gunakan nama root baru setiap run):

```sh
ROOT="$(php -r 'echo sys_get_temp_dir();')/zafira-pdf-$(date +%s)-$$"
php docs/execution/evidence/2026-10-14/remediation/pdf/render-synthetic.php "$ROOT" "$ROOT/synthetic-report.pdf"
mkdir "$ROOT/pages"
swiftc -module-cache-path "$ROOT/module-cache" docs/execution/evidence/2026-10-14/remediation/pdf/render-pages.swift -o "$ROOT/render-pages"
"$ROOT/render-pages" "$ROOT/synthetic-report.pdf" "$ROOT/pages"
shasum -a 256 "$ROOT/synthetic-report.pdf" "$ROOT/pages/"*.png
```

Rasterizer menerima direktori output eksplisit dan menolak output di luar temp. Pada run bukti final, kompilasi Swift dengan `-O` dan cache bersama gagal signal 11; kompilasi tanpa `-O` dengan cache baru per root berhasil. Output PDFKit: `pages=3`. PNG berukuran 2382 × 3368; NSImage 2× ditambah skala Retina host menghasilkan raster tersebut. PNG final identik secara hash dengan tiga PNG yang telah diperiksa visual pada render sebelumnya.

## SHA-256 artefak final

| Artefak | SHA-256 |
|---|---|
| `synthetic-report.pdf` | `8200bce23761e3ceb77f7e633533de51c90906c6b6ebd13c3372288da9653dd6` |
| `pages/page-1.png` | `6c4bff5d1c5488044a42982cdcfa196c07cc4f5343815e2cff1bf6dbcb929027` |
| `pages/page-2.png` | `08aeaa361b84b7a96a884de81de8fa281688d867b745e3e5576827df4cbfd46a` |
| `pages/page-3.png` | `b56796d46ab4976a065bf01908a15562d1a1e735ca553a3ae6c60164c7c61391` |
| `pages/synthetic-report.png` (salinan halaman 1) | `6c4bff5d1c5488044a42982cdcfa196c07cc4f5343815e2cff1bf6dbcb929027` |

PDF memuat metadata waktu/ID sehingga hash PDF dapat berbeda antar-run meskipun raster identik. Hash di atas mengidentifikasi artefak tersimpan, bukan klaim determinisme byte PDF.

## Batasan dan keamanan data

Nama, periode, dan nilai uang dalam bukti adalah **data sintetis**, bukan riwayat pelanggan atau pendapatan nyata. Tidak ada kredensial atau isi database operasional pada bukti.

Tidak diuji: download melalui browser, seluruh suite repository, viewer selain PDFKit, seluruh Unicode, ataupun shaping kompleks. Dua font ini bukan jaminan cakupan semua aksara/emoji. Test embedding dan ekstraksi mencegah regresi pilihan font, tetapi tidak menggantikan verifikasi visual glyph. Root temp baru dibiarkan sebagai bukti lokal; tidak ada pembersihan root temp yang sudah ada.
