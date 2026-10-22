# Ketertelusuran Requirement — REM-20261021 dan Arsip R-01–R-60

**Revisi:** 2026-10-21 — REM-20261021 DOC / DOC-05; pembaruan Final2 berdasarkan bukti integrator r7.

**Baseline REM:** `041ed083a1be330d97d4f335cda8555670045cdd`; sumber yang dibaca adalah working tree pascaremediasi, bukan bukti eksekusi pada commit tersebut. Tidak ada commit, eksekusi CLI NgodingPakeAI, atau perubahan status plan server dalam revisi ini.

## 1. Aturan membaca crosswalk aktif

**Update terakhir DATA-08:** export akun dan nested order items kini keyset-streaming, mempertahankan 12 key/tipe/allowlist/privacy. `ProfileExportStreamingTest` menguji fixture owner+foreign >1.000 baris, lazy emission, bound SQL dan nonmutasi; review DATA APPROVE. Rerun penuh terbaru **1.154 passed/3 skipped/156.806 assertions**, Node465 dan Pint377 lulus; [metadata](../05-quality/evidence-remediation-2026-10-21/php-full-export-final.json). Ini menambah bukti FR-REM-004/NFR-REM-001/003 tanpa mengklaim byte/RSS, latency atau engine parity; angka P7/1150 di bawah adalah histori.

**Update final integrator:** seluruh tes P7 dijalankan ulang setelah tambahan inquiry: **1.150 passed, 3 skipped, 29.864 assertions; EXIT=0** ([metadata](../05-quality/evidence-remediation-2026-10-21/php-full-final.json)). P7 tetap label run historis; hasil final mendukung regresi yang sama ditambah assertion inquiry. Skipped InnoDB dan seluruh gate staging tetap terbuka.

