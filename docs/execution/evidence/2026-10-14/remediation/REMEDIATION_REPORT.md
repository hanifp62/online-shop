# Laporan Remediasi ZAFIRA — 14 Oktober 2026

## Status eksekutif

Paket ini adalah **bukti remediasi dan verifikasi terbatas**, bukan pernyataan siap produksi. Bukti terbaru mencakup jalur native terisolasi berbasis SQLite, browser Chromium lokal, generator PDF sintetis, regresi konkurensi SQLite disposable, suite PHP/Node, dan build Vite. MariaDB, screen reader, studi pengguna, payment settlement, delivery email, serta keputusan tarif pengiriman belum diverifikasi.

**Fingerprint native root final:** `61ae491c87be9c6ccb5120753d898061379bdd18ae97c8e7332e3523401647d7`.

**Batas penting:** native probe Artisan nonisolated sebelumnya tetap **FAIL**. Bukti native aman yang datang kemudian tidak menghapus penyimpangan tersebut.

## Basis bukti

| Bukti | Status dan batas |
|---|---|
| `remediation/final/RESULT.md` | Browser dan native final; home diagnostic mencatat toast/badge setelah wait. Diagnostic bukan assertion otomatis. Katalog inline feedback PASS, toast/badge katalog FAIL. |
| `remediation/native/SINK-8018.md` | Mencatat probe Artisan dari repo, tanpa preflight, sehingga klaim isolasi penuh tetap FAIL. |
| `remediation/pdf/REPORT.md` | PDF sintetis tiga halaman, glyph visual diperiksa manual, Dompdf 3.1.6 tidak berubah. |
| `remediation/concurrency/green-v2.json` | Enam skenario konkurensi lulus pada SQLite WAL disposable; `mysql: NOT_RUN`. |
| `architecture-assessment/GAP_MATRIX.csv` | Matriks gap aktual GAP-001 sampai GAP-023. Tidak ada pemetaan workbook Z-01..Z-50. |
| `architecture-assessment/TEST_RESULTS.md` | Run asesmen sebelumnya: status dan batas isolasi. |
| `docs/02-product/REQUIREMENTS_TRACEABILITY.md` | ID requirement resmi FR/NFR/US. |
| `docs/demo/ZAFIRA_ACCEPTANCE_MATRIX.csv` | Acceptance matrix dengan ID tambahan seperti NFR-003-ISOLATED dan NFR-003-OPERATIONAL. |

## Acceptance criteria berbasis ID resmi

Status memakai **PASS, FAIL, PARTIAL, NOT_RUN, atau N/A**. Tidak ada ID Z-01..Z-50 karena workbook tersebut tidak tersedia dalam bukti lokal.

