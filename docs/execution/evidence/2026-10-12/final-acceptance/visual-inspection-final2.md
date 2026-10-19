# Visual Inspection — shots-sweep-15-final2

**Date:** 2026-10-12
**Source:** `docs/execution/evidence/2026-10-12/final-acceptance/shots-sweep-15-final2/` (56 PNG, full-page captures)
**Method:** Each PNG opened with the image read tool and checked by eye. Dimensions come from `sips`. No source, DB, `.env`, server or git changes.

## Honesty note

The tool session dropped some image payloads before the model saw them. Only files whose pixels the model actually saw are marked **SEEN**. Files marked **NOT SEEN** were opened, but the pixels never reached the model. Nothing is claimed about them beyond dimensions. See the counts at the end.

Note: the captures are full-page images up to 14,688 px tall, shown downscaled. Small text such as prices, micro-labels and the footer copyright cannot be read at this scale. Findings cover layout, composition, imagery, headings and language only.

---

## A. Prototype (`prototype-*`)

### prototype-en-1440.png — 1440×6254 — SEEN
- Hero is split: model in kebaya on the left, text on the right ("LA COLLECTION PREMIÈRE · 2026", "Born of Light, Shaped by Elegance", two CTAs).
- **DEFECT (header/clipping):** the sticky header bar (COLLECTIONS · HERITAGE · NEW ARRIVALS · L'ART D'OFFRIR · SERVICES · ZAFIRA · Search · Private Rendez-vous · Shopping Bag · Profile) is rendered across the middle of the hero. It cuts through the headline: "Shaped by Elegance" is half hidden, with only the top of the glyphs visible.
- **DEFECT (dock overlap):** the floating dark dock (ZAFIRA · Multi-Lang · Boutique · Studio PDP · VIP Account · Atelier) sits over the Kebaya Lumière detail block. It covers the end of the provenance line and the top of the "Kebaya Lumière" title.
- Top announcement bar text is truncated ("…ACROSS TH…").
- **DEFECT (language, EN page):** Indonesian copy appears on the English page: the italic quotes ("Bordir tambah…", "Canting malam 9 celupan…"), the "Setiap jahitan adalah dialog abadi…" manifesto, the stats labels ("BORDIR TANGAN | HELAI ADIBUSANA", "SERAT ALAMI & PEWARNA NABATI", "SINERGI ATELIER DUA BENUA"), "Koleksi Kriya Pilihan", the "Minaudière Saphir" card description, "TAMPILAN EDITORIAL / TAMPILAN GALERI", "60+ Jam", "3 Minggu / 9 Kali", and the gift note.
- Coordinates strip, filter pills, two editorial product sections (Kebaya Lumière, Chemise Rivière), 6-card grid, gift packaging section and dark footer all render. All product images load and none look broken.
- Footer has 4 columns, a newsletter field with a gold SUBSCRIBE button, "International · English" and "Light Mode" selects. Large empty ivory area below the footer.

### prototype-en-1366.png — 1366×6164 — SEEN
- Same structure as 1440.
- The header bar crosses the bottom part of the hero image, below the CTAs. The headline is fully visible here.
- **DEFECT:** the dock covers the Kebaya Lumière title/quote area. The kebaya quote is partly hidden.
- Indonesian copy on the EN page, as at 1440.
- Grid has 3 columns. Footer OK.

### prototype-en-1650.png — 1650×6254 — SEEN
- **DEFECT:** the header bar crosses the hero and hides the CTA buttons. Only the gold bar edge of "Craft Studio" and the outline of the second CTA show under the header.
- **DEFECT:** the dock overlaps the Kebaya Lumière text block. The quote line is covered.
- Content is centred with wide ivory margins.
- Mixed EN/ID copy, as above.
- Images OK. Footer OK.

### prototype-en-1024.png — 1024×9289 — SEEN
- Hero image sits at the top. The text block (headline + 2 CTAs) is below it, then the coordinates strip and filter pills.
- **DEFECT (header crowding):** the header bar sits under the filter pills, partway down the page. Nav items and "ZAFIRA" are cramped. "SERVICES" butts against the logo, and the right-side actions are compressed.
- **DEFECT:** the dock sits on top of the Kebaya hero image, in the upper-left area.
- Product grid has 2 columns. The Indonesian copy issue persists. Footer OK.

### prototype-en-768.png — 768×13951 — SEEN
- Hero image with the text below it.
- The header strip appears below the coordinates strip/filters, not at the top of the page. A dark bar (dock/header) is visible near the top filter row.
- Products are 1 column with large images. Images load.
- Indonesian quote and stats on the EN page.
- The gift section heading "Signature Gift Packaging & Gold Wax Seal" wraps with "Seal" alone on the second line (minor orphan).
- Footer OK.

