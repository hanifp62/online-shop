# Browser Lifecycle & Multilingual Guest Smoke — Evidence (2026-10-21)

## Scope
- Owned runner `scripts/amadis/browser-lifecycle.cjs` and its helper tests only.
- No application source edits by this agent. `app/Services/CheckoutService.php`,
  `tests/Feature/*.php` belong to another agent (authenticated checkout locale snapshot).
- Bounded desktop/mobile `en/fr/it/zh` guest checkout smoke added as `browser-locales.cjs`.

## What changed
- Fixed stale Indonesian literals in the runner:
  - ready: `'Tas server siap.'` -> `'Tas belanja siap.'` (`shop_ui.shop.js.ready`)
  - S1 cart feedback: `'Tas server diperbarui. Jumlah 3'` -> `'Tas belanja diperbarui. Jumlah 3 menggantikan jumlah sebelumnya'` (`shop_ui.shop.js.updated`)
  - S2 order feedback: `'Pesanan #\\d+ dibuat: pending'` -> `'Pesanan #\\d+ dibuat: menunggu pembayaran. Total Rp3.750.000.'` (`order_created`)
  - S2 order list match: `/pending/` -> `/menunggu pembayaran/` (localized status)
  - S5 persistence match: `'Status: cancelled'` -> `'Status: dibatalkan'`
  - S5 admin view: raw `cancelled` text -> semantic `.badge-status.badge-cancelled` + `/^\\s*Dibatalkan\\b/`
- Phase attribution: post-login cart step now `S1-cart`, so a cart-timeout is no longer
  mislabeled `S1-login` (the prior false "login failure").
- Failure screenshot guard now skips `guest-*` phases (contexts close locally; no stale page to capture).

## Commands and exit codes
| Command | Exit |
| --- | --- |
| `./scripts/amadis/launch.sh init` | 0 |
| `./scripts/amadis/launch.sh migrate` | 0 |
| `./scripts/amadis/launch.sh seed` | 0 |
| `./scripts/amadis/launch.sh provision` | 0 |
| `node --test scripts/amadis/browser-lifecycle.test.cjs` | 0 (10/10) |
| `node scripts/amadis/browser-lifecycle.cjs` | 0 |
| `node --check` (both runner + locales) | 0 |

## Actual coverage
- Lifecycle `S0`..`S5`: all PASS.
- Guest smoke: 8/8 PASS (`en`, `fr`, `it`, `zh` x `1365px`, `390px`), each verifying:
  `lang` attribute, quantity 1 -> total Rp 1.250.000, localized ready text, order
  created pending, tracking token is 64-hex and finds the order, token clears.
- Snapshots confirm stock 37 -> 34 (pending) -> 37 (cancelled), one order, total 3.750.000.
- Replay `POST /orders/1/cancel` returns 200 `cancelled`.

## Sanitized artifacts
- Saved for commit: `result.json`, `commands.json`, `sha256.json`, `S0/S2/S3/S4/S5-snapshot.json`,
  `S4-replay.json`, `helper-tests.txt`.
- Screenshots and per-locale JSON remain in the isolated root evidence dir only
  (they may embed guest-facing data); not committed.

## Limitations / blockers
- Authenticated checkout locale snapshot is owned by another agent; not asserted here.
- `browser-locales.cjs` does not capture screenshots (avoids any tracking-token exposure);
  token content is asserted only as a 64-hex shape and never logged.
- `process.exitCode = 1` on BLOCKED remains unchanged; no assertion was weakened to hide app bugs.
