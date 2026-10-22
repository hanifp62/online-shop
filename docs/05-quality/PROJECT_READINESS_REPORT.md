# Laporan Kesiapan Proyek (Project Readiness Report)

**Proyek:** ZAFIRA (La Maison de Lumière — `online-shop`)  
**Workspace:** `~/Documents/online-shop`  
**Status kesiapan saat ini — 22 Oktober 2026:** **CI Linux sampai kandidat artefak LULUS; BELUM DISETUJUI untuk produksi.** [Laporan publikasi](CI_CD_PUBLICATION_REPORT_2026-10-22.md) mengikat commit `baee799` dan run39766255281: PHP1.200pass+3skip engine exact, ketiganya3/3pass pada MySQL8.4.13; Node485; Python371/0skip; browser/cleanup, restore fixture, build/verify kandidat dan aggregate semuanya PASS. Kandidat juga diverifikasi offline sesudah unduh. Protection `main` belum aktif; staging, promosi, secret manager/operator, daemon/alert, recovery operasional, TPS/load dan a11y menyeluruh tetap terbuka.

> **Supersession terbatas:** bagian R7/Final2, tabel gate, dan urutan handoff di bawah dipertahankan sebagai riwayat 21 Oktober. Pernyataan «cleanup terblokir», «browser belum direrun» dan «restore MySQL belum diuji» bukan status CI terbaru. Bukti baru adalah fixture Linux/SQLite/MySQL dan kandidat, bukan semua alur HCI/admin atau pemulihan produksi; status otoritatif ada pada laporan publikasi.

**Revisi:** 2026-10-21 — REM-20261021 DOC / DOC-04, Final2 berbasis bukti integrator r7. Arsip dasar dan Evaluation Record sebelum r7 dipertahankan dengan label historis.

## 1. Keputusan berbasis gate, bukan persentase

[Audit AUD-20261021](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md) adalah baseline temuan; [kontrak REM](../04-implementation/REMEDIATION_REAL_WORLD_2026-10-21.md) menetapkan 7 FR dan 6 NFR remediasi. Baseline commit yang dicatat REM: `041ed083a1be330d97d4f335cda8555670045cdd`, mulai `2026-10-21T11:53:56Z`. Revisi ini membaca working tree pascaimplementasi, bukan memverifikasi runtime commit dasar atau membuat SHA rilis baru.

**Bukti integrator r7 di bawah menggantikan status `NOT RUN/PENDING` lane DOC sebelumnya dalam cakupan yang telah diuji/direview.** Lane DOC membaca metadata dan log, tidak menjalankan ulang suite. [Crosswalk aktif](../04-implementation/REQUIREMENT_TRACEABILITY.md) kini memisahkan hasil per requirement, approval source, skip, dan gate tersisa. Tidak ada klaim semua audit tertutup, seluruh acceptance lulus, WCAG selesai, provider siap, uptime/throughput terukur, izin produksi, atau plan server selesai.

### Bukti integrator r7

**Supersession terbaru sesudah streaming export:** PHP penuh **1.154 passed/3 skipped/156.806 assertions, EXIT=0**; [metadata](evidence-remediation-2026-10-21/php-full-export-final.json), [log](evidence-remediation-2026-10-21/php-full-export-final.log), [manifest132 file](evidence-remediation-2026-10-21/source-manifest-export-final.json). Node465 lulus lagi (`node-full-export-final.*`), Pint377 (`pint-full-export.*`). Source export dan fixture direview DATA independen; target throughput/RSS/latensi tetap belum diukur. Seluruh angka R7/1150 di bawah adalah riwayat, bukan hasil terbaru.

**Riwayat integrator sebelum slice export:** tambahan inquiry sudah masuk rerun **PHP penuh 1.150 passed/3 skipped/29.864 assertions, EXIT=0**; [metadata final](evidence-remediation-2026-10-21/php-full-final.json), [log](evidence-remediation-2026-10-21/php-full-final.log), [manifest source](evidence-remediation-2026-10-21/source-manifest-final.json). Angka P7 di bawah tetap riwayat run sebelumnya, bukan angka final yang ditimpa. Snapshot ini kemudian diperbarui oleh slice export yang dibuktikan di paragraf supersession terbaru; gate staging tidak berubah.

