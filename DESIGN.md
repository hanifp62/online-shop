# Design System: ZAFIRA (La Maison de Lumière)
# Semantic Design System for Google Stitch (labs.google/stitch)

## 1. Visual Theme & Atmosphere
An opulent yet rigorously restrained digital atelier that fuses Parisian architectural haute couture with Indonesian ancestral textile craftsmanship. The atmosphere is **"Art Gallery Airy" (Density: 3/10)**, characterized by monumental whitespace, museum-grade typography contrast, and museum-lit physical textures. The structural rhythm uses **"Offset Asymmetric" balance (Variance: 7/10)** to reject template symmetry, while motion operates under **"Fluid Spring Mechanics" (Motion: 6/10)** — weighted, deliberate, and free of playful bounce.

---

## 2. Color Palette & Roles

- **Linen Ivory Canvas** (`#F3EFE7`) — The warm, unbleached silk foundation for all customer-facing viewports. Replaces sterile digital white.
- **Pure Silk Surface** (`#FFFDF9`) — Card bodies, dropdown surfaces, and modal sheets.
- **Deep Midnight Sapphire** (`#17263F`) — Dominant brand anchor, hero backgrounds, primary buttons, and admin sidebar framework.
- **Atmospheric Sapphire** (`#263A5D`) — Secondary header elements, active states, and focused navigation links.
- **Champagne Gold Accent** (`#B69A64`) — Singular high-prestige metallic accent used strictly for call-to-actions, active indicators, monogram tags, and key metadata. Saturation is calibrated to 42% (anti-neon).
- **Pale Gold Tint** (`#DFCAA0`) — Subtle borders, hover halos, and secondary badges.
- **Charcoal Ink** (`#20201D`) — High-legibility editorial text. Deep organic graphite depth (never pure `#000000`).
- **Muted Taupe Steel** (`#6E6A62`) — Subheadings, provenance attribution, care guides, and technical specifications.
- **Whisper Hairline** (`rgba(32, 32, 29, 0.12)`) — 1px architectural divider lines separating content sections.

*Banned Colors:* Neon violet/purple glows, saturated pure greens/reds, generic bootstrap blues, and cold zinc grays are strictly forbidden.

---

## 3. Typography Architecture

- **Display & Headlines (Storefront):** `Bodoni Moda` (400, 400i, 500, 600) — Track-tight (-0.02em), editorial posture, high optical contrast between thick stems and hairline serifs. Headline hierarchy is communicated via weight and phrasing, not brute scale.
- **Body & Subtitles:** `Manrope` (300, 400, 500, 600) — Modern geometric sans-serif with open apertures and relaxed leading (1.65). Line length strictly capped at 65 characters (`65ch`).
- **Data & Administrative Dashboard:** `Manrope` for UI labels + `JetBrains Mono` / `Geist Mono` for financial figures (IDR prices), order timestamps, SKU codes, and inventory counters.
- **Banned Typography:** Generic `Inter`, `Roboto`, `Arial`, `Times New Roman`, and `Georgia` are strictly BANNED. Serif fonts are strictly BANNED in the software dashboard/table views.

---

## 4. Component Stylings

### Buttons
- **Primary Luxury Action:** Flat Champagne Gold (`#B69A64`) with Deep Midnight Sapphire text (`#17263F`). Border-radius `3px` (subtle architectural roundness). No drop shadows, no neon halos. Tactile press feedback (`transform: translateY(1px)`).
- **Secondary Action:** Transparent fill with 1px Whisper Hairline (`rgba(32,32,29,0.2)`), hover fill `rgba(182, 154, 100, 0.08)`.

### Cards & Media Vessels
- **Product & Story Vessels:** Generously proportioned with a 3:4 vertical aspect ratio. Framed by a 1px Whisper Hairline. No elevation shadows at rest; on hover, applies a diffused velvet shadow (`0 14px 36px rgba(23, 38, 63, 0.08)`).
- **Craft Overlay:** Quick View button appears as a crisp frosted pill (`backdrop-filter: blur(8px)`) floating over the bottom edge of the photography.

