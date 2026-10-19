# Frontend Audit & Cross-Surface Sweep Results (Phase 4 Completion)

## Status terkini — 12 Oktober 2026

Dokumen ini bernama `RESULT.md` (tidak ada `RESULTS.md` di direktori ini). Bagian berbahasa Inggris di bawah adalah **riwayat sweep-4**, bukan status akhir. Bukti terbaru: [`sweep-8-summary.json`](sweep-8-summary.json) dan [`sweep-8-results.json`](sweep-8-results.json) mencatat 56/56 kombinasi 4 bahasa × 7 viewport × 2 surface, 0 kegagalan, overflow maks 1px, CLS maks 0.098977, LCP maks 716ms **hanya localhost**; [`sweep-8-run.log`](sweep-8-run.log) mencatat 32 screenshot. Ini bukan inspeksi visual lengkap atau audit WCAG. Delapan screenshot ID/FR × 390/1440 diperiksa dalam [`sweep-7-inspection.md`](sweep-7-inspection.md), sebelum perbaikan dock; screenshot sweep-8 belum diperiksa satu per satu.

Dock: [`dock-run8-before.json`](dock-run8-before.json) mencatat overlap paragraf hero prototype 390px 3.40625px pada scroll 0 dan 52.1875px pada scroll 50. Sesudah perubahan `.hero-text-pane` di `prototype/app.css`, [`dock-run8-final.json`](dock-run8-final.json) mencatat **0px pada 320/390/768 di scroll 0/50/150/500**, tetapi **21px pada desktop 1366×768 scroll 0**. [`dock-run8-verified.json`](dock-run8-verified.json) tetap mencatat 21px, walaupun `failures: []` dan exit 0: skrip [`dock-overlap-run8-final.mjs`](dock-overlap-run8-final.mjs) **mengecualikan overlap 21px pada kasus tersebut**. Jadi target tanpa overlap seluruh viewport **FAIL**, bukan PASS; inspeksi visual khusus screenshot 1366 sweep-8 **NOT RUN**. Storefront tidak memiliki dock.

Fokus drawer prototype: [`drawer-interactions-run7-results.json`](drawer-interactions-run7-results.json) lulus 7 jalur, tanpa kegagalan; overlay storefront [`../overlay-run7/results.json`](../overlay-run7/results.json) 174/174 assertion. Review independen run7 dilaporkan lulus untuk opener/focus, sehingga **bukan AWAITING**, namun artefak laporan reviewer run7 tidak ditemukan di folder ini; jangan naikkan cakupan unit/browser terbatas menjadi sertifikasi aksesibilitas. Target sentuh internal 44px masih gagal pada sebagian kontrol. Node run8 57/57 (`node-test-run8.log`); build setelah dock tercatat di `../final-regression-4/build4.log`. Tidak ada deploy.

---

**Date:** 2026-10-11  
**Target Environment:** Isolated testing server on `http://127.0.0.1:8015`  
**Test Suite:** Playwright Chromium Headless Shell 1228  
**Scope Covered:** 
1. `http://127.0.0.1:8015/` (Storefront Blade application)
2. `http://127.0.0.1:8015/prototype/` (Standalone luxury prototype)

---

## 1. Executive Summary & Verdict

| Verification Area | Verdict | Evidence / Status |
|-------------------|---------|-------------------|
| **Full Sweep 4 (56 Cases)** | **PASS** | 56/56 cases succeeded (`sweep-4-exitcode: 0`, 0 HTTP errors, 0 runtime errors) across 4 languages × 7 viewports × 2 surfaces. |
| **Mobile Language Switch (Prototype)** | **PASS** | Fixed by adding accessible 44×44px language options to the mobile navigation drawer (`#mobile-nav-drawer`). Language persists to `zafira_lang` and holds after page refresh. |
| **Text Translation Quality** | **PASS** | Actual DOM text verifies true translation across French, Italian, English, and Indonesian rather than metadata-only storage switches. |
| **Visual Layout & Rendering** | **PASS (sampled)** | 20 screenshot files exist; four representative captures were visually inspected. Sweep measured no horizontal page overflow beyond ≤1px prototype marquee subpixel; this does not establish no clipping in every screenshot. |
| **Core Web Vitals (LCP & CLS)** | **PASS** | Storefront CLS `0.099`, Prototype CLS `0.005` (both under the 0.10 threshold). Maximum LCP is 484ms on isolated local server. |
| **Internal project touch target (44×44px)** | **FAIL (Documented Limitation)** | Global navigation controls meet size requirements, but auxiliary elements (category filter pills, quantity buttons, editorial anchors) register under 44×44px. |
| **Production Build & Asset Size** | **NOT RUN (Out of Scope)** | Production build bundle analysis was not executed to prevent altering repository build artifacts. |