Seluruh waktu selesai berikut UTC pada 21 Oktober 2026; metadata memuat command, waktu mulai/selesai dan exit. Artefak berada di `docs/05-quality/evidence-remediation-2026-10-21/`. Ini hasil integrator, bukan eksekusi lane DOC; cakupannya snapshot run terkait, bukan otomatis perubahan sesudahnya.

| Kode / gate | Hasil yang didukung | Bukti / selesai UTC | Batas |
|---|---|---|---|
| **P7 — PHP penuh** | **1.150 passed, 3 skipped, 29.856 assertions; EXIT=0** | [Metadata](evidence-remediation-2026-10-21/php-full-r7.json), [log](evidence-remediation-2026-10-21/php-full-r7.log); 15:51:24 | Tiga skip InnoDB tidak dihitung lulus; bukan bukti engine parity/produksi |
| **H7 — ordered host** | **48 passed, 358 assertions; EXIT=0** | [Metadata](evidence-remediation-2026-10-21/php-host-order-green.json), [log](evidence-remediation-2026-10-21/php-host-order-green.log); 15:47:06 | Urutan LocaleKernelRegression/SecurityRemediation/Task3Remediation/TrustProxy; tidak dijumlahkan ke total PHP sebagai coverage unik |
| **PY7 — Python penuh** | **165 run, 6 skipped = 159 executed; EXIT=0** | [Metadata](evidence-remediation-2026-10-21/python-full-r7.json), [log](evidence-remediation-2026-10-21/python-full-r7.log); 15:51:30 | Enam skip karena FontTools tidak terpasang; bukan 165 tes dieksekusi/lulus |
| **N7 — Node penuh** | **465 passed, 0 failed, 0 skipped; EXIT=0** | [Metadata](evidence-remediation-2026-10-21/node-full-r7.json), [log](evidence-remediation-2026-10-21/node-full-r7.log); 15:51:31 | Unit/kontrak/fake DOM bukan pengganti browser nyata |
| **L7 — Pint** | **376 file pass; EXIT=0** | [Metadata](evidence-remediation-2026-10-21/pint-full-r7.json), [log](evidence-remediation-2026-10-21/pint-full-r7.log); 15:46:41 | Format source, bukan tes fungsional |
| **B3 — build r3** | **EXIT=0** | [Metadata](evidence-remediation-2026-10-21/build-r3.json), [log](evidence-remediation-2026-10-21/build-r3.log); 15:14:46 | Source frontend tidak berubah setelah build menurut handoff main; tidak diklaim sebagai build r7 atau verifikasi browser |
| **Observer native Darwin** | `NATIVE_EXIT_OBSERVER_OK`; observed exit dan child exit 0 | [Artefak](evidence-remediation-2026-10-21/native-observer-green.json); 15:52:29 | Membuktikan observer `waitid` Darwin saja; tanpa ps, signal, browser/server/DB. **Bukan cleanup lulus** |
| **Cleanup native nyata** | **BLOCKED** oleh izin ps/signal; elevasi tidak tersedia pada tool | Handoff final main; [probe awal gagal](evidence-remediation-2026-10-21/native-supervisor-probe.json) tetap historis | Tidak menyebut observer green sebagai keberhasilan terminasi/drain process group |
| **Browser** | **Dua kegagalan sebelumnya belum direrun** | Riwayat [audit](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md) dan handoff main | Tidak ada PASS browser baru; bukan bukti semua skenario UI gagal ataupun lulus |
| **Negatif inquiry tambahan** | **12 passed/51 assertions; EXIT=0**, Pint satu file EXIT=0 | [Metadata](evidence-remediation-2026-10-21/php-inquiry-final.json), [log](evidence-remediation-2026-10-21/php-inquiry-final.log) | Pengujian sesudah R7; reviewer DATA `a3568e6b-70f7-48d9-8e67-99595380745b` APPROVE source. Cart/stok tetap, tidak membuat order/item/reservasi/payment |

### Review source independen

**Keputusan berikut dilaporkan main dalam mandat Final2; ID sesi lengkap cocok dengan identitas pada ledger.** Snapshot R1/R2/R3 yang masih meminta rework/pending adalah historis dan disupersesi oleh handoff ini. APPROVE terbatas pada source/kontrak yang direview, **bukan approval produksi** atau persetujuan otomatis terhadap perubahan tes maupun pembaruan status Final2 setelah review.