### Forms & Bespoke Monogramming
- **Inputs:** Minimalist border-bottom or 1px fine stroke. Labels positioned above in uppercase tracking (`letter-spacing: 0.12em; font-size: 10px`). Focus state highlights with Champagne Gold ring (`box-shadow: 0 0 0 1px #B69A64`).
- **Monogram Customizer:** Dashed metallic badge indicating initials (e.g., `✦ MONOGRAM: ZFL`).

### Status Badges
- Strict semantic palette:
  - `New`: Soft Azure (`background: #E0F2FE; color: #0369A1;`)
  - `Contacted`: Amber Honey (`background: #FEF3C7; color: #B45309;`)
  - `Confirmed`: Emerald Atelier (`background: #DCFCE7; color: #15803D;`)
  - `Completed`: Imperial Amethyst (`background: #F3E8FF; color: #7E22CE;`)
  - `Cancelled`: Rose Muted (`background: #FEE2E2; color: #B91C1C;`)

---

## 5. Layout Principles

- **Asymmetric Narrative Grids:** Rejects the generic "3 identical horizontal cards" pattern. Employs 2-column editorial splits where high-resolution imagery occupies 58% of the viewport width and contemplative narrative copy occupies the remaining 42%.
- **Inline Image Typography:** In hero and editorial sections, small rectangular vignettes (e.g., close-up of gold songket thread or silk drapery) sit directly between words in the display headline at type-height, functioning as visual punctuation.
- **Max-Width Containment:** All content zones constrained within a maximum width of `1360px` centered with fluid horizontal padding (`clamp(24px, 5vw, 64px)`).
- **Mobile-First Collapse:** Multi-column asymmetric splits collapse cleanly to a single stacked vertical column below `880px` without horizontal overflow.

---

## 6. Motion & Interaction

- **Spring Physics Standard:** `stiffness: 100, damping: 20` for all sheet transitions, modal entrances, and cart drawer slides.
- **Cart Drawer Slide:** Smooth cubic-bezier entrance (`cubic-bezier(0.16, 1, 0.3, 1)`) over `350ms`.
- **Perpetual Micro-Interactions:** Subtle, continuous pulse (`scale: 1.02` over `4s`) on bespoke concierge indicators and craft status markers.
- **Performance:** Transitions strictly bound to `transform` and `opacity` to utilize hardware-accelerated GPU compositing.

---

## 7. Anti-Patterns (Banned AI Clichés)

1. **NO Emojis:** Replace all emojis with custom minimalist SVG geometric symbols (diamonds `✦`, fine monoline icons).
2. **NO Inter Font:** Under no circumstance should `Inter` be loaded or used.
3. **NO Pure Black (`#000000`):** Use Charcoal Ink (`#20201D`) or Midnight Sapphire (`#17263F`).
4. **NO Neon Gradients or Glows:** Ban all purple-to-blue gradient borders and glowing buttons.
5. **NO 3-Column Equal Feature Boxes:** Use asymmetric masonry or editorial pairings.
6. **NO AI Buzzword Copywriting:** Never use "Elevate your style", "Seamless experience", "Unleash your luxury", or "Next-gen couture". Use dignified, artisanal prose focusing on weave, provenance, silk momme count, and tailor hours.
7. **NO "Scroll to Explore" Clichés:** No bouncing chevrons or floating scroll arrows. The visual hierarchy must pull the reader downward organically.
8. **NO Broken Image URLs:** Always supply high-definition, contextual editorial photography.

---

## 8. Google Stitch Screen Prompts (Prompt Templates for labs.google/stitch)

### Screen 1: Storefront Editorial Home & Collection Discovery
```text
Design a luxury haute couture fashion storefront for ZAFIRA (La Maison de Lumière). 
Canvas background is warm Linen Ivory (#F3EFE7). 
Header features a centered editorial serif logo 'ZAFIRA' in Bodoni Moda with 'La Maison de Lumière' in tiny spaced caps below it. 
Left nav has minimalist uppercase links: 'Koleksi', 'Warisan Nusantara', 'Koleksi Kapsul', 'Concierge'. 
Right nav has Search, Client Portal, and Shopping Bag with counter pill. 
Hero section is an asymmetric split banner with dark midnight sapphire tone (#17263F) featuring an elegant Parisian silhouette model wearing an Indonesian embroidered silk gown; headline reads 'Siluet Paris, Keahlian Italia, Jiwa Nusantara' with small inline lifestyle vignettes between words. 
Below the hero is a row of delicate pill buttons for category filtration ('Semua', 'Busana Wanita', 'Warisan Nusantara', 'Koleksi Kapsul'). 
The product grid displays 3:4 aspect ratio luxury clothing cards with price formatted in Indonesian Rupiah (Rp14.900.000), provenance tags ('Batik Solo', 'Sutra Lyon'), and a subtle gold badge. 
Strictly no neon colors, no emojis, no Inter font. Everything looks like a world-class luxury house like Hermès or Chanel.
```

