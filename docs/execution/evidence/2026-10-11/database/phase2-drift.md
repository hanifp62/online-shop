# Phase 2 — per-primary-key dump drift check

Date: 2026-10-11. Read-only comparison; no database writes, migrations, `.env` reads, or credential/hash/PII values disclosed.

## Source and method

- Dump: `database/zafira_ecommerce_20260921.sql`; SHA-256 `3daec2004e10c03acbb50a661e1602e9e166d8cb38281acd685a21dfe63177a1` (matched `dump-metadata.json`). 15 tables.
- Compared against live `ecommerce` and synthetic validation DB `zafira_restore_test_20261011`.
- Parsed dump column order and primary keys. Computed per-column SHA-256 fingerprints in a temp directory. Queried live values as hex under `START TRANSACTION READ ONLY`; emitted only counts and column names.
- Set session timezone to UTC for comparison because dump explicitly sets `TIME_ZONE='+00:00'`; server system timezone is WIB. This avoids false timestamp drift.
- Checked restore-test extra records using only synthetic IDs and email-domain predicate. No email addresses or row values printed.

## Commands, attempts, exit codes

All commands ran from repo root unless noted. MySQL executable: `/Applications/XAMPP/xamppfiles/bin/mysql -uroot -S /Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`.

| Attempt | Command/action | Exit | Result |
|---|---|---:|---|
| 1 | `find database -maxdepth 1 -type f -name '*20260921*.sql' -print` | 0 | Located full, clean, and chat dumps; full dump chosen per recorded restore validation. |
| 2 | `shasum -a 256 database/zafira_ecommerce_20260921.sql` | 0 | Hash matches recorded source. |
| 3 | Python inspection of INSERT table, approximate row counts, and tuple lengths | 0 | 8 INSERT statements across 15 declared tables. |
| 4 | `mysql ... -e "SELECT DATABASE(), @@version, @@read_only"` | 0 | MariaDB 10.4.28; no default DB selected; server global read-only flag off. All actual data queries explicitly used read-only transactions. |
| 5 | `mysql ... -e "SHOW DATABASES LIKE ... UNION SHOW DATABASES LIKE ..."` | 1 | Syntax error (1064); no data read or changed. |
| 6 | `mysql ... -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE ..."` | 0 | Both target schemas exist. |
| 7 | Temp parser `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/drift/parse_dump.py` | 0 | Parsed all 15 table definitions and dump rows; no row values printed. |
| 8 | Temp comparison script, initial server-local timezone pass | 0 | Detected all restored TIMESTAMP columns shifted; diagnosed UTC dump vs WIB server timezone. Discarded as invalid comparison. |
| 9 | `mysql ... SELECT @@global.time_zone, @@session.time_zone, @@system_time_zone` and dump-header grep | 0 | Server `SYSTEM`/WIB; dump uses UTC. |
| 10 | Temp comparison script with UTC session timezone and read-only transactions | 0 | Final counts below. |
| 11 | Synthetic-row predicate query on restore-test users/admins id 2 and newsletter id 2 | 0 | Each matching synthetic record count = 1; live `ecommerce` users/admins id 2 count = 0. |

Temp scripts and aggregate results are under `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/drift/`. The parser temporarily stored primary keys and per-column fingerprints; `parsed.json` was deleted after comparison (exit 0). Live values were processed in memory, not persisted. Numeric PK values appeared in the initial diagnostic output; they are deliberately omitted from this report except the supplied synthetic-row selectors. No PII, password hashes, or secret values appeared in output.

Batas metode: setiap SELECT memakai transaksi read-only tersendiri, bukan satu snapshot lintas tabel. Jumlah sesi DB uji berubah selama pemeriksaan; angka akhir adalah observasi sesaat. Pemeriksaan isi mencakup fingerprint kolom sensitif juga, bukan hanya metadata non-sensitif; yang dilaporkan tetap jenis perbedaan dan hitungan. Kesetaraan fingerprint bukan audit semantik. Tidak ada self-test parser independen; kesesuaian seluruh baris katalog dan migrasi pada DB restore menjadi pemeriksaan silang.

## Schema and row counts

All 15 dump tables exist in each database; no extra base tables. The existing validation record reports schema-identical databases (15 tables, 110 columns, 28 indexes, 3 foreign keys). This pass focused on data drift.