### prototype-en-390.png — 390×11282 — SEEN
- Single column. The header ("ZAFIRA" bar with icons) appears below the hero CTAs, not at the top.
- The filter pills wrap onto 3 rows.
- Very long stacked page. The "Setiap jahitan…" quote takes about one screen height in large serif type.
- Images load and none clip horizontally.
- Footer link list stacks. No horizontal overflow visible.

### prototype-en-320.png — 320×11162 — SEEN
- Same as 390, but narrower.
- Text sizes look large relative to the width. The manifesto quote wraps to about 10 lines.
- No visible horizontal overflow at thumbnail scale.

### prototype-en-*: summary
The header position, dock overlap and EN/ID mixing appear at every width checked.

### prototype-fr-320 / 390 / 768 / 1024 / 1366 / 1440 / 1650 — NOT SEEN
Dimensions: 320×11385, 390×11433, 768×13892, 1024×9301, 1366×6164, 1440×6254, 1650×6254.

### prototype-id-320 / 390 / 768 / 1024 / 1366 / 1440 / 1650 — NOT SEEN
Dimensions: 320×11240, 390×11333, 768×13951, 1024×9301, 1366×6164, 1440×6254, 1650×6254.

### prototype-it-320 / 390 / 768 / 1024 / 1366 / 1440 / 1650 — NOT SEEN
Dimensions: 320×11232, 390×11382, 768×13951, 1024×9314, 1366×6194, 1440×6284, 1650×6284.

---

## B. Storefront (`store-*`)

Section order on every store page seen:
1. announcement bar
2. header (nav, centred ZAFIRA logo, icons)
3. full-bleed hero
4. region marquee
5. "Discover" category tiles (3)
6. product grid (12)
7. navy sapphire story with a man in a coat
8. "Three lands" (3 cards)
9. bag personalisation configurator
10. gift packaging
11. full-bleed Songket hero
12. 4 service cards
13. navy newsletter
14. black footer

### store-en-1440.png — 1440×10174 — SEEN
- Header is clean and at the top.
- Hero: "Born of Light, Shaped by Elegance" is centred over the image. The subtitle crosses the model's figure, so contrast is weaker there.
- Tiles: Indonesia, Evening Bags, Haute Couture Capsule.
- "Enchanting Objects of Art" grid has 4×3 cards. All 12 images load.
- Sapphire section is 2 columns. Configurator shows a brown bag with a swatch row. The gift box mock renders.
- "Songket & Silk, Two Shores." Services: Private Styling, Full Transparency, Care for Life, Craft Card.
- Newsletter "Step into the Maison." Footer OK.
- Language is consistently English. No clipping seen.

### store-en-1650.png — 1650×10421 — SEEN
- Same as 1440 with wider margins. Composition is balanced.
- The hero subtitle overlaps the model again. No defects beyond that.

### store-en-1366.png — 1366×9894 — SEEN
- Same layout. The hero headline sits over the model's torso. Contrast is acceptable.
- The tile label "Evening Bags" sits over the bag image. Grid is 4 columns. OK.

### store-en-1024.png — 1024×11025 — SEEN
- The header nav is condensed into one line.
- Hero is OK. Grid is 3 columns × 4.
- "Signature Gift Packaging & Gold Wax Seal" fits on one line. Services are 2×2. Footer OK.
- No defects seen.

### store-en-768.png — 768×14631 — SEEN
- Category tiles stack as full-width banners. Grid is 2 columns.
- The sapphire image is a tall single column, with the navy text block below it.
- Services are 2×2. Footer OK. No clipping seen.

### store-en-390.png — 390×13982 — SEEN
- Hero headline is centred over the image. Tiles are stacked. Grid is 2 columns with small text.
- Headings wrap cleanly ("Like sapphire, true beauty emerges through mastery.").
- Long single-column page. The footer renders. No horizontal overflow seen.

### store-en-320.png — 320×13757 — SEEN
- Same as 390. Headings take 3–5 lines.
- "Songket & Silk, Two Shores." is large white type over a dark image and is readable.
- No horizontal overflow seen.

### store-fr-320 / 390 / 768 / 1024 / 1366 / 1440 / 1650 — NOT SEEN
Dimensions: 320×14135, 390×14164, 768×14688, 1024×11183, 1366×9976, 1440×10259, 1650×10479.

### store-id-320 / 390 / 768 / 1024 / 1366 — NOT SEEN
Dimensions: 320×13829, 390×13929, 768×14661, 1024×11074, 1366×9872.

### store-id-1440.png — 1440×10137 — SEEN
- Hero "Lahir dari Cahaya, Dibentuk oleh Keanggunan" runs 3 lines and covers the model's head and torso.
- Tiles: Indonesia, Tas Malam, Kapsul Adibusana. "Objek-objek penuh pesona" grid is 4×3 and the images load.
- Sapphire heading: "Seperti safir, keindahan sejati lahir dalam proses."
- **DEFECT (language, ID page):** the Songket heading reads "Songket & Sutra, Deux Rives." "Deux Rives" is French. EN uses "Two Shores" and IT uses "Due Rive".
- **DEFECT (language, ID page):** two service cards are untranslated: "Private Styling" and "Care for Life". Their siblings are translated ("Transparansi Penuh", "Kartu Kriya").
- Newsletter "Masuk ke dunia Maison." Footer OK.

