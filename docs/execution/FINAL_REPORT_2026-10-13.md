# Laporan akhir ZAFIRA / Amadis — 13 Oktober 2026

## 1. Ringkasan eksekutif

Status: **demo lokal bersyarat; produksi belum siap**.

Dompdf `3.1.6` dan tepat lima paket closure PDF terpasang melalui guarded isolated stage. Unduhan PDF nyata dari admin UI terbukti dengan PDFKit, font Unicode/CJK embedded, dua halaman, dan inspeksi visual. Gate closeout S0–S5 dan Pint delapan file lulus (lihat §6). Sentinel export dan audit allowlist bundle telah dijalankan; hasil keseluruhan PARTIAL karena provenance dan TOCTOU masih terbuka (lihat §8 dan INTERIM). Advisory Composer 13 Oktober sudah diremediasi pada pembaruan 14 Oktober: CommonMark 2.10.3, audit guarded tanpa advisory.

Tidak ada commit, push, deploy, database operasional, `.env`, atau reset destruktif.

## 2. Bukti PDF UI dan rekonsiliasi data

Bukti lengkap: [`evidence/2026-10-13/pdf-ui/`](evidence/2026-10-13/pdf-ui/README.md).

- Filter UI: `custom`, dari `2026-10-13` sampai `2026-10-13` UTC.
- Waktu unduh: `2026-10-12T20:24:13.585Z` UTC.
- PDF: `orders-20261013.pdf`, 10.199 bytes.
- SHA-256 sebelum dan sesudah penyalinan: `ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc`.
- Isi: 48 order, 8 cancelled, 40 pending, total non-cancelled `Rp 4.960.000`.
- Query readonly SQLite demo menghasilkan angka yang sama.
- Nama Mandarin `王小明`, nama panjang ter-wrap, dan fallback `-` untuk nama blank terbukti.
- PDFKit mengekstrak teks dua halaman.
- Font subset `NotoSansSC-Regular`, `CIDFontType2`, `FontFile2`, dan `ToUnicode` terbukti.
- Render kedua halaman diperiksa dari file unduhan yang sama.

Data fiktif dan terisolasi. Bukan data operasional atau fixture S0–S5.

## 3. Audit stage dan temuan yang ditutup

Bukti runtime terkini: [`stage-runtime/66dd10879584/report.json`](evidence/2026-10-13/stage-runtime/66dd10879584/report.json), dengan 11 predikat **PASS**, `returncode: 0`, dan `applied_to_mac: false`. [`stage-runtime/REPORT.md`](evidence/2026-10-13/stage-runtime/REPORT.md) menjelaskan topologi dan regresi rollback; penamaan run final di laporan itu masih merujuk run sebelumnya.

- **Direct-egress:** topologi proxy/internal PASS; koneksi literal-IP ke `1.1.1.1`, `8.8.8.8`, dan `9.9.9.9:443` ditolak dengan errno 101. Kontrol bridge berhasil terhubung. Proxy yang diizinkan dan delapan TLS paralel PASS; target terlarang/privat/sentinel ditolak. Probe `--network none` pada `stage-closeout` hanya riwayat, bukan bukti runtime terkini.
- **Rollback:** regresi fixture disposable `test_partial_copy_failure_rolls_back_to_initial_digest` PASS (`23bdbb01260e/unittest.*`: 28 tests, OK; tidak diulang pada run `66dd10879584`): salinan kedua gagal setelah salinan pertama menimpa file; digest pulih dan file baru dihapus. Ini bukan predikat runtime container atau bukti kegagalan rollback itu sendiri.
- **Ownership dan akses container:** `runtime_sentinel` PASS: uid/gid `501:20`, owner sentinel stage/root `501:20`, read/write sukses, `CapEff: 0`, `NoNewPrivs: 1`, rootfs write ditolak, dan repo sumber tidak terlihat. Bukti ini berlaku pada VM Linux Docker Desktop, bukan host Linux produksi atau kebijakan firewall eksternal.
- **Stage hash historis:** tree disposable `ffcb1dd14491` tidak tersedia lengkap untuk hash ulang. Status: **evidence gap tetap terbuka**; run runtime baru tidak merekonstruksi tree tersebut.
- **Provenance sumber:** run `66dd10879584` tidak memiliki `source-hashes.json`; manifest historis `stage-runtime/SHA256SUMS` tidak mencakup run baru. Hash sumber saat ini di §8 bukan bukti pengikatan sumber saat eksekusi. Status: **source provenance gap tetap terbuka**.

## 4. Triase advisory Composer

Bukti audit otoritatif: [`guarded-audit/REPORT.md`](evidence/2026-10-13/guarded-audit/REPORT.md); [proposal CommonMark 2.10.3](evidence/2026-10-13/guarded-audit/commonmark-upgrade-proposal.md).