| Scope | Reviewer session | Keputusan |
|---|---|---|
| SEC | `4a87e550-206a-4389-8929-ba10cafaffc7` | APPROVE scoped source |
| TXN | `88fc481b-b7cd-4d36-a1e2-d41c567d49ca` | APPROVE scoped source |
| DATA/BACKUP | `a3568e6b-70f7-48d9-8e67-99595380745b` | APPROVE scoped source |
| UI/ADMIN-UI | `0ad08d07-3e0b-4d70-a6fd-35f1bd4b8225` | APPROVE scoped source |
| OPS | `c7bc6b8f-e74e-494d-a99c-4bd79edfad05` | APPROVE scoped source |
| MSI/TPS | `baba615a-5f4d-4965-82f6-70194d773b5d` | APPROVE scoped source/kontrak |
| DOC-01–05 | `a3568e6b-70f7-48d9-8e67-99595380745b` | APPROVE rekonsiliasi dokumentasi sebelumnya; bukan bukti semua gate rilis tertutup |

[Laporan remediasi rinci](REMEDIATION_REPORT_2026-10-21.md) telah diterbitkan integrator; menjadi rujukan hasil final, dampak migrasi, analisis enam dimensi dan gate uji lapangan. Status tujuan publikasi pada handoff Final2 telah disupersesi.

### Gate kesiapan yang masih dibedakan

| Gate | Bukti source / kontrak yang tersedia | Status dan syarat sebelum acceptance |
|---|---|---|
| Produk dan dokumen DOC-01–05 | PRD owner-only; cart/order/inquiry terpisah; skema/crosswalk | APPROVE scoped DOC; Final2 hanya memperbarui dua dokumen status. Targeted assertion inquiry tambahan lulus dan direview terpisah; bukan penutupan seluruh audit |
| Keamanan, transaksi, integritas | Regresi terkait dalam P7 dan ordered host H7; review SEC/TXN/DATA | Lulus pada snapshot run terkait; tambahan inquiry sesudah R7 lulus terarah; tiga skip InnoDB, race engine target dan ingress produksi tetap belum terbukti |
| Migrasi dan engine parity | [Desain database](../03-architecture/DATABASE_DESIGN.md), regresi P7 dan review DATA/TXN | Regresi nominal/provenance lulus; penerapan migrasi produksi dan parity/race InnoDB belum terbukti. Tiga skip bukan PASS; up/down dengan writers quiesced perlu bukti engine target |
| Operasi, CI, worker/scheduler | P7/N7/PY7/L7, review OPS; observer native Darwin | Mock/kontrak lulus, observer saja green; cleanup nyata BLOCKED izin ps/signal, elevasi tidak tersedia. Remote protection, worker/scheduler dan delivery nyata tetap gate staging |
| Backup/restore dan availability | Regresi backup P7 dan review DATA/BACKUP APPROVE | **PARTIAL:** hash/gzip bukan restore. MySQL/MariaDB restore belum tertutup; drill disposable engine target, skema/data/constraint, transport dan RPO/RTO tetap wajib. Lane DOC tidak mengulang bukti SQLite |
| Provider, ingress dan layanan bisnis | Endpoint pembayaran/shipment/retur/refund tersedia | Tidak ada charge/refund nyata atau persetujuan provider/deploy diperiksa. UI/API/prototype tidak membuktikan layanan end-to-end; fitur deferred bukan otomatis bug |
| HCI, aksesibilitas, visual | N7 lulus, B3 EXIT=0 tanpa perubahan frontend setelahnya, review UI APPROVE | **PARTIAL:** dua browser failure belum direrun. Keyboard/viewport/kontras/screen reader dan WCAG 2.2 AA penuh belum terbukti; fake DOM/build bukan browser acceptance |
| Performa, TPS dan MSI | [TPS real-world](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md), regresi `AdminOperationsSummaryTest` dalam P7, review MSI APPROVE | **PARTIAL:** kontrak metrik minimum lulus; best-effort/partial bukan seluruh MSI. Throughput/storage dan latensi staging belum diukur; target 50/100/150ms tetap perlu definisi ukur bersama |
| Review dan keputusan rilis | [Model operasi](../04-implementation/AGENT_OPERATING_MODEL.md), [protokol](../04-implementation/ENGINEERING_EXCELLENCE_PROTOCOL.md), ID reviewer dan artefak di atas | Review scoped source APPROVE; approval produksi belum ada. Lengkapi hubungan snapshot/diff akhir, targeted setelah perubahan, gate staging dan persetujuan rilis; jangan mewariskan approval ke seluruh backlog |

