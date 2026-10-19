# Ledger eksekusi terintegrasi

Status: **tidak siap produksi**. Pembaruan 14 Oktober 2026 di bawah adalah status terkini. Ringkasan bertanda 13 Oktober dan tabel area berikutnya adalah snapshot historis; angka dan `NOT RUN` di sana tidak menggantikan status terkini. Catatan snapshot 12 Oktober: hanya CSS dock berubah sejak full suite run3; tidak ada perubahan database atau deploy.

## Snapshot historis — 13 Oktober 2026 (sebelum pembaruan 14 Oktober)

- Docker Desktop aktif (client/server `29.8.2`, Docker Desktop `4.94.0`, arm64).
- Dompdf `3.1.6` terpasang bersama tepat 4 sub-dependensi (`PDF_PINS`: `dompdf/php-font-lib:1.0.2`, `dompdf/php-svg-lib:1.0.2`, `masterminds/html5:2.11.0`, `sabberworm/php-css-parser:v9.5.0`) melalui guarded isolated stage. Verifikasi platform Mac dan class loading lulus (`rc 0`).
- Bukti unduhan UI admin PDF: `docs/execution/evidence/2026-10-13/pdf-ui/` memuat file asli `orders-20261013.pdf` (SHA-256: `ad3b849ce4add80d44096ace6d40a22fd3adba0b353636dea205268dd18925bc`), hasil ekstraksi PDFKit 2 halaman, inspeksi visual CJK (`王小明`), fallback strip `-`, dan wrap nama panjang.
- Composer audit triage: `composer audit --locked` menghasilkan `rc 1` dengan dua advisory pada paket `league/commonmark:2.10.0` (PKSA-m2dq-1fhr-29b1, PKSA-m4t9-vsgq-8khn). Terbukti pre-existing pada commit HEAD sebelum penambahan PDF; berada di luar cakupan 5 paket PDF dan tidak diubah.
- Database operasional / NFR-003: tidak diubah. Status MariaDB tetap `ERROR 1558` historis; tidak ada klaim kelulusan palsu.
- Full gate final 13 Oktober selesai: PHP 158/158 standar (1.253 assertions) & acak (1.252 assertions), Node 86, Python 133 (0 skip, FontTools 4.66.1), build, diff, S0–S5 (stok 37→34→37→37→37), serta Pint delapan file requested semuanya **PASS** (rc 0); sentinel export dan bundle allowlist audit kini terbukti dalam batas `export-closeout/LAPORAN_EXPORT_DAN_AUDIT_PDF.md` (PARTIAL, capture/export rc 0). Run awal `final-gate/LAPORAN_FINAL_GATE.md` (Pint 8 fail, Python 102/6 skip) dipertahankan sebagai riwayat. Detail gate mutakhir: `evidence/2026-10-13/final-gate-closeout/REPORT.md`.
- Stage runtime run terbaru `66dd10879584`: 11 predikat PASS, `returncode: 0`, Docker Desktop VM Linux, mtime 13 Oktober 2026 12:15:50 (lokal); hash report `f26d7b4cdd31520b5f75a818985c53aa8dc8e8cea315627f418d9272f8c3aa6a`. Hash manifest historis folder dipertahankan.
- Acceptance AM terisolasi (`acceptance-am-isolated/REPORT.md`): AM-01, AM-02, AM-06, AM-10 lulus pada SQLite terisolasi; AM-10 stok 37 terkonservasi (0+37=37; bukan bukti locking MariaDB). Baris lain berstatus lulus feature-test; F1–F9 NOT RETESTED. Matriks AM-01–AM-17 tetap usulan kerja, bukan rubrik resmi.

## Pembaruan — 14 Oktober 2026

- Akses manual Artisan: akar HTTP 500 adalah `.env` operasional (MySQL `ecommerce`) yang belum menerapkan tujuh migration 2026-10-12. Profil lokal `--env=manual` dengan SQLite terisolasi lulus alur manual. Bukti: `evidence/2026-10-14/manual-artisan/VERIFIKASI.md`.
- CommonMark diupgrade `2.10.0 → 2.10.3` atas persetujuan pengguna; audit guarded `advisories: []`; suite guarded PHP 158 passed. Bukti: `evidence/2026-10-14/commonmark-upgrade/README.md`. Render Markdown mail langsung belum diuji.
- Retest F1–F9 (`evidence/2026-10-14/f-findings-retest/REPORT.md`): F1–F6 STILL PRESENT, F7–F8 NOT REPRODUCIBLE ON THIS DATA, F9 INVALID ORIGINAL CHECK.
- Root temp dipertahankan untuk audit atas keputusan pengguna.

### Snapshot historis area — 11–12 Oktober 2026

Tabel ini tidak memuat bukti 13 Oktober; gunakan ringkasan di atas untuk status terkini.