`league/commonmark` `2.10.0` memiliki:

- `PKSA-m2dq-1fhr-29b1` / `GHSA-97jj-33gv-5xf9`, medium, affected `>=1.3.0,<=2.10.1`.
- `PKSA-m4t9-vsgq-8khn` / `GHSA-3q6v-r5mr-hxv8`, high, affected `>=2.0.0,<=2.10.1`.

Paket ditarik Laravel Framework dan digunakan oleh Markdown mailables. Paket sudah ada pada lock HEAD sebelum pekerjaan PDF. Audit terjaga `./scripts/amadis/launch.sh composer-audit --locked --format=json` menghasilkan JSON valid, stderr kosong, dan rc **1 karena dua advisory**; hash manifest/installed metadata sebelum dan sesudah sama. Audit langsung sebelumnya dalam [`composer-audit/REPORT.md`](evidence/2026-10-13/composer-audit/REPORT.md) hanya diagnostik setelah launcher menolak `run composer`, bukan bukti guarded acceptance.

Proposal terpisah mengusulkan `league/commonmark:2.10.3`; status 13 Oktober: belum diterapkan.

**Pembaruan 14 Oktober 2026 (disetujui pengguna):** `league/commonmark` diupgrade `2.10.0 → 2.10.3`. Bukti: [`commonmark-upgrade/README.md`](evidence/2026-10-14/commonmark-upgrade/README.md). Diff `composer.lock` mengubah versi CommonMark saja di luar lima paket PDF yang sudah ada sebelumnya. `composer.json` tidak diubah oleh upgrade ini. `installed.json` cocok dengan lock. Audit guarded diulang: `advisories: []`. Suite guarded PHP: 158 passed, 1.253 assertions. Batas: render Markdown mail langsung belum diuji; test hanya memverifikasi mail masuk antrean.

## 5. Verifikasi dependency Mac

Verifikasi dependency Mac tidak lulus menyeluruh (bukan wholesale PASS). Hasil dan catatan:

- Tepat lima paket baru: `dompdf/dompdf`, `dompdf/php-font-lib`, `dompdf/php-svg-lib`, `masterminds/html5`, `sabberworm/php-css-parser`.
- Tidak ada paket lama dihapus atau versinya berubah.
- Platform dan platform-dev lock tetap identik dengan HEAD.
- `vendor/composer/installed.json` cocok dengan lock untuk paket production.
- `composer check-platform-reqs --lock`: rc 0.
- Composer manifest berubah sesuai scope: Dompdf require dan guarded Composer hooks.
- **Masalah pin dan hash belum terselesaikan:** [`stage-closeout/mac-verification.json`](evidence/2026-10-13/stage-closeout/mac-verification.json) mencatat pin `false` untuk `dompdf/dompdf` dan `sabberworm/php-css-parser` (hanya 3/5 bernilai `true`) serta 226 `hash_mismatches` dari `hash_files: 229`. Penyebab belum direkonsiliasi; temuan ini tetap OPEN dan tidak digantikan oleh pemeriksaan lock/installed di atas.

## 6. Full gate dan S0–S5

Bukti terbaru: [`final-gate-closeout/REPORT.md`](evidence/2026-10-13/final-gate-closeout/REPORT.md), `commands.json` (timestamp UTC, argv, raw rc), `source-before.json`/`source-after.json` dan `harness-hashes.json`. Semua gate tabel ini rc 0 kecuali pemeriksaan port: rc 1 berarti tidak ada listener. S0–S5 stok: **37→34→37→37→37**; selisih satu assertion PHP berasal dari cache helper `pdfText()`, bukan assertion gagal. Laporan `final-gate/` sebelumnya dipertahankan sebagai riwayat.

| Gate | Hasil |
|---|---|
| PHP standar | PASS, 158 tests, 1.253 assertions |
| PHP acak seed 20261013 | PASS, 158 tests, 1.252 assertions |
| Node | PASS, 86 tests, 0 failed |
| Python | PASS, 133 tests, 0 skipped (satu `ResourceWarning` non-fatal) |
| Browser S0–S5 | PASS, semua tahap lulus |
| Vite build | PASS, JS 19.50 kB |
| `git diff --check` | PASS |
| Pint | PASS, delapan file PHP requested (build diff S0–S5) |
| Sentinel export | PASS terbatas pada `export-closeout` (capture/export rc 0); keseluruhan PARTIAL |
| Bundle allowlist audit | PASS terbatas pada `export-closeout`; provenance self-asserted dan TOCTOU OPEN |

Port 8016 kosong sebelum dan sesudah lifecycle. Root temp final gate belum dihapus; root PDF dipertahankan.