### Urutan handoff, bukan eksekusi backlog

1. **Selesai:** integrator mengisi hasil targeted assertion negatif `StorefrontTest` terbaru dari run tersendiri, bukan menyalin PASS P7. PRD/AC/desain DB yang sudah direview tidak diedit pada Final2.
2. **Laporan terbit:** bukti snapshot/diff dan keputusan final dirujuk pada laporan rinci. Pembaruan status tidak menggantikan gate staging atau memberikan persetujuan rilis.
3. Selesaikan kendala izin cleanup nyata pada lingkungan yang diotorisasi, lalu tentukan rerun browser berbasis diagnosis; jangan menganggap elevasi tersedia atau observer pass menutup gate cleanup. Pemilik rilis tetap perlu gate staging engine/restore, provider/ingress, worker/scheduler, performa dan aksesibilitas.
4. Fitur deferred/UI after-sales dan validasi bisnis ditangani terpisah, bukan otomatis bug. AOV, margin, kapasitas atelier, asal/keaslian karya atau kepatuhan hukum tetap memerlukan sumber, periode dan pemilik data.

### Catatan evaluasi Final2 (riwayat sebelum tambahan inquiry diverifikasi)

- **Scope:** hanya `docs/04-implementation/REQUIREMENT_TRACEABILITY.md` dan `docs/05-quality/PROJECT_READINESS_REPORT.md`; tidak ada file baru atau perubahan PRD/AC/desain DB, aplikasi/tes, ledger, indeks, TPS, REM maupun audit baseline.
- **Metode/bukti:** `documentation-and-adrs` tetap dipakai; metadata enam command dan artefak observer dibaca, ringkasan log r7/ordered host/Python/Node/Pint dicocokkan baca-saja. Review APPROVE dan frontend tidak berubah setelah B3 berasal dari handoff main, tidak diklaim sebagai review/eksekusi lane DOC. Tidak menjalankan tes/aplikasi.
- **Perubahan:** mengganti pending blanket dengan hasil per requirement dan reviewer scoped; mempertahankan skip, kegagalan browser, cleanup BLOCKED, targeted inquiry baru PENDING dan gate staging.
- **Batas keputusan:** r7 bukan penutupan seluruh audit/approval produksi. Laporan rinci masih milik main; tidak ada perubahan status plan server. Pemeriksaan statis Final2 dicatat pada handoff sesi, terpisah dari hasil suite integrator.

### Arsip Continuous Evaluation Record — slice sebelum r7

**Record berikut merekam posisi lane DOC sebelum hasil integrator r7 dan approval source dilaporkan. Status review/runtime tertunda di dalamnya telah disupersesi oleh bagian aktif di atas; command dan hasil historisnya tidak diubah.**