### Screen 2: Haute Couture Creation Detail & Craftsmanship Viewport
```text
Design a luxury product detail viewport for ZAFIRA's 'Kebaya Lumière'. 
Split-screen layout on Linen Ivory background (#F3EFE7). 
Left side: large editorial portrait of the cream and gold hand-embroidered kebaya with subtle thumbnail navigation underneath. 
Right side: sticky container with luxury serif title 'Kebaya Lumière', category 'Warisan Nusantara', price 'Rp14.900.000'. 
Includes a poetic description of the Parisian silhouette paired with Kebayoran embroidery. 
Dedicated craftsmanship metadata cards with fine hairline borders: 
- 'Material': Brokat Calais, Prancis & Sutra Organza
- 'Provenance': Calais, Prancis & Kebayoran, Jakarta
- 'Savoir-Faire': 60 jam bordir tangan oleh 3 pengrajin
- 'Perawatan': Dry clean spesialis bordir
Includes a monogram personalization input allowing up to 3 capital letters (e.g. 'ZFL') with real-time preview. 
Primary CTA is a flat Champagne Gold button (#B69A64) reading 'TAMBAH KE TAS BELANJA' followed by a secondary button 'KONSULTASI BERSAMA CLIENT ADVISOR'.
```

### Screen 3: Slide-over Shopping Bag & Monogram Personalizer
```text
Design a slide-over luxury cart drawer sliding from the right of the screen over an obscured dark sapphire backdrop (#17263F with 60% opacity). 
The drawer width is 440px with a pure silk white interior (#FFFDF9). 
Header: 'Tas Belanja Maison' with an elegant fine close icon. 
List of luxury items: shows thumbnail, creation title in Bodoni Moda serif, color, price in IDR, and an elegant golden dashed badge displaying the personalized monogram (e.g. '✦ Monogram: ZFL'). 
Each item has a refined quantity stepper (- 1 +) and a discreet 'Hapus' link. 
Bottom section on warm ivory (#F3EFE7) with 1px border-top: displays subtotal 'Rp34.700.000', complimentary shipping indicator, and a prominent Champagne Gold button: 'LANJUT BERSAMA CLIENT ADVISOR'. 
Under the button is a note: 'Pesanan akan dikonfirmasi secara privat oleh Client Advisor ZAFIRA.'
```

### Screen 4: Atelier Admin Workspace (Overview & KPI)
```text
Design an executive back-office workspace for 'ZAFIRA Atelier Administration'. 
Desktop layout with a deep midnight sapphire sidebar (#17263F) on the left and a crisp, well-lit canvas on the right (#F8FAFC). 
Sidebar features the ZAFIRA emblem, status pill 'Database: ecommerce (MariaDB 10.4)', and navigation links with monoline icons: 'Overview & KPI', 'Katalog Kreasi', 'Kategori Koleksi', 'Pesanan & Concierge', 'Data Klien', 'Arsip Audit'. 
Main area has top breadcrumb 'Administration / ZAFIRA Atelier' and a golden '+ Tambah Kreasi Baru' button. 
Top row shows 4 crisp KPI stat cards with hairline borders: 'Total Kreasi' (12), 'Kategori' (8), 'Pesanan Aktif' (3), 'Klien Terdaftar' (8). 
Main section displays a data table of recent concierge requests with columns: Order ID (#101), Client Name (Madame Aurelia Tan), Contact (WhatsApp), Order Summary, and an interactive status badge dropdown with luxury colors (New, Contacted, Confirmed, Completed). 
Typography is strictly sans-serif (Manrope) and monospace (JetBrains Mono) for numbers. Zero serif fonts in this data dashboard.
```