| ID | Kriteria ringkas | Status | Bukti aktual / alasan |
|---|---|---|---|
| FR-001 | Beranda dan hero editorial | PARTIAL | Native/browser final berjalan; browser initial home/admin fail disimpan. Home follow-up mengamati toast dan badge, tetapi ini bukan assertion otomatis. |
| FR-002 | Filter kategori dinamis | PARTIAL | Filter/disclosure katalog 390px dan 320px lulus dalam `browser-results.json`; seluruh acceptance induk tidak tertutup. |
| FR-003 | PDP dan kriya | PARTIAL | PDP synthetic HTTP 200 dalam native acceptance; seluruh content/data-empty acceptance belum dibuktikan. |
| FR-004 | Tas belanja dan personalisasi | PARTIAL | Drawer plus tiga klik, hapus/undo, dan checkout summary lulus. Browser initial failure tetap dicatat. |
| FR-005 | Checkout concierge | PARTIAL | Native checkout/order/cancel lulus dengan fixture sintetis; payment/email delivery tidak diuji. |
| FR-006 | Newsletter | NOT_RUN | Tidak ada bukti delivery nyata atau acceptance final baru. |
| FR-007 | Auth pelanggan/password policy | PARTIAL | Login customer browser dan native lulus pada fixture sintetis; seluruh policy acceptance tidak tertutup. |
| FR-008 | Profil dan riwayat akun | PARTIAL | `/akun` 200 setelah login; acceptance ownership/email conflict belum selesai. |
| FR-009 | Admin guard | PARTIAL | Admin login dan PDF download lulus pada fixture sintetis; browser initial admin failure tetap dipertahankan sebagai bukti. |
| FR-010 | Dashboard/KPI | NOT_RUN | Tidak ada verifikasi akurasi seluruh KPI. |
| FR-011 | Manajemen katalog | NOT_RUN | Tidak ada run browser admin catalog lengkap terbaru. |
| FR-012 | Kategori dan cache flush | NOT_RUN | Kontrak cache masih terbuka; tidak ada acceptance lengkap terbaru. |
| FR-013 | Status order/concierge | PARTIAL | Cancel dan replay native lulus; seluruh edge transition belum diuji. |
| FR-014 | Password admin | NOT_RUN | Bcrypt round 12 belum dibuktikan. |
| FR-201 | Varian ukuran/monogram melekat | NOT_RUN | Source/test historis ada; run terbaru tidak menutup acceptance ini. |
| FR-202 | Data PDP material/ukuran/perawatan | PARTIAL | PDP synthetic tersedia; fallback data kosong belum diuji penuh. |
| FR-203 | Concierge menerima dan memberi tindak lanjut | PARTIAL | Create/queue didukung bukti historis; email delivery nyata NOT_RUN. |
| FR-204 | Riwayat hanya milik user | PARTIAL | Perilaku ownership diuji dalam bukti historis; kontrak email-match masih konflik. |
| NFR-001 | Performa/caching storefront | PARTIAL | Build berhasil dengan asset `app-BcSIKTJe.js`; TTL stock freshness 300 detik tetap unresolved. Latency target tidak diukur. |
| NFR-002 | HTTP headers/CSRF | PARTIAL | Bukti static/historis ada; seluruh route dan CSP enforced belum diverifikasi. |
| NFR-003 | Integritas relasional | PARTIAL | SQLite disposable dan order flow lulus terbatas; target MariaDB dan operational DB tidak diuji. |
| NFR-004 | Konsistensi visual | PARTIAL | Screenshot viewport tersedia; audit visual penuh dan desktop dock residual belum tertutup. |
| NFR-005 | Kompatibilitas macOS | PARTIAL | Toolchain berjalan pada host ini; matriks instalasi lain tidak diuji. |
| NFR-201 | Nav prototype tersembunyi di produksi | NOT_RUN | Hanya bukti historis/source; tidak ada run acceptance baru khusus. |
| NFR-202 | Tidak ada klaim operasional palsu | PARTIAL | Subset klaim teruji; pencarian menyeluruh belum dilakukan. |
| NFR-203 | Keyboard, nama aksesibel, fokus, Escape, WCAG | PARTIAL | Keyboard/viewport/reduced-motion terbatas lulus. Screenreader, kontras penuh, target 44px, zoom 200%, dan WCAG penuh NOT_RUN. |
| NFR-301 | Isolasi fixture/regresi | PASS | Final native root fingerprint dan isolated runner tersedia; status hanya pada fixture disposable SQLite. |
| GAP-001 | JSON-LD mengikuti stock/status aktif | PARTIAL | ADR-008 menetapkan aturan; freshness cache dan seluruh invalidation belum terbukti. |
| GAP-002 | Tarif kirim/penagihan eksplisit | NOT_RUN | Shipping policy belum diputuskan. Jangan invent tarif. |
| GAP-003 | Paid hanya setelah settlement | PASS | Order final tetap `pending / belum dibayar`; settlement payment tidak tersedia. |
| GAP-005 | Race stok/voucher pada engine target | PARTIAL | SQLite six-scenario concurrency PASS; MariaDB `NOT_RUN`. |
| GAP-006 | Double cancel tidak double-restock | PARTIAL | Sequential/double-cancel SQLite evidence ada; target engine dan semua deployment path belum diuji. |
| GAP-009 | Semua sink terisolasi sebelum bootstrap/HTTP | FAIL | Probe native nonisolated dari repo tetap tercatat di `SINK-8018.md`; safe later proof tidak menghapus FAIL. |
| GAP-011 | Migrasi engine target tanpa error | NOT_RUN | MariaDB rehearsal tidak dijalankan. |
| GAP-015 | Suite otomatis pada profil tepat | PASS | Latest independent full run: PHP **175 passed, 1 skipped, 1350 assertions**; Node **83/83**; cart4 regression PASS. Profil target MariaDB tetap belum diuji. |
| GAP-016 | Benchmark 10 retailer comparable | PARTIAL | 4 OBSERVED, 6 UNKNOWN; comparative scoring NOT_RUN. |
| GAP-019 | UI WCAG/HCI | PARTIAL | Narrow reflow, keyboard, reduced motion terbatas; screenreader dan user study NOT_RUN. |
| GAP-020 | Idempotency replay | PASS | Same-key, changed-address, distinct-key SQLite scenarios PASS; MariaDB NOT_RUN. |
| GAP-021 | Admin PDF protected/bounded/readable | PARTIAL | Admin PDF download synthetic PASS; PDF glyph evidence 3 halaman; full auth/bounds/visual contract belum lengkap. |
| GAP-022 | Direct markdown mail render | NOT_RUN | Tidak diuji. |
| GAP-023 | Dompdf/Commonmark freeze | PASS | Dompdf **3.1.6** unchanged; Commonmark **2.10.3** retained per evidence. |
| NFR-003-ISOLATED | Order/order-item FKs isolated SQLite | PARTIAL | Isolated SQLite evidence ada; acceptance matrix historis masih memuat FAIL/NOT_RUN lama. |
| NFR-003-OPERATIONAL | Schema operational DB | NOT_RUN | Operational DB access prohibited; effect of DB absence UNKNOWN. |