| Area / acceptance | Status | Bukti dan batas |
|---|---|---|
| Verifikasi setelah dock CSS + sweep8 | **PARTIAL** | [`final-regression-4/commands-exitcodes.json`](evidence/2026-10-11/phase4/final-regression-4/commands-exitcodes.json): build, Pint seluruh repo, dan `git diff --check` lulus setelah perubahan CSS dock. Node 57/57 lulus di [`completion-frontend/node-test-run8.log`](evidence/2026-10-11/phase4/completion-frontend/node-test-run8.log). PHP **tidak dijalankan ulang** karena perubahan hanya CSS. Full suite terbaru tetap [`final-regression-3`](evidence/2026-10-11/phase4/final-regression-3/commands-exitcodes.json), mendahului dock CSS: PHPUnit standar/acak 101/576, Node 56/56, build/diff lulus; Pint awal gagal hanya pada router QA, diformat hanya berkas bukti itu, lalu Pint seluruh repo **lulus** pada `pint3-rerun.log`. Tidak seluruh full suite pasca-dock. Database tidak disentuh. |
| UI bahasa/responsif | **PASS terbatas** | [`sweep-8-summary.json`](evidence/2026-10-11/phase4/completion-frontend/sweep-8-summary.json), `sweep-8-exitcode=0`: 56/56 kombinasi 4 bahasa × 7 viewport × 2 surface, 0 kegagalan, overflow maksimum 1px, CLS maksimum 0.098977, LCP maksimum 716ms pada localhost 8015. Persistensi bahasa hanya sampel 390px; bukan audit seluruh copy/visual. `sweep-8-run.log` mencatat 32 capture; folder `shots-sweep-8/` memuat 16 berkas unik. Delapan screenshot sweep7 dibaca pada `sweep-7-inspection.md`; inspeksi satu per satu sweep8 **NOT RUN**. |
| Drawer prototype dan overlay storefront | **PASS terbatas** | [`drawer-interactions-run7-results.json`](evidence/2026-10-11/phase4/completion-frontend/drawer-interactions-run7-results.json): 7 jalur fokus/shortcut/re-render/close lulus, tanpa failure. [`overlay-run7/results.json`](evidence/2026-10-11/phase4/overlay-run7/results.json): 174/174 assertion tamu pada tujuh overlay × desktop/mobile; 14 screenshot. Review independen untuk pembuka/focus-trap dilaporkan **lulus** pada run7, jadi tidak lagi menunggu; bukti runtime terbatas pada jalur yang diuji dan artefak laporan reviewer run7 tidak ditemukan di direktori bukti. Bukti ini tidak mengesahkan semua skenario aksesibilitas. |
| Keamanan sesi dan throttle | **PASS terbatas** | [`final-regression-2/session2.log`](evidence/2026-10-11/phase4/final-regression-2/session2.log): replay ID sesi file lama ditolak untuk guard admin dan web setelah logout admin-only/dual, CSRF dan ID berotasi, web guard dual tetap login; guard login disuntik via `guard->login(model)`, bukan endpoint password/browser penuh. [`throttle2.log`](evidence/2026-10-11/phase4/final-regression-2/throttle2.log): `/concierge` cache file pada 8015 merespons 422,422,422,429,429 dengan CSRF valid dan payload invalid, tanpa tulis DB. Lima login pada 8016 tetap 302 sesuai limit 10/menit; **bukan bukti login ter-throttle**. Konfigurasi produksi/proxy belum diuji. |
| Aksesibilitas | **PARTIAL / FAIL target internal** | Focus drawer/overlay di atas teruji. Satu pasangan kontras solid `.cat-pill.active` 19.05:1 pada `drawer-interactions-run7-results.json`; pasangan lain dan WCAG 2.2 AA penuh **NOT RUN**. Data sweep7/sweep8 tetap mencatat target <44px, termasuk ikon storefront 37×37px; daftar `smallTargets` dipotong 30 per kasus, bukan audit lengkap. Dock: baseline [`dock-run8-before.json`](evidence/2026-10-11/phase4/completion-frontend/dock-run8-before.json) 390px overlap 3.40625px (scroll 0) dan 52.1875px (scroll 50); setelah CSS [`dock-run8-final.json`](evidence/2026-10-11/phase4/completion-frontend/dock-run8-final.json) 0px untuk 320/390/768 pada empat posisi scroll. **Desktop prototype 1366×768 scroll 0 tetap overlap 21px: FAIL residual.** `dock-run8-verified` exit 0 hanya karena skrip mengecualikan kasus ini; inspeksi visual 1366 sweep8 NOT RUN. |
| Acceptance produk & kontrak pemilik | **PARTIAL / BLOCKED keputusan** | Fitur concierge create+queue, varian, riwayat `user_id`, nav produksi, dan transisi tertentu teruji terbatas; rincian per-ID pada [traceability](../02-product/REQUIREMENTS_TRACEABILITY.md). Pemilik harus memutuskan `<100ms` objective vs `<150ms` NFR-001, FR-008 email-match vs FR-204 `user_id`, FR-011 `Cache::flush()` vs invalidasi `forget`, dan FR-014 bcrypt round 12 belum terverifikasi. FK Order **FAIL**; `MustVerifyEmail` **NA untuk implementasi saat ini** (tidak ada kontrak verifikasi yang diputuskan), bukan PASS verifikasi email. |
| Riset | **PASS sumber / BLOCKED storefront** | [`source-excerpts.md`](evidence/2026-10-11/phase4/completion-research/source-excerpts.md) membaca isi S1–S8, bukan HTTP-only; masing-masing punya batas inferensi. Render kompetitor parsial; CAPTCHA/403/Cloudflare/timeout/cert tetap BLOCKED. Tidak ada ranking UX, baseline konversi, atau uji pengguna ZAFIRA. |
| Data & keamanan produksi | **FAIL / BLOCKED** | Dump 21 September tidak identik dengan `ecommerce` (12 produk berbeda); keputusan otoritas data belum ada. Routine `getcat` restore ERROR 1558, `db:show` XAMPP BLOCKED. [`csp-inventory2.md`](evidence/2026-10-11/phase4/final-regression-2/csp-inventory2.md): CSP dan report-only tidak terpasang pada route yang diprobe; inventaris parsial, belum rollout. [`csp3-summary.md`](evidence/2026-10-11/phase4/final-regression-3/csp3-summary.md) menambahkan simulasi Report-Only melalui Playwright, hanya harness, bukan header aplikasi/deployment; violation / 95, /prototype/ 445, PDP 16, admin login 1. `csp3.json` akhir mencatat 0 kegagalan fungsional; 4 kegagalan awal tetap riwayat. Port 8000 historis hanya dibaca lognya; dampak terhadap sesi/cache **tidak dapat ditentukan**. |
| Kesiapan | **Lokal/demo bersyarat; produksi TIDAK SIAP** | QA localhost terisolasi mendukung demo alur yang diuji, bukan klaim WCAG, performa jaringan nyata, email terkirim, atau deployment. Tidak ada commit/push/deploy dalam rekonsiliasi dokumen ini. |

