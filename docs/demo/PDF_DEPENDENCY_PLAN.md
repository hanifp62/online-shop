# Rencana kapabilitas dependensi PDF

## Status — 13 Oktober 2026

Instalasi guarded Dompdf `3.1.6` dan lima-package closure sudah diterapkan dari stage terisolasi. Bukti stage historis dan verifikasi host tersimpan di `docs/execution/evidence/2026-10-13/pdf-stage/ffcb1dd14491/`; bukti UI nyata tersimpan di `docs/execution/evidence/2026-10-13/pdf-ui/`. Status acceptance akhir tetap bergantung pada full gate dan audit bundle.

**Open risk:** `league/commonmark` `2.10.0` memiliki dua advisory Composer yang sudah ada pada lock HEAD sebelum pekerjaan PDF. Paket ini berada di luar lima-package closure; tidak diubah tanpa keputusan terpisah. Detail: `docs/execution/evidence/2026-10-13/composer-audit/REPORT.md`.

Implementasi kandidat tersedia di `scripts/amadis/launch.py`. Jangan menjalankan instalasi ulang atau mengubah lock tanpa keputusan baru.

## Kontrak

- **FR-PDF-DEP-001:** Composer menghasilkan kandidat dari salinan manifest dan lock di direktori luar repositori. Acceptance: perubahan manifest hanya menambahkan `dompdf/dompdf:3.1.6`; seluruh paket lama dan metadata platform tetap identik.
- **FR-PDF-DEP-002:** Kandidat menambahkan tepat lima paket pada `PDF_PINS`. Acceptance: versi, commit source/dist 40 karakter, URL repositori resmi, lisensi, dan requirement cocok dengan metadata Packagist yang diekspansi.
- **NFR-PDF-DEP-001:** Mutasi terikat fingerprint manifest, lock, dan installed.json. Acceptance: perubahan fingerprint membatalkan operasi sebelum penulisan.
- **NFR-PDF-DEP-002:** Scripts, plugins, dan audit tidak dinonaktifkan. Acceptance: instalasi nyata menjalankan hook guarded dengan lingkungan terisolasi dan log exit yang tersimpan.
- **NFR-PDF-DEP-003:** Bukti integritas membedakan hash arsip lokal dari checksum upstream. Acceptance: lima arsip mempunyai SHA-256 lokal; checksum upstream kosong dilaporkan sebagai tidak tersedia.

## Alur implementasi saat ini

Launcher memvalidasi hook dan allowlist plugin, mengunci operasi, mengambil metadata lima paket, lalu menjalankan `composer require dompdf/dompdf:3.1.6 --no-install` pada salinan kandidat. Validasi membandingkan seluruh entri paket lama dan delta kandidat. Mode `dry-run` berhenti setelah validasi; mode `install` menyalin byte kandidat yang divalidasi lalu menjalankan `composer install` pada repositori dengan lingkungan launcher.

HOME/cache Composer dibuat privat per operasi. Backup manifest dan lock serta log Composer disimpan pada direktori evidence. Kegagalan instalasi dapat meninggalkan manifest atau vendor berubah sebagian; tidak ada jaminan rollback.

## Inventaris isolasi — 13 Oktober 2026

- `sandbox-exec` tetap **tidak disetujui**: worker abort `-6`/SIGABRT sebelum menghasilkan output; enforcement tidak terbukti. Iterasi profil dihentikan.
- Crash report readonly `true-2026-10-13-000055.ips` menunjukkan `EXC_CRASH/SIGABRT` di `dyld` (`ProcessConfig/DyldCache`) sebelum `main()`. Akar penyebab belum teridentifikasi.
- Docker CLI tersedia (`29.8.2`, Docker Desktop `4.94.0`). **Historis:** pada pemeriksaan awal daemon belum siap (`~/.docker/run/docker.sock` tidak tersedia). **Terkini:** Docker aktif, `docker version` server `29.8.2`, arm64.
- Image terunduh & berjalan: `php:8.3-cli`, digest `sha256:aafe21201943a8a6e497ddfb471e2ce68ada76adede38c7d61fede8918b24319`. Smoke test non-root, `--network none`, read-only root, `cap-drop ALL`, `no-new-privileges`, batas pids/memory lulus untuk tulis `/stage` dan penolakan tulis rootfs. Status: **smoke test only**, bukan bukti platform penuh atau guard aplikasi.
- **Approved full-app guarded Docker stage:** sedang dibangun oleh agen terpisah; **belum selesai**.
- Python: Hasil historis **65 total tests: 6 skipped (59 passed), OK, exit 0** (bukan 65 passed). **Koreksi faktual:** tebakan alasan skip sebelumnya keliru. Tidak ada skip pada jalur Composer. Pada `tests/python/test_amadis_pdf_font.py:52`, seluruh kelas `PdfFontStaticInstanceTests` di-skip karena pustaka `FontTools` (`TTFont`) tidak terpasang di interpreter aktif (`@unittest.skipIf(TTFont is None, ...)`). Enam test yang di-skip adalah:
  1. `test_no_variable_tables_and_glyf_outlines`
  2. `test_weight_and_names_are_regular`
  3. `test_cmap_is_same_as_variable_source`
  4. `test_required_coverage`
  5. `test_regular_is_not_the_default_thin_master`
  6. `test_check_rejects_variable_font`
- Instalasi langsung `launch.sh pdf-dependency install` ke repo dan status daemon unready adalah prosedur/catatan **historis** dan tidak boleh dipakai.
- Tidak ada Dockerfile, Compose file, atau `.devcontainer` bawaan di repo. Docker tidak boleh dianggap memvalidasi perilaku macOS; container hanya dapat memvalidasi stage Linux.
- Tidak ada Podman, Lima, OrbStack, UTM, VirtualBox, QEMU, atau runtime VM lain yang terdeteksi pada PATH/aplikasi yang diperiksa.
- Tidak ada tindakan memasang software atau mengubah konfigurasi sistem host dilakukan.

