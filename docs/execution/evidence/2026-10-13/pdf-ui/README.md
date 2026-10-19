# Bukti unduhan PDF admin UI — ZAFIRA

Run berhasil: Playwright terhadap server terisolasi, periode custom 13-10-2026 UTC.

- Aksi UI: login admin, dashboard `/admin`, formulir periode `custom`, tanggal `2026-10-13`, tombol `Unduh PDF`.
- File: `orders-20261013.pdf` (`file_size: 10199`, SHA-256 `ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc`).
- Waktu unduh: `2026-10-12T20:24:13.585Z` UTC. Periode laporan 2026-10-13 UTC menjelaskan perbedaan tanggal dengan waktu run.
- Isi: periode `13-10-2026 00:00 hingga 13-10-2026 23:59 UTC`, 48 pesanan, 8 batal, total non-batal Rp 4.960.000.
- Nama: `王小明` (`12`), `Madame Élodie de Longueville` (`12`), nama panjang wrap (`12`), fallback `-` (`12` blank, relasi utuh).
- Database: SQLite demo terisolasi, seluruh 48 order `2026-10-13 08:01–08:48 UTC`, 40 pending, 8 cancelled, total aktif `4960000`, `orphan=0`, `blank=12`.
- Provenance fixture: fiktif terisolasi (`scripts/amadis/pdf-ui-fixture.php`, 48 order, total non-batal 4960000), bukan transaksi operasional/S0–S5.
- Parsing: PDFKit (macOS) 2 halaman teks, `王小明` 12 kemunculan, font subset `SUBAAB+NotoSansSC-Regular`, `CIDFontType2`/`FontFile2`/`Type0`/`ToUnicode`.
- Inspeksi visual: `render/page-1.png` dan `render/page-2.png` dari file yang sama dibaca nyata, header/ringkasan/48 baris/paginasi benar.
- Kode terkait: `app/Http/Controllers/AdminOrderController.php` (`e6bf4cf894fd8ff4c381741e7a73374420669b95a9bd4e92c1627bbb795ead72`), `app/Services/PdfDocumentService.php` (`27aa79924298e96c74daba2b79164d858e3507ad25723ac2d1fc622e6e38ce91`), font `resources/fonts/NotoSansSC-Regular.ttf` (`09e1417d9e3ae2fc7db6f136d51905f10f3ec10851da7383c60c5ed38c20c31a`), helper `scripts/amadis/pdf-ui-download.cjs` (`9896e36644652b62079874bfb6b48bd4b4baeacd5676d992776702e87da5c1c9`), fixture `scripts/amadis/pdf-ui-fixture.php` (`95cdeb4e5aa5eab13c87683c7727758b420640e82797f92a224396a1a1c49a68`).
- Commit referensi: `2f355e1624306d7277f7828ee319321b21bee0ea`, tidak ada nilai secret di bundle ini.

Riwayat kegagalan sebelumnya dipertahankan di `history/` tanpa mengubah bukti run berhasil.
