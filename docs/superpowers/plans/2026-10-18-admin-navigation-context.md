# Admin Navigation and Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admin dashboard and authentication-event log a shared, accessible navigation shell without changing routes or operations.

**Architecture:** Add one Blade layout for the two existing work views. Move document chrome and shared navigation into that layout; keep each page's functional content and existing dashboard panels/forms and auth-event filters/paginator intact.

**Tech Stack:** Laravel Blade, PHP feature tests, existing CSS/Vite assets; no new dependency.

**Spec:** `docs/superpowers/specs/2026-10-18-admin-navigation-context-design.md`

## Global Constraints

- Scope only `admin.dashboard` and `admin.auth-events.index`.
- Do not change routes, middleware, guards, TOTP, controllers, models, schema, JSON endpoints, or dependencies.
- Keep dashboard panels and existing form actions unchanged.
- Keep auth-event filters and pagination query strings unchanged; do not store filter state in session or browser storage.
- Escape dynamic data with Blade; do not expose authentication secrets.
- Preserve storefront identity and use responsive, keyboard-accessible navigation with visible focus.

## Review Focus

- Unauthenticated or TOTP-incomplete admin access must remain blocked by existing middleware; assert existing redirects/denials remain.
- Long operator email and narrow viewport must not hide navigation or overlap the title; assert responsive styles and verify in browser at mobile width.
- Empty auth-event results with active filters must still show valid reset behavior and empty state; add feature assertion.
- Filtered pagination must preserve `event` and `outcome`; assert generated next-page URL/query string.
- Anchor navigation and skip link must target existing landmarks; assert `href` targets exist in rendered HTML.

---

### Task 1: Capture Baseline and Add Shell Regression Tests

**Files:**
- Modify: `tests/Feature/AuthEventsTest.php` or create `tests/Feature/AdminNavigationTest.php`
- Baseline artifact: `/tmp/routes-before.json` (captured before implementation; do not commit)

**Interfaces:**
- Consumes: existing admin/TOTP test helpers and routes.
- Produces: regression assertions for shared shell, active page, and preserved page behavior.

- [ ] Run relevant baseline tests using `DB_CONNECTION=sqlite DB_DATABASE=:memory:`: `AuthTest`, `AdminTotpTest`, `AuthEventsTest`, `AdminManagementTest`, and `AdminOrderReportTest`; record each command and EXIT in `/tmp/totp-state.md`.
- [ ] Add feature tests proving authorized dashboard and auth-event pages return 200 with the existing shared shell markers, an active-page marker, breadcrumb/title, accessible navigation label, and skip link targeting a rendered main landmark.
- [ ] Add assertions that dashboard retains `Dashboard Maison`, `Keluar`, anchor IDs, and representative existing form actions including category, product, report, concierge, subscriber, and password forms.
- [ ] Add assertions that auth-event filter selects remain selected, reset URL remains named route, and pagination URL preserves `event` and `outcome` query parameters.
- [ ] Add an access assertion that the existing TOTP gate still redirects or denies a logged-in admin without verification.
- [ ] Run focused new tests; verify they fail specifically because shared shell/markers do not exist, and record EXIT.

### Task 2: Create Shared Admin Work Layout

**Files:**
- Create: `resources/views/admin/layouts/app.blade.php`
- Test: `tests/Feature/AdminNavigationTest.php` (or tests added in Task 1)

**Interfaces:**
- Consumes: layout variables/sections from the two views.
- Produces: shared HTML document with `title`, `pageTitle`, `breadcrumb`, `activePage`, `content`, and optional page-specific style sections; named links to `admin.dashboard`, `admin.auth-events.index`, and `home`.

- [ ] Implement semantic document metadata, visible-on-focus skip link to `#main-content`, labeled primary navigation, shared header/operator identity, breadcrumb, page heading, and main landmark.
- [ ] Mark active dashboard/auth-event link with `aria-current="page"`; do not rely on color alone.
- [ ] Keep dashboard section-anchor links in dashboard-specific navigation and include the named route to the auth-event log; do not add placeholder menus.
- [ ] Add visible keyboard focus and responsive styles that preserve access to every navigation link on narrow viewports.
- [ ] Run the layout regression tests; require PASS and record EXIT.

### Task 3: Migrate Dashboard Without Changing Operations

**Files:**
- Modify: `resources/views/admin/dashboard.blade.php`
- Test: `tests/Feature/AdminNavigationTest.php` and existing admin tests

**Interfaces:**
- Consumes: shared layout from Task 2.
- Produces: dashboard page using the layout while retaining all current content, panel IDs, forms, actions, and toast markup.

- [ ] Replace standalone document chrome with Blade layout usage and page metadata; retain all eight dashboard panels and their IDs.
- [ ] Move dashboard-only CSS into a Blade style section or existing layout slot without changing unrelated storefront CSS.
- [ ] Preserve every existing form method, action, CSRF field, method spoofing, and user-facing text including `Dashboard Maison` and `Keluar`.
- [ ] Run `AuthTest`, `AdminTotpTest`, `AdminManagementTest`, `AdminOrderReportTest`, and dashboard navigation tests; require PASS and record EXIT.

### Task 4: Migrate Authentication-Event Log

**Files:**
- Modify: `resources/views/admin/auth-events.blade.php`
- Test: `tests/Feature/AdminNavigationTest.php`, `tests/Feature/AuthEventsTest.php`

**Interfaces:**
- Consumes: shared layout from Task 2.
- Produces: auth-event log page using the shared layout with unchanged controller-provided paginator and filter request state.

- [ ] Replace standalone document chrome and back link with shared layout metadata and active auth-events navigation.
- [ ] Preserve event/outcome filters, selected values, reset link, event table, empty state, and `{{ $events->links() }}` unchanged in behavior.
- [ ] Add current filter values as visible page context using escaped Blade output; omit empty values.
- [ ] Keep event context rendering escaped; do not display secrets or raw authentication credentials.
- [ ] Run `AuthEventsTest`, auth-event flow tests, and navigation tests; require PASS and record EXIT.

### Task 5: End-to-End Verification and Review

**Files:**
- Verify: both views, tests, route list, and browser behavior

**Interfaces:**
- Consumes: completed shared shell and migrated views.
- Produces: evidence that acceptance criteria pass without route or workflow regressions.

- [ ] Run focused admin, TOTP, auth-event, and dashboard form tests with the required SQLite-memory prefix; record exact commands and EXIT values in `/tmp/totp-state.md`.
- [ ] Run full test suite with the same database prefix; record summary and EXIT.
- [ ] Run `php artisan route:list --json > /tmp/routes-after.json 2>&1; echo "EXIT=$?"` and compare against `/tmp/routes-before.json`; require no route additions or changes.
- [ ] Run the app and perform browser checks for dashboard and auth-events at desktop and narrow mobile widths: skip-link keyboard activation, visible focus, active navigation, anchor jumps, filter submission/reset, and pagination query retention. Record actual outcomes; mark any unrun check TIDAK TERBUKTI.
- [ ] Inspect `git diff` and staged paths; ensure unrelated dirty files remain untouched and no routes/controllers/middleware changed.
- [ ] Run security review for exposed auth-event data and TOTP/middleware integrity.
- [ ] Commit only the spec, plan, shared layout, two migrated views, and focused test file using explicit paths; verify `git diff --cached --name-status` before committing.
- [ ] Complete NgodingPakeAI task only after all checks pass, then run `npx ngodingpakeai sync --if-changed --plan` and fetch the next task only after applying the phase/layer checkpoint rule.