### store-id-1650.png — 1650×10421 — SEEN
- Same as 1440: 3-line hero headline, "Deux Rives" mix, and "Private Styling" / "Care for Life" untranslated.
- Layout is clean. No clipping.

### store-it-1650.png — 1650×10463 — SEEN
- Hero "Nata dalla Luce, Plasmata dall'Eleganza". Tiles: Indonesia, Borse da Sera, Capsula Alta Moda. Grid "Oggetti di pura grazia" is 4×3.
- Other headings: "Come lo zaffiro, la vera bellezza nasce nella maestria.", "Tre terre, una sola Maison", "Rendetela un'opera unicamente vostra.", "Scatola d'Autore & Sigillo in Cera d'Oro", "Songket & Seta, Due Rive.", "Più di una creazione, un'esperienza di grazia.", "Entrate nel mondo della Maison."
- **MINOR (copy consistency):** service title "Scheda artigianale" uses lower case. Its siblings are title case: "Styling Privato", "Trasparenza Totale", "Cura Senza Tempo".
- Footer OK.

### store-it-1440.png — 1440×10190 — SEEN
- Same as 1650. Hero headline is 2 lines over the model. The "Scheda artigianale" casing issue is present. No clipping.

### store-it-1366.png — 1366×10041 — SEEN
- Same. "Tre terre, una sola Maison" wraps to 2 lines next to the intro paragraph, which is fine.
- The sapphire heading runs about 5 lines in the navy column. That is heavy, but nothing is clipped.

### store-it-1024.png — 1024×11122 — SEEN
- Grid is 3 columns. The sapphire heading runs 6 lines ("Come lo / zaffiro, / la vera / bellezza / nasce nella / maestria.") and is very tall next to the image.
- Services are 2×2. The "Scheda artigianale" casing issue is present.

### store-it-768.png — 768×14658 — SEEN
- Tiles stack. Grid is 2 columns. "Scatola d'Autore & Sigillo in Cera d'Oro" fits on one line.
- Services are 2×2. Footer OK.

### store-it-390.png — 390×14047 — SEEN
- Single column. Headings wrap cleanly. Product grid is 2 columns. No overflow seen.

### store-it-320.png — 320×13993 — SEEN
- Same as 390. Hero copy sits over the image. Headings run 4–5 lines. No overflow seen.

---

## C. Confirmed findings (from SEEN images only)

| # | Severity | Finding | Files |
|---|---|---|---|
| 1 | High | The prototype header bar renders mid-hero or mid-page, not at the top. At 1440 it cuts through the "Shaped by Elegance" headline. At 1650 it hides the CTAs. | prototype-en-1440, -1650, -1366, -1024, -768, -390, -320 |
| 2 | High | The prototype floating dock overlaps the Kebaya Lumière title/quote (desktop) or the hero image (1024). | prototype-en-1366, -1440, -1650, -1024 |
| 3 | Medium | The EN prototype shows Indonesian copy: quotes, manifesto, stats labels, "Koleksi Kriya Pilihan", "Tampilan Editorial/Galeri", the "Jam/Minggu/Kali" units, and a card description. | prototype-en-* (all 7) |
| 4 | Medium | The ID storefront shows the French "Deux Rives" in the Songket heading. | store-id-1440, -1650 |
| 5 | Medium | The ID storefront leaves "Private Styling" and "Care for Life" in English. | store-id-1440, -1650 |
| 6 | Low | IT service card "Scheda artigianale" has inconsistent casing. | store-it-1024, -1366, -1440, -1650 |
| 7 | Low | Storefront hero headline/subtitle sit over the model's figure. Contrast drops locally. | store-en-1366/1440/1650, store-id-1440/1650 |
| 8 | Low | The prototype announcement bar is truncated with an ellipsis on desktop. | prototype-en-1366/1440/1650 |
| 9 | Low | IT sapphire heading runs 5–6 lines at 1024–1366. | store-it-1024, -1366 |

Positive: on every SEEN storefront page the header, footer, imagery (12/12 product images, tiles, editorial photos) and section spacing render without broken images or horizontal overflow.

## D. Count

- Total PNG: **56**
- Pixels actually seen by the model: **23**
  - prototype-en: 320, 390, 768, 1024, 1366, 1440, 1650 (7)
  - store-en: 320, 390, 768, 1024, 1366, 1440, 1650 (7)
  - store-it: 320, 390, 768, 1024, 1366, 1440, 1650 (7)
  - store-id: 1440, 1650 (2)
- Not seen (dimensions only): **33**
  - prototype-fr ×7, prototype-id ×7, prototype-it ×7, store-fr ×7, store-id 320/390/768/1024/1366