- Sumber requirement REM: [kontrak FR-REM-001–007/NFR-REM-001–006](REMEDIATION_REAL_WORLD_2026-10-21.md); kontrak produk: [PRD](../02-product/PRD.md); kriteria lokal: [AC-REM](ACCEPTANCE_CRITERIA.md). Audit [DOC-01–05](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md#10-dokumentasi-requirement-dan-klaim-produk) tetap baseline historis, tidak ditulis ulang.
- **Status integrator r7 menggantikan `NOT RUN/PENDING` lane DOC sebelumnya hanya untuk scope yang terbukti di bawah.** Label pending pada snapshot PRD/AC/desain DB tidak lagi berarti seluruh suite/review belum berjalan; kontrak dan batas acceptance-nya tidak berubah. Keberadaan source saja tetap bukan bukti lulus, dan hasil suite bukan penerimaan seluruh requirement.
- [Bukti integrator r7](../05-quality/PROJECT_READINESS_REPORT.md#bukti-integrator-r7): **P7** = PHP penuh 1.150 passed, 3 skipped InnoDB, 29.856 assertions; **H7** = ordered host 48 passed/358 assertions; **N7** = Node 465 passed/0 skip; **PY7** = Python 165 run, 6 skip FontTools, **159 executed**; **L7** = Pint 376 file pass; semuanya EXIT=0. **B3** = build r3 EXIT=0, source frontend tidak berubah sesudahnya menurut handoff main. Lane DOC membaca artefak, tidak mengulang eksekusi.
- [Review source independen](../05-quality/PROJECT_READINESS_REPORT.md#review-source-independen) SEC/TXN/DATA/UI/OPS/MSI/DOC dilaporkan main **APPROVE dalam scope source**, bukan approval produksi. Alias reviewer pada tabel merujuk ID lengkap di laporan kesiapan; approval tersebut tidak otomatis menerima perubahan tes setelah r7 atau pembaruan status Final2 ini.
- Bukti berikutnya tetap perlu command, timestamp, snapshot SHA/diff, engine/fixture, hasil/log dan reviewer/approver independen. [Laporan remediasi rinci](../05-quality/REMEDIATION_REPORT_2026-10-21.md) telah diterbitkan integrator dan menjadi rujukan hasil final. Lane DOC tidak mengubah plan server.
- Pemetaan FR/US lama ke REM di bawah adalah keterkaitan domain, **bukan ekuivalensi penuh** atau konfirmasi mapping R→G. [Matriks produk 10–12 Oktober](../02-product/REQUIREMENTS_TRACEABILITY.md) tetap arsip; klaim email-match, personalisasi cart, dan “verifikasi email bukan fitur aktif” tersupersesi oleh source/PRD kini. `routes/web.php` sudah memuat verifikasi email; delivery runtime belum dibuktikan.

## 2. Crosswalk kontrak produk yang direkonsiliasi

Path kode/tes relatif terhadap root repository.

| Requirement / temuan | AC aktif | Kode sumber | Tes / batas coverage | Hasil kini | Reviewer |
|---|---|---|---|---|---|
| FR-008/204, US-005; DOC-01 P1 | AC-REM-OWN | `app/Http/Controllers/ProfileController.php::show`, `app/Http/Controllers/OrderController.php::show` | `tests/Feature/LegacyPatchTest.php` — `test_account_history_excludes_guest_and_other_user_rows_with_matching_email`, `test_account_history_prefers_user_id_link`; `tests/Feature/OrderOwnershipRegressionTest.php` | Regresi ownership P7 lulus; klaim guest terpisah tetap tidak dinyatakan tersedia | APPROVE source — SEC; DOC untuk kontrak |
| FR-004, US-003; batas baru FR-201; DOC-02 P2 | AC-REM-CART | `app/Services/CartStore.php`, `app/Services/CheckoutService.php::cart/priced`, `resources/js/app.js::openInquiry` | `tests/Feature/UnifiedPurchaseCartTest.php` — harga otoritatif, persistensi login, penolakan size/monogram | Regresi cart P7 dan unit UI N7 lulus; bukan acceptance browser | APPROVE source — TXN/UI; DOC untuk kontrak |
| FR-REM-002 terkait FR-004/US-003; DOC-02 P2 | AC-REM-ORDER | `routes/web.php`, `app/Http/Controllers/OrderController.php::store`, `app/Http/Controllers/GuestCheckoutController.php`, `app/Services/CheckoutService.php` | `tests/Feature/CheckoutIdempotencyAntiDoubleChargeTest.php`, `tests/Feature/GuestCheckoutTest.php`, `tests/Feature/OrderStatusHistoryTest.php` | Regresi checkout P7 lulus; uji InnoDB yang skipped/provider nyata tidak dicakup | APPROVE source — TXN; DOC untuk kontrak |
| FR-005/203, US-004; DOC-02 P2 | AC-REM-INQUIRY | `app/Http/Controllers/StorefrontController.php::bookConcierge`, `resources/js/app.js::setConciergeMode/openInquiry` | `tests/Feature/StorefrontTest.php::test_concierge_request_can_be_created`, `tests/Feature/Task3RemediationTest.php` untuk payload legacy. Main menambah assertion negatif concierge/cart/order yang lebih kuat setelah r7; delivery nyata tidak dicakup Mail fake | P7 lulus; tambahan pasca-R7: **12 passed/51 assertions, EXIT=0** pada `php-inquiry-final.*`, cart/stok tidak berubah dan tidak membuat order/item/reservasi/payment | APPROVE source tambahan — DATA `a3568e6b-70f7-48d9-8e67-99595380745b`; runtime integrator |

## 3. Crosswalk fungsional REM

| Requirement / keterkaitan | AC aktif | Kode sumber | Tes yang tersedia | Hasil kini | Reviewer |
|---|---|---|---|---|---|
| **FR-REM-001**; FR-007/008/009/014/204, NFR-002; SEC-01–07 | AC-REM-F01, AC-REM-OWN | `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/PasswordResetController.php`, `app/Http/Controllers/AdminTotpController.php`, `app/Http/Controllers/CustomerTotpController.php`, `app/Http/Controllers/ProfileController.php` | `tests/Feature/SecurityRemediationTest.php` — canonical origin, input array, owner recovery, revokasi sesi, TOTP/CAS, subscriber | Regresi P7 + ordered host H7 lulus; ingress/provider produksi tetap gate terpisah | APPROVE source — SEC |
| **FR-REM-002**; NFR-003; TXN-01–08 | AC-REM-F02, AC-REM-ORDER | `app/Models/Order.php::STATUS_FLOW`, `app/Services/PaymentWebhookService.php`, `app/Services/CheckoutService.php`, `app/Services/TransactionLockRetry.php`, `app/Services/ReturnAllocation.php`, `app/Http/Controllers/OrderReturnController.php` | `tests/Feature/TransactionRemediationTest.php` — progres/dedup, cancel, saldo/rounding/provenance, nested retry, expiry/hold | Regresi P7 lulus; 3 skip InnoDB bukan PASS, race engine target/provider nyata belum terbukti | APPROVE source — TXN |
| **FR-REM-003**; OPS-01/02, DATA-05/06 | AC-REM-F03 | `app/Services/DatabaseBackupService.php`, `routes/console.php` | `tests/Feature/BackupRemediationTest.php`, `tests/ops-remediation.test.mjs` — snapshot/retensi/transport | Regresi backup P7 dan kontrak N7 lulus; restore MySQL/MariaDB tetap gate terbuka | APPROVE source — DATA/BACKUP |
| **FR-REM-004**; NFR-003; DATA-01–04/07–09 | AC-REM-F04, AC-REM-OWN | `app/Http/Controllers/ProfileController.php::destroy`, `app/Http/Controllers/AdminProductController.php`, `app/Http/Controllers/StorefrontController.php`; migrasi `database/migrations/2026_10_21_000050_widen_return_refund_money_columns.php` dan `2026_10_21_000060_add_return_allocation_provenance.php` pada direktori yang sama | `tests/Feature/DataRemediationTest.php`, `tests/Feature/SecurityRemediationTest.php`; `tests/Feature/TransactionRemediationTest.php::test_txn04_r2_rollback_refuses_to_discard_allocation_provenance` | Regresi data/provenance P7 lulus; bukan bukti migrasi produksi atau engine parity. Lane DOC tidak menjalankan DB | APPROVE source — DATA, TXN untuk provenance |
| **FR-REM-005**; FR-001–005/013/015–018, NFR-004/006; UI-01–10 | AC-REM-F05 | `resources/js/app.js`, `resources/js/admin-table.js`, `resources/js/search-match.js`, `resources/views/welcome.blade.php`, `resources/views/catalog/index.blade.php`, `prototype/app.js` | `tests/storefront-remediation.test.mjs`, `tests/admin-remediation.test.mjs` — DOM simulasi/kontrak; bukan browser atau pembaca layar nyata | N7/P7 lulus; B3 lulus dan frontend tidak berubah setelahnya. Dua kegagalan browser terdahulu belum direrun | APPROVE source — UI |
| **FR-REM-006**; NFR-005; OPS-03–09 | AC-REM-F06 | `tests/TestCase.php`, `routes/console.php`, `scripts/amadis/acceptance_isolated.sh`, `scripts/amadis/acceptance_server.py`, `.github/workflows/ci.yml`, `scripts/ci/protect-main.sh`, `package.json` | `tests/Feature/OperationsRemediationTest.php`, `tests/ops-remediation.test.mjs`, `tests/python/test_remediation_gates.py` | P7/N7/PY7/L7 lulus dalam cakupannya; 6 skip FontTools tetap skip. Native observer lulus hanya observasi waitid Darwin; cleanup nyata **BLOCKED** izin ps/signal, tanpa elevasi tersedia; remote protection belum diverifikasi | APPROVE source — OPS |
| **FR-REM-007**; FR-010, US-006 | AC-REM-F07 | [TPS real-world](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md), `app/Services/OperationalMetrics.php::summary`, `routes/web.php`, `app/Http/Controllers/AdminOperationsSummaryController.php`, `resources/views/admin/operations-summary.blade.php` | `tests/Feature/AdminOperationsSummaryTest.php` — guards/window, event vs snapshot, koneksi queue, zero vs unknown, PII/error. Pengukuran throughput terpisah | Regresi metrik P7 lulus; MSI minimum tetap partial/best-effort, bukan seluruh MSI atau throughput terukur | APPROVE source — MSI |

## 4. Crosswalk nonfungsional REM

| Requirement / keterkaitan | AC aktif | Kode / artefak yang diperiksa | Tes dan bukti tambahan yang diperlukan | Hasil kini | Reviewer |
|---|---|---|---|---|---|
| **NFR-REM-001**; NFR-001 | AC-REM-N01 | `app/Services/OperationalMetrics.php`, `app/Http/Controllers/StorefrontController.php`, [TPS](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md) | `DataRemediationTest.php` dan `AdminOperationsSummaryTest.php` di `tests/Feature/` hanya kontrak boundedness; benchmark workload/latensi/aset belum tersedia untuk acceptance ini | **PARTIAL:** kontrak boundedness P7 lulus; workload/latensi/kapasitas staging belum diukur | APPROVE source — DATA/MSI; bukan approval performa |
| **NFR-REM-002** | AC-REM-N02 | `app/Services/DatabaseBackupService.php`, `app/Services/OrderNotificationDispatcher.php`, `routes/console.php` | `tests/Feature/BackupRemediationTest.php`, `tests/Feature/OperationsRemediationTest.php`; drill staging restore, worker/scheduler dan RPO/RTO diperlukan | **PARTIAL:** regresi P7/PY7 lulus; restore engine target, operasi staging dan cleanup nyata masih gate terbuka | APPROVE source — DATA/OPS; bukan availability produksi |
| **NFR-REM-003**; NFR-003 | AC-REM-N03 | Source FR-REM-001/002/004; [skema kini](../03-architecture/DATABASE_DESIGN.md) | `tests/Feature/SecurityRemediationTest.php`, `tests/Feature/TransactionRemediationTest.php`, `tests/Feature/DataRemediationTest.php`, `tests/Feature/OrderOwnershipRegressionTest.php`; engine parity/race diperlukan | Regresi P7 lulus; **PARTIAL** untuk parity/race InnoDB (3 skipped). Assertion inquiry tambahan lulus terarah (12 tes/51 assertions), review DATA APPROVE | APPROVE source — SEC/TXN/DATA |
| **NFR-REM-004**; NFR-004/006/203 | AC-REM-N04 | Source UI FR-REM-005 dan protokol HCI | `tests/storefront-remediation.test.mjs`, `tests/admin-remediation.test.mjs`; browser/keyboard/viewport/screen reader dan audit kontras diperlukan | **PARTIAL:** unit/fake-DOM N7 lulus; dua browser failure belum direrun, WCAG/screen reader belum terbukti | APPROVE source — UI; bukan sertifikasi WCAG |
| **NFR-REM-005** | AC-REM-N05 | Diff terarah FR-REM-001–007; batas `CartStore`/`CheckoutService`/`StorefrontController` | Regresi terkait di atas + review arsitektur/diff independen; tidak ada satu unit test yang membuktikan modularitas | Review source modularitas terarah APPROVE; P7/N7/PY7/L7 dan B3 mendukung scope yang diuji, bukan bukti semua kualitas produk | APPROVE source — SEC/TXN/DATA/UI/OPS/MSI; DOC untuk kontrak |
| **NFR-REM-006** | AC-REM-N06 | `routes/web.php`, `app/Services/OperationalMetrics.php`, `app/Services/PaymentWebhookService.php`, workflow CI; [protokol](ENGINEERING_EXCELLENCE_PROTOCOL.md) | `tests/Feature/AdminOperationsSummaryTest.php`, `tests/Feature/TransactionRemediationTest.php`, `tests/ops-remediation.test.mjs`; Evaluation Record + reviewer independen wajib | **PARTIAL:** regresi/gate teruji lulus dan review source APPROVE; cleanup nyata BLOCKED, targeted inquiry tambahan lulus, approval rilis belum ada | APPROVE source — SEC/TXN/OPS/MSI; DOC untuk pelaporan |

## 5. Jejak koreksi dokumentasi dan residual

| Temuan audit | Perubahan terkontrol / acceptance dokumen | Status penutupan |
|---|---|---|
| DOC-01 P1 | PRD FR-008 → AC-REM-OWN → query owner + regresi matching-email; email-only dicabut, klaim guest terpisah | Koreksi kontrak APPROVE scoped — DOC `a356…`; regresi ownership P7 lulus |
| DOC-02 P2 | PRD/AC memisahkan cart, order, inquiry; riwayat AC-005 diberi label salah/tersupersesi | Koreksi domain APPROVE scoped — DOC `a356…`; targeted assertion inquiry tambahan 12 tes/51 assertions lulus, review DATA APPROVE |
| DOC-03 P2 / DATA-10 | `DATABASE_DESIGN.md` memuat source skema, uang BIGINT, FK, migrasi 000050/000060; SQL lama nonaktif | Skema dokumentasi APPROVE scoped — DOC/DATA `a356…`; penerapan DB produksi/engine parity belum terbukti |
| DOC-04 P2 | `PROJECT_READINESS_REPORT.md` memakai gate bukti, bukan skor 100%/49 tes atau klaim WCAG selesai | Rekonsiliasi awal APPROVE scoped — DOC `a356…`; status r7 diperbarui pada Final2, gate rilis tetap terbuka |
| DOC-05 P2 | Crosswalk ini mengaitkan 7 FR + 6 NFR REM, AC, source, tes, hasil dan reviewer; mapping R lama tidak dijadikan acceptance | Crosswalk awal APPROVE scoped — DOC `a356…`; Final2 mengganti hasil/reviewer sesuai bukti, bukan menerima seluruh requirement |

**Residual dan scope Final2:** hanya dokumen ini dan `PROJECT_READINESS_REPORT.md` diperbarui; PRD/AC/desain DB yang sudah direview tidak disentuh. Catatan pending/author aktif pada snapshot lama dibaca menurut supersession r7 di atas. Native observer bukan cleanup; izin ps/signal menghalangi uji cleanup nyata dan elevasi tidak tersedia. Dua browser failure belum direrun, tiga skip InnoDB/enam skip FontTools bukan lulus, dan tambahan assertion inquiry kini lulus terarah dengan review DATA terpisah. Catatan finalisasi integrator menggantikan pending inquiry pada handoff Final2. Matriks produk lama, tracker, ADR/TPS, DOC-06, fitur deferred dan klaim bisnis eksternal tidak dinyatakan selesai. Approval source bukan penutupan seluruh audit, provider siap, atau izin produksi.

## 6. Arsip konseptual R-01–R-60 — bukan status plan terkini

Bagian berikut dipertahankan dari snapshot lama. Seluruh mapping R→G **INFERRED**, tidak ada bukti persetujuan plan terbaru dalam lane ini; R-08/09 belum punya phase dan R-33 ambigu pada snapshot tersebut. Kalimat “None” hanya berarti semua R punya baris konseptual, bukan semua requirement terimplementasi/diterima. Tidak ada CLI dijalankan untuk memperbaruinya.

**Source historis:** saved PRD from `npx ngodingpakeai plan get 26abc11f-166b-408a-86aa-bf1c94aa41bd`, PRD v46 / document v1.32. Reviewed with existing files in `docs/04-implementation/`.

### Reading rules (arsip)

- `EXPLICIT` means the saved PRD states the requirement-to-goal mapping verbatim.
- `INFERRED` means the goal is a conceptual match only. It is **not** an authoritative mapping.
- The saved PRD defines G1–G7 and separately defines requirement waves and phases. It does not provide a complete verbatim R→G matrix. Therefore, all rows below are marked `INFERRED` unless a future PRD revision adds a direct R→G statement.
- `—` in Phase means the saved PRD does not state a phase for that requirement.
- Phase follows the saved PRD §7.1. A feature may span phases; this table records requirement-level placement where the PRD states it.

### Mapping table (arsip)

| Requirement | Goal mapping | Status | Exact gap to close for inferred mapping | Phase in saved PRD |
|---|---|---|---|---:|
| R-01 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-01→G2 statement to the saved PRD. | 2 |
| R-02 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-02→G2 statement to the saved PRD. | 2 |
| R-03 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-03→G2 statement to the saved PRD. | 3 |
| R-04 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-04→G2 statement to the saved PRD. | 3 |
| R-05 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-05→G2 statement to the saved PRD. | 6 |
| R-06 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-06→G2 statement to the saved PRD. | 6 |
| R-07 | G5 — PII health | INFERRED | Add verbatim R-07→G5 statement to the saved PRD. | 2 |
| R-08 | G3 — observability and measurable operations | INFERRED | Add a phase assignment and verbatim R-08→G3 statement to the saved PRD. | — |
| R-09 | G5 — PII health and privacy | INFERRED | Add a phase assignment and verbatim R-09→G5 statement to the saved PRD. | — |
| R-10 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-10→G2 statement to the saved PRD. | 3 |
| R-11 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-11→G2 statement to the saved PRD. | 6 |
| R-12 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-12→G2 statement to the saved PRD. | 6 |
| R-13 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-13→G2 statement to the saved PRD. | 6 |
| R-14 | G6 — usable operational console and feedback | INFERRED | Add verbatim R-14→G6 statement, or assign it to a separate catalog/discovery goal. | 6 |
| R-15 | G1 — automated assurance | INFERRED | Add verbatim R-15→G1 statement to the saved PRD. | 3 |
| R-16 | G1 — automated assurance | INFERRED | Add verbatim R-16→G1 statement to the saved PRD. | 3 |
| R-17 | G5 — PII health and access protection | INFERRED | Add verbatim R-17→G5 statement to the saved PRD. | 3 |
| R-18 | G3 — observability and operations | INFERRED | Add verbatim R-18→G3 statement to the saved PRD. | 3 |
| R-19 | G4 — single source of truth | INFERRED | Add verbatim R-19→G4 statement to the saved PRD. | 3 |
| R-20 | G3 — observability and resilient operations | INFERRED | Add verbatim R-20→G3 statement to the saved PRD. | 6 |
| R-21 | G4 — single source of truth and reproducibility | INFERRED | Add verbatim R-21→G4 statement to the saved PRD. | 6 |
| R-22 | G1 — automated assurance | INFERRED | Add verbatim R-22→G1 statement to the saved PRD. | 6 |
| R-23 | G6 — usable, accessible console and feedback | INFERRED | Add verbatim R-23→G6 statement to the saved PRD. | 6 |
| R-24 | G7 — consistent identity and language | INFERRED | Add verbatim R-24→G7 statement to the saved PRD. | 6 |
| R-25 | G6 — usable interface and recovery feedback | INFERRED | Add verbatim R-25→G6 statement to the saved PRD. | 6 |
| R-26 | G6 — usable interface and visible status | INFERRED | Add verbatim R-26→G6 statement to the saved PRD. | 6 |
| R-27 | G6 — usable interface validated with users | INFERRED | Add verbatim R-27→G6 statement to the saved PRD. Note: the saved PRD records R-27 as intentionally without a feature home. | 6 |
| R-28 | G6 — usable interface and immediate feedback | INFERRED | Add verbatim R-28→G6 statement to the saved PRD. | 6 |
| R-29 | G1 — automated assurance | INFERRED | Add verbatim R-29→G1 statement to the saved PRD. | 2 |
| R-30 | G3 — observability | INFERRED | Add verbatim R-30→G3 statement to the saved PRD. | 2 |
| R-31 | G3 — observability and business dashboard | INFERRED | Add verbatim R-31→G3 statement to the saved PRD. | 6 |
| R-32 | G1 — automated assurance | INFERRED | Add verbatim R-32→G1 statement to the saved PRD. | 6 |
| R-33 | G4 — single source of truth | INFERRED | Add verbatim R-33→G4 statement to the saved PRD. Resolve phase ambiguity: §7.1 lists R-33 in phase 1, and the phase-6 range `R-31…R-35` also covers it. | 1 (also within phase-6 range) |
| R-34 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-34→G2 statement to the saved PRD. | 6 |
| R-35 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-35→G2 statement to the saved PRD. | 6 |
| R-36 | G5 — PII and access protection | INFERRED | Add verbatim R-36→G5 statement to the saved PRD. | 3 |
| R-37 | G5 — PII and recoverability | INFERRED | Add verbatim R-37→G5 statement to the saved PRD. | 3 |
| R-38 | G1 — automated assurance | INFERRED | Add verbatim R-38→G1 statement to the saved PRD. | 2 |
| R-39 | G1 — automated assurance and safe release | INFERRED | Add verbatim R-39→G1 statement to the saved PRD. | 6 |
| R-40 | G1 — automated assurance and measurable performance | INFERRED | Add verbatim R-40→G1 statement to the saved PRD. | 6 |
| R-41 | G3 — measurable operations | INFERRED | Add verbatim R-41→G3 statement to the saved PRD. | 1 |
| R-42 | G6 — usable admin console | INFERRED | Add verbatim R-42→G6 statement to the saved PRD. | 5 |
| R-43 | G6 — usable admin console | INFERRED | Add verbatim R-43→G6 statement to the saved PRD. | 5 |
| R-44 | G6 — usable admin console | INFERRED | Add verbatim R-44→G6 statement to the saved PRD. | 5 |
| R-45 | G6 — usable admin console | INFERRED | Add verbatim R-45→G6 statement to the saved PRD. | 5 |
| R-46 | G6 — usable admin console | INFERRED | Add verbatim R-46→G6 statement to the saved PRD. | 5 |
| R-47 | G6 — usable admin console | INFERRED | Add verbatim R-47→G6 statement to the saved PRD. | 5 |
| R-48 | G7 — consistent identity and language | INFERRED | Add verbatim R-48→G7 statement to the saved PRD. | 4 |
| R-49 | G7 — consistent identity and language | INFERRED | Add verbatim R-49→G7 statement to the saved PRD. | 4 |
| R-50 | G7 — consistent identity and language | INFERRED | Add verbatim R-50→G7 statement to the saved PRD. | 4 |
| R-51 | G7 — consistent identity and language | INFERRED | Add verbatim R-51→G7 statement to the saved PRD. | 4 |
| R-52 | G7 — consistent identity and language | INFERRED | Add verbatim R-52→G7 statement to the saved PRD. | 4 |
| R-53 | G7 — consistent identity and language | INFERRED | Add verbatim R-53→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #052, tes `CommercialClaimIntegrityTest`. | 4 |
| R-54 | G7 — consistent identity and language | INFERRED | Add verbatim R-54→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #053, tes `GuidingMicrocopyTest`. | 4 |
| R-55 | G7 — consistent identity and language | INFERRED | Add verbatim R-55→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #054, guard `tests/brand-tokens.test.mjs`, doc `BRAND_TOKEN_LOCK.md`. | 4 |
| R-56 | G5 — identity and access protection | INFERRED | Add verbatim R-56→G5 statement to the saved PRD. | 3 |
| R-57 | G5 — identity and access protection | INFERRED | Add verbatim R-57→G5 statement to the saved PRD. | 3 |
| R-58 | G5 — identity and access protection | INFERRED | Add verbatim R-58→G5 statement to the saved PRD. | 3 |
| R-59 | G6 — usable, accessible authentication flow | INFERRED | Add verbatim R-59→G6 statement to the saved PRD. | 3 |
| R-60 | G5 — identity and session protection | INFERRED | Add verbatim R-60→G5 statement to the saved PRD. | 3 |

### Unmapped requirements (arsip)

**None.** R-01 through R-60 each have one conceptual goal mapping and a row. However, **all 60 goal mappings remain unconfirmed inferred mappings** because the saved PRD does not contain a complete verbatim R→G matrix.

The saved PRD does not state a wave or phase for **R-08 and R-09**. Both need explicit phase decisions before the roadmap can claim complete phase coverage. R-33 has an ambiguous phase placement (phase 1 and the phase-6 range).
