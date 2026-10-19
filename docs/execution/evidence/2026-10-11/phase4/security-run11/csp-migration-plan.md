# CSP Migration Plan — Security Run 11

**Status:** inventory DONE, enforcement NOT DEPLOYED  
**Evidence:** `csp.json`, captured 2026-10-11. No app policy was changed.

## Observed inventory

Counts are per rendered page. `Violations` are Report-Only browser events under the candidate policy, not proof that all interactions are covered.

| Path | HTTP | Inline scripts | `style=` | `<style>` blocks | Inline handlers | External origins | Violations |
|---|---:|---:|---:|---:|---:|---|---|
| `/` | 200 | 3 | 92 | 1 | 7 | `fonts.bunny.net` (2), `images.unsplash.com` (6) | 4 |
| `/prototype/` | 200 | 1 | 436 | 1 | 158 | `fonts.bunny.net` (2) | 8 |
| `/produk/celeste-trench` | 200 | 1 | 16 | 0 | 1 | `fonts.bunny.net` (2), `images.unsplash.com` (3) | 0 (no clicks) |
| `/admin/login` | 200 | 0 | 1 | 0 | 0 | `fonts.bunny.net` (2) | 0 |
| `/login` | 200 | 0 | 1 | 0 | 0 | `fonts.bunny.net` (2) | 0 |
| `/account` | 404 | 0 | 0 | 2 | 0 | none | 0 |
| `/akun` (authenticated) | 200 | 0 | 7 | 0 | 0 | `fonts.bunny.net` (2) | 0 |
| `/admin/dashboard` | 404 | 0 | 0 | 2 | 0 | none | 0 |
| `/admin` (authenticated) | 200 | 0 | 30 | 1 | 16 | `fonts.bunny.net` (2) | 0 (no clicks) |

The `/account` and `/admin/dashboard` paths are non-canonical 404s; actual pages are `/akun` and `/admin`. The two 404s include Laravel error-page style blocks. Inline handler violations only surface when actions execute. Mobile menu clicks timed out at desktop viewport on `/` and `/prototype/`; treat those paths as incomplete interaction coverage.

## Staged rollout

1. **Report-Only baseline:** deploy a report-only header first, retaining `'unsafe-inline'` in `style-src` during inventory/cleanup. Candidate tested policy:

   ```text
   default-src 'self'; script-src 'self'; style-src 'self' https://fonts.bunny.net 'unsafe-inline'; font-src 'self' https://fonts.bunny.net data:; img-src 'self' data: https:; connect-src 'self'; media-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; report-uri /__qa_csp_report
   ```

   This is a harness candidate, not a production-ready header. `img-src https:` is broad; narrow it to observed/required origins after full asset inventory.

2. **Nonce inline scripts:** add per-response nonce support and apply nonce to the 3 inline scripts on `/`, 1 on `/prototype/`, and 1 on the product detail page. Verify rendered nonce and script execution on every route before removing script allowances.

3. **Remove inline event handlers:** replace inline handlers with `addEventListener` in owned scripts. Inventory shows 158 on `/prototype/`, 7 on `/`, and 16 on authenticated `/admin`; product detail has 1. Exercise each action after migration. Do not edit the concurrently owned frontend files as part of this QA report.

4. **Clean inline styles:** `style=` attributes remain a later cleanup item: 436 `/prototype/`, 92 `/`, 30 `/admin`, 16 product detail, 7 `/akun`, and 1 each on login pages. `<style>` blocks: one on `/`, `/prototype/`, and `/admin`; error-page blocks occur on tested 404s. Keep `'unsafe-inline'` for styles until these are addressed or a supported nonce/hash strategy is verified.

5. **Reporting and enforcement:** implement and verify a real CSP report receiver before relying on `report-uri /__qa_csp_report` (no report endpoint was found/verified during this run). Review report volume and browser coverage, then enforce the narrowed policy in a separately approved change. Maintain rollback to Report-Only.

## External sources to review

Observed external origins: `https://fonts.bunny.net` and `https://images.unsplash.com`. Allow only directives and hosts actually required; do not retain broad `https:` in `img-src` without justification.
