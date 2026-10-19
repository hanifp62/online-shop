# F1–F9 closeout source audit — 2026-10-13

## Scope and evidence rule

Read-only source audit. No browser, server, or test command launched; no files outside this new evidence directory changed by this audit. The user's dirty working tree was preserved. Findings below are source observations and proposed proofs, **not acceptance PASS results**. The only current browser artifact here remains historical `triage-final3` from 2026-10-12.

`triage-final3.md` is canonical definition text for F1–F9. Full exact definitions and historic measurements are retained there (lines 7–15); this closeout summarizes them and does not revise their rubric. Parent gate explicitly says F1–F9 were **NOT RETESTED** (`final-gate/LAPORAN_FINAL_GATE.md:78–99`). The acceptance CSV is a *proposed* AM rubric and states official AMADIS rubric was not found; no F1 rubric is inferred from it.

## Source state record

- Audit time: `2026-10-13T02:06:37Z` (UTC clock observed during audit).
- HEAD: `2f355e1624306d7277f7828ee319321b21bee0ea`.
- Worktree was already extensively dirty/untracked. No attempt was made to clean, stage, or reconcile it.
- The snapshot is this audit's read time, not necessarily a single atomic tree state; listed source files have their SHA-256 below. Paths are relative to repo unless noted.
- Historical triage source is external to repo and untracked: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-qa/triage-2.mjs` (SHA-256 `992399d878bd2f5613cbf3076504202053b9cbaf5adc20de0dceff00dbe5c2b7`). It binds `127.0.0.1:8015`; **do not run as-is**. Need an acceptance-agent-owned, unused/approved port, a reviewed script copy, and explicit server ownership/cleanup.

| File | SHA-256 at audit | State |
|---|---|---|
| `prototype/app.css` | `b5ddd9e5653f5f5962118f60cf5409d2eb940b63956faac62c5ee505ffef979f` | dirty |
| `prototype/app.js` | `9cdbbf6a3ce1dbb4932243e4684a07439e9598380221a2f19bb60d20993f64bb` | dirty |
| `prototype/zafira-core.js` | `b41998b442ea92855a7ca399cdcda0d85bd5ca267fc0ca8ad63d38df5ad7d21d` | dirty |
| `prototype/index.html` | `b1b636d373ea3f1a295c90444e18e457fad46231283acf99298bb0d11ab0c8fa` | dirty |
| `public/js/zafira-core.js` | `bdd538632d7eadc0dcb9cfc68eb8b51e74db3509f87fcce875512b386b8b210c` | dirty |
| `resources/views/welcome.blade.php` | `0ea6cee610d04530e256459d3872ce4422e3195f96530a73c9177bde819f5857` | dirty |
| `resources/css/app.css` | `dce55a3ed41e3ae9f668f0342d1aff474951942e769cbf014ce2a97d5d669ac2` | dirty |
| `tests/Feature/AdminOrderReportTest.php` | `ef028fadd513a6438c87d5fa25bcad87e3b0bf690933625f0091325bd9b742f4` | untracked |
| `tests/Support/pdf-text.swift` | `e3aa3579ca89095b037b5da8a045c7797020ea2bb2545751dddceaf09b8b980c` | untracked |
| `tests/zafira-i18n.test.mjs` | `187b37c60dd290ac383ecc50dd12bf4c7c140a43a37d0f0ffc90c1df94257ac3` | untracked |

## Findings: definitions, current source signal, remaining proof

| ID | Exact canonical definition (triage-final3) | Current-source evidence (not runtime verdict) | Remaining proof; available command/artifact path |
|---|---|---|---|
| **F1** | Prototype header: reported headline/CTA clipping is **SCREENSHOT ARTEFACT**; 1024 nav crowding is **CONFIRMED layout defect**. Historic desktop rects showed no first-paint overlap; 1024 hero content is below fold and nav pair-overlap count was zero, but labels looked crowded. | `prototype/index.html` contains prototype header and hero. Existing `tests/prototype-header-1025.test.mjs` protects 1025–1400 CSS compact sizing, not 1024 nav scanability or actual clipping. | Browser at 1024 and comparison desktop widths: capture first-paint viewport, measure header/nav/hero element rects, label readability/overlap, and document scroll; separately classify headline/CTA below-fold vs actual intersection. Existing structural test: `node --test tests/prototype-header-1025.test.mjs`. Historical browser recipe: `docs/execution/evidence/2026-10-12/final-acceptance/header-breakpoint-final2.mjs` (other site/header, not equivalent F1 proof); triage scripts in external directory are old and port-8015-bound. |
| **F2** | Floating dock overlap: **CONFIRMED defect**; fixed dock overlays first-viewport controls/text/image at reported widths, with scroll-composite Kebaya overlap partly a full-page screenshot artefact. | `prototype/app.css:130–146` positions `.floating-luxury-dock` fixed, bottom 24px, z-index 9999. Mobile hero spacing reserves a band in CSS, but that does not prove clearance for all first-paint elements or desktop. `prototype/app.js` auto-tucks only while scrolling down; no source proof that all intersections are prevented. | Browser first viewport at 1024/1366/1440/1650; compare dock rect against every visible actionable/content rect, including controls/filters and mobile hero. Also inspect normal viewport screenshots vs full-page screenshot separately. Existing available structural check: `node --test tests/prototype-dock.test.mjs` (checks mobile hero spacing only). Historical capture/measurement script: external `triage-2.mjs`, F2 block, port 8015. |
| **F3** | Indonesian strings on EN prototype: **CONFIRMED defect**; hard-coded editorial/stat strings and untranslated fallback product copy; French brand names remain legitimate. | `prototype/app.js:28,32,49` contains Indonesian product `hero_quote`/`craft` strings. Lines 685, 716, 720, 747, 751, 771 contain visible hard-coded Indonesian stats/quote/heading. `prototype/index.html:200` hardcodes `Tampilan Editorial`. `getProductLocalized()` at `prototype/app.js:317–332` uses `multi[lang].desc || p.desc`, permitting fallback. `tests/zafira-i18n.test.mjs` covers storefront Blade dictionary bindings, not prototype fixed literals or dynamic product fallback. | Browser render `/prototype/` with `zafira_lang=en`; inventory all visible text nodes, including dynamically rendered product text and states, and review proper names/brand terms against triage exclusions. Existing command only partial: `node --test tests/zafira-i18n.test.mjs`; use as regression, not acceptance proof. Existing sweep `sweep-15-final2.mjs` inventories visible `[data-i18n]` only and misses no-key strings; extend in acceptance-owned copy. |
| **F4** | ID storefront “Deux Rives”: **CONFIRMED defect**; phrase absent from intended ID dictionary translation; French is intentional for FR. | `public/js/zafira-core.js:43` ID `editorial_title` uses `Deux Rives`; EN line 283 is `Two Shores`, FR line 524 is `Deux Rives`, IT line 764 `Due Rive`. `resources/views/welcome.blade.php:477` binds `editorial_title`. | Browser or isolated dictionary/runtime assertion: storefront locale ID resolves rendered editorial heading without `Deux Rives`; FR still retains `Deux Rives`; confirm no other visible ID use is unintentionally changed. Existing `node --test tests/zafira-i18n.test.mjs` validates key presence only, not correct values. |
| **F5** | ID service cards: **CONFIRMED defect**; service_1_title and service_3_title ID values are untranslated English values. | `public/js/zafira-core.js:117,123` ID values are `Private Styling`, `Care for Life`; EN intentionally same at 357,363. Blade service bindings exist by triage report. | Render ID storefront and inspect both visible card headings, plus EN unchanged. No existing test asserts these values. Could add narrowly scoped dictionary-value regression, then run `node --test tests/zafira-i18n.test.mjs` (not browser evidence). |
| **F6** | IT “Scheda artigianale” casing: **CONFIRMED copy inconsistency**; only service_4 title has lowercase second word. | `public/js/zafira-core.js:838,844,847` shows IT title values `Styling Privato`, `Cura Senza Tempo`, `Scheda artigianale`; values are source-confirmed. | Product copy/style owner must confirm title-case convention before change (historic proposal said “if title case intended”). If confirmed, dictionary assertion/browser IT rendering and neighboring service titles provide proof. No current test. Do not silently promote casing preference to defect criteria. |
| **F7** | Storefront hero contrast: reported defect is **SCREENSHOT ARTEFACT**; historic background-only sampling found zero sampled points below 4.5:1, with explicit approximation limitation. | `resources/css/app.css:96–100` has white hero foreground context and `.hero-overlay` `rgba(8,16,29,0.45)`. This is not contrast evidence. | If acceptance requires revalidation: run actual rendered foreground/background contrast at representative hero text glyphs across locale and viewport, including image-dependent worst backgrounds; use WCAG-compliant per-pixel or selected worst-case measurement and disclose methodology. Historical external `triage-2.mjs` F7 samples glyph-hidden backgrounds and is explicitly approximate; do not claim WCAG pass from source or re-use approximation as definitive. |
| **F8** | Prototype announcement truncation: **CONFIRMED visible clipping**, but not text-element ellipsis; old measurement selected hidden/unmeasurable node and visible screenshot was only proof. | `prototype/index.html:73` marks visible announcement copy. `prototype/app.css:295–306` styles center copy with `white-space:normal`, `overflow:visible`; mobile parent `.announcement` is `overflow:hidden` at 2119 onward. Text can still be clipped by available flex/parent geometry. | Render visible `[data-i18n=announcement]` at EN/ID/FR/IT, 1366/1440/1650 and relevant narrow widths; record its own rect/client/scroll dimensions and parent clipping bounds, screenshot. Existing external `triage-2.mjs` F8 block is old, port-8015-bound, and its historical selector returned a hidden/unmeasurable node — do not describe that selector as corrected. Existing `sweep-15-final2.mjs` can capture viewport images, not dedicated text clipping measurements. |
| **F9** | IT sapphire heading wraps: **CONFIRMED wrapping, not overflow; not a defect by itself**. Historic 1024 and 1366 rendered six lines, no x/y overflow. | Storefront heading, not prototype: `resources/views/welcome.blade.php:359` binds `maison_title`; `public/js/zafira-core.js:799` IT value is `Come lo zaffiro,<br>la vera bellezza<br>nasce nella maestria.`. Source alone cannot establish rendered line count or overflow. | Browser storefront IT at 1024 and 1366 (route `/`, `[data-i18n="maison_title"]`): measure rendered heading line boxes, container bounds, scroll/client overflow, and screenshot. Call wrap defect only if owner supplies an additional design requirement (e.g. max lines); original rubric says no fix by itself. No existing focused test. |

## Existing browser harness safety and command inventory

- Parent acceptance browser harness is `scripts/amadis/browser-lifecycle.cjs`; it owns port `8016`, checks that port is unused before spawning its own detached launcher process, stops only its own process group, and captures lifecycle proof. Current launcher and harness hashes are recorded in `../final-gate/source-hashes.stdout` (historically captured hash values); current audit hashes were: `scripts/amadis/launch.py` `126848b8fa9b4a8e209b1aad8caf27b917203c6c37e9c1a26cc28be59fea1926`, `scripts/amadis/browser-lifecycle.cjs` `41cd0690a8ce6a1c26d4f36ee9760a263e8923f748be5b3f669ec6ac4212e9e4`.
- Launcher usage `scripts/amadis/launch.sh serve [PORT]` defaults to port 8016 and allows validated non-reserved ports; parent acceptance harness is expressly 8016. Do not collide with that agent. F1–F9 require storefront/prototype browser surfaces, while lifecycle harness is AMADIS `/koleksi` S0–S5, so its PASS would not prove F items.
- Existing browser measurement scripts are outside repository under `.../T/opencode/zafira-qa/` and hard-code port 8015. Existing result output under triage-final3 is historical and timestamped 2026-10-12. No safe, repo-owned, F1–F9 browser runner at 8016 was found. Acceptance agent should own any new command/script and ensure safe root/port/process cleanup; no browser/server was started in this audit.
- Existing repo checks are source/structure tests only: `node --test tests/prototype-header-1025.test.mjs tests/prototype-dock.test.mjs tests/zafira-i18n.test.mjs`. This command was **not run** here and cannot establish browser acceptance.

## PHP standard/random assertion delta

Gate evidence in `../final-gate/php-test-standard.stdout` and `php-test-random.stdout` shows both runs: 158 passed, rc 0. Standard reports 1,253 assertions; randomized seed `20261013` reports 1,252. Per-test list diff shows identical 20 test classes and 158 test cases. Only a duration changes conspicuously: `AdminOrderReportTest::test_pdf_service_embeds_unicode_font_and_sanitizes_html_and_unicode` took 22.51s standard and 0.10s random.

Likely concrete cause, source-supported: `tests/Feature/AdminOrderReportTest.php:56–77` has a static `$binary` cache and calls `swiftc` only if temp executable is absent. Standard run likely compiled the helper and asserted compile rc (one extra PHPUnit assertion); randomized run then reused the binary and skipped that assertion. At the audit, isolated root contains executable `zafira-pdf-text-1dc58dc2e7152662abef0994762b5fc4e5f621ec`, matching SHA-1 of `tests/Support/pdf-text.swift`; its mtime is Oct 13 04:18, while standard output file was written 04:18 and random 04:19. Evidence strongly supports cache-hit explanation, but logs do **not** attribute the assertion count to a specific assertion. Do not state causal certainty without isolated rerun evidence.

Small proof (not run). Launcher overrides `TMPDIR` to `$AMADIS_ROOT`, so the cached helper lives per root:

- **Fresh root** (no cached binary): test compiles helper → expected to include the compile-rc assertion.
- **Repeated root** (binary already present): compile skipped → expected exactly one fewer assertion.

Narrow JUnit command (unexecuted; requires acceptance-agent authorization and an approved fresh `amadis-*` root and output path):

```sh
AMADIS_ROOT=<fresh-approved-root> scripts/amadis/launch.sh test \
  --filter=test_pdf_service_embeds_unicode_font_and_sanitizes_html_and_unicode \
  --log-junit=<allowed-output-path>/pdf-fresh.xml
# repeat with same AMADIS_ROOT, output pdf-repeat.xml
```

Compare `assertions` attribute on the `<testcase>` in both XML files. Expected: fresh = repeat + 1. Confirm launcher forwards `--filter`/`--log-junit` to PHPUnit before relying on output. Do not delete shared cached binaries or run the full gate. Worktree has untracked test/helper files; preserve them.

## Bounded recommendations / limits

1. Acceptance agent should retest F1/F2/F3/F4/F5/F8 with measured browser evidence; F7 only with stated contrast method. F6 needs owner decision on copy style. F9 requires evidence of actual overflow or a new explicit line-count rubric before remediation.
2. Do not call any item PASS from this source audit, existing source tests, screenshots from another date, or AMADIS S0–S5 lifecycle results.
3. Do not launch browser/server from this subagent. Port 8016 belongs to another acceptance agent; port 8015 belongs to historical triage scripts.
4. Skipped here: no frontend source changes, no shared docs changes, no browser execution, no PHP/JUnit test rerun, no full gate, no operational DB, no commit.