### Tabel final per kriteria/permintaan — 12 Oktober 2026

Tidak ada klaim bahwa semua kriteria lulus. Tidak ada commit, push, atau deploy; database tidak disentuh.

| Kriteria / permintaan | Yang berubah | Bukti | Status |
|---|---|---|---|
| Dock tidak menutup paragraf hero (mobile) | `.hero-text-pane { padding-top: 112px; }` pada blok `max-width: 768px`, `prototype/app.css` | `dock-run8-before.json` vs `dock-run8-final.json` (0px di 320/390/768) | PASS terbatas |
| Dock tidak menutup paragraf hero (desktop 1366×768, scroll 0) | Tidak ada | `dock-run8-final.json`: overlap 21px; `dock-run8-verified.json` tetap 21px, dikecualikan skrip | **FAIL residual** (inspeksi visual 1366 NOT RUN) |
| Sweep bahasa/viewport | Tidak ada perubahan pada sweep | `sweep-8-summary.json`: 56/56, LCP maks 716ms, CLS maks 0.098977, localhost | PASS terbatas |
| Fokus drawer/overlay | Sebelumnya (drawer) | `drawer-interactions-run7-results.json`, `overlay-run7/results.json` 174/174, review independen run7 lulus | PASS terbatas; laporan reviewer run7 tidak ditemukan |
| Regresi PHP/Node penuh | — | `final-regression-3` (sebelum dock CSS): PHP 101/576, Node 56/56; Node run8 57/57 | Terbaru penuh, tetapi mendahului dock CSS; PHP tidak diulang |
| Build, Pint seluruh repo, diff check pasca-dock | — | `final-regression-4/*`; Pint run3 awal fail (history), `pint3-rerun.log` pass | PASS |
| CSP | Tidak ada header | `final-regression-3/csp3-summary.md`: Report-Only via Playwright, hanya harness; ratusan violation inline | OPEN, tidak dideploy |
| Sesi/throttle | — | `final-regression-2/session2.log`, `throttle2.log` tetap berlaku; login throttle tidak diuji | PASS terbatas |
| Kontras / target sentuh / WCAG | — | satu pasangan 19.05:1; target <44px tercatat | PARTIAL / FAIL internal |
| Riset | — | `source-excerpts.md` dibaca; kompetitor storefront terblokir | PASS sumber / BLOCKED |
| Order FK, konflik owner, DB, email | — | traceability | FAIL / BLOCKED / NOT RUN |
| Deployment | — | tidak ada | NOT RUN |

## STATUS HISTORIS — Phase 4 (penutupan rescue terbatas)

Status: **riwayat 11 Oktober.** Angka pada bagian ini diukur sebelum edit prototype terakhir (drawer, href, target 44px, footer) dan sebelum `final-regression-3`; gunakan tabel status tunggal di atas. Tidak ada riwayat yang dihapus.

### Adendum final setelah sweep frontend dan rotasi sesi (11 Oktober 2026 — HISTORIS)

Status di bawah menggantikan klaim lama pada tabel batch di atas untuk verifikasi yang dijalankan ulang sesudah perubahan terakhir. Bukti mentah tersimpan di [`phase4/final-regression/`](evidence/2026-10-11/phase4/final-regression/); sweep UI dan report di [`completion-frontend/`](evidence/2026-10-11/phase4/completion-frontend/).

| Pemeriksaan | Hasil aktual | Exit | Bukti |
|---|---|---:|---|
| PHPUnit standar | 101 tests / 576 assertions | 0 | `final-regression/phpunit-standard.log` |
| PHPUnit acak, seed 1791725092 | 101 tests / 576 assertions | 0 | `final-regression/phpunit-random.log` |
| Node `tests/*.test.mjs` | 51 lulus, 0 gagal | 0 | `final-regression/node.log` |
| Pint kode produk | passed | 0 | `final-regression/pint.log` |
| Build Vite | CSS 124.30 kB (gzip 23.79), JS 19.50 kB (gzip 6.48) | 0 | `final-regression/build.log` |
| Syntax PHP/JS terpilih | 12 berkas tanpa syntax error | 0 | `final-regression/syntax.log` |
| `git diff --check` | bersih | 0 | `final-regression/diffcheck.log` |
| Sweep bahasa ID/EN/FR/IT | 56 kasus; 0 gagal; persistensi reload teruji | 0 | `completion-frontend/sweep-4-summary.json`, `sweep-4.log`, screenshot dan `RESULT.md` |
| Browser metrics sweep | LCP maksimum 484 ms; CLS maksimum 0.09898; overflow storefront 0 px, prototype ≤1 px subpixel | 0 | `completion-frontend/sweep-4-summary.json`, `RESULT.md` |
| Mobile language controls | tombol ID/EN/FR/IT 44×44 px pada drawer; regresi dalam Node suite | 0 | `prototype/index.html`, `prototype/app.css`, `tests/prototype-mobile-lang.test.mjs` |
| Logout dua guard | kedua arah mengubah session ID dan CSRF token, mempertahankan guard tersisa; 21 tests / 136 assertions | 0 | `final-regression/phpunit-standard.log`; perubahan `AuthController`, `AdminAuthController`, `RescueBackendAuditTest` |

Catatan: satu piksel overflow prototype adalah marquee subpixel dan dilaporkan compliant oleh sweep, bukan nol universal. Kontrol ikon pencarian/keranjang dan filter pills storefront yang lebih kecil dari target internal 44 px masih tercatat di `completion-frontend/RESULT.md`; aset mentah sekitar 6 MB. Sweep bukan audit WCAG penuh atau validasi produksi. Temuan independen sebelumnya tentang CSP report-only belum ditutup; `completion-review/SECURITY_REVIEW.md` mencatat cakupan dan rekomendasi. Review independen run7 untuk opener/focus dilaporkan lulus secara terbatas; artefak laporan reviewer tidak ditemukan.