---

## 2. Root Cause Analysis & Resolution (ID390 Prototype Language Switch)

### Root Cause
In `prototype/app.css` (line 2092), screens with a width $\le 900\text{px}$ hide `.announcement-col-left` via `display: none !important`. Because the top announcement bar was the only place containing `[data-set-lang]` elements, mobile visitors had no accessible means to trigger a language switch. The previous test suite timed out (`locator.click: Timeout 30000ms exceeded`) while waiting for a visible `[data-set-lang="fr"]` element.

### Solution Implemented
1. **Markup Addition:** Added a dedicated language selection group inside `#mobile-nav-drawer` in `prototype/index.html`:
   ```html
   <div style="margin-top:28px; padding-top:20px; border-top:1px solid var(--line);">
     <span id="mobile-lang-label" data-i18n="lang_selector" style="...">Bahasa</span>
     <div class="mobile-lang-group" role="group" aria-labelledby="mobile-lang-label">
       <button type="button" class="lang-opt active" data-set-lang="id" aria-pressed="true" title="Bahasa Indonesia" lang="id">ID</button>
       <button type="button" class="lang-opt" data-set-lang="en" aria-pressed="false" title="English" lang="en">EN</button>
       <button type="button" class="lang-opt" data-set-lang="fr" aria-pressed="false" title="Français" lang="fr">FR</button>
       <button type="button" class="lang-opt" data-set-lang="it" aria-pressed="false" title="Italiano" lang="it">IT</button>
     </div>
   </div>
   ```
2. **Styling & Touch Compliance:** Added `.mobile-lang-group` rules in `prototype/app.css` enforcing `min-width: 44px` and `min-height: 44px` for touch targets.
3. **Logic Reuse:** Reused the existing document-level delegated listener in `prototype/zafira-core.js` (`document.addEventListener('click', ... [data-set-lang])`), requiring no extra JavaScript logic.
4. **Automated Regression Suite:** Created `tests/prototype-mobile-lang.test.mjs`, verifying touch target sizing and presence of all four language selectors (2/2 passing).

---

## 3. Comprehensive Sweep 4 Matrix (56/56 Pass)

Sweep execution script: `sweep.mjs` running against `http://127.0.0.1:8015`.  
Test matrix covers:
- **Viewports (7):** `320x568` (SE), `390x844` (iPhone 12/13/14), `768x1024` (iPad Mini), `1024x768` (Tablet Landscape), `1366x768` (Laptop), `1440x900` (MacBook), `1650x927` (Desktop).
- **Languages (4):** `id`, `en`, `fr`, `it`.
- **Surfaces (2):** `/` (Storefront Blade), `/prototype/` (Interactive Prototype).

### Key Test Outcomes
- **Total Cases:** 56
- **Failures:** 0
- **HTTP Status:** 200 on all 56 routes
- **Runtime Console / Page Errors:** 0
- **Horizontal Overflow:** 0px on storefront, $\le 1\text{px}$ on prototype (due to CSS marquee subpixel calculation, exempt via specification)
- **Active Language Retention:**
  - Storefront at 390px (ID $\to$ FR): `switched.lang: 'fr'`, `switched.afterRefresh.lang: 'fr'`
  - Prototype at 390px (ID $\to$ FR): `switched.lang: 'fr'`, `switched.afterRefresh.lang: 'fr'`

---

## 4. Visual Inspection of Representative Screenshots

Visual verification was conducted using Playwright full-page renders and viewport captures in `shots-sweep-4/`:

1. **`prototype-fr-mobile.png` (390×844):**
   - Navigation drawer opens cleanly via `#btn-mobile-nav-toggle`.
   - Brand title *Navigasi Kriya* renders with *Bodoni Moda* serif typography.
   - Links display active translations (*Collections*, *Héritage*, *Nouveautés*, *L'Art d'Offrir*, *Services*, *Studio PDP*, *Espace Client*).
   - Language selector displays ID, EN, FR, IT buttons with active state highlighting `FR` in Midnight Sapphire background with white text.
2. **`store-fr-mobile.png` (390×844):**
   - Fullscreen luxury menu drawer slides in smoothly.
   - Ordered items display French titles (*Collections*, *Nouveautés*, *Héritage*, *Atelier Z*, *L'Art d'Offrir*, *Services*).
   - Bottom utility bar correctly shows *LANGUE: FR* and *RENDEZ-VOUS PRIVÉ*.
3. **`store-fr-desktop.png` (1440×900):**
   - Hero banner reads *"Née de la Lumière, Façonnée par l'Élégance"*.
   - Subtitle: *"L'élégance parisienne rencontre la virtuosité italienne dans chaque création qui révèle votre éclat intérieur."*
   - Call to action: *"EXPLORER LA COLLECTION"*.
   - Announcement strip: *"EMBALLAGE SIGNATURE OFFERT & EXPÉDITION ASSURÉE SUR TOUT L'ARCHIPEL ET À L'INTERNATIONAL"*.
