# Sweep 7 — Visual Screenshot Inspection

Source: `shots-sweep-7/` (captured by `sweep-7.mjs`, 56 cases, 0 failures).
Scope: 8 required screenshots (store + prototype × id/fr × 390/1440).
Method: direct visual inspection of each full-page capture at native resolution.

## Per-screenshot findings

| Screenshot | What is visible | Overlap / clipping findings |
| --- | --- | --- |
| `prototype-id-390.png` | Full prototype storefront in Indonesian at 390px: hero portrait, "Née de la Lumière" hero, category pills, product rows, manifesto band, Koleksi Kriya grid, packaging section, footer. | Floating dock sits over the hero image area and its lower edge touches the hero paragraph block — the ~3.4px overlap measured at scroll 0. No text clipping; no horizontal overflow. |
| `prototype-fr-390.png` | Same prototype layout in French; translated hero, nav labels, product metadata and footer all render. | Identical dock-to-hero-paragraph proximity as the id variant. No clipped or truncated French strings despite longer words. |
| `prototype-id-1440.png` | Desktop prototype in Indonesian: split hero (image left, copy right), dock pinned centre-bottom of the hero band, category pill row, editorial product blocks, dark manifesto band, 6-card Koleksi Kriya grid, packaging panel, dark footer. | Dock overlays the lower hero image region and sits near the hero copy column — matches the 21px desktop overlap at 1366px class. All cards and prices fully visible; no clipping. |
| `prototype-fr-1440.png` | Desktop prototype in French with the same structure; full French nav, product descriptions and footer link columns. | Same dock position over hero. Longer French labels (e.g. "Écrin Signature & Sceau de Cire d'Or") wrap cleanly to two lines without clipping. |
| `store-id-390.png` | Live store surface in Indonesian at 390px: hero, "Temukan dunia Anda" category tiles, product grid, sapphire narrative band, three-territories section, personalisation block, packaging, footer. | No dock present on this surface (prototype-only component). No overlap, clipping or overflow observed. |
| `store-fr-390.png` | Same store surface in French with translated headings ("Découvrez votre univers", "Objets de désir et de grâce") and footer. | No dock. Clean section spacing; no overlap or clipping. |
| `store-id-1440.png` | Desktop store in Indonesian: full-bleed hero with centered copy, marquee strip, category mosaic, 12-card product grid, sapphire narrative, "Tiga tanah, satu Maison" tri-column, personalisation, packaging, songket band, footer. | No dock. Hero copy fully legible over the image; grid aligned; no clipping or overflow. |
| `store-fr-1440.png` | Same desktop store in French with translated headings and identical structure. | No dock. French headings wrap correctly; no clipping or overlap. |

## Summary

- Dock overlap is confined to `/prototype/` only; the live store surface has no floating dock and no overlap findings.
- No text clipping, truncation or horizontal overflow found in any of the 8 screenshots, in either language.
- Footer renders in ivory on sapphire across all captures (the earlier browser-blue link regression stays fixed).

## Related measurements

Numeric overlap evidence is in `dock-run8-before.json` (baseline) and `dock-run8-final.json`
(after the `.hero-text-pane { padding-top: 112px; }` fix in the `max-width: 768px` block).
