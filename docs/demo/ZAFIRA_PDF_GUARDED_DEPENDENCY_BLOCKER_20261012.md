# PDF Mandarin — status jalur dependensi terisolasi

Tanggal: 2026-10-12
Status: **BELUM SELESAI / BLOCKED**, bukan bukti kelulusan Unicode.

## Bukti aktual

- `app/Services/PdfDocumentService.php` masih menggunakan Helvetica/WinAnsi. `toWinAnsi()` mengganti karakter Mandarin dengan `?`.
- `composer.json` belum memuat dompdf; `resources/fonts/` belum tersedia.
- `scripts/amadis/launch.py` menolak Composer pada jalur `run`. Tidak ada capability pemasangan dependensi yang telah diverifikasi.
- Hook `post-autoload-dump` menjalankan `Illuminate\\Foundation\\ComposerScripts::postAutoloadDump`, kemudian Artisan proyek tanpa guard.
- Implementasi `ComposerScripts::clearCompiled()` menghapus cache konfigurasi. Menyiapkan cache isolasi sebelum Composer saja tidak cukup: hook berikutnya harus masuk melalui Artisan guarded dan membangun kembali konfigurasi isolasi.
- Hook `post-update-cmd` menjalankan `vendor:publish --tag=laravel-assets --force`. Menjalankan hook ini tanpa pembatasan akan menambah permukaan mutasi di luar perubahan PDF.
- Pemeriksaan historis manifest Composer global menemukan `laravel/installer`; inventaris tipe plugin seluruh paket global belum lengkap.

## Verifikasi yang benar-benar dijalankan

```text
python3 -m unittest discover -s tests/python -p 'test_am*.py'
Ran 27 tests in 0.714s
OK
```

Hasil ini hanya memverifikasi suite launcher yang ada. Tidak membuktikan instalasi Composer aman atau PDF Mandarin bekerja.

## Kandidat dependensi dan font

- `dompdf/dompdf`: metadata resmi Packagist mencantumkan v3.1.6, LGPL-2.1, PHP `^7.1 || ^8.0`, ekstensi DOM/mbstring.
- Dependensi langsung: `masterminds/html5`, `dompdf/php-font-lib`, `dompdf/php-svg-lib`. Allowlist harus mempertimbangkan dependensi transitif setelah resolusi; jangan menganggap empat paket sudah lengkap.
- Kandidat font: `LXGWWenKai-Regular.ttf`, distribusi TTF resmi LXGW; lisensi OFL-1.1 tersedia di repositori asal.
- API resmi mencantumkan berkas regular berukuran 25.575.676 byte dan Git blob `3e050e87caa5ce47f9c873079183e31f88fb8ce5` saat pemeriksaan. Ini bukan hash SHA-256 berkas.
- Font belum diunduh. Tabel `glyf`/`cmap`, ketiadaan `fvar`, cakupan glyph Mandarin, dan penggunaan memori belum diverifikasi. Kandidat bukan keputusan implementasi final.

Sumber:

- https://repo.packagist.org/p2/dompdf/dompdf.json
- https://api.github.com/repos/lxgw/LxgwWenKai/contents/fonts/TTF?ref=main
- https://raw.githubusercontent.com/lxgw/LxgwWenKai/main/OFL.txt

## Jalur penyelesaian minimal

1. Tambahkan capability Composer berargumen tetap dengan HOME/cache privat, validasi hook dan metadata plugin, serta allowlist paket resolusi yang tepat.
2. Arahkan hook yang diperlukan melalui entrypoint Artisan guarded dengan konfigurasi root sebelum bootstrap. Jangan memakai `--no-scripts` sebagai pengganti kontrol keamanan.
3. Uji penolakan argumen tambahan, perubahan hook, plugin tak diizinkan, root tidak valid, dan kegagalan bootstrap sebelum menjalankan resolusi nyata.
4. Unduh font dari revisi resmi yang dipatok; simpan lisensi, provenance, SHA-256, dan bukti cakupan glyph.
5. Pertahankan `render(string $title, array $lines): string`. Gunakan HTML UTF-8 yang di-escape, font tertanam, cache/temp privat, dan larangan fetch remote.
6. Ganti parser regex literal PDF dalam `AdminOrderReportTest.php` dengan parser semantik melalui capability terbatasi. Uji Mandarin, Latin, escaping, wrapping, pagination, footer, autentikasi, 500 pesanan, 366 hari, dan UTC.
7. Jalankan pengujian laporan melalui launcher. Verifikasi unduhan dan render browser secara terpisah.

## Batas pekerjaan

Percobaan perubahan capability launcher telah ditarik kembali dalam riwayat sesi; tidak ada capability Composer baru yang ditinggalkan. Karena launcher masih untracked, `git diff` kosong tidak membuktikan kesamaan dengan baseline sesi.

Tidak dilakukan instalasi dependensi, perubahan renderer, perubahan test PDF, migrasi database, startup layanan, atau commit. Pemeriksaan historis sempat menjalankan perintah metadata PHP/Composer langsung; itu bukan bukti eksekusi melalui guard. Tidak ada klaim bahwa seluruh perintah historis memenuhi kebijakan launcher.

Unduhan PDF dan render browser **belum diuji**. Celah Mandarin tetap ada.