## Browser evidence correction

Browser initial home/admin failures **dipertahankan**, bukan dihapus. Follow-up mengamati home toast dan cart count setelah menunggu render dan membaca server state. Itu adalah observasi diagnostik, bukan automated assertion pass. Catalog add-to-cart memiliki inline feedback PASS, tetapi `.toast` dan `.cart-count` tidak ada pada halaman katalog, sehingga kriteria toast/badge katalog FAIL.

Kegagalan awal dalam `browser-results.json`: `home-add-toast-count` FAIL (assertion `/ditambahkan/`), `catalog-toast-count` FAIL (`Catalog has no shared toast`), dan `admin-login-pdf-download` FAIL (download timeout 30000 ms). Follow-up admin kemudian PASS; diagnostic home tidak mengubah assertion awal menjadi PASS.

## Test/build evidence

Angka PHP, Node, hasil build, dan cart4 berikut berasal dari run independen yang disampaikan koordinator. Log mentah run tersebut tidak tersedia dalam paket; hasil ini tidak dieksekusi ulang saat pengemasan. Status PASS terkait dibatasi oleh provenance ini dan bukan verifikasi ulang pengemas.

- PHP full: **175 passed, 1 skipped, 1350 assertions**.
- Node: **83/83**.
- Vite build: sukses; asset output `app-BcSIKTJe.js`.
- Cart4 regression: PASS.
- PDF evidence: 3 halaman; glyph `Ĳ`, `Ł`, CJK, diakritik, wrapping, dan footer diperiksa manual.
- SQLite concurrency: six scenarios PASS dalam `green-v2.json`; `mysql` bernilai `NOT_RUN`.

## ADR dan arsitektur

ADR-006 sampai ADR-009 tersedia di `docs/03-architecture/ARCHITECTURE_DECISIONS.md`:

- ADR-006: kontrak cart terpadu dan queue mutation; perbaikan khusus masih diperlukan karena halaman katalog tidak memiliki toast/badge bersama.
- ADR-007: idempotency checkout dan Model A stok atomik.
- ADR-008: JSON-LD availability dan TTL 300 detik.
- ADR-009: profil manual native terisolasi.

ADR-009 diterima hanya untuk uji lokal. Ia tidak menghapus insiden nonisolated probe.

## Runbook aman

1. Jangan menjalankan Artisan dari repo untuk acceptance atau browser proof.
2. Buat root disposable baru untuk setiap run.
3. Jalankan preflight sink sebelum bootstrap, migrate, fixture, atau HTTP.
4. Gunakan SQLite disposable hanya untuk bukti lokal yang memang ditandai SQLite.
5. Untuk target release, rehearse migrasi dan concurrency pada disposable MariaDB yang cocok.
6. Jangan menggunakan `.env`, credentials, database, session, vendor, atau node_modules sebagai packet evidence.
7. Jangan menyebut diagnostic browser sebagai automated assertion.
8. Jangan menyebut status production-ready sebelum MariaDB, WCAG penuh, shipping/payment decisions, dan sink gate lulus.

## Dokumen hilang dan keputusan terbuka

Dokumen eksternal original 3 PDF/XLSX tidak tersedia dalam bounded evidence. Z IDs workbook tidak tersedia; laporan ini memakai ID aktual FR/NFR/US/GAP/NFR-003-* saja. Target MariaDB, screenreader, studi pengguna, payment settlement, email delivery, dan dampak operational DB absence tetap NOT_RUN atau UNKNOWN. Tarif shipping dan keputusan payment/shipping masih terbuka. Stock freshness 300 detik unresolved.

## Kesimpulan

Status keseluruhan: **PARTIAL / NOT PRODUCTION-READY**. Bukti menunjukkan regresi otomatis dan beberapa jalur isolated browser/native bekerja, tetapi target database, accessibility penuh, operational safety gate, keputusan produk, dan beberapa acceptance utama belum terbukti.