### Batch penutupan (dijalankan ulang pada sumber final, server terisolasi 8015)

Isolasi QA dibuktikan: router sementara di luar repo memaksa `useStoragePath()` ke `…/zafira-qa/storage`, sehingga sesi berkas, view, log, cache array, dan SQLite semuanya di direktori temp. Bukti: `phase4/rescue-final-isolation/`. Port 8000/8001 tidak diakses.

| Pemeriksaan | Hasil aktual | Exit | Bukti |
|---|---|---:|---|
| `npm run build` | CSS 124.28 kB (gzip 23.79), JS 19.50 kB (gzip 6.48) | 0 | output sesi; `public/build/manifest.json` |
| Audit navigasi `audit-nav.mjs` (7 viewport × 7 halaman) | 704/704 assertion | 0 | `phase4/rescue-final/nav.log`, `rescue-final-nav/` |
| Audit overlay `audit-overlays.mjs` (1440 + 390, 7 overlay) | 174/174 assertion, 0 error, 0 request gagal | 0 | `phase4/rescue-final/overlays.log`, `rescue-final-overlays/` |
| Segel lilin klik/Enter/Space | 3/3 aksi, `BUTTON type=button`, 0 submit, URL tetap | 0 | `phase4/rescue-final/wax.log` |
| `php artisan test` (urutan standar) | 99 tests / 556 assertions | 0 | `phase4/rescue-final/php-standard.log` |
| `php artisan test --order-by=random` (seed 1791724776) | 99 tests / 556 assertions | 0 | `phase4/rescue-final/php-random.log` |
| `node --test tests/*.test.mjs` | 47 pass | 0 | output sesi |
| `vendor/bin/pint --test app routes config tests database` | passed | 0 | output sesi |
| `vendor/bin/pint --test` (seluruh repo) | **fail** — hanya `rescue-final-isolation/router-isolated.php` (berkas bukti, bukan kode produk) | 1 | `phase4/rescue-final/pint.log` |
| `git diff --check` | bersih | 0 | output sesi |

Defect yang ditutup pada batch ini:
- **DEF-ADM-01** (temuan review independen, MEDIUM): dropdown status concierge admin menawarkan semua status, termasuk transisi yang ditolak backend (mis. `new → completed`). Peta transisi dipindah ke `ConciergeRequest::TRANSITIONS` dan dipakai bersama oleh validasi controller serta view; status terminal (`completed`, `cancelled`) kini hanya menampilkan badge tanpa form. Tes `test_dashboard_only_offers_allowed_concierge_transitions` gagal dulu (opsi `completed` muncul untuk `new`), lalu lulus. Regresi sesudahnya: PHPUnit standar dan acak (seed 1791725092) **100 tests / 568 assertions** exit 0 (`rescue-final/php-standard-2.log`, `php-random-2.log`); Pint produk passed; diff check bersih; audit navigasi 704/704 exit 0 (`rescue-final/nav-2.log`).
- **DEF-OVL-01** modal pintasan: `.modal-window` anak kini menerima/melepas kelas `open` mengikuti siklus backdrop (`resources/js/app.js`), bukan dipaksa tampil lewat CSS. Reproduksi merah tersimpan sebelum perbaikan; audit overlay sesudahnya 174/174.
- **DEF-WAX-01** segel lilin: `div[role=button]` diganti `<button type="button">` dengan nama aksesibel, gaya dipertahankan (`resources/views/welcome.blade.php`). Enter dan Space kini menjalankan aksi yang sama dengan klik tanpa submit.
- **DEF-I18N-01** kunci `editorial_eyebrow/title/desc/cta` hilang di keempat kamus; regresi `tests/zafira-i18n.test.mjs` gagal lebih dulu, lalu kunci ditambahkan di `public/js/zafira-core.js`. Celah sisa `studio_monogram_desc` (ID/FR/IT) hanya dipakai `prototype/app.js` dengan fallback inline; tidak diperbaiki.

Audit backend tambahan (`tests/Feature/RescueBackendAuditTest.php`, 20 tests / 128 assertions, exit 0, bukti `phase4/rescue-backend/final-phpunit.log`) mencakup mass assignment dan IDOR update akun, logout POST + penolakan tanpa CSRF, throttle login/register/concierge, validasi concierge dan escaping payload tersimpan, proteksi kategori berisi produk, sitemap, security header, dan redirect guard. Tidak ada perubahan controller atau route.

Koreksi angka aset: homepage memuat **empat** berkas, bukan dua. Build CSS gzip 23.79 + build JS gzip 6.48 + `prototype/app.css` gzip 15.80 + `public/js/zafira-core.js` gzip 21.50 = **±67.6 kB gzip**, masih di bawah target 120 kB NFR-001. Angka 28.25 kB pada catatan sebelumnya hanya mencakup bundel Vite dan **tidak** mewakili berat halaman. Bukti: `phase4/rescue-perf/asset-sizes.txt` (diukur sebelum build terakhir; selisih hanya pada dua berkas build).

### Perbaikan pada sesi ini
- Header 901–1100px: `.site-header { padding-inline: 16px }` di `@media (max-width:1100px)` (`resources/css/app.css`). Sebelumnya overflow nyata 13px (EN) dan 5px (FR) pada 901px. Upaya grid `minmax(0,1fr)` dibatalkan karena brand/nav bertabrakan.
- Escape menu mobile kini mereset `aria-expanded="false"` pada pemicu (`resources/js/app.js`, handler keydown). Jalur tutup lain sudah melakukannya.
- Monogram PDP: inline `oninput` dan `<script>` dihapus dari `products/show.blade.php`; listener setara di `resources/js/app.js` (sanitasi A–Z, maks 3, uppercase, fallback `Z`, counter `N / 3`).
- Concierge: pembatasan email-match dibatalkan setelah temuan HIGH code-reviewer. Permintaan pengguna login tetap terhubung ke `user_id`. Test baru `StorefrontTest::test_authenticated_concierge_with_alternate_contact_email_stays_in_account_history`.
- `shortcuts_trigger` ditambahkan ke kamus ID/EN/FR/IT `public/js/zafira-core.js`.

