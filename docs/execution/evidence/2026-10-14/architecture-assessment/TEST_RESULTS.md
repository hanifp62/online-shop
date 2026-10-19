# ZAFIRA — Hasil Verifikasi Audit

Tanggal snapshot: 14 Oktober 2026. Semua hasil di bawah membedakan OBSERVED saat audit ini dari REPORTED historis.

## Status eksekusi

| ID | Area | Command/metode | RC | Status | Bukti |
|---|---|---|---:|---|---|
| T-001 | toolchain | `php -v`, `node -v`, `npm -v`, `composer --version --no-ansi` | 0 | OBSERVED | `evidence/stack-isolation.json` |
| T-002 | source baseline | hash 211 file dari `_srcset.txt` | 0 | OBSERVED | `evidence/source-hashes-before.txt`; after comparison below |
| T-003 | manual DB | SQLite URI `mode=ro`, `PRAGMA query_only=ON`, count/schema | 0 | OBSERVED | `evidence/stack-isolation.json` |
| T-004 | isolation gate | inspect `.env.manual` allowlist and sink overrides | 0 | OBSERVED | `evidence/stack-isolation.json` |
| T-005 | PHP/Laravel bootstrap | Amadis guard-test; migrate/seed/provision disposable | 0 | OBSERVED / PASS | `evidence/runtime-20261014/RUNTIME_REPORT.md`; native `--env=manual` NOT RUN: sink shared |
| T-006 | HTTP/browser/PDF | lifecycle 8043; PDF 8044 | 0 / 0 | OBSERVED / PASS terbatas | `evidence/runtime-20261014/lifecycle-result.json`, `pdf-result.json`; insiden awal 8016 tetap FAIL |
| T-007 | automated tests | `./scripts/amadis/launch.sh test` | 0 | OBSERVED / PASS | 158 passed, 1253 assertions; `evidence/runtime-20261014/phpunit-isolated.txt` |
| T-008 | concurrency | acceptance SQLite 8042 | 1 | OBSERVED SQLite / target NOT RUN | HTTP 201/422/500; database is locked; invariant tetap 1; bukan bukti engine target |
| T-009 | mobile/desktop/WCAG | mobile-check + console-check; keyboard acceptance | 0 / 0 | OBSERVED / PARTIAL | HTTP 200, no overflow; keyboard PASS; native zoom dan WCAG penuh NOT RUN |
| T-010 | restart proof | lifecycle S5 | 0 | OBSERVED / PASS | `evidence/runtime-20261014/lifecycle-S5-ui.json` |
| T-011 | public benchmark fetch + relevance review | `python3 evidence/benchmark_collect.py` + manual review stored excerpts | 0 | PARTIAL | 4 OBSERVED / 6 UNKNOWN after reclassifying Walmart soft-404 and Coupang corporate page; Rakuten contact-routing only; comparative score NOT RUN; `evidence/public-benchmarks.json` |
| T-012 | source integrity | compare after hash terhadap before | 0 | OBSERVED | `evidence/source-hashes-after.txt` dan `evidence/source-hash-comparison.txt` |

## Read-only DB discrepancy

`storage/app/manual-test/database.sqlite` dibaca langsung tanpa bootstrap. Snapshot saat audit berisi 24 migration, 0 products, 0 categories, 0 orders, dan 2 session rows pada pembacaan pertama; pembacaan berikutnya: 6 session rows, mtime 08:49:42 WIB; sebab selisih UNKNOWN. Artefak historis `manual-artisan/VERIFIKASI.md` melaporkan 12 produk dan order cancelled #1. Kedua fakta tidak boleh digabung: data historis tidak diperlakukan sebagai keadaan saat ini.

## Historical evidence only

- `docs/execution/evidence/2026-10-14/manual-artisan/VERIFIKASI.md`: REPORTED, bukan PASS audit baru.
- `docs/execution/evidence/2026-10-14/f-findings-retest/REPORT.md`: REPORTED, bukan PASS audit baru.
- Historical test claim of 158 tests / 1253 assertions remains REPORTED; this run independently OBSERVED the same count under Amadis isolation.
- Historical MariaDB ERROR 1558 and seven pending migrations: REPORTED only.
- `mac-verification.json`: REPORTED 226/229 hash mismatches and two false pins.
- Direct markdown mail render: NOT RUN.

## Isolation decision

Manual profile has an isolated SQLite file, but no `LARAVEL_STORAGE_PATH`. Sessions/cache/queue use database, while storage, compiled views, and logs remain repository-shared. Native `artisan serve --env=manual` was NOT RUN. Subsequently the existing Amadis runner provided disposable SQLite and isolated sinks for all new Laravel HTTP/tests on 8042–8046. Coordinator's earlier two GETs on existing 8016 before sink proof remain a safety FAIL; neither existing server was stopped. See `evidence/runtime-20261014/RUNTIME_REPORT.md`.

## Safety incident

Prior full `.env.manual` print to tool output was REPORTED by the handoff, not independently verified from original output in this pass. No values are repeated. File `.env` mode `0666` is OBSERVED; consult owner and actual access logs before deciding rotation. No rotation or permission mutation occurred. Coordinator separately REPORTED GET `/koleksi` and `/` on existing 8016, HTTP 200 before full isolation; no browser test PASS. Direct read-only DB snapshots showed sessions 2, later 6, but attribution of changes is UNKNOWN.

## Verification conclusion

This remains an INCOMPLETE full assessment. Amadis runner tests, dummy-data lifecycle/PDF, viewport smoke checks and part of keyboard interaction were observed under isolated disposable SQLite. Native manual runtime, target-engine concurrency, full WCAG 2.2 AA, migration rehearsal on target engine, production readiness and payment settlement remain unproven.

## Addendum sumber profil aman dan integritas paket

`evidence/runtime-profile-addendum.json` mencatat allowlist key konfigurasi `.env` dan `.env.manual`, mode berkas, driver/sink yang aman disebut, dan proses listening 8015/8016 tanpa menampilkan password/key/DB name. `.env` saat dibaca menyebut MySQL; target operational sebenarnya UNKNOWN karena tidak ada koneksi/bootstrapping. `evidence/stack-isolation.json` membaca allowlist `.env.manual` juga; pernyataan terdahulu “tidak dibaca” keliru. Perbandingan SHA 211 source hanya berlaku pada manifest `_srcset.txt` di waktu pembacaan, bukan bukti seluruh repo tak berubah.
