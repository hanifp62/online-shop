# Amadis Demo — P0 isolation baseline

Tanggal: 2026-10-12  
Status: P0 foundation runnable; product implementation owned by other agents.

## Scope

- `scripts/amadis/launch.sh`: thin entrypoint.
- `scripts/amadis/launch.py`: unique approved-root launcher, environment isolation, evidence capture.
- `scripts/amadis/artisan.php`: guard before Laravel bootstrap and explicit isolated Artisan entrypoint.
- `scripts/amadis/guard.php`: fail-closed root, identity, SQLite, and cache/storage assertions.
- `scripts/amadis/server.php`: guarded PHP server router.
- `config/amadis.php`: explicit root/database/evidence config surface.

No `.env` read or edited. No git mutation. No production database access. No ports `8000` or `8001`.

## Verified baseline evidence

- Framework: Laravel 12 (`composer.json` pins `^12.0`).
- Runtime observed: PHP 8.5.11; Python 3.14.8; Node/npm available.
- Existing `database/database.sqlite` remains unchanged by P0.
- Canonical symlinks inventoried: `public/app.css -> ../prototype/app.css`, `public/app.js -> ../prototype/app.js`, `public/prototype -> ../prototype`.
- Existing final2 manifest dimensions reviewed in `docs/execution/evidence/2026-10-12/final-acceptance/RESULT-final2.md`: 16 command checks, 56 sweep cases, 56 PNGs, visual inspection caveat.
- Parent-session caveat: parent ran `artisan --version` once before isolation. No database operation was observed. Impact is not asserted.

## Usage

```sh
scripts/amadis/launch.sh init
AMADIS_ROOT=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-... scripts/amadis/launch.sh migrate
scripts/amadis/launch.sh artisan about
scripts/amadis/launch.sh test --filter=ExampleTest
scripts/amadis/launch.sh seed
scripts/amadis/launch.sh serve 8016
scripts/amadis/launch.sh guard-test
```

`init` creates a unique root and records `identity.json`, source/config/test hashes, versions, and git status. Evidence files store timestamp, command, return code, stdout, and stderr. Secret-like values are redacted.

## Isolation contract

Every app process receives:

- `DB_CONNECTION=sqlite`, `DB_DATABASE=<root>/database.sqlite`, `DB_URL=`.
- Root-scoped Laravel storage, sessions, cache, views, logs, uploads, and framework cache paths.
- `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, and `MAIL_MAILER=log`.
- A per-run ephemeral app key, never recorded in evidence.

Migration and seed commands require the identity file and exact root SQLite path before bootstrap. Sentinel test rejects repository root and repository database. Raw `git`, `mysql`, `mysql_upgrade`, Composer, and raw PHP commands are blocked through `run`; use named safe operations.

## Deliberate omissions

- No full baseline suite was run.
- No product routes, views, theme, domain, or demo data were changed.
- No server process was left running.
