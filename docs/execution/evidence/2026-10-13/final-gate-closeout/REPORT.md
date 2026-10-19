# Final gate closeout — 2026-10-13

## Verdict

**PASS for the scoped final gate**, with no source edits. All requested commands returned rc 0. Port 8016 was free before and after the browser run. The guarded S0–S5 lifecycle passed. Source, dependencies, and both preserved fixtures matched their before-run hashes.

Scope means this run's requested checks only. It does not promote the separate acceptance matrix or export audit to PASS.

## Runtime and safety

- Fresh isolated fixture: see `root.txt`; created, migrated, seeded, provisioned only through `scripts/amadis/launch.sh`.
- PDF fixture `amadis-d183833c94dbbd1638368827` and prior final-gate fixture `amadis-4ce30337e17a9a7fa8cfcb4e` preserved; identities and SQLite database hashes match before/after.
- No `.env`, operational database, raw `php -r`, raw server, prohibited port, dependency install/update, source edit, or commit.
- Existing 8015 listener was observed during preflight, left untouched; lifecycle used 8016.
- Process groups created by the lifecycle (PIDs in `s0s5-linkage.json`) are absent after stop. Browser evidence manifest validates.
- `integrity.json`: source unchanged, dependencies unchanged, preserved fixtures unchanged.

## Gates

Every recorded command has `.cmd`, `.stdout`, `.stderr`, `.rc`, and timing/argv metadata in its `.json`; `commands.json` is the index. Timestamps are UTC.

| Gate | Result | Counts/details |
|---|---|---|
| Setup: init, migrate, seed, provision | PASS, rc 0 each | New isolated SQLite fixture |
| PHP standard | PASS, rc 0 | 158 passed, 0 skipped, 0 failed/errors; 1,253 assertions |
| PHP random, seed 20261013 | PASS, rc 0 | 158 passed, 0 skipped, 0 failed/errors; 1,252 assertions |
| Node | PASS, rc 0 | 86 passed; 0 skipped/failed |
| Python using approved FontTools venv | PASS, rc 0 | 133 tests; 0 skipped/failed. FontTools 4.66.1 verified. One `ResourceWarning` appears in stderr; suite is green. |
| Pint, all eight requested PHP files | PASS, rc 0 | `vendor/bin/pint --test` reported passed |
| Build | PASS, rc 0 | `npm run build`; Vite build succeeded |
| Diff whitespace | PASS, rc 0 | `git diff --check` |
| Port 8016 before/after | PASS, rc 1 each | `lsof` found no listener; rc 1 is expected for no matches |
| Browser lifecycle | PASS, rc 0 | S0–S5 all PASS, CLI 0.1.22, browser 149.0.7827.55 |

Exact argv, runtime, stdout, stderr, and exit codes are in the command evidence. Harness code is `execute-gate.py`; its hash and launcher/lifecycle hashes are in `harness-hashes.json`.

## PHP assertion delta

JUnit trace is in `assertion-delta.json`; full XML is `php-test-standard.xml` and `php-test-random.xml`.

- Both runs contain the same 158 testcase names, all passing; suite totals differ by one assertion: 1,253 standard versus 1,252 random.
- Only differing testcase: `Tests.Feature.AdminOrderReportTest::test_pdf_service_embeds_unicode_font_and_sanitizes_html_and_unicode` (13 standard, 12 random).
- Cause is the test helper's process-local `static $binary` cache in `tests/Feature/AdminOrderReportTest.php::pdfText()`. Standard ordering first compiles the Swift PDF text extractor and executes its `assertSame(0, $rc, ...)` compile assertion inside this testcase. Random ordering invokes the helper earlier in another test; the cached binary exists when this testcase runs, so compile assertion is correctly not repeated. No assertion failed and no source/test edits were made.

## S0–S5 linkage

Full browser evidence stays at the isolated root referenced by `s0s5-linkage.json`, not copied into the repository. Its `sha256.json` validates every evidence file. `commands.json` and snapshots provide the chain:

- S0 stock 37, no order; initial catalog renders 37.
- S1 customer adds quantity 3 and uses own address; cart total 3,750,000.
- S2 pending order; stock 34; launcher stopped and restarted.
- S3 cancellation; stock restored to 37; catalog refresh renders 37.
- S4 cancellation replay remains cancelled; stock stays 37; launcher restarted.
- S5 refreshed customer/admin views show cancelled order; stock remains 37; final fresh catalog after restart renders 37.
- All five owned server process groups are confirmed absent after cleanup.

## Evidence index

- `commands.json`: ordered commands, argv, UTC start/finish, duration, rc.
- `<gate>.cmd/.stdout/.stderr/.rc/.json`: raw command evidence.
- `source-before.json`, `source-after.json`, `dependencies-before.json`, `dependencies-after.json`, `integrity.json`: source and dependency manifests.
- `harness-hashes.json`: launch/harness script and execution harness hashes.
- `preserved-before.json`, `preserved-after.json`: preserved fixture integrity.
- `assertion-delta.json`, PHP JUnit XML: per-test assertion trace.
- `s0s5-linkage.json`: browser result, validated artifact manifest, snapshots, and process cleanup.
- `root.txt`: fresh fixture location; fixture intentionally retained for auditability.

## Limits

This final gate does not rerun acceptance criteria outside requested scope, historical visual findings, export-sentinel or bundle allowlist audits. No failure required code change; therefore source remained untouched.
