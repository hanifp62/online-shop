# R-45 Plan Draft — Admin table loading and recovery

## Evidence and scope

- **Requirement (verbatim, `plan get 26abc11f-166b-408a-86aa-bf1c94aa41bd`, §6):** “R-45 · GAP · E2 — **State lengkap setiap layar admin**: loading (skeleton), kosong (empty state + aksi lanjut), galat (pesan + langkah pemulihan), sukses/no-op — termasuk state parsial (Nielsen #1 & #9).”
- R-44 source is frozen while browser verification proceeds. This plan limits changes to the AJAX table loading and failure recovery slice deferred to R-45.
- Existing code already sets `aria-busy`, announces “Memuat data tabel…”, hides `[data-table-error]` before fetch, and displays a retry instruction on failure. It has no skeleton, actionable retry control, request timeout, or teardown. Stale request safety uses `AbortController` plus controller identity checks.
- Existing fake DOM: `tests/helpers/admin-table.mjs`; focused suites: `tests/admin-table*.test.mjs`. Dashboard provides a persistent `role="status" aria-live="polite"` announcement and per-table error region (`resources/views/admin/dashboard.blade.php`).
- Request timeout is optional/unconfirmed by R-45 text. Decide during implementation whether bounded timeout is needed; if included, keep it small and test abort/recovery. Do not add teardown without a caller/lifecycle need.

## Requirements and acceptance criteria

- **FR-R45-001 Loading:** During AJAX table swap, expose a row-shaped skeleton without removing current table content; clear skeleton after success, failure, abort, or superseding request.
- **FR-R45-002 Recovery:** Fetch/parse/timeout failure shows a keyboard-operable “Coba lagi” control. Retrying the same URL uses existing request and stale-response protections.
- **NFR-R45-001 Accessibility:** Set `aria-busy="true"` for all affected table regions while loading and false on every settled path. Skeleton is decorative (`aria-hidden="true"`); no duplicated screen-reader announcements.
- Keep existing live-region loading wording “Memuat data tabel…” in Bahasa Indonesia. Failure wording remains actionable and Bahasa Indonesia; announce concise failure/retry status through the existing persistent polite live region, avoiding duplicate `role=alert` speech.
- Retry returns focus to the retry control or stable table heading after retry completion; do not steal focus if user moved elsewhere while request ran.
- Skeleton animation respects `prefers-reduced-motion: reduce` (disable shimmer/movement; retain static placeholder).
- No-JS GET forms and links remain unchanged; error/retry/skeleton enhancements must not hide or block native navigation without JavaScript.
- Slow 3G: while response is pending, skeleton is visibly present and table region reports busy; after response it disappears, content updates, and focus/announcement remain usable.

## TDD implementation steps

### Phase 1 — Failing fake-DOM tests

1. **Extend fake DOM narrowly** (`tests/helpers/admin-table.mjs`). Model skeleton and retry button in each table section, including attributes, hidden state, click/focus, replacement, and event delegation only as needed. Preserve existing helper contracts. Dependency: none. Risk: Medium; helper underpins all table suites.
2. **Loading lifecycle tests** (`tests/admin-table.test.mjs` or dedicated `tests/admin-table-r45.test.mjs`). Assert skeleton appears and is hidden during pending fetch; `aria-busy=true`; settled success hides skeleton and busy=false. Assert failure and superseding/aborted request also clear it. Run tests first and confirm RED. Dependency: step 1. Risk: Medium.
3. **Retry failure tests** (`tests/admin-table-r45.test.mjs`). Force rejected fetch and non-OK response; assert retry affordance is visible, correct Bahasa Indonesia message is announced, click starts a fresh request to the same URL, success removes error and restores content. Assert keyboard focus handling and no focus stealing after focus moves elsewhere. Confirm RED. Dependency: step 1. Risk: Medium.
4. **Timeout tests only if adopted** (`tests/admin-table-r45.test.mjs`). Use deterministic fake timer injection or bounded test timer; assert timeout aborts request, renders recovery, resets busy/skeleton, and retry succeeds. Avoid flaky wall-clock sleeps. Dependency: timeout decision. Risk: Medium.

### Phase 2 — Minimal implementation

5. **Markup and styles** (`resources/views/admin/dashboard.blade.php`, and only if shared controls own the markup, `resources/views/admin/components/table-controls.blade.php`). Add hidden decorative skeleton and retry button within each existing table region/error area. Ensure server-rendered no-JS state keeps skeleton/retry hidden and existing GET navigation intact. Add static reduced-motion styling; shimmer only under `prefers-reduced-motion: no-preference`. Dependency: phase 1 tests specify contract. Risk: Medium.
6. **Swap lifecycle and retry wiring** (`resources/js/admin-table.js`). Toggle skeleton alongside existing `aria-busy`; centralize cleanup so success, current-request failure, abort/supersede, and optional timeout cannot leave stale skeleton. Retry calls existing `load(key, href, options)`; preserve current query/history/focus intent. Do not replace `AbortController` identity guard. Dependency: step 5. Risk: High; navigation races and history restoration.
7. **Update fake-DOM tests to green** (`tests/helpers/admin-table.mjs`, R-45 tests). Confirm failure messages, retry, focus, abort, stale-response, and all existing R-44 suites. Dependency: step 6. Risk: Medium.

### Phase 3 — Verification

8. Run focused Node tests: `node --test tests/admin-table*.test.mjs`; then project test command from `package.json`, formatting/lint/type checks applicable to changed JS, and inspect diff. Do not alter frozen R-44 behavior outside required integration.
9. Browser verification by parent session: normal latency, throttled Slow 3G, failed/offline request followed by retry, reduced-motion emulation, keyboard-only retry, and JavaScript disabled. No browser/server work in this planning task.

## Risks and mitigations

- **Stale/error request leaves loading UI stuck:** one cleanup path gated by `controller === mine`; test stale and aborted requests explicitly.
- **Retry changes navigation state incorrectly:** retry same target without pushing a duplicate history entry; preserve `restoreOnCancel` behavior.
- **Skeleton causes layout shift or obscures readable stale data:** overlay/placeholder dimensions should match existing table; keep old data present until atomic swap.
- **Live-region double announcement:** use existing persistent polite status for concise state; avoid making both error text and retry button assertive.
- **Focus disruption:** focus retry only where appropriate; if focus moved elsewhere, preserve it. Verify keyboard operation and focus visibility.
- **Reduced-motion oversight:** static skeleton remains visible, animation disabled under reduced-motion.
- **Timeout false positives on slow links:** timeout is not explicitly required; defer unless product owner confirms threshold. If added, choose a conservative bounded duration and test deterministically.
- **No-JS regression:** leave native GET markup and links functional; progressive enhancement owns only hidden visual affordances and AJAX behavior.

## Out of scope

- Empty states, other admin screens, general table redesign, new dependency, request teardown absent lifecycle need, broader R-44 changes, and browser execution by this planning agent.
