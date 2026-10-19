# Evaluasi PDF Unicode ZAFIRA — 12 Oktober 2026

## Status independen

**Perbaikan WinAnsi diterapkan langsung di working tree oleh subagent ini (`PdfDocumentService.php` dan `AdminOrderReportTest.php` disunting; `AdminOrderController.php` tidak diubah) dan lulus tes terarah. Status keseluruhan tetap NOT PASS** karena cakupan Unicode terbatas dan render visual NOT RUN (lihat Keputusan). Ketiga file scope berstatus untracked (`??`) di working tree, bukan di `HEAD`, sehingga `git diff` tidak menampilkan patch; pembanding baseline hanya dari hasil tes sebelum perubahan.

## Baseline dan batas

Baseline service memakai Helvetica Type1/WinAnsi dan mengganti seluruh byte non-ASCII dengan `?`. Ini merusak karakter Indonesia/Latin seperti `é`, tanda `—`, `€`, dan `×`. Kontrak controller yang dibaca tetap: laporan admin-authenticated, periode berbasis UTC, batas 500 order inklusif (501 menghasilkan 422), dan batas rentang sesuai kode saat ini. Controller tidak diedit.

Composer mengandung Laravel 12 saja sebagai runtime; tidak ditemukan pustaka PDF/font vendor. PHP lokal menyediakan `mbstring` dan `intl`; sistem memiliki Arial Unicode, tetapi font OS itu tidak dianggap aset dependency/berlisensi yang aman untuk dibundel. `pdftotext`, qpdf, mutool, Ghostscript, pypdf, dan PyMuPDF tidak tersedia.

## Pendekatan kandidat

Konversi UTF-8 ke byte Windows-1252 dengan substitusi `?` untuk input invalid, karakter tak terpetakan, serta byte kontrol. Normalisasi NFC dilakukan bila `intl` tersedia. Encoding stream sudah `/WinAnsiEncoding`, sesuai byte yang ditulis; escaping tanda kurung dan backslash dipertahankan. Font embedded Unicode, dukungan CJK/emoji/Cyrillic, PDF tagged, gambar, dan layout kaya tidak diklaim.

Keterbatasan ini disengaja: solusi tidak menambah dependency atau menyalin font sistem. Jika aksara di luar Windows-1252 wajib tampil, gunakan pustaka PDF yang didukung dan font Unicode dengan lisensi yang disetujui.

## Verifikasi yang benar-benar dijalankan

| Pemeriksaan | Hasil |
|---|---|
| PHPUnit langsung sebelum kandidat, `tests/Feature/AdminOrderReportTest.php` | Gagal 1/11 pada ekspektasi lama `Sutra ??`; mengonfirmasi baseline mengganti `é` dua kali menjadi tanda tanya |
| PHPUnit terarah melalui `FAST_TEST=1 AMADIS_ROOT=<root-baru> scripts/amadis/launch.sh test tests/Feature/AdminOrderReportTest.php` | Exit 0; 11 tes, 355 assertion lulus |
| `php -l app/Services/PdfDocumentService.php` | Exit 0; tanpa syntax error |
| `php -l tests/Feature/AdminOrderReportTest.php` | Exit 0; tanpa syntax error |
| PDFKit macOS parse + extraction dari PDF sintetis | 1 halaman terbaca; `Lumière — €`, `Sutra café naïve × ™` diekstrak; CJK/emoji menjadi `?? ?`; input combining `Lumiére` diekstrak sebagai `Lumiére` |
| Pemeriksaan layout/render visual di browser atau PDF viewer | **NOT RUN**; tes unit dan ekstraksi teks bukan bukti fidelity layout visual |

PDF sintetis ditulis ke root temp terisolasi `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-unicode.pdf`. Test launcher memakai root baru `amadis-35465b29333c851200e2b00d`; tidak membuka server atau port.

## Hash source saat pencatatan

SHA-256 pada saat laporan ditulis:

- `app/Services/PdfDocumentService.php`: `24ea0282ab13c2bbdeccf221fe9d783168987c0f87b155105cd4c8a67a5d96c8`
- `app/Http/Controllers/AdminOrderController.php`: `8605e3806a1b947153d1fe447c34d31a09890eae7ec8ff51ece272bc5c1f18c7`
- `tests/Feature/AdminOrderReportTest.php`: `cef464007ba1f0b8ebb9c500c2ad63641ccd1783cd5f2c79230d5e47a284ef2e`

Hash menangkap isi working tree saat inspeksi; bukan bukti file masuk commit. Karena file tidak tracked saat itu, diff git standar tidak menunjukkan patch-nya.

## Keputusan

Status keseluruhan: **NOT PASS**. Bukti mendukung pemetaan teks WinAnsi (Latin-1 + `€ — ™`) untuk aksara yang dites, bukan Unicode universal dan bukan kualitas visual. Sisa: inspeksi render visual (NOT RUN) dan keputusan font Unicode embed bila CJK/emoji dibutuhkan. Catatan: `FAST_TEST` tidak didefinisikan di repo atau launcher (hasil grep kosong); variabel hanya diset, tanpa efek yang terverifikasi.
