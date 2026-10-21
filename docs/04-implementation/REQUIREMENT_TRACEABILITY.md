# Requirement Traceability: R-01–R-60

**Source:** saved PRD from `npx ngodingpakeai plan get 26abc11f-166b-408a-86aa-bf1c94aa41bd`, PRD v46 / document v1.32. Reviewed with existing files in `docs/04-implementation/`.

## Reading rules

- `EXPLICIT` means the saved PRD states the requirement-to-goal mapping verbatim.
- `INFERRED` means the goal is a conceptual match only. It is **not** an authoritative mapping.
- The saved PRD defines G1–G7 and separately defines requirement waves and phases. It does not provide a complete verbatim R→G matrix. Therefore, all rows below are marked `INFERRED` unless a future PRD revision adds a direct R→G statement.
- `—` in Phase means the saved PRD does not state a phase for that requirement.
- Phase follows the saved PRD §7.1. A feature may span phases; this table records requirement-level placement where the PRD states it.

## Mapping table

| Requirement | Goal mapping | Status | Exact gap to close for inferred mapping | Phase in saved PRD |
|---|---|---|---|---:|
| R-01 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-01→G2 statement to the saved PRD. | 2 |
| R-02 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-02→G2 statement to the saved PRD. | 2 |
| R-03 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-03→G2 statement to the saved PRD. | 3 |
| R-04 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-04→G2 statement to the saved PRD. | 3 |
| R-05 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-05→G2 statement to the saved PRD. | 6 |
| R-06 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-06→G2 statement to the saved PRD. | 6 |
| R-07 | G5 — PII health | INFERRED | Add verbatim R-07→G5 statement to the saved PRD. | 2 |
| R-08 | G3 — observability and measurable operations | INFERRED | Add a phase assignment and verbatim R-08→G3 statement to the saved PRD. | — |
| R-09 | G5 — PII health and privacy | INFERRED | Add a phase assignment and verbatim R-09→G5 statement to the saved PRD. | — |
| R-10 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-10→G2 statement to the saved PRD. | 3 |
| R-11 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-11→G2 statement to the saved PRD. | 6 |
| R-12 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-12→G2 statement to the saved PRD. | 6 |
| R-13 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-13→G2 statement to the saved PRD. | 6 |
| R-14 | G6 — usable operational console and feedback | INFERRED | Add verbatim R-14→G6 statement, or assign it to a separate catalog/discovery goal. | 6 |
| R-15 | G1 — automated assurance | INFERRED | Add verbatim R-15→G1 statement to the saved PRD. | 3 |
| R-16 | G1 — automated assurance | INFERRED | Add verbatim R-16→G1 statement to the saved PRD. | 3 |
| R-17 | G5 — PII health and access protection | INFERRED | Add verbatim R-17→G5 statement to the saved PRD. | 3 |
| R-18 | G3 — observability and operations | INFERRED | Add verbatim R-18→G3 statement to the saved PRD. | 3 |
| R-19 | G4 — single source of truth | INFERRED | Add verbatim R-19→G4 statement to the saved PRD. | 3 |
| R-20 | G3 — observability and resilient operations | INFERRED | Add verbatim R-20→G3 statement to the saved PRD. | 6 |
| R-21 | G4 — single source of truth and reproducibility | INFERRED | Add verbatim R-21→G4 statement to the saved PRD. | 6 |
| R-22 | G1 — automated assurance | INFERRED | Add verbatim R-22→G1 statement to the saved PRD. | 6 |
| R-23 | G6 — usable, accessible console and feedback | INFERRED | Add verbatim R-23→G6 statement to the saved PRD. | 6 |
| R-24 | G7 — consistent identity and language | INFERRED | Add verbatim R-24→G7 statement to the saved PRD. | 6 |
| R-25 | G6 — usable interface and recovery feedback | INFERRED | Add verbatim R-25→G6 statement to the saved PRD. | 6 |
| R-26 | G6 — usable interface and visible status | INFERRED | Add verbatim R-26→G6 statement to the saved PRD. | 6 |
| R-27 | G6 — usable interface validated with users | INFERRED | Add verbatim R-27→G6 statement to the saved PRD. Note: the saved PRD records R-27 as intentionally without a feature home. | 6 |
| R-28 | G6 — usable interface and immediate feedback | INFERRED | Add verbatim R-28→G6 statement to the saved PRD. | 6 |
| R-29 | G1 — automated assurance | INFERRED | Add verbatim R-29→G1 statement to the saved PRD. | 2 |
| R-30 | G3 — observability | INFERRED | Add verbatim R-30→G3 statement to the saved PRD. | 2 |
| R-31 | G3 — observability and business dashboard | INFERRED | Add verbatim R-31→G3 statement to the saved PRD. | 6 |
| R-32 | G1 — automated assurance | INFERRED | Add verbatim R-32→G1 statement to the saved PRD. | 6 |
| R-33 | G4 — single source of truth | INFERRED | Add verbatim R-33→G4 statement to the saved PRD. Resolve phase ambiguity: §7.1 lists R-33 in phase 1, and the phase-6 range `R-31…R-35` also covers it. | 1 (also within phase-6 range) |
| R-34 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-34→G2 statement to the saved PRD. | 6 |
| R-35 | G2 — closed transaction lifecycle | INFERRED | Add verbatim R-35→G2 statement to the saved PRD. | 6 |
| R-36 | G5 — PII and access protection | INFERRED | Add verbatim R-36→G5 statement to the saved PRD. | 3 |
| R-37 | G5 — PII and recoverability | INFERRED | Add verbatim R-37→G5 statement to the saved PRD. | 3 |
| R-38 | G1 — automated assurance | INFERRED | Add verbatim R-38→G1 statement to the saved PRD. | 2 |
| R-39 | G1 — automated assurance and safe release | INFERRED | Add verbatim R-39→G1 statement to the saved PRD. | 6 |
| R-40 | G1 — automated assurance and measurable performance | INFERRED | Add verbatim R-40→G1 statement to the saved PRD. | 6 |
| R-41 | G3 — measurable operations | INFERRED | Add verbatim R-41→G3 statement to the saved PRD. | 1 |
| R-42 | G6 — usable admin console | INFERRED | Add verbatim R-42→G6 statement to the saved PRD. | 5 |
| R-43 | G6 — usable admin console | INFERRED | Add verbatim R-43→G6 statement to the saved PRD. | 5 |
| R-44 | G6 — usable admin console | INFERRED | Add verbatim R-44→G6 statement to the saved PRD. | 5 |
| R-45 | G6 — usable admin console | INFERRED | Add verbatim R-45→G6 statement to the saved PRD. | 5 |
| R-46 | G6 — usable admin console | INFERRED | Add verbatim R-46→G6 statement to the saved PRD. | 5 |
| R-47 | G6 — usable admin console | INFERRED | Add verbatim R-47→G6 statement to the saved PRD. | 5 |
| R-48 | G7 — consistent identity and language | INFERRED | Add verbatim R-48→G7 statement to the saved PRD. | 4 |
| R-49 | G7 — consistent identity and language | INFERRED | Add verbatim R-49→G7 statement to the saved PRD. | 4 |
| R-50 | G7 — consistent identity and language | INFERRED | Add verbatim R-50→G7 statement to the saved PRD. | 4 |
| R-51 | G7 — consistent identity and language | INFERRED | Add verbatim R-51→G7 statement to the saved PRD. | 4 |
| R-52 | G7 — consistent identity and language | INFERRED | Add verbatim R-52→G7 statement to the saved PRD. | 4 |
| R-53 | G7 — consistent identity and language | INFERRED | Add verbatim R-53→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #052, tes `CommercialClaimIntegrityTest`. | 4 |
| R-54 | G7 — consistent identity and language | INFERRED | Add verbatim R-54→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #053, tes `GuidingMicrocopyTest`. | 4 |
| R-55 | G7 — consistent identity and language | INFERRED | Add verbatim R-55→G7 statement to the saved PRD. Implementasi lokal: `COUNCIL_DECISIONS.md` #054, guard `tests/brand-tokens.test.mjs`, doc `BRAND_TOKEN_LOCK.md`. | 4 |
| R-56 | G5 — identity and access protection | INFERRED | Add verbatim R-56→G5 statement to the saved PRD. | 3 |
| R-57 | G5 — identity and access protection | INFERRED | Add verbatim R-57→G5 statement to the saved PRD. | 3 |
| R-58 | G5 — identity and access protection | INFERRED | Add verbatim R-58→G5 statement to the saved PRD. | 3 |
| R-59 | G6 — usable, accessible authentication flow | INFERRED | Add verbatim R-59→G6 statement to the saved PRD. | 3 |
| R-60 | G5 — identity and session protection | INFERRED | Add verbatim R-60→G5 statement to the saved PRD. | 3 |

## Unmapped requirements

**None.** R-01 through R-60 each have one conceptual goal mapping and a row. However, **all 60 goal mappings remain unconfirmed inferred mappings** because the saved PRD does not contain a complete verbatim R→G matrix.

The saved PRD does not state a wave or phase for **R-08 and R-09**. Both need explicit phase decisions before the roadmap can claim complete phase coverage. R-33 has an ambiguous phase placement (phase 1 and the phase-6 range).
