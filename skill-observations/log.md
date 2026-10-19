# Skill Observation Log

Observations captured during task-oriented work. Each entry identifies a potential skill improvement or new skill opportunity.

**Status key:** OPEN = not yet actioned | ACTIONED = skill updated/created | DECLINED = user decided not to pursue

---

## 2026-10-19 — Browser QA workflow

### Observation 1: Freeze build inputs before browser QA
**Status:** OPEN
**Date:** 2026-10-19
**Session context:** Coordinating build and browser QA while parallel agents can edit JavaScript or Blade templates.
**Skill:** browser-qa
**Type:** internal
**Phase/Area:** Build-to-browser verification handoff

**Issue:** Browser QA can pass against a stale bundle if parallel agents edit JS or Blade after the build but before the browser harness runs.

**Suggested improvement:** Hash or otherwise freeze the relevant build inputs at build completion, then require browser QA to verify that same snapshot before treating results as current.

**Principle:** Gate validation on immutable inputs; otherwise a passing downstream check may validate different code than the reviewed change.

### Observation 2: Recheck dirty form state before replacing async content
**Status:** OPEN
**Date:** 2026-10-19
**Session context:** Async table navigation coexists with inline POST forms.
**Skill:** browser-qa
**Type:** internal
**Phase/Area:** Async navigation and form-state protection

**Issue:** A pre-fetch dirty-form check alone leaves a race: the user can edit an inline form while navigation is awaiting `response.text()`, then `replaceWith` discards those edits.

**Suggested improvement:** Call `mayDiscard()` again after `await response.text()` and immediately before `replaceWith`.

**Principle:** Revalidate user-state preservation immediately before asynchronous UI replacement, not only before starting the request.