- **Task Ref / Fitur:** REM-20261021 DOC; DOC-01 P1, DOC-02–05 P2; terkait AC-REM-OWN/CART/ORDER/INQUIRY dan crosswalk seluruh FR/NFR REM.
- **Acceptance Criteria per Gate:** hanya lima dokumen yang diizinkan berubah; email-match tidak aktif; domain dipisah; migrasi/provenance akurat; sejarah tetap berlabel; tidak ada klaim GREEN atau plan selesai; tautan/ID crosswalk dapat ditelusuri.
- **Baseline / hipotesis:** lima file belum dimodifikasi saat status Git scoped diperiksa; berisi kontrak email-match, checkout concierge ambigu, skema order “masa depan”, skor 100%/49 tes dan mapping R inferred. Penyebab drift: snapshot historis dibaca sebagai current-state.
- **Commit / Diff Awal–Akhir:** acuan commit REM di atas; tidak membuat commit. Diff hanya dokumentasi dalam scope: `docs/02-product/PRD.md`, `docs/04-implementation/ACCEPTANCE_CRITERIA.md`, `docs/03-architecture/DATABASE_DESIGN.md`, `docs/04-implementation/REQUIREMENT_TRACEABILITY.md`, `docs/05-quality/PROJECT_READINESS_REPORT.md`.
- **Controlled Change:** rekonsiliasi kontrak aktif dan label supersession; tidak menghapus arsip atau menyunting source, tes, audit baseline, REM, TPS, indeks, maupun shared ledger.
- **Verification Commands & Exit Status:** status awal `git --no-pager --no-optional-locks status --short --` pada lima path di atas → EXIT=0, output kosong (2026-10-21). `git --no-pager diff --check --` pada scope sama awalnya EXIT=2 (enam trailing whitespace baru), dirapikan lalu diulang **2026-10-21T14:48:10Z → EXIT=0**. Diff stat saat ricek: 5 file, 269 insertions, 39 deletions. `python3 -c` pemeriksa dokumentasi baca-saja pada waktu yang sama → **EXIT=0**: 30 tautan lokal, 92 referensi path source, 13 baris REM beserta AC/status pending; checklist, ERD/SQL lama, 60 baris R dan scorecard historis tetap terpelihara. Command lengkap ada di transkrip; ini verifikasi statis, bukan uji aplikasi.
- **Manual Review Evidence:** ricek penulis 2026-10-21 atas route/controller/migrasi/tes dan lima dokumen; kriteria: klaim tepat domain dan bukti, tidak mewarisi PASS lama. Ini bukan crosscheck independen; sesi author tidak diekspos tool.
- **Skill / lensa:** `documentation-and-adrs` dipakai untuk alasan keputusan, supersession dan batas bukti. Hudson: petunjuk tidak menyesatkan; Purbo: Bahasa Indonesia jelas; Berners-Lee: tautan relatif; Evans: inquiry/order berbeda. Ini lensa kerja, bukan endorsement/riset primer baru. Skill implementasi/runtime tidak dipakai karena mandat docs-only; review independen tetap tugas reviewer lain. Catatan ditempatkan di sini karena shared ledger tidak boleh diedit.
- **Artifact / Log Path:** diff lima dokumen dan transkrip sesi; tidak membuat artefak/file tambahan.
- **Comparison to Baseline:** kontrak aktif dan gate kini terpisah dari arsip; kode/tes hanya dipakai sebagai evidence source, bukan hasil eksekusi.
- **Regressions Checked:** tidak ada suite aplikasi dijalankan; PHP/DB/browser/server/network dan NgodingPakeAI tidak dijalankan.
- **Reviewer Verification / Approver:** identitas dan approval independen belum tersedia; final read-review menunggu main. Ricek author tidak memenuhi syarat reviewer berbeda.
- **Decision:** **BLOCKED untuk persetujuan rilis** sampai bukti runtime relevan dan review independen tersedia. Koreksi dokumen diserahkan untuk review independen; bukan klaim task server selesai.

### Tidak diperiksa dan risiko tersisa saat Final2

Lane DOC tidak membaca `.env*`/secret, DB hidup, status migrasi terpasang, konfigurasi produksi, remote protection, plan server atau sumber bisnis eksternal; tidak menjalankan tes/build/aplikasi/browser/network/restore. Ini batas tindakan lane DOC, **bukan** klaim tidak ada eksekusi integrator: hasil integrator tercantum di atas. Cleanup nyata tetap BLOCKED izin ps/signal tanpa elevasi tersedia; dua browser failure belum direrun; tiga skip InnoDB dan enam skip FontTools tidak diubah menjadi PASS. Targeted inquiry baru tetap PENDING sampai main mengisi hasil. Performance/availability/WCAG serta gate provider/deploy/restore belum diterima. Dokumen lain dapat memuat snapshot basi (termasuk status author/pending TPS); DOC-06 di luar mandat. Tidak ada klaim seluruh audit tertutup atau penyelesaian plan server.

---

## 2. Arsip kartu skor 10 Oktober — tidak berlaku sebagai kesiapan saat ini

**Label lama “SIAP UNTUK TAHAP IMPLEMENTASI FITUR PRODUKSI”, semua 100%, 49 tes/168 assertions dan status Ready di bawah adalah klaim snapshot historis, bukan hasil verifikasi 21 Oktober atau izin produksi.** Checklist deliverable hanya merekam keberadaan yang diklaim pada saat itu. Petunjuk lanjutan di arsip tidak mengotorisasi eksekusi backlog pada mandat DOC ini.