### Harness `phase4/audit-nav.mjs`
- Login menunggu POST (302) dan navigasi sebelum klik; tujuan harus tepat `/` (customer) atau `/admin`.
- Kontrol negatif memanggil guard yang sama (`loginOrThrow`); hanya `AUTH_REJECTED` diterima.
- Escape menuntut: tanpa kelas open, `aria-hidden="true"`, `inert`, `aria-expanded="false"`, fokus kembali ke pemicu.
- Review independen code-reviewer (`ses_ed52e7d74ffemMOBJwv6JLfNRH`): tidak ada temuan tersisa pada perbaikan ini (statik, tanpa runtime).

| Pemeriksaan | Hasil | Exit | Bukti |
|---|---|---:|---|
| `npm run build` | CSS 110.33 kB (gzip 21.79), JS 19.41 kB (gzip 6.46) | 0 | log build sesi (temp) |
| Audit browser `mandate-current-6` (8015, 7 viewport × 7 halaman) | 704/704 assertion | 0 | `phase4/mandate-current-6/report.json` + screenshot |
| Self-test: server tidak tersedia (8099) | 70/70 gagal | 1 (diharapkan 1) | `phase4/mandate-selftest/exit.json`, `no-server.log` |
| Self-test: password salah (8015) | 14/592 gagal | 1 (diharapkan 1) | `phase4/mandate-selftest/exit-badpw.json`, `bad-password.log` |
| PHPUnit urutan acak (seed 1791721010) | 79 tests / 428 assertions | 0 | `phase4/mandate-regression/phpunit-random.log` |
| Pint `--test` | pass | 0 | `mandate-regression/pint-test.log` |
| `node --test tests/*.test.mjs` | 46 pass | 0 | `mandate-regression/node-test.log` |
| `git diff --check` | bersih | 0 | `mandate-regression/git-diff-check.log` |

Screenshot diperiksa visual: home 1024×768 (header satu baris, announcement tanpa tabrakan), menu mobile 320 dan 390 (drawer terbuka, label tidak terpotong), PDP 768 (header mobile). Screenshot lain belum diperiksa satu per satu.

### Belum tuntas (status NOT RUN kecuali disebut)
- Sweep EN/FR/IT per viewport (persistensi, overflow, kunci hilang) — hanya header 901px yang dicek lewat probe.
- Overlay di browser: cart, search, quick view, concierge, profil, size guide, shortcuts (Escape, klik luar, fokus, sentuh). Hanya menu mobile yang diuji.
- Alur backend E2E browser (update akun, newsletter, CRUD admin, konfirmasi destruktif admin).
- Keamanan menyeluruh (sink tersisa, IDOR, mass assignment, sesi) dan asesmen CSP report-only.
- Performa terukur (CSS gzip 21.79 kB + JS 6.46 kB = 28.25 kB < 120 kB target NFR-001; latensi < 150 ms belum diukur) dan a11y WCAG 2.2 AA.
- PHPUnit urutan standar tidak dijalankan ulang pada sesi ini (hanya acak).
- Insiden port 8000: hanya `ls`/`tail` read-only pada `storage/logs/laravel.log`; tidak ditemukan bukti yang mengukur penulisan sesi. **Dampak tidak dapat ditentukan.**
- Baseline asli hilang; baseline 8015 parsial dan dibuat setelah perbaikan dimulai.

### Kesiapan
- Lokal/QA terisolasi: cakupan teruji lulus; audit lain di atas belum dijalankan.
- Demo: belum dinyatakan; overlay dan bahasa belum diaudit.
- Produksi: **tidak siap** — sumber kebenaran dump vs live belum diputuskan, restore `getcat` BLOCKED (ERROR 1558), audit keamanan belum selesai.

## STATUS SEBELUMNYA — Phase 3 (sink prototype, URL gambar, logout browser)

Sumber otoritatif: [phase3-report.md](evidence/2026-10-11/phase3/phase3-report.md). Bagian dan angka lain di bawah (42/42, 37/37, 75/390, "logout NOT RUN", "sink belum diaudit", "review tidak tersedia") adalah **riwayat sebelum Phase 3** dan sudah digantikan.