Evidence probe diperbarui di `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/isolation-probe-1yu0o4i4/report.json`. `PROBE_RC=0` hanya status pengumpul. Control worker `0`; sandbox worker `-6`; status enforcement `UNVERIFIED`. Probe timeout TEST-NET dan probe listener loopback dicatat terpisah. Hasil sandbox hilang/abort menggagalkan penilaian. Sentinel lama tidak boleh dianggap bukti enforcement karena fixture kontrol dan sandbox memakai isi sama; harness gap dicatat.

## Verifikasi dan blocker

### Blocker aktif

1. Stage Composer guarded full-app belum selesai (approved full-app guarded Docker stage sedang dibangun oleh agen terpisah; belum selesai).
2. Dompdf `3.1.6` belum terpasang. Acceptance PDF nyata belum tersedia.
3. Image running `php:8.3-cli` (`sha256:aafe21201943a8a6e497ddfb471e2ce68ada76adede38c7d61fede8918b24319`) saat ini berstatus smoke test only; belum memvalidasi guard aplikasi penuh.
4. Prosedur langsung ke repositori dan status daemon belum siap adalah historis; eksekusi wajib melalui stage kontainer terisolasi yang disetujui.

### Pekerjaan independen selesai

- Kesimpulan probe dikoreksi menjadi proses abort dengan tahap dan akar penyebab belum teridentifikasi.
- Status collector, worker, dan enforcement dipisahkan.
- Bukti crash report dicatat readonly tanpa memperluas profil sandbox.
- Inventaris runtime isolasi dan integrasi repo dicatat.
- Font statis, provenance, transaksi launcher, rollback, dan hasil tes Python (65 total: 59 passed, 6 skipped karena ketiadaan FontTools, exit 0) dicatat sesuai bukti aktual.

### Acceptance belum boleh diklaim

Jangan menandai PDF dummy, test WinAnsi, atau bukti font sebagai acceptance PDF Dompdf. Acceptance memerlukan instalasi guarded nyata, autoload dan hook terverifikasi di stage, lalu unduhan UI dengan ekstraksi teks CJK/amount dan render multipage.

## Checklist eksekusi langkah tersisa

1. **Runtime check-platform-reqs & SQLite:** Verifikasi `composer check-platform-reqs --lock` dan ketersediaan ekstensi PHP serta runtime SQLite aktual di dalam container stage.
2. **Full-app copy exclusions:** Terapkan eksklusi ketat saat menyalin aplikasi ke stage kontainer (abaikan `.env`, kredensial, socket Docker, database operasional, demo DB, cache host, dan artefak sensitif).
3. **Final boundary & network isolation:** Verifikasi kepatuhan boundary kontainer (`--network none`, non-root user, root filesystem read-only, `cap-drop ALL`, `no-new-privileges`, alokasi memory/pids terikat).
4. **5 package install, guarded hooks & delta:** Jalankan instalasi tepat 5 paket pin (`dompdf/dompdf:3.1.6` beserta 4 dependensi `PDF_PINS`), verifikasi fingerprint manifest/lock, eksekusi hook guarded, dan validasi delta candidate.
5. **Mac artifacts verification:** Verifikasi integritas dan kecocokan artefak yang dihasilkan stage sebelum ditempatkan pada host macOS.
6. **UI PDF acceptance:** Uji unduhan PDF nyata dari antarmuka storefront/atelier, verifikasi render dokumen multi-halaman, serta pastikan ekstraksi teks CJK dan nominal amount sesuai dokumen.
7. **Final gate, export & docs:** Persetujuan akhir (final gate sign-off), ekspor bukti integritas, dan sinkronisasi dokumentasi rekonsiliasi.

Jangan mengatasi blocker dengan `--no-scripts`, `--no-plugins`, bypass audit, lock manual, atau perubahan hook di luar cakupan kepemilikan.

## Verifikasi dan blocker sebelumnya (12 Oktober 2026)

Perintah `python3 -m unittest discover -s tests/python -p 'test_am*.py'` menghasilkan **60 tests, OK, 6 skipped** pada sesi ini. Pengujian mencakup ekspansi/inheritance/penghapusan metadata, delta kandidat, penolakan perubahan paket lama/platform/URL/ref/tipe plugin, dan argumen subprocess.

Bukti ini belum cukup untuk izin instalasi:

1. Pemeriksaan independen belum tersedia atau dijalankan.
2. Pengujian alur dry-run masih memock validator; jalur instalasi sukses, kegagalan parsial, dan perubahan fingerprint belum diuji menyeluruh.
3. Kompatibilitas kandidat `--no-install` dengan hook `post-update-cmd` belum dibuktikan. Kandidat tidak mempunyai autoloader aplikasi; hook guarded mensyaratkan cwd repositori.
4. Pencarian arsip cache berdasarkan `<commit>.zip` belum diverifikasi terhadap penamaan cache Composer. Hash yang tidak ditemukan saat ini menjadi null dan tidak menggagalkan status verified.
5. Tidak ada bukti dry-run launcher atau instalasi launcher nyata pada sesi ini. Percobaan test yang sempat mencapai subprocess nyata gagal dengan `Bad file descriptor`; itu bukan bukti eksekusi Composer yang berhasil.

Jangan mengatasi blocker dengan `--no-scripts`, `--no-plugins`, bypass audit, lock manual, atau perubahan hook di luar cakupan kepemilikan. Rendering PDF, font, dan database operasional belum diuji.
