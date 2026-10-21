# Indeks bukti ZAFIRA

Tanggal dibuat: 2026-10-12; diperbarui 2026-10-13. Bukti run 13 Oktober: `docs/execution/evidence/2026-10-13/acceptance-am-isolated/` (AM-01/02/06/10, SQLite terisolasi, rc 0) dan `docs/execution/evidence/2026-10-13/final-gate-closeout/` (gate penuh). Baris "NOT RUN" di bawah adalah snapshot 12 Oktober; fixture, AM-10, dan browser AM-02/06 kini superseded; test katalog/order lulus di gate closeout; audit HCI/WCAG menyeluruh tetap NOT RUN.

## Source dan dokumen demo

| Item | Path | Catatan |
|---|---|---|
| Fixture | `database/seeders/AmadisDemoSeeder.php` | Belum dieksekusi |
| Traceability | `docs/demo/ZAFIRA_AMADIS_CRITERIA_TRACEABILITY.md` | Pemetaan AM usulan |
| Matriks | `docs/demo/ZAFIRA_ACCEPTANCE_MATRIX.csv` | Status NOT RUN/BLOCKED |
| Laporan | `docs/demo/ZAFIRA_REAL_WORLD_TEST_REPORT.md` | Tanpa hasil baru |
| Runbook | `docs/demo/ZAFIRA_DEMO_RUNBOOK.md` | Integrasi launcher/seeder |
| Isi PPT | `docs/demo/ZAFIRA_PRESENTATION_CONTENT.md` | Sumber slide |
| Prompt PPT | `docs/demo/ZAFIRA_PPT_GENERATION_PROMPT.md`, `docs/demo/ZAFIRA_PPT_GENERATION_PROMPT.txt` | Siap salin |

## Test dan schema

| Item | Path | Catatan |
|---|---|---|
| Test katalog | `tests/Feature/AmadisCatalogTest.php` | Source ada; hasil NOT RUN |
| Test order | `tests/Feature/AmadisOrderTest.php` | Source ada; hasil NOT RUN |
| Guard isolasi | `scripts/amadis/guard.php` | Source ada |
| Migration stok | `database/migrations/2026_10_12_000001_add_stock_and_active_to_products_table.php` | Tidak membuktikan schema diterapkan |
| Migration order | `database/migrations/2026_10_12_000003_create_orders_table.php` | FK user/voucher |
| Migration item | `database/migrations/2026_10_12_000004_create_order_items_table.php` | FK order/product |

## Bukti historis terkait

| Item | Path | Batas |
|---|---|---|
| Audit recheck | `docs/execution/evidence/2026-10-12/final-acceptance/AUDIT_RECHECK_CURRENT.md` | Node 73 lulus; blocker Order pra-perluasan |
| Visual inspection | `docs/execution/evidence/2026-10-12/final-acceptance/visual-inspection-final2.md` | Cakupan visual 23/56 |
| NFR-003 historis | `docs/02-product/REQUIREMENTS_TRACEABILITY.md` | FAIL 2026-10-11 |
| Handoff historis | `docs/execution/FINAL_HANDOFF_2026-10-11.md` | Bukan hasil AMADIS |
| Ledger | `docs/execution/EXECUTION_LEDGER.md` | Bukan hasil AMADIS |

## Placeholder run baru

| Bukti | Status | Path |
|---|---|---|
| Fixture run | NOT RUN | TBD |
| Test katalog/order | NOT RUN | TBD |
| AM-10 paralel | NOT RUN | TBD |
| Browser/HCI | NOT RUN | TBD |