- Review independen code-reviewer **sudah dijalankan** sebelum Phase 3: HIGH (`openProductStudio` field produk mentah + atribut `img`) dan MEDIUM (`emptyMsg` terjemahan keranjang) — keduanya ditutup. Review ulang pasca-perbaikan **sudah dijalankan**: tidak ada bug tersisa pada `prototype/app.js` dan `tests/prototype-security.test.mjs` (cakupan terbatas dua berkas itu, tanpa interaksi browser).
- Perbaikan `prototype/app.js`: diptych katalog editorial dan seluruh `openProductStudio` memakai `escapeHtml`, `Number()` untuk ID, `escapeHtml(JSON.stringify(sizeKey))` untuk handler ukuran; `emptyMsg` di-encode. Helper `safeImageSrc` menerima hanya path relatif dan `http(s)`, menolak `javascript:`, `data:`, `vbscript:`, `//host`, backslash, dan karakter kontrol; dipakai di kartu, diptych, galeri studio, dan keranjang. Sumber URL gambar nyata: `assets/*.jpg` relatif (`zafira-core.js`, `app.js`), plus `localStorage` `zafira_v3_5_products` yang dapat diubah klien — itu alasan allowlist.
- RED→GREEN: `phase3-sinks-red.log` (exit 1, 3 test gagal) → `node-red-green.log` (22/22, exit 0). Harness browser `browser-sinks.mjs` gagal pada salinan `HEAD` (`browser-red-head.log`, exit 1) dan lulus 14 assertion pada sumber baru (320px dan 1440px, `injected` tetap 0).
- Logout customer di browser: `logout-e2e.mjs` terhadap aplikasi sementara dengan SQLite berkas sementara (bukan MariaDB; `ecommerce` dan DB uji tidak disentuh), user sintetis `@example.test`: **14/14 PASS** desktop dan mobile (`logout-final.log`, exit 0): form + CSRF ada, logout keluar dari `/akun`, `/akun` kembali redirect `/login`, token sesi berotasi. Ada 2 run gagal sebelumnya karena asumsi nama cookie dan locator ambigu pada skrip (bukan pada aplikasi); diperbaiki di skrip, riwayat tercatat. Server 8013 dihentikan (PID 83565), berkas sementara dihapus; 8000/8001 tetap hidup.
- Regresi akhir (`final-1..10.log`, semua exit 0): `php artisan test` 76/394; random seed 1791702000 76/394; Pint PASS; `npm run build` PASS; Node 46/46; `node --check` tiga berkas exit 0; `browser-sinks.mjs` 14 assertion; `git diff --check` exit 0.
- Batas: harness browser memakai DOM nyata tetapi renderer diekstrak dari `app.js` (bukan halaman prototype penuh); tidak ada audit kontras/UX; tidak ada commit.

## Riwayat Phase 2 — bukti parsial (DIGANTIKAN sebagian oleh Phase 3 di atas)

Status berikut menggantikan kriteria terkait pada baseline di bawah. Gap yang belum tuntas tetap ditandai secara eksplisit.

| Kriteria | Status | Hasil dan bukti |
|---|---|---|
| Identifikasi drift per primary key | PASS | [Perbandingan Phase 2](evidence/2026-10-11/database/phase2-drift.md): 15 tabel dibandingkan; timestamp dinormalisasi UTC. Bukan snapshot konsisten lintas tabel; parser belum memiliki self-test independen. |
| Dump identik dengan database aktif | FAIL | Seluruh 12 produk berbeda pada sedikitnya satu kolom; kategori dan migrasi masing-masing memiliki satu PK hanya-dump dan satu hanya-aktif. Identitas akun/metadata juga berbeda. Tidak dilakukan rekonsiliasi atau tulis ke `ecommerce`. |
| Kebutuhan runtime `getcat` | PASS | [Audit sumber Phase 2](evidence/2026-10-11/phase2-security.md): runtime kategori menggunakan Eloquent, bukan `CALL getcat`. Ini tidak membuktikan ketiadaan konsumen SQL eksternal. |
| Restore kompatibilitas `getcat` | BLOCKED | Bukti terdahulu ERROR 1558 tetap berlaku; tidak diulang atau diperbaiki pada server bersama. Migrasi berstatus Ran tidak menjamin routine tersedia. |
| Riset browser kompetitor parsial | PASS (parsial) | [Bukti Phase 2](evidence/2026-10-11/phase2-competitor.md): 10 halaman terender; 9 URL terblokir/tidak tersedia. Observasi teks saja, tanpa login atau metrik konversi. |
| Login sintetis dan UI terautentikasi | PASS (parsial) | Playwright desktop/mobile: 32 assertion lulus, 0 gagal. Verifikasi redirect, password salah, guard, logout admin, dan screenshot tersamarkan. Logout user lewat browser NOT RUN (halaman `/akun` tidak menampilkan kontrol logout; suite HTTP `AuthTest` tetap mencakup POST `/logout`). [Bukti](evidence/2026-10-11/phase2-auth/). |
| Sink prototype HTML mentah: toast, kanban, portal akun | PASS (terbatas) | Reproduksi Node sandbox dengan state palsu: merah 0/10 sebelum fix; sesudah `escapeHtml` + ID `Number()`, `node --test tests/*.mjs` 37/37 dan `node --check prototype/app.js` exit 0. Sink prototype lain (keranjang, material, audit log, popup) belum diaudit; tidak ada uji browser. [Audit](evidence/2026-10-11/phase2-security.md). |
| Cleanup data uji teridentifikasi | PASS | Hapus 1 user sintetis, 1 admin sintetis, 1 backup, 5 sesi user; lalu 1 newsletter sintetis, 1 concierge sintetis tanpa user_id, 2 job berisi `@example.test`. Tetap 15 tabel, user/admin/newsletter asli masing-masing 1; concierge/jobs/backup 0, orphan produk 0. Tidak menghapus DB atau sesi/cache ambigu. Password temp dihapus; port 8013 bebas; 8000/8001 tetap hidup. |
| Regresi akhir Phase 2 | PASS | Standard PHP 75/390; random PHP 75/390 seed 1791686702; Pint, build, Node 37/37, `git diff --check` exit 0. Log `phase2-final-*.log` pada [direktori evidence](evidence/2026-10-11/). |

### Bukti Phase 2

- Auth E2E: [phase2-auth-report.md](evidence/2026-10-11/phase2-auth/phase2-auth-report.md), `results.json`, empat screenshot tersamarkan.
- Regression: `phase2-final-php.log`, `phase2-final-php_random.log`, `phase2-final-pint.log`, `phase2-final-build.log`, `phase2-final-node.log`, `phase2-final-diff.log` (semua exit 0).
- Drift DB: [phase2-drift.md](evidence/2026-10-11/database/phase2-drift.md).
- Keamanan dan `getcat`: [phase2-security.md](evidence/2026-10-11/phase2-security.md).
- Browser kompetitor: [phase2-competitor.md](evidence/2026-10-11/phase2-competitor.md). **BLOCKED** remains for competitor sites denied/CAPTCHA; report is partial only.