| Aspek Penilaian | Skor Kesiapan | Status | Catatan Evaluasi |
|---|---|---|---|
| **Fondasi Teknis macOS** | **100%** | Ready | PHP 8.5.11, Composer 2.10, Node 24, Vite 8, MariaDB 10.4 terstabilisasi |
| **Audit & Konteks Sistem** | **100%** | Ready | 5 laporan audit lengkap berbasis bukti kode nyata |
| **Definisi Produk & PRD** | **100%** | Ready | PRD formal, 14 FR, 5 NFR, 8 US, User Journeys & Traceability Matrix lengkap |
| **Arsitektur & Database** | **100%** | Ready | Arsitektur target, ERD terpadu, data dictionary, dan 5 ADR disepakati |
| **Interactive Prototype** | **100%** | Ready | Source code nyata di `prototype/`, aktif di `http://127.0.0.1:8000/prototype` |
| **Test Suite Otomatis** | **100%** | Ready | 49 test passing (168 assertions), Pint pass, zero build errors |
| **Tata Kelola Dokumentasi** | **100%** | Ready | 20 dokumen terstruktur rapi di `docs/` dengan indeks di `docs/README.md` |

---

## 3. Arsip matriks deliverable — bukan status terkini

```
docs/
├── README.md                                  [✓ SELESAI]
├── 00-project/
│   ├── ENVIRONMENT_BASELINE.md               [✓ SELESAI]
│   └── PROJECT_CONTEXT.md                    [✓ SELESAI]
├── 01-audit/
│   ├── PROJECT_INSPECTION.md                 [✓ SELESAI]
│   ├── FEATURE_INVENTORY.md                  [✓ SELESAI]
│   ├── INTEGRATION_AUDIT.md                  [✓ SELESAI]
│   ├── UI_UX_AUDIT.md                        [✓ SELESAI]
│   ├── SECURITY_AND_QUALITY_AUDIT.md         [✓ SELESAI]
│   └── COMPATIBILITY_REPORT.md               [✓ SELESAI]
├── 02-product/
│   ├── PRODUCT_BRIEF.md                      [✓ SELESAI]
│   ├── RESEARCH_LOG.md                       [✓ SELESAI]
│   ├── PRD.md                                [✓ SELESAI]
│   ├── USER_JOURNEYS.md                      [✓ SELESAI]
│   └── REQUIREMENTS_TRACEABILITY.md          [✓ SELESAI]
├── 03-architecture/
│   ├── TARGET_ARCHITECTURE.md                [✓ SELESAI]
│   ├── DATABASE_DESIGN.md                    [✓ SELESAI]
│   ├── ARCHITECTURE_DECISIONS.md             [✓ SELESAI]
│   └── ENVIRONMENT_AND_RUNTIME.md            [✓ SELESAI]
├── 04-implementation/
│   ├── IMPLEMENTATION_PLAN.md                [✓ SELESAI]
│   ├── TASKS.md                              [✓ SELESAI]
│   └── ACCEPTANCE_CRITERIA.md                [✓ SELESAI]
├── 05-quality/
│   ├── TEST_PLAN.md                          [✓ SELESAI]
│   ├── QUALITY_REVIEW.md                     [✓ SELESAI]
│   ├── PROJECT_READINESS_REPORT.md           [✓ SELESAI]
│   └── FOUNDATION_CHANGELOG.md               [✓ SELESAI]
└── 06-prototype/
    ├── PROTOTYPE_GUIDE.md                    [✓ SELESAI]
    ├── SCREEN_AND_FLOW_MAPPING.md            [✓ SELESAI]
    └── PROTOTYPE_EVALUATION.md               [✓ SELESAI]
```

---

## 4. Arsip petunjuk serah terima — digantikan gate bagian 1

Jika pengembang atau AI agent lain melanjutkan pekerjaan pada repository ini:

1. **Jadikan Repository sebagai Single Source of Truth:**
   - Baca file persisten `AGENTS.md` di root untuk memahami aturan operasional.
   - Buka `docs/README.md` untuk menjelajahi spesifikasi teknis dan PRD.
2. **Jangan Mengulang Proses Discovery dari Nol:**
   - Seluruh audit inventaris fitur, skema database, dan rute telah terdokumentasi lengkap di folder `docs/01-audit/` dan `docs/02-product/`.
3. **Menjalankan dan Mengembangkan Fitur Produksi:**
   - Gunakan `prototype/` sebagai acuan hierarki visual dan pengalaman interaksi yang diinginkan.
   - Ikuti urutan task pada `docs/04-implementation/TASKS.md`.
   - Jalankan `php artisan test` secara rutin untuk memastikan tidak ada regresi pada 49 test yang sudah ada.