## 7. Acceptance matrix

AM-01 sampai AM-17 bukan acceptance PASS menyeluruh karena rubrik resmi tidak ditemukan; CSV membedakan uji terisolasi, feature test, dan NFR-003 yang belum dijalankan.

- **PASS berbasis feature test saja:** AM-03, AM-04, AM-05, AM-07, AM-08, AM-09, AM-11, AM-12, AM-13, AM-14, AM-15, AM-16, AM-17 (`final-gate-closeout/REPORT.md`, PHP rc 0).
- **PASS terisolasi:** AM-01 (76 produk, 4×19), AM-02 (9 halaman × 8 + 4), AM-06 (keyboard dan escaping), AM-10 (3 request paralel; stok akhir 0, unit bersih 37). `acceptance-am-isolated/REPORT.md` dan `commands.tsv` mencatat semua rc 0 pada `2026-10-13T03:32:37Z`–`03:32:44Z`; `SHA256SUMS` valid. AM-10 hanya SQLite, bukan bukti locking MariaDB.
- **F1–F9:** NOT RETESTED pada gate 13 Oktober. **Retest 14 Oktober** ([`f-findings-retest/REPORT.md`](evidence/2026-10-14/f-findings-retest/REPORT.md); `SHA256SUMS` 34 file OK; Playwright rc 0) memakai server manual 8016 dengan 12 produk: F1–F6 **STILL PRESENT**; F7–F8 **NOT REPRODUCIBLE ON THIS DATA**; F9 **INVALID ORIGINAL CHECK**. Retest ini tidak mengubah kode.

Status tersebut membuktikan cakupan test, bukan kesiapan produksi atau kelulusan rubric resmi.

## 8. Blocker, risiko, dan tindak lanjut

1. Pint delapan file dan full gate kini PASS pada closeout terisolasi; pertahankan batas cakupan, jangan klaim semua gate produksi lulus.
2. Export sentinel dan audit allowlist bundle kini PASS terbatas (`export-closeout/LAPORAN_EXPORT_DAN_AUDIT_PDF.md`, capture/export rc 0); status keseluruhan PARTIAL karena provenance self-asserted, TOCTOU cleanup OPEN untuk proses UID sama, dan visual history belum diperiksa.
3. Stage-runtime run terbaru `66dd10879584/report.json` mencatat 11 predikat PASS, `returncode: 0`, Docker Desktop Linux VM; hash report `f26d7b4cdd31520b5f75a818985c53aa8dc8e8cea315627f418d9272f8c3aa6a`. Manifest historis `stage-runtime/SHA256SUMS` hanya mencakup run sebelumnya dan tidak diubah; sumber saat ini `docker_stage.py` SHA-256 `0c992b683167f996d6c45c70bfe7121e1b32fa9211602fb914d8155fd548257b`, test SHA-256 `12a600bbea550c63a79aa4af9cde965bc12cf29955d1c5e3c83f944ccccc72e4` (tidak ada `source-hashes.json` pada run baru).
4. CommonMark: diupgrade ke 2.10.3 pada 14 Oktober; audit guarded kini tanpa advisory (`commonmark-upgrade/README.md`). Sisa risiko: render langsung dua Markdown mailables belum diuji.
5. AM-01/02/06/10 kini PASS terbatas pada fixture SQLite terisolasi (`acceptance-am-isolated/REPORT.md`, `commands.tsv`, `invariants.json`; 2026-10-13T03:32:37Z–03:32:44Z; migration/seed/provision/browser/AM-10 rc 0; SHA-256 manifest `acceptance-am-isolated/SHA256SUMS`). AM-10 belum membuktikan locking MariaDB; rubric resmi tetap tidak ditemukan. F1–F6 masih ada pada retest 14 Oktober dan perlu perbaikan UI/copy terpisah (F4–F6 butuh keputusan owner).
6. Pertahankan NFR-003 sebagai belum terbukti karena MariaDB tetap memiliki `ERROR 1558` historis.
7. Pertahankan status evidence kompetitor dan credential lama sesuai bukti; jangan mengarang completion.
8. Root temp dipertahankan untuk audit atas keputusan pengguna 14 Oktober; jangan dibersihkan tanpa persetujuan baru.

### Rujukan utama

