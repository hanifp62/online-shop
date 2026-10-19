# Sweep 9 — Full Visual Inspection

Source: `shots-sweep-9/` — **56 files** (4 langs x 7 viewports x 2 surfaces). Count confirmed = 56.

Method / limits (honest): every file viewed via composite sheets (top 1000px crop of all 4 langs per viewport, full-page thumbnails, bottom/footer crops) plus native crops for suspect areas. Numeric checks via live DOM at 8015 (dock rect, hero overlap, placeholder width) for 390/1024/1366/1440/1650. Not measured numerically: 320/768 dock overlap, colour contrast ratios. Prototype drawer/menu is not present in these shots (closed state only).

## Confirmed defects

| ID | Sev | Files | Defect |
|---|---|---|---|
| D1 | Med | store-{id,en,fr,it}-{320,390,768,1024}.png (16) | Lower product-grid cards (Minaudiere Saphir, Manteau Firenze, Carre Lumiere, Montre Nocturne, Kebaya Lumiere, Echarpe Eclat, Robe Aurore) show blank beige placeholders. Fine at 1366/1440/1650. Likely lazy-load not triggered by fullPage capture; if real, product images missing on <=1024. |
| D2 | Med | prototype-*-1024.png (4, all langs); prototype-{en,fr,it}-{1366,1440,1650}.png (9) | Header search placeholder truncated. 1440/1650/1366: needs 211-219px, has 140 ("Search silk, weaving, c…", "Rechercher soie, tissa…"). 1024: input 100px wide, ~6 chars visible, all langs incl. id. Noted-at-1440 issue NOT fixed. id fits only borderline (138/140). |
| D3 | Low | prototype-{id,en,fr,it}-1366.png | Fixed dock (y 686-744 in 768 viewport) covers the Paris/Lyon/Biella provenance coordinate strip. Hero copy/CTA overlap with dock = 0 (confirmed). |
| D7 | Low | prototype-en-1366.png | PNG width 1367 at a 1366px viewport: 1px horizontal overflow. Header crowded: nav "SERVICES" nearly touches ZAFIRA wordmark, logo subtitle wraps to 2 lines. Profile button remains visible; no confirmed button clipping. |
| D8 | Low | prototype-*-1440.png, -1650.png | Dock overlays catalog control bar (Tampilan editorial/view buttons) in first viewport. Intentional floating UI; note only. |
| D4 | Med | store-*-* (all 28) | Footer links, tagline and small legal text are dim grey on near-black (#1b1b19); appears low contrast. Not measured numerically; likely from the contrast CSS changes going the wrong way on dark footer. |
| D5 | Med | store-{id,fr,it}-*.png (all widths) | Footer tagline "Parisian in spirit. Italian by craft. Indonesian at heart." stays English in id/fr/it (en correct). |
| D6 | Low | all store and prototype | Appearance select reads "Mode Terang (Clair)" in every language (mixed id+fr, not localised). |

## Specific checks requested

- Desktop hero at 1366 (prototype): 0 overlap with dock. PASS (hero text pane + CTAs end at y 587; dock starts 686).
- Prototype 390 title/dock: dock y 778-832, measured text overlap 0 for id/en/fr/it; titles wrap cleanly, no clipping. PASS.
- Search placeholder truncation at 1440 prototype: STILL PRESENT for en/fr/it (D2).
- 44px CSS: header controls (search 44px tall, buttons) look un-clipped; no layout shift visible. Contrast: footer on store reads too dim (D4); no other text too dark observed.
- Empty/broken regions: only D1.
- Language correctness: hero/nav/section headings correct in all 4 langs on both surfaces; exceptions D5, D6; brand strings "LA LETTRE ZAFIRA", "SAVOIR-FAIRE" remain French in id/en/it footers (likely intentional brand terms).

## Per-file lines

| File | Dimensions | Bytes | Observation |
|---|---|---|---|
| `prototype-en-1024.png` | 1024x10281 | 8296104 | Split hero ok, dock over hero image only. D2: search input 100px wide, placeholder shows ~6 chars. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-1366.png` | 1367x6152 | 5170061 | Hero 0 overlap with copy/CTA (CTA bottom 587, dock top 686). D3: dock covers provenance strip. D7: PNG width 1367 (1px overflow); nav 'SERVICES' crowded near logo. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-1440.png` | 1440x6214 | 5506914 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-1650.png` | 1650x6214 | 5682173 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-320.png` | 320x11162 | 2125975 | Stacked hero ok, en title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-390.png` | 390x11282 | 3021817 | Stacked hero ok, en title wraps cleanly, no clipping. Dock 778-832 measured, 0 text overlap, drawer not in shot. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-en-768.png` | 768x13951 | 9388459 | Stacked hero ok, en title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-1024.png` | 1024x10293 | 8286993 | Split hero ok, dock over hero image only. D2: search input 100px wide, placeholder shows ~6 chars. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-1366.png` | 1366x6138 | 5170482 | Hero 0 overlap with copy/CTA (CTA bottom 587, dock top 686). D3: dock covers provenance strip. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-1440.png` | 1440x6214 | 5500200 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-1650.png` | 1650x6214 | 5674028 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-320.png` | 320x11385 | 2119275 | Stacked hero ok, fr title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-390.png` | 390x11410 | 3020328 | Stacked hero ok, fr title wraps cleanly, no clipping. Dock 778-832 measured, 0 text overlap, drawer not in shot. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-fr-768.png` | 768x13892 | 9380420 | Stacked hero ok, fr title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-1024.png` | 1024x10293 | 8283150 | Split hero ok, dock over hero image only. D2: search input 100px wide, placeholder shows ~6 chars. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-1366.png` | 1366x6138 | 5166165 | Hero 0 overlap with copy/CTA (CTA bottom 587, dock top 686). D3: dock covers provenance strip. Search placeholder fits (138/140, borderline). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-1440.png` | 1440x6214 | 5497587 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. Search placeholder fits (138/140, borderline). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-1650.png` | 1650x6214 | 5669193 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. Search placeholder fits (138/140, borderline). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-320.png` | 320x11240 | 2120752 | Stacked hero ok, id title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-390.png` | 390x11333 | 3012935 | Stacked hero ok, id title wraps cleanly, no clipping. Dock 778-832 measured, 0 text overlap, drawer not in shot. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-id-768.png` | 768x13951 | 9377924 | Stacked hero ok, id title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-1024.png` | 1024x10306 | 8283669 | Split hero ok, dock over hero image only. D2: search input 100px wide, placeholder shows ~6 chars. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-1366.png` | 1366x6168 | 5165636 | Hero 0 overlap with copy/CTA (CTA bottom 587, dock top 686). D3: dock covers provenance strip. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-1440.png` | 1440x6244 | 5497399 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-1650.png` | 1650x6244 | 5671142 | Hero split ok, header aligned, no overlap. D8: dock sits over catalog control bar at first viewport. D2: search placeholder truncated (need ~211-219px, have 140). Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-320.png` | 320x11232 | 2114939 | Stacked hero ok, it title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-390.png` | 390x11360 | 3012005 | Stacked hero ok, it title wraps cleanly, no clipping. Dock 778-832 measured, 0 text overlap, drawer not in shot. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `prototype-it-768.png` | 768x13951 | 9377154 | Stacked hero ok, it title wraps cleanly, no clipping. Dock not numerically measured; looks clear at thumbnail scale. Footer ok; D6 'Mode Terang (Clair)' unlocalised. |
| `store-en-1024.png` | 1024x11003 | 3653752 | Header/hero/en copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-1366.png` | 1366x9807 | 5397973 | Header/hero/en copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-1440.png` | 1440x10083 | 5851304 | Header/hero/en copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-1650.png` | 1650x10321 | 6432284 | Header/hero/en copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-320.png` | 320x13733 | 1495916 | Header/hero/en copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-390.png` | 390x13899 | 1907503 | Header/hero/en copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-en-768.png` | 768x14540 | 3990134 | Header/hero/en copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-1024.png` | 1024x11161 | 3698721 | Header/hero/fr copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-1366.png` | 1366x9888 | 5439100 | Header/hero/fr copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-1440.png` | 1440x10168 | 5882803 | Header/hero/fr copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-1650.png` | 1650x10378 | 6464659 | Header/hero/fr copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-320.png` | 320x14112 | 1530129 | Header/hero/fr copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-390.png` | 390x14081 | 1938546 | Header/hero/fr copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-fr-768.png` | 768x14597 | 4014273 | Header/hero/fr copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-1024.png` | 1024x11052 | 3659143 | Header/hero/id copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-1366.png` | 1366x9785 | 5391036 | Header/hero/id copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-1440.png` | 1440x10046 | 5847101 | Header/hero/id copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-1650.png` | 1650x10321 | 6425545 | Header/hero/id copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-320.png` | 320x13806 | 1500101 | Header/hero/id copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-390.png` | 390x13872 | 1910152 | Header/hero/id copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-id-768.png` | 768x14634 | 3992191 | Header/hero/id copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-1024.png` | 1024x11100 | 3661436 | Header/hero/it copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-1366.png` | 1366x9954 | 5402507 | Header/hero/it copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-1440.png` | 1440x10099 | 5854632 | Header/hero/it copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-1650.png` | 1650x10362 | 6436296 | Header/hero/it copy intact, no overflow or clipped text. D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-320.png` | 320x13945 | 1504369 | Header/hero/it copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-390.png` | 390x13964 | 1908039 | Header/hero/it copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
| `store-it-768.png` | 768x14567 | 3986555 | Header/hero/it copy intact, no overflow or clipped text. D1: lower product-grid cards render blank beige (images not loaded). D5: footer tagline English + dim grey on near-black (D4). D6: 'Mode Terang (Clair)' unlocalised. |