| Table | Dump | `ecommerce` | Test DB | Notes |
|---|---:|---:|---:|---|
| admins | 1 | 1 | 2 | Test-only id 2 is synthetic. |
| cache | 10 | 10 | 16 | Volatile. |
| cache_locks | 0 | 0 | 0 | |
| categories | 8 | 8 | 8 | |
| concierge_requests | 0 | 0 | 1 | Test traffic. |
| failed_jobs | 0 | 0 | 0 | |
| job_batches | 0 | 0 | 0 | |
| jobs | 0 | 0 | 2 | Test traffic. |
| migrations | 17 | 17 | 17 | |
| newsletter_subscribers | 1 | 1 | 2 | Test-only id 2 synthetic. |
| password_reset_tokens | 0 | 0 | 0 | |
| products | 12 | 12 | 12 | |
| sessions | 29 | 73 | 12 | Volatile. |
| user_info_backups | 0 | 0 | 1 | Test traffic. |
| users | 1 | 1 | 2 | Test-only id 2 synthetic. |

Synthetic confirmation: test DB users id 2, admins id 2, and newsletter id 2 each match `@example.test` (one row each). No such user/admin id 2 exists in `ecommerce`. Test-only concierge, backup, and jobs records were excluded from dump-vs-live interpretation as test traffic.

## Dump vs live primary-key differences

Counts are `dump-only PK / database-only PK`; same-PK changed counts indicate number of rows with at least one differing compared column. Column entries are count of common-PK rows differing in that column. Secret/sensitive fields are named only when differing; values and hashes are never shown.

| Table | `ecommerce`: only dump/live | Common PK identical/changed | Changed columns (rows) |
|---|---:|---:|---|
| admins | 0 / 0 | 0 / 1 | password 1; remember_token 1; created_at 1; updated_at 1 |
| cache | 3 / 3 | 0 / 7 | value 6; expiration 7 (volatile) |
| cache_locks | 0 / 0 | 0 / 0 | — |
| categories | 1 / 1 | 0 / 7 | cat_title 1; created_at 7; updated_at 7 |
| concierge_requests | 0 / 0 | 0 / 0 | — |
| failed_jobs | 0 / 0 | 0 / 0 | — |
| job_batches | 0 / 0 | 0 / 0 | — |
| jobs | 0 / 0 | 0 / 0 | — |
| migrations | 1 / 1 | 12 / 4 | migration 1; batch 3 |
| newsletter_subscribers | 0 / 0 | 0 / 1 | created_at 1; updated_at 1 |
| password_reset_tokens | 0 / 0 | 0 / 0 | — |
| products | 0 / 0 | 0 / 12 | product_title 8; product_description 6; product_origin 12; product_care 2; product_craft 2; product_cat 4; created_at 12; updated_at 12 |
| sessions | 29 / 73 | 0 / 0 | No common session PK; volatile. |
| user_info_backups | 0 / 0 | 0 / 0 | — |
| users | 0 / 0 | 0 / 1 | first_name 1; last_name 1; mobile 1; address1 1; address2 1; email_verified_at 1; password 1; remember_token 1; created_at 1; updated_at 1 |

`ecommerce` primary-key additions/removals: one category PK differs between dump and live; one migration PK differs. The specific category/migration PK values are omitted. Same-PK product content differs on all 12 products; category assignments differ on 4.

## Dump vs restore-test database

| Table | Dump-only/test-only PK | Common PK identical/changed | Interpretation |
|---|---:|---:|---|
| admins | 0 / 1 | 1 / 0 | Extra test id 2 is synthetic. |
| cache | 0 / 6 | 4 / 6 | Volatile/test activity. |
| categories | 0 / 0 | 8 / 0 | Exact match. |
| concierge_requests | 0 / 1 | 0 / 0 | Test traffic. |
| jobs | 0 / 2 | 0 / 0 | Test traffic. |
| migrations | 0 / 0 | 17 / 0 | Exact match. |
| newsletter_subscribers | 0 / 1 | 1 / 0 | Extra test id 2 is synthetic. |
| products | 0 / 0 | 12 / 0 | Exact match. |
| sessions | 29 / 12 | 0 / 0 | Volatile. |
| user_info_backups | 0 / 1 | 0 / 0 | Test traffic. |
| users | 0 / 1 | 1 / 0 | Extra test id 2 is synthetic. |
| Other four empty tables | 0 / 0 | 0 / 0 | Exact empty match. |

All dump rows in nonvolatile catalog/migration tables match the test restore at the same PK. Timestamp comparison is UTC-normalized. Synthetic/test traffic is not evidence of dump drift.

## Decision needed

The 2026-09-21 dump is not identical to current `ecommerce`: product content, one category PK/title, migration identity/batches, users/admin credentials and metadata, newsletter timestamps, and volatile cache/session state differ. Decide which source is authoritative for each data domain before any reconciliation or restore. This report makes no source-of-truth decision and performs no writes.
