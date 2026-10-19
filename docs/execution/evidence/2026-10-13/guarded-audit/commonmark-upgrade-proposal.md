# Separate dependency proposal: `league/commonmark` security fix

**Status:** Proposal only. No dependency or lock update performed.

## Evidence

- Guarded audit: [`audit.stdout.json`](audit.stdout.json), [`audit.stderr.txt`](audit.stderr.txt), [`audit.rc.txt`](audit.rc.txt).
- Command: `./scripts/amadis/launch.sh composer-audit --locked --format=json`.
- Composer returned exit code `1`: two advisories affect locked `league/commonmark` `2.10.0`.
- `PKSA-m2dq-1fhr-29b1` / [GHSA-97jj-33gv-5xf9](https://github.com/advisories/GHSA-97jj-33gv-5xf9), medium: disallowed raw HTML bypass.
- `PKSA-m4t9-vsgq-8khn` / [GHSA-3q6v-r5mr-hxv8](https://github.com/advisories/GHSA-3q6v-r5mr-hxv8), high: quadratic-time GFM table scan denial of service.
- Both affected ranges include `2.10.0` and end at `2.10.1`.
- Composer lock resolves `laravel/framework` `v12.69.1`; its declared CommonMark constraint is `^2.8.1`. Root requires Laravel `^12.0`, PHP `^8.2`.
- Packagist metadata read on 2026-10-13 lists `league/commonmark` `2.10.3`, PHP `^7.4 || ^8.0`, reference `6efbd9c472b91db0a3350fcd601c8332c2382e1f`. Version `2.10.3` is above both reported affected ranges.
- Application Markdown entry points found: `app/Mail/NewsletterWelcome.php` and `app/Mail/ConciergeRequested.php` use Markdown mail views. Framework Markdown rendering is therefore exercised by application mail flows.

## Recommendation

Approve a separate, narrowly scoped update to resolve `league/commonmark` to `2.10.3` (or later reviewed safe release) without changing the Laravel framework version if Composer can do so under its existing `^2.8.1` constraint. Prefer a targeted Composer update for that package and its required dependencies, with scripts/plugins enabled and exact existing hook definitions preserved. Do not edit `composer.json` solely to add a redundant constraint unless Composer resolution requires it and review approves that manifest change.

## Required checks before merge

1. Review the exact proposed `composer.lock` diff; confirm no unrelated package drift and no PDF dependency closure change.
2. Run guarded Composer audit again; require zero CommonMark advisories and record raw JSON, stderr, and exit code.
3. Run existing test suite and targeted Markdown mail rendering tests; add a test only if current coverage does not exercise those views.
4. Verify all Composer hooks remain unchanged, `vendor/composer/installed.json` matches lock, and `composer audit --locked` reports no regressions.

## Risks / unknowns

- No framework/rendering compatibility test was run against `2.10.3`; only dependency constraints and audit metadata were inspected.
- Current audit identifies CommonMark advisories, not whether attacker-controlled Markdown reaches vulnerable parser paths. Treat as dependency remediation, not a confirmed exploit report.
- The working tree already has unrelated changes to `composer.json` and `composer.lock`; proposal makes no claim that those pre-existing changes are safe or part of this audit task.
