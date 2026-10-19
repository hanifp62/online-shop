# Visual Inspection Notes — Sweep-14 (2026-10-12)

Directory: `shots-sweep-14/` (56 full-page PNG, ~5.5 MB each, total 309,958,546 bytes).
Files existence verified: 56/56 present (store/prototype × id/en/fr/it × 320/390/768/1024/1366/1440/1650).

## Honest scope

| Status | Count | Files |
|---|---|---|
| Directly opened & viewed | 3 | `store-id-320.png`, `prototype-en-320.png`, `store-en-1440.png` |
| NOT RUN (not opened by a human/agent viewer) | 53 | all remaining files |

Important: the 3 viewed images came from the **first** sweep-14 run (`sweep-14.log`), captured
**before** the final CSS edit (`.footer-bottom > div > :is(a, button) { min-height: 44px }`).
`sweep-14-final.log` overwrote every PNG after that edit; **0 of the final 56 PNGs were visually inspected.**
Visual pass for the final set is therefore **NOT RUN**. JSON metrics are not a substitute.

## Per-file notes (pre-final images, full-page downscaled view)

- `store-id-320.png`: single-column mobile; hero, catalog 2-col grid, sapphire sections, footer render; no visible horizontal clipping. Full-page downscale is too small to judge footer-bottom spacing or text legibility.
- `prototype-en-320.png`: prototype mobile stack renders top to footer; no visible clipping; detail not resolvable at downscale.
- `store-en-1440.png`: desktop 4-col grid, hero, editorial sections, footer present; no visible layout break. Footer-bottom row too small to judge at downscale.

## Not covered
- Per-section zoomed review of any final screenshot.
- Footer shortcuts button appearance after the min-height fix (verified only geometrically by `extra-browser.mjs`, not visually).
