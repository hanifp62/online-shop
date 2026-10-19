# Closeout export dan audit PDF

Tanggal: 2026-10-13. Status keseluruhan: **PARTIAL**, bukan persetujuan publikasi bebas rahasia.

## Implementasi dan pengujian

- `scripts/amadis/export_evidence.py`: exporter CLI mandiri; tidak mengubah `launch.py`.
- Kontrak: satu stem `.json/.stdout/.stderr`, ditambah manifest hash sumber dan hasil.
- Validasi cwd repo, identitas repo/root/database, root `amadis-<24hex>`, tujuan kosong langsung di BASE.
- Direktori dan berkas harus dimiliki UID pemanggil tanpa izin group/other. Ini bukan pemeriksaan mode tepat 0700.
- Pembukaan komponen memakai descriptor relatif dan `O_NOFOLLOW`; berkas reguler harus single-link.
- Tujuan memakai advisory lock dan exclusive creation. Ini bukan perlindungan terhadap proses jahat dengan UID sama.
- Sanitasi ulang memakai helper `launch`; tidak mendeteksi rahasia arbitrer, encoding, atau semua informasi pribadi.

`python3 -m unittest tests.python.test_amadis_export`: 15 tes PASS.
`python3 -m unittest discover -s tests/python -p "test_am*.py"`: 126 tes, OK, 6 skipped; terlihat ResourceWarning koneksi SQLite pada suite lain.
Output ringkas pengujian historis tersedia dalam sesi; belum diarsipkan sebagai log lengkap.

## Bukti capture nyata

`run_export_evidence.py` menjalankan child process nyata melalui `launch.capture()`, lalu exporter CLI nyata; bukan mock.
Root dan tujuan disposable di BASE dibersihkan setelah pemeriksaan.
`export-run.json` merekam waktu, rc capture/export, hash kode exporter/launch, hash dan ukuran seluruh keluaran.
Capture: `2026-10-13T03:16:09.304846+00:00`, rc 0.
Export: `2026-10-13T03:16:09.347787+00:00`, rc 0.

Empat keluaran dibaca seluruhnya, termasuk manifest: allowlist tepat, hash cocok, sentinel unik tidak ditemukan, marker dipertahankan.
File terlarang `.extra`, `other.stdout`, dan `x.log` tidak diekspor.
Suffix stem `94b518` bukan nilai sentinel.

Reproduksi dari root repo:

```sh
python3 docs/execution/evidence/2026-10-13/export-closeout/run_export_evidence.py
python3 docs/execution/evidence/2026-10-13/export-closeout/audit_pdf_ui.py
```

Keterbatasan bukti: runner belum merekam argv capture/export lengkap dan hash seluruh sumber fixture. Bukti ini bukan run melalui CLI `launch.sh`.

## Audit pdf-ui

`pdf-ui-audit.json` memuat klasifikasi eksplisit masing-masing 20 berkas, ukuran, SHA-256 penuh, mode, link count, pemeriksaan pola, JSON keys, PNG chunks, dan inspeksi stream PDF.
Allowlist cocok 20/20; semua hash yang dicatat manifest historis cocok.
Tidak ditemukan pola credential yang diperiksa. Hasil ini bukan bukti universal tidak adanya rahasia.
Manifest `pdf-ui/sha256.json` belum mencakup README, extracted text, render, dan history; inventaris baru mencakup semuanya tanpa mengubah bundle.

PDF asli tetap:

```text
ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc
```

PDFKit berhasil membuka dua halaman A4; atribut Producer `dompdf 3.1.6 + CPDF`, CreationDate/ModDate `2026-10-12 20:24:13 +0000`, tanpa anotasi.
Pemeriksaan byte menemukan PDF 1.7, 17 objek, lima stream terdeteksi (empat berhasil didekompresi zlib), satu EOF.
Tidak ditemukan nama dictionary aktif/embedded/Metadata yang diperiksa. Pemindaian regex bukan parser PDF lengkap atau audit keamanan formal.
Font terdeteksi `SUBAAB+NotoSansSC-Regular`, CIDFontType2, FontFile2, ToUnicode.

Teks turunan: ID 1–48 berurutan, delapan CANCELLED, 12 nama CJK, 12 fallback nama kosong, total non-batal Rp 4.960.000.
Render dua halaman diperiksa visual: baris 1–26 dan 27–48, nama panjang membungkus, CJK terbaca, footer dua halaman.
Empat PNG diperiksa chunk metadata. Tidak ditemukan chunk teks/private metadata yang diperiksa; bukan OCR atau pemeriksaan steganografi.
History `202348360Z` mencatat BLOCKED karena strict-mode locator pada `/admin`, bukan kegagalan login.
History `201907688Z` mencatat TypeError; `202006740Z` mencatat Error. Tidak menyimpulkan penyebab lebih jauh.
Visual history `202348360Z` belum diperiksa; audit visual keseluruhan masih PARTIAL.

## Review dan batas closeout

Security-reviewer independen (sesi review, 2026-10-13): 2 MEDIUM, 1 LOW.
Code-reviewer independen (sesi hardening review, 2026-10-13): 2 MEDIUM warning.

- LOW tanpa batas ukuran — diperbaiki: `MAX_BYTES = 16 MiB` per file, ditolak sebelum dibaca; test `test_oversized_capture_file_is_refused` dan `test_capture_file_at_exact_size_cap_is_accepted` PASS.
- MEDIUM identitas self-asserted — batas desain, didokumentasikan: exporter hanya memilih berkas lokal yang memenuhi syarat, bukan bukti provenance bahwa triplet berasal dari `launch.capture`. Jika export diperlakukan sebagai bukti terautentikasi, butuh provenance tepercaya.
- MEDIUM cleanup error handling (code-reviewer) — diperbaiki: error cleanup dicatat per entri tanpa menghentikan unlink entri tersisa; exception asli dipertahankan dan `__notes__` menambahkan laporan cleanup tidak lengkap jika ada kegagalan; test `test_cleanup_error_does_not_stop_remaining_cleanup_or_mask_original` PASS.
- MEDIUM regresi rollback/size coverage (code-reviewer) — ditambahkan 4 test fault-injection deterministik (rollback write later file, preserve replaced inode, cleanup error continuation, exact size cap accepted); seluruhnya PASS.
- MEDIUM cleanup TOCTOU (security-reviewer + code-reviewer) — **mitigasi parsial, bukan perbaikan lengkap**: inode dibandingkan sebelum unlink, tetapi penggantian tetap mungkin di antara stat dan unlink. Destinasi harus eksklusif (tercatat sebagai `shortcut:` di kode). Temuan tetap OPEN untuk ancaman proses UID sama yang tidak kooperatif.

`python3 -m unittest tests.python.test_amadis_export`: 20 tes PASS (15 awal + 1 cap + 4 hardening coverage).
Tidak ada perubahan dependensi, `.env`, database operasional, PDF, atau commit dalam scope ini.