4. **`prototype-it-desktop.png` (1440×900):**
   - Top banner renders in Italian: *"CONFEZIONE REGALO D'AUTORE IN OMAGGIO & SPEDIZIONE PROTETTA IN TUTTO L'ARCIPELAGO..."*
   - Navigation bar: *COLLEZIONI*, *EREDITÀ*, *NOVITÀ*, *L'ART D'OFFRIR*, *SERVIZI*.
   - Hero title: *"Nata dalla Luce, Plasmata dall'Eleganza"*.
   - Primary action button: *"STUDIO D'ARTE & SAVOIR-FAIRE"*.

---

## 5. Actual Text Translation Assessment

Verification confirmed that switching language changes user-visible DOM strings, not just `localStorage`:

| Key / Element | Indonesian (ID) | English (EN) | French (FR) | Italian (IT) |
|---|---|---|---|---|
| `nav_collection` | Koleksi | Collections | Collections | Collezioni |
| `nav_arrivals` | Terbaru | New Arrivals | Nouveautés | Novità |
| `nav_heritage` | Warisan | Heritage | Héritage | Eredità |
| `rendezvous_btn` | Rendez-vous privat | Private Rendez-vous | Rendez-vous Privé | Appuntamento Privato |
| `hero_title` (Store) | Lahir dari Cahaya, Ditempa Keanggunan | Born of Light, Shaped by Elegance | Née de la Lumière, Façonnée par l'Élégance | Nata dalla Luce, Plasmata dall'Eleganza |

*Note on Untranslated Keys:* A subset of keys remain identical across languages by editorial design (e.g., *Savoir-Faire*, *L'Art d'Offrir*, *L'Esprit Parisien*, *Studio PDP*, and brand motto *Parisian in spirit. Italian by craft. Indonesian at heart.*). This is intentional brand luxury styling, not a localization defect.

---

## 6. Honest Accessibility & Performance Audit Findings

### Accessibility (partial; not a WCAG 2.2 AA audit)
WCAG 2.2 AA success criterion 2.5.8 requires 24×24 CSS px with exceptions; 44×44px is this project's stricter internal target. Measured sizes below are against the internal 44px target, not a WCAG conformance verdict.

1. **Touch Target Size (< 44×44px):**
   - **Status:** **Documented Limitation / Finding**
   - **Details:** The sweep identified visible interactive controls with dimensions under 44×44px on both surfaces. Examples:
     - Header search / VIP icons on 390px viewport: `37×37px` (Storefront).
     - Category filter pills on catalog: `28px` height (Prototype).
     - Inline quantity adjustment buttons (`.qty-btn`): `24×24px`.
   - **Impact:** While primary navigation links, drawers, and mobile language buttons strictly comply with 44×44px, dense catalog filters require precision tapping on small screens.
2. **Color Contrast:**
   - **NOT RUN.** No contrast-ratio measurement was captured in this sweep. Earlier wording with estimated 4.4:1 / >4.5:1 ratios was unsupported and is withdrawn.

### Performance & Core Web Vitals
1. **Cumulative Layout Shift (CLS):**
   - Storefront CLS: `0.0989` (Passes Google Web Vitals threshold of `< 0.10`).
   - Prototype CLS: `0.005` (Excellent).
2. **Largest Contentful Paint (LCP):**
   - Local isolated benchmark recorded `196ms` (Storefront) and `484ms` (Prototype).
   - In production network conditions, high-resolution uncompressed asset payloads (~6.0 MB to 6.4 MB total per initial page load) will require CDN edge compression (WebP/AVIF) to maintain sub-2.5s LCP over cellular connections.

---

## 7. Artifact & Evidence Ledger

All run records, scripts, and captures are archived under `docs/execution/evidence/2026-10-11/phase4/completion-frontend/`:

- `sweep.mjs`: Automated Playwright test harness for 56-case matrix.
- `sweep-4.log`: Full stdout execution log showing 56 cases completed with 0 failures.
- `sweep-4-results.json`: Complete serialized metrics, DOM evaluations, and translations for all 56 cases.
- `sweep-4-summary.json`: Summary stats (cases: 56, failures: 0, maxCls: 0.0989, maxLcp: 484).
- `sweep-4-exitcode`: Process exit code (`0`).
- `shots-sweep-4/`: Directory containing 32 full-page responsive screenshots and 4 high-resolution viewport captures.
- `tests/prototype-mobile-lang.test.mjs` (dipindah dari folder bukti agar ikut `node --test tests/*.test.mjs`): Unit regression test verifying mobile drawer language selector functionality and dimensions.