Keputusan pengguna diperlukan sebelum restore: tentukan sumber kebenaran per domain data. Jumlah sesi/cache pada laporan drift merupakan observasi sementara, bukan baseline tetap.

## Verifikasi final setelah timeout — baseline sebelum Phase 2

Bagian di bawah ini menggantikan status sementara pada catatan historis berikutnya; angka lama tetap dipertahankan sebagai baseline.

| Kriteria | Status | Hasil dan bukti |
|---|---|---|
| NFR-301 isolasi pengujian dan regresi | PASS | PHP 75 tests / 390 assertions, standard dan random; SQLite in-memory. [Manifest final](evidence/2026-10-11/final-test-verification.json), log `final-php_tests.log` dan `final-php_random_tests.log` pada direktori yang sama. |
| Build, format, Node, diff | PASS | Pint, build, 24 Node tests, diff check exit 0; manifest menyimpan perintah dan exit asli. |
| Encoding JSON-LD dan konfirmasi admin | PASS | Dua encoder dan tiga handler diaudit; 12 tests / 141 assertions. [Review keamanan](evidence/2026-10-11/security-final.md). Bukan audit XSS seluruh aplikasi. |
| FR-201–204 / NFR-201–203: responsive terbatas | PASS | Browser dijalankan ulang: 77 baris, 0 baris bermasalah pada probe overflow, console/page error dan dock; [metrics terbaru](evidence/2026-10-11/ui/final-2026-10-11/metrics.json). Ini tidak menutup seluruh requirement induk. |
| UI authenticated, semua interaksi, network | NOT RUN | Probe akun/admin mengikuti redirect login; bukan pemeriksaan dashboard authenticated. Tidak ada audit network atau seluruh interaksi akhir. |
| Restore tabel dan relasi | PASS | DB uji tetap ada: 15 tabel, 28 indeks, 3 FK, 110 kolom, 17 migration tercatat, orphan 0. [Audit read-only](evidence/2026-10-11/database/final-readonly.md). |
| Integrasi newsletter/concierge | PASS | Bukti sesi sebelumnya: [restore-validation](evidence/2026-10-11/database/restore-validation.md); tidak dijalankan ulang pada penutupan. |
| Restore routine `getcat` | BLOCKED | ERROR 1558 pada mysql.proc; migration tercatat Ran tidak membuktikan routine tersedia. Runtime kategori memakai Eloquent. |
| `db:show` XAMPP | BLOCKED | `performance_schema.session_status` tidak tersedia; tidak memperbaiki database sistem bersama. |
| Dump identik dengan aktif | FAIL | Skema cocok tetapi isi berbeda; ringkasan kolom/PK ada pada audit read-only, tanpa nilai sensitif. |
| Login sintetis melalui browser | NOT RUN | Akun sintetis user/admin dibuat hanya pada DB uji; login interaktif belum dilakukan. Fixture tetap tersimpan. |
| Riset sekunder tiga kelompok | PASS | Enam dokumen tersedia di `docs/research/`; scope sumber publik/teks, bukan perilaku browser. |
| Browser kompetitor dan baseline konversi | BLOCKED | Akses sebagian domain terblokir; belum ada audit browser kompetitor atau baseline konversi ZAFIRA. |
| Commit/merge/push/deploy | NOT APPLICABLE | Tidak dilakukan sesuai batas tugas. |

MariaDB aktual adalah XAMPP 10.4.28, bukan kesimpulan server mati pada pemeriksaan socket awal. Fingerprint `ecommerce` sama sebelum/sesudah tiga putaran audit read-only; bukti ini bukan pemantauan seluruh riwayat sesi. Port 8000/8001 tidak dihentikan. Server sementara 8012 dihentikan setelah audit (exit 143 karena terminasi sengaja).

Angka 75/352 merupakan snapshot lebih awal; verifikasi final menghasilkan 75/390. Cakupan assertions payload keamanan diperluas tanpa penambahan jumlah test. Dibanding baseline 63/249, suite BackendPermissionAuditTest menambah 12 tests / 141 assertions.

Handoff konsolidasi: [FINAL_HANDOFF_2026-10-11.md](FINAL_HANDOFF_2026-10-11.md).

## Catatan historis — bukan status akhir

## Otoritas dan batas

Indeks utama: [../README.md](../README.md). Baseline historis: [verifikasi 10 Oktober](../05-quality/REMEDIATION_VERIFICATION_2026-10-10.md), [sintesis produk](../02-product/benchmark/SINTESIS_KOMPARATIF_ZAFIRA.md), PRD dan traceability yang sudah ada. Tidak membuat PRD pengganti.

Branch aktual `fix/audit-benchmark-remediation`, HEAD `2f355e1`; commit terverifikasi melalui `git log` dan `git show --stat` (22 berkas). Ada 44 berkas tracked berubah sebelum pekerjaan, terutama skills dan laporan task-3, serta banyak berkas untracked milik pengguna. Tidak dihapus atau ditimpa. Tidak commit/push/merge/deploy, reset admin, rotasi kredensial, atau operasi DB destruktif.

## Kebutuhan dan penerimaan

- NFR-301: isolasi fixture kredensial seeder terhadap dotenv. Penerimaan: fixture acak mengalahkan env lokal, kasus absent benar-benar null, ketiga sumber env dipulihkan setelah test; seluruh suite tetap lulus.
- NFR-201–203 / FR-201–204: gunakan ID yang sudah ada; audit prototype/keyboard/klaim belum otomatis dianggap selesai oleh suite PHP.

## Eksekusi dan bukti