- [`docs/execution/EXECUTION_LEDGER.md`](EXECUTION_LEDGER.md)
- [`docs/execution/evidence/2026-10-13/pdf-ui/README.md`](evidence/2026-10-13/pdf-ui/README.md)
- [`docs/execution/evidence/2026-10-13/stage-closeout/stage-closeout.md`](evidence/2026-10-13/stage-closeout/stage-closeout.md)
- [`docs/execution/evidence/2026-10-13/composer-audit/REPORT.md`](evidence/2026-10-13/composer-audit/REPORT.md)
- [`docs/execution/evidence/2026-10-13/final-gate/LAPORAN_FINAL_GATE.md`](evidence/2026-10-13/final-gate/LAPORAN_FINAL_GATE.md)
- [`docs/execution/evidence/2026-10-13/final-gate-closeout/REPORT.md`](evidence/2026-10-13/final-gate-closeout/REPORT.md)
- [`docs/execution/evidence/2026-10-13/acceptance-am-isolated/REPORT.md`](evidence/2026-10-13/acceptance-am-isolated/REPORT.md)
- [`docs/execution/evidence/2026-10-13/stage-runtime/REPORT.md`](evidence/2026-10-13/stage-runtime/REPORT.md)
- [`docs/execution/evidence/2026-10-13/guarded-audit/REPORT.md`](evidence/2026-10-13/guarded-audit/REPORT.md)
- [`docs/execution/evidence/2026-10-13/export-closeout/LAPORAN_EXPORT_DAN_AUDIT_PDF.md`](evidence/2026-10-13/export-closeout/LAPORAN_EXPORT_DAN_AUDIT_PDF.md)

## INTERIM: Catatan sinkronisasi bukti 13 Oktober 2026

Catatan ini bersifat historis setelah pembaruan 14 Oktober. Klaim advisory CommonMark dan F1–F9 di bawah tidak menggantikan bukti terbaru.

Bagian interim ini mencatat kemajuan bukti terkini per 13 Oktober 2026 tanpa mengubah struktur delapan bagian utama:

1. **Stage runtime run terbaru `66dd10879584`:** 11 predikat PASS, `returncode: 0`, Docker Desktop VM Linux, mtime `2026-10-13 12:15:50` (lokal). Hash report `f26d7b4cdd31520b5f75a818985c53aa8dc8e8cea315627f418d9272f8c3aa6a`. Kode sumber saat ini cocok (`docker_stage.py` `0c992b683167f996d6c45c70bfe7121e1b32fa9211602fb914d8155fd548257b`, `test_amadis_docker_stage.py` `12a600bbea550c63a79aa4af9cde965bc12cf29955d1c5e3c83f944ccccc72e4`). Berkas `source-hashes.json` tidak ada pada run ini; manifest historis `stage-runtime/SHA256SUMS` dipertahankan tidak berubah.
2. **Final gate closeout (`final-gate-closeout/REPORT.md`):** PHP standar 158 tests / 1.253 assertions PASS (rc 0), PHP acak seed 20261013 158 tests / 1.252 assertions PASS (rc 0); Node 86 tests PASS (rc 0); Python 133 tests PASS (rc 0, 0 skipped, FontTools 4.66.1); Pint delapan file requested PASS (rc 0); S0–S5 PASS (stok 37→34→37→37→37). Laporan `final-gate/LAPORAN_FINAL_GATE.md` (Pint 8 fail, Python 102/6 skip) tetap tersimpan sebagai riwayat run sebelumnya.
3. **Acceptance AM terisolasi (`acceptance-am-isolated/REPORT.md`):** AM-01, AM-02, AM-06, AM-10 PASS pada SQLite terisolasi. AM-10 mengonfirmasi 3 request paralel 20/20/17 menghasilkan 201/422/201, stok akhir 0, unit bersih 37 (0+37=37). Tidak membuktikan locking MariaDB produksi. Baris AM lain berstatus feature test lulus pada gate; F1–F9 NOT RETESTED.
4. **Guarded audit (`guarded-audit/REPORT.md`):** rc 1 karena advisory Composer `league/commonmark:2.10.0` (PKSA-m2dq-1fhr-29b1, PKSA-m4t9-vsgq-8khn). Usulan upgrade 2.10.3 belum diterapkan; mailable belum diuji.
5. **Export sentinel & audit PDF (`export-closeout/LAPORAN_EXPORT_DAN_AUDIT_PDF.md`):** Exporter capture/export rc 0, 20 test PASS. PDF `orders-20261013.pdf` SHA-256 `ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc`. Status PARTIAL: mitigasi TOCTOU parsial (OPEN untuk UID sama), identitas self-asserted, screenshot visual history belum diperiksa visual lengkap.
6. **Keamanan & integritas (sinkronisasi dokumen):** Interim ini hanya sinkronisasi dokumen. Pekerjaan closeout sebelumnya **memang mengubah kode**, antara lain `scripts/amadis/docker_stage.py`, `scripts/amadis/launch.py` (`composer-audit`), dan test Python terkait. Sinkronisasi ini tidak mengubah kode, dependency, bukti, database operasional, `.env`, atau git commit. Status keseluruhan tetap: demo lokal bersyarat; produksi belum siap.
