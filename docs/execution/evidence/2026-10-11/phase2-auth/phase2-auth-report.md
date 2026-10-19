# Phase 2 — authenticated browser E2E and cleanup

Date: 11 October 2026. Scope: isolated restore DB `zafira_restore_test_20261011`, temporary app on `127.0.0.1:8013` only. No source application files changed.

## Results

- Playwright evidence: 32 assertions passed across desktop (1440×900) and mobile (320×568). No assertion failures.
- Verified anonymous redirect, wrong user/admin password, successful login and intended destination, user/admin guard separation, reciprocal account/admin access denial, both admin logout flows, and preservation of user auth during admin login/logout.
- Four redacted screenshots captured. All four viewport measurements show horizontal overflow `0`. Admin screenshot masks table bodies and sensitive inputs; account screenshot masks profile heading/form fields. No raw HTML, HAR, cookie, session token, or secret logged.
- `results.json` reports two user-logout cases as **NOT RUN**. Account page exposes no logout form. The storefront header partial provides logout, but coverage was not rerun after exhausted three-attempt budget. Do not count these as passes.
- Reusable runner: `auth-e2e.mjs`; requires `QA_PASSWORD_FILE` containing temporary synthetic credential. It exits `2` when coverage is incomplete.

## Temporary fixture and cleanup

Verified exact synthetic principals before cleanup: `users.id=2` with `qa.synthetic@example.test`; `admins.id=2` with `qa.admin@example.test`. Confirmed a backup row referenced user 2, no concierge row referenced user 2, three session rows belonged to user 2, and no matching password-reset rows existed. Existing other user/admin IDs were preserved. Deleted only exact synthetic principal rows, exact backup matching user_id 2 plus synthetic email, and user_id 2 session rows. Counts: one backup, five sessions at cleanup, one user, and one admin deleted. Post-cleanup: one user/admin remains; synthetic backup and concierge references zero; FK orphan checks all zero; database retains 15 tables. Full DB was not deleted.

Temporary app process PID 35168 was confirmed to be PHP server `127.0.0.1:8013`, then stopped. Port 8013 is free. User-owned ports 8000/8001 were not stopped. Temporary password file was removed. No commit created.

## Caveats

- Successful admin dashboard contains restored real data; screenshots use Playwright masks, not unredacted capture delivery.
- User logout remains untested in browser; UI's account page has no visible logout control. Main session should decide if logout coverage can use storefront header or if absent control needs separate UX/security triage.
- This evidence does not replace main regression suite or broader security review.