| Pemeriksaan | Hasil aktual | Exit | Bukti |
|---|---|---:|---|
| `php artisan test --filter=LegacyPatchTest` sebelum perbaikan | 3 gagal, 21 lulus, 88 assertions | 1 | Output sesi: hash fixture, login, perubahan password gagal |
| Inspeksi `AdminSeeder`, `Env::getRepository`, semua referensi `ADMIN_SEED_PASSWORD` | dotenv mengisi superglobal; putenv saja tidak mengalahkan nilai yang sudah dimuat | 0 | `tests/Feature/LegacyPatchTest.php`, `database/seeders/AdminSeeder.php` |
| Test Legacy sesudah perbaikan | 24 lulus, 93 assertions | 0 | Output sesi; kasus absent sekarang menegaskan null |
| `php artisan test` | 63 lulus, 249 assertions | 0 | Output sesi 11 Oktober; SQLite in-memory dari phpunit.xml |
| `node --test tests/*.mjs` | 22 lulus | 0 | Output sesi |
| `npm run build` | Vite selesai 279 ms | 0 | `public/build/manifest.json`, CSS 123.51 kB, JS 19.11 kB |
| `vendor/bin/pint --test` | passed | 0 | Output JSON sesi |

Snapshot pemulihan `storage/app/recovery/2026-10-11/LegacyPatchTest.php.HEAD` berasal dari HEAD yang identik dengan berkas sebelum edit (berkas test tidak berada dalam dirty baseline). Snapshot dibuat sesudah edit, **bukan** snapshot seluruh workspace sebelum edit. Tidak mencakup `.env`, database, atau nilai rahasia. Jangan menimpa perubahan pengguna ketika memulihkan.

## Lingkungan terverifikasi

- macOS 27.0.1 (26A434), arm64, Apple M5, RAM 17,179,869,184 byte (16 GiB); root 926 GiB, tersedia 816 GiB.
- PHP CLI 8.5.11, Composer 2.10.3, Laravel 12.69.1; composer.json meminta PHP ^8.2, Laravel ^12.0.
- Node 24.21.0, npm 11.19.0; build aktual lulus, bukan mengandalkan hasil historis Node 22.
- MySQL client 8.4.11; `brew services list` menampilkan mysql@8.4 dan php status none. `mysqladmin ping` gagal socket `/tmp/mysql.sock`. Ini tidak membuktikan seluruh kemungkinan server TCP mati.
- PDO MySQL/SQLite, mbstring, intl, curl, openssl, fileinfo, gd, zip tersedia. Status phpMyAdmin belum diverifikasi.

## Backup: metadata saja

Di `database/` ditemukan tiga dump relevan: `zafira_ecommerce_20260921.sql` (128 kB), `_chat.sql` (24.2 kB), `_clean.sql` (28.1 kB). Masing-masing memiliki 15 CREATE TABLE; jumlah baris perintah INSERT adalah 8, 17, 40, bukan jumlah record. Dump clean mencantumkan MariaDB 10.4.32 Windows. Tidak mencetak payload INSERT, hash password, data pribadi, atau isi `.env`.

Restore belum dijalankan. Jangan impor ke database aplikasi. Langkah berikutnya: siapkan instance MySQL disposable terisolasi, audit SQL untuk USE/DEFINER/routine/trigger sebelum import; validasi schema dan hitungan saja. SQLite bukan bukti kompatibilitas procedure MySQL.

## Temuan dan pekerjaan belum selesai

1. Kontrak IDR prototype diperbaiki: format harga tetap IDR, pemilih EUR/USD dihapus, pergantian bahasa tidak mengubah mata uang. Regression test baru mereproduksi kegagalan sebelum perbaikan. Verifikasi ulang: Node 24/24 lulus, PHP 63/63 lulus (249 assertions), build selesai 246 ms, Pint passed; seluruh exit 0. Output aktual tersedia pada transcript sesi. Grep tidak menemukan sisa `setCurrency`, EUR/USD, atau klaim konversi real-time.
2. Klaim ledger, sertifikat, stok dan metrik demo belum diaudit menyeluruh; jangan samakan data localStorage dengan pesanan backend.
3. Browser tujuh viewport dijalankan sesi ini dengan Chromium headless shell 1228 dari cache lokal. Bukti akhir: `evidence/2026-10-11/browser.json` beserta 14 screenshot. Tidak ada console/page error. Simbol `$`/`€` nol; Rupiah muncul (12 kemunculan di aplikasi, 8 di prototype). Elemen `[data-curr]` prototype tinggal indikator IDR statis. Overflow prototype pada 320×568 dan 1024×768 diperbaiki dengan perubahan CSS. Overflow app 320×568 (15 px) masih ada setelah `.hero-media { overflow: hidden; }`; viewport lainnya nol. Tidak mengklaim toolbar overlap tervalidasi: pemeriksaan banner khusus belum dilakukan.
4. Audit menyeluruh backend/permission/dataflow belum selesai; suite hijau hanya membuktikan kasus yang tercakup.
5. Riset 10 global + 6 luxury + Indonesia belum diperbarui lewat sumber primer sesi ini. Benchmark lama adalah riset sekunder, bukan ranking favorit atau audit browser.
6. Inventaris plugin hanya menemukan `.claude/settings.local.json` dan `.codex/hooks.json`; konfigurasi belum diaudit lengkap. Tidak mengaktifkan seluruh plugin/skill secara buta.
7. Subagent tambahan tidak tersedia lewat katalog tool sesi ini. Skill systematic-debugging dimuat; kemampuan video, trading, klinis dan lainnya tidak relevan dan tidak diaktifkan.

## Handoff aman

Mulai dari `git diff -- tests/Feature/LegacyPatchTest.php`, pertahankan fixture acak dan pemulihan lingkungan. Jalankan ulang suite serta randomized order. Simpan output verifikasi berikutnya dalam folder bukti; output awal di atas hanya tersimpan di transcript sesi, belum sebagai log berkas. Lanjutkan browser dengan database disposable, bukan kredensial admin pengguna. Semua gap di atas tetap terbuka sampai ada bukti aktual.
