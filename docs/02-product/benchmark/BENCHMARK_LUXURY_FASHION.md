# Benchmark Luxury Fashion E-commerce — Mytheresa, Farfetch (+NET-A-PORTER), Hermès, Zalando

> Bahasa: Indonesia. Nama tool/term tetap English. Status: riset evidence-based, Okt 2026.
> Legenda: **[Fakta]** = ada sumber URL | **[Inferensi]** = gabungan sumber, label eksplisit | **[Unknown]** = belum terverifikasi / tulis `unverified`.
> ZAFIRA konteks: Laravel luxury shop — concierge request/appointment ganti payments, products/categories, wishlist, localStorage cart, admin CRUD, MVP tanpa tabel Order. *(Koreksi 2026-10-19: konteks ini historis. Cart kini di tabel `carts`/`cart_items` (migrasi `2026_10_19_000002_create_carts_tables.php`); localStorage hanya rekonsiliasi legacy. Tabel `orders`/`order_items` kini ada.)*

## 1. Mytheresa (luxury multi-brand curator)

### 1.1 Popularitas / market evidence
- **[Fakta]** GMV €988,5 juta FY2025 (berakhir Juni 2025), bagian dari LuxExperience. Sumber: https://www.mytheresa.com/us/en/about-us (diakses Okt 2026).
- **[Fakta]** Kurasi ≤250 brand (Bottega Veneta, Loro Piana, The Row, dsb.), kirim 130+ negara, 900 new arrivals/minggu, CS 24/7 13 bahasa. Sumber: sama.
- **[Fakta]** Akuisisi YOOX NET-A-PORTER dari Richemont selesai 2025. Sumber: sama (timeline About Us).
- **[Inferensi]** Traffic ±10,9 juta visits Jan 2026, peringkat #3 multi-brand luxury setelah Farfetch/Saks — sumber sekunder sosial, treat as `unverified`: https://www.instagram.com/p/DVvl5xlEZ8B?hl=en ; https://www.facebook.com/retailboss/posts/1534411242024106
- **[Fakta]** Revenue alternatif ECDB: US$1.256m 2025 mytheresa.com. Sumber: https://ecdb.com/resources/sample-data/retailer/mytheresa

### 1.2 HCI / UI-UX
- Navigasi & mega menu: top-level Women/Men/Kids/LIFE + sub New Arrivals/Designers/Clothing/Shoes/Bags/Accessories/Jewelry/Vacation/Sale **[Fakta]** (sumber: fetch about-us nav). **[Inferensi]** Pola Shneiderman "overview first, filter, details-on-demand" + Nielsen consistency; Krug Trunk Test lolos (identitas + lokasi jelas).
- Search: search icon persisten + halaman designers A–Z **[Inferensi, unverified detail]**; cocok Nielsen "recognition rather than recall".
- Filter/sort: faset designer/size/colour/pattern pada PLP **[Inferensi dari pola standar luxury PLP, unverified]**.
- Product card density: grid longgar 3–4 kolom desktop, foto model full-look, harga + designer **[Inferensi, unverified]** — Norman visceral (estetika) diutamakan, behavioral tetap jalan.
- PDP: galeri multi-angle + worn-on-model, composition & care, size guide per kategori, availability per size, delivery/returns promise ("delivery 130+ countries", "free returns 30 days") **[Fakta untuk promise]** (https://www.mytheresa.com/us/en/about-us ; https://www.mytheresa.com/us/en/customer-care/returns-exchanges).
- Size recommendation: size guide statis per kategori **[Fakta]** (https://www.mytheresa.com/us/en/customer-care/sizes) — belum ada AI fit ala Zalando **[Inferensi]**.
- Wishlist (ikon hati + /wishlist), cart (/checkout/cart), checkout multi-bahasa/mata uang **[Fakta nav]**.
- Trust/provenance: "exclusive capsule", "finest edit", SSL notice, Vestiaire Collective pre-owned link **[Fakta]** (footer about-us) — Cooper manifest model: bahasa atelier, bukan struktur DB.
- Microcopy: sopan-premium ("Thank you for shopping", "We hope you are pleased") **[Fakta]** (returns page).
- Mobile: app iOS/Android dipromosikan agresif (Adjust links + footer) **[Fakta]**.
- Accessibility: **[Unknown, unverified]** — tidak ada klaim publik ditemukan; acuan umum Baymard luxury-goods benchmark (paywalled): https://baymard.com/research/luxury-goods

### 1.3 Workflow & logic
- browse → wishlist → purchase → tracking → return/exchange (exchange hanya same-item different-size, sekali saja) **[Fakta]** (returns page).
- Concierge/advisor: Customer Care email + telepon 24/7, bukan video-styling **[Fakta]**.
- Waitlist/notify-me: newsletter + SMS "first access new arrivals/exclusives" **[Fakta]** (footer subscribe).
- VIP: pre-shopping newsletter + exclusive capsule; tidak ada tier publik seperti NAP EIP **[Inferensi]**.
- Size/variant model: variant = size per produk fashion; no made-to-order di flow utama **[Inferensi]**.

### 1.4 MIS (public info only)
- Clienteling CRM: newsletter profiling perilaku ("personalized profile based on purchasing/browsing") **[Fakta]** (privacy consent footer).
- Inventory visibility: sentral (own-buy, bukan marketplace) + DC 55.000 m² Halle/Leipzig **[Fakta]** (about-us timeline).
- OMS: returns portal via order-number+email, label DHL/FedEx, refund ke original method atau store credit **[Fakta]** (returns page).
- Analytics: "leading technology and analytical platforms" **[Fakta, vague]** (about-us).

## 2. Farfetch (luxury marketplace) + catatan NET-A-PORTER

### 2.1 Popularitas / market evidence
- **[Fakta]** Marketplace boutique: tiap produk tampil store name + country of origin; semua item "guaranteed authentic... from brand stores or authorized boutiques". Sumber: https://www.farfetch.com/faqs
- **[Inferensi, unverified]** Traffic ±21,9 juta visits Jan 2026 (#1 luxury multi-brand). Sumber sekunder: https://www.facebook.com/retailboss/posts/1534411242024106
- **[Fakta]** NET-A-PORTER traffic ±7,4 juta (sumber sekunder sama) dan kini satu grup LuxExperience dengan Mytheresa (lihat §1.1).
- **[Fakta]** Kirim 190+ negara; brand/boutique punya 2 hari kerja memproses order; tracking via Orders & Returns / email. Sumber: https://www.farfetch.com/orders-and-shipping ; https://www.farfetch.com/customer-promise

### 2.2 HCI / UI-UX
- Navigasi: departemen + designer + kategori; PDP cantumkan asal boutique (provenance cue unik) **[Fakta]** (faqs).
- Search/filter: katalog raksasa multi-boutique → search + faset agresif **[Inferensi, unverified]**; risiko choice-paralysis (Shneiderman: kategorisasi wajib).
- PDP: galeri, composition, size guide, availability per boutique, delivery promise per origin **[Inferensi; promise multi-origin Fakta]** (orders-and-shipping).
- Fashion Concierge On Demand: layanan cari rare/sold-out item untuk premium customers via Membership/profile **[Fakta]** (faqs).
- Wishlist, bag, checkout multi-currency; refund bisa ke Farfetch account credit **[Fakta]** (https://www.farfetch.com/returns-and-refunds).
- Returns: unworn + tags + original packaging; final sale non-returnable **[Fakta]** (sama).
- Trust: "only partner with the best", CS phone/email **[Fakta]** (customer-promise, contact-us: https://www.farfetch.com/contact-us).
- Microcopy: playful-global ("fetching diverse fashion from afar") **[Fakta]** (customer-promise) — Cooper: beda dari Mytheresa yang formal.
- Accessibility: **[Unknown, unverified]**.
- Pemetaan teori: Nielsen visibility (asal barang + tracking jelas); Norman constraint (2-day processing expectation); Krug: label aksi lugas ("Orders & Returns").

### 2.3 Workflow & logic
- browse → wishlist → purchase (multi-origin) → boutique fulfil → tracking → return ke partner via Farfetch as agent **[Fakta]** (returns-and-refunds, orders-and-shipping).
- Clienteling: Fashion Concierge (hunting sold-out) **[Fakta]**.
- Waitlist: via Concierge/membership, bukan tombol notify-me generik **[Inferensi]**.
- VIP: tiermembership premium (detail paywalled) **[Unknown]**.
- Size/variant: size per boutique stock; promo tidak berlaku jika boutique non-partisipan **[Fakta]** (faqs).

### 2.4 MIS (public info only)
- Inventory: distributed (no own stock — "we don't buy stock directly") **[Fakta]** (contact-us/buying section).
- OMS: split-fulfilment per partner, SLA 2 hari, refund agency model **[Fakta]**.
- Analytics: data marketplace terpusat **[Inferensi, unverified]**.
- **NET-A-PORTER EIP (relevan pasca-akuisisi):** invite-only >£10.000/12 bulan; dedicated Personal Shopper, 36-hour preview, early sale, same-day London/NYC/HK **[Fakta]**: https://www.net-a-porter.com/en-om/campaigns/discover-eip ; latar: https://medium.com/@in.parallel/is-net-a-porters-loyalty-programme-the-ultimate-luxury-secret-a509178ce17f

## 3. Hermès (maison DTC — dipilih karena info web experience paling publik)

### 3.1 Popularitas / market evidence
- **[Fakta]** hermes.com/us/en adalah official store; homepage editorial (runway SS, "Discover the current collection"). Sumber: https://www.hermes.com/us/en
- **[Fakta]** Kategori: ready-to-wear, shoes, fine/fashion jewelry, SLG, belts, bags, silk. Sumber: sama.
- **[Inferensi]** Posisi ultra-luxury: sebagian lini (Birkin) tidak dijual online / invitation-only. Sumber analisis: https://www.nngroup.com/articles/luxury-principles-ecommerce-design/ (NN/g, Jun 2022).
- **[Fakta]** G&Co case study: pairing content+commerce, human element sebagai pendorong segmen online. Sumber: https://www.g-co.agency/insights/hermes-an-ecommerce-case-study

### 3.2 HCI / UI-UX (banyak dari NN/g research 2022 — tetap relevan sebagai prinsip)
- Navigasi: mega menu kategori produk + editorial universe; search persisten **[Inferensi dari homepage, unverified detail]**.
- PDP: foto in-context + diagram styling (contoh: cara mengikat scarf) dipuji partisipan NN/g **[Fakta]** (luxury-principles article).
- Availability: PDP ada yang tanpa cara membeli (khususnya Birkin — hanya foto + deskripsi) **[Fakta]** (NN/g).
- Advisor/appointment: pola maison umum — book store/virtual appointment + live chat advisor + repair online (bukti kuat di LV sebagai proxy: https://us.louisvuitton.com/eng-us/mylv/booking-appointments ; https://us.louisvuitton.com/eng-us/contact-us ; repair: NN/g "LV provided streamlined online repair + video chat"). Untuk Hermès spesifik: **[Unknown, unverified]** apakah ada booking online setara.
- Trust: heritage + craftsmanship narrative di PDP **[Fakta]** (G&Co + NN/g "tell the brand's story").
- Microcopy: minimal, artistik, sedikit kata (Krug "omit needless words") **[Inferensi]**.
- Mobile: app + mobile web visual-first **[Inferensi, unverified]**.
- Accessibility: **[Unknown]** — acuan: Baymard accessibility research https://baymard.com/research/accessibility ; studi PMC 2022 tantangan aksesibilitas e-commerce https://pmc.ncbi.nlm.nih.gov/articles/PMC9044289
- Pemetaan teori: Norman reflective (status/heritage) > behavioral (kemudahan) — NN/g: "make it difficult to buy" sebagai antilaw Kapferer & Bastien **[Fakta kutipan]** (https://www.nngroup.com/articles/luxury-terrible-ecommerce/) ; Nielsen dilanggar sadar (no price/stock) demi exclusivity; Shneiderman closure via appointment/repair; Cooper goal-directed (klien VIP vs artisan).

### 3.3 Workflow & logic
- browse editorial → PDP (beli atau "contact advisor / find in store") → appointment → purchase offline → aftercare (cleaning/repair) **[Fakta pola umum NN/g; detail Hermès unverified]**.
- Waitlist: Birkin quota/invitation, bukan notify-me biasa **[Fakta]** (NN/g).
- VIP: purchase history → undangan; ultra-luxury = uang saja tidak cukup **[Fakta]** (NN/g luxury-terrible article).
- Size/variant: size + colourway; silk/scarf styling variants **[Inferensi]**.

### 3.4 MIS (public info only)
- CRM clienteling: riwayat pembelian + relasi advisor jangka panjang (SMS/email) **[Fakta pola]** (NN/g).
- Inventory: scarcity-by-design, alokasi toko **[Fakta]** (NN/g).
- Aftercare: repair/cleaning sebagai membership club **[Fakta]** (NN/g).
- Analytics internal: **[Unknown]**.

## 4. Zalando (high-traffic fashion marketplace — pembanding skala & fit-tech)

### 4.1 Popularitas / market evidence
- **[Fakta]** 62 juta active customers 2025; multi-app (Zalando, ABOUT YOU, Lounge); Zalando Plus 16,8 juta members = 48% GMV; ZEOS layani 1.200+ merchants ≈ €11 miliar GMV. Sumber: https://corporate.zalando.com/en/investor-relations/zalando-full-year-2025-results
- **[Fakta]** AI personalization → +13% items added to bag. Sumber: sama.
- **[Fakta]** Pasar fashion EU €500 miliar, baru ~30% online. Sumber: https://www.linkedin.com/posts/zalando-partner_the-european-fashion-market-is-worth-500-activity-7437853302328967168-h0dG (secondary corporate post).

### 4.2 HCI / UI-UX
- Navigasi: mega menu + search dominan + Content Score ranking **[Fakta persyaratan konten]**: https://www.plytix.com/connection/zalando/zalando-best-practices (main image ≥1000px, ≥5 foto, model height/size wajib).
- Filter/sort: atribut presisi (size system/code, material %) sebagai pertahanan retur **[Fakta]** (sama).
- PDP: size flags ("runs small/large") + size recommendation ML sejak 2018, cakup ~70% assortment **[Fakta]**: https://corporate.zalando.com/en/technology/how-zalando-uses-technology-help-customers-find-right-size
- Size Profile + Body Measurement (2 foto/video; 1,5 juta+ pengguna) + Virtual Fitting Room 3D avatar; pilot jeans turunkan retur s.d. 40%; 2025 cegah 8% size-related returns **[Fakta]** (sama).
- Wishlist, bag, checkout cepat, 100-day returns (klaim umum; **[unverified di dokumen ini]**).
- Trust: fit-data + review + konten standar, bukan provenance artisan **[Inferensi]**.
- Microcopy: fungsional-informatif (bukan puitis) **[Inferensi]**.
- Mobile: app-first + "Shop the Look" **[Inferensi, unverified]**.
- Accessibility: skala besar → WCAG jadi hygiene; **[Unknown detail Zalando]**.
- Pemetaan teori: Nielsen error prevention (size flag cegah retur); Shneiderman informative feedback + reversal mudah (retur longgar); Norman constraint (atribut wajib); Krug scan-friendly PLP.

### 4.3 Workflow & logic
- browse → size-aware PDP → bag → checkout → tracking → free return → refund **[Inferensi umum marketplace]**.
- Waitlist/notify-me standar **[Inferensi, unverified]**.
- VIP = Zalando Plus (loyalty fungsional, bukan EIP eksklusif) **[Fakta]** (FY2025 results).
- Size/variant: size system multi-negara + model measurements **[Fakta]** (Plytix + size-tech article).

### 4.4 MIS (public info only)
- CRM: Size Profile + preference graph **[Fakta]** (size-tech).
- Inventory/OMS: ZEOS + Partner Business ~34% B2C GMV; logistik + returns own-operated **[Fakta]** (FY2025 + https://corporate.zalando.com/en/about-us/what-we-do).
- Analytics: foundational models + dataset body-measurement anonim terbesar EU **[Fakta]** (size-tech).
- Feedback loop ke brand soal sizing/returns **[Fakta]** (sama).

## 5. Adopsi untuk ZAFIRA (Laravel kecil, concierge-first, tanpa Order di MVP)

| # | Pola | Sumber inspirasi | Effort | Catatan implementasi |
|---|------|------------------|--------|----------------------|
| A1 | PDP kaya konteks: foto worn-on-model + composition/care + delivery promise | Hermès/NN/g; Mytheresa | S | Tambah field PDP + care text; tanpa ML |
| A2 | Size guide per kategori + counter constraint (mis. monogram [2/3]) | Mytheresa sizes; Norman constraints | S | Tabel statis + validasi blur (Nielsen error prevention) |
| A3 | Wishlist persisten + "lengkapi look" (You may also like bermutu) | NAP; NN/g Tiffany counterexample | S | Relasi product_tags sederhana |
| A4 | Concierge/appointment booking (ganti checkout): pilih slot + advisor + follow-up SMS/email | LV booking; Gucci Digital Showroom via NN/g | M | Tabel Appointment (padanan Order MVP); Krug label lugas |
| A5 | Notify-me / waitlist sold-out + pre-shopping newsletter | Farfetch Concierge; Mytheresa SMS | S | Tabel Waitlist (product+email/size) |
| A6 | Returns & aftercare page jujur + exchange same-size-once | Mytheresa returns | S | Halaman statis + status concierge |
| A7 | Provenance cue: asal atelier + artisan + material % | Farfetch store-origin; Zalando atribut | S | Field origin/craftsmanship di PDP |
| A8 | Repair/cleaning request flow (video/chat menyusul) | LV repair via NN/g | M | Form aftercare + status |
| A9 | Personal Shopper lite: histori wishlist → rekomendasi manual admin | NAP EIP (versi murah) | M | Admin lihat wishlist per client (CRM sederhana) |
| A10 | Size recommendation ML / 3D avatar / ZEOS-style OMS | Zalando | L | Tunda; cukup size-flag manual ("runs small") |
| A11 | Invite-only tier + quota (Birkin logic) | Hermès/NAP EIP | L | Tunda hingga demand nyata; risiko antilaw |

### MUST NOT copy
- Menyembunyikan harga/stok demi eksklusivitas (Chanel/Goyard pattern via NN/g) — bunuh konversi concierge kecil.
- Popup feedback mengganggu di PDP mahal (Cartier counterexample NN/g) — Nielsen aesthetic + Norman behavioral rusak.
- Navigasi brutalist/supreme-style (pola demi gaya, fungsi mati) — NN/g.
- All-caps light-gray body text (Tom Ford counterexample) — kontras WCAG 2.2 AA wajib.
- Marketplace multi-origin split-fulfilment (Farfetch) — terlalu berat untuk atelier tunggal.
- Loyalty EIP £10k + same-day 3 kota — tidak relevan skala ZAFIRA.
- AR/metaverse sebelum basic PDP beres (Gucci AR warning NN/g).

## 6. Tabel perbandingan

| Dimensi | Mytheresa | Farfetch (+NAP) | Hermès (maison) | Zalando |
|---|---|---|---|---|
| Model | Curated own-buy | Marketplace boutique | DTC maison | Marketplace+retail skala EU |
| Skala bukti | €988,5M GMV FY25 | 21,9M visits Jan26 `unverified` | Ultra-luxury, scarcity | 62M customers, €11B via ZEOS |
| Navigasi | Mega menu rapi, designer A–Z | Katalog raksasa, search-first | Editorial + kategori | Mega menu + Content Score |
| PDP | Kurasi + promise jelas | Provenance boutique | Styling diagram, kadang tanpa harga | Size flag ML + avatar fit |
| Advisor | CS 24/7 | Fashion Concierge hunting | Appointment + repair | Self-service + Plus |
| Waitlist | Newsletter/SMS early access | Concierge hunting | Invitation/quota | Notify standar `unverified` |
| VIP | Exclusive capsule | Membership premium / NAP EIP £10k | Top-client invitation | Plus fungsional |
| Returns | 30 hari, exchange same-size 1× | Via partner, credit opsi | Aftercare club | Longgar + fit-prevention |
| MIS | DC sentral, profiling newsletter | Distributed stock, SLA 2 hari | Alokasi scarcity | ZEOS + fit dataset |
| Cocok untuk ZAFIRA | ★★★ kurasi + promise | ★★ concierge hunting | ★★★ appointment/aftercare | ★★ fit-data ringan |

## 7. Adopt / Adapt / Reject

- **Adopt (langsung):** PDP konteks + care; size guide statis + validasi; wishlist; notify-me; provenance cue; returns page jujur; booking appointment sebagai "checkout" MVP; microcopy sopan-premium.
- **Adapt (modifikasi):** Personal shopper lite (admin-manual, bukan dedicated EIP); size-flag manual ("runs small") sebelum ML; aftercare form sebelum video-chat; newsletter segmentasi sebelum predictive profiling.
- **Reject (jangan):** Harga/stok disembunyikan; popup interupsi; navigasi gaya-di-atas-fungsi; all-caps low-contrast; multi-origin fulfilment; tier £10k; AR/3D sebelum fondasi; klaim tanpa sumber.

---
*Sumber kunci: Mytheresa about-us & returns; Farfetch faqs/orders/returns/contact/customer-promise; NAP discover-eip; LV booking & contact; Hermès homepage; NN/g 3-artikel luxury (Moran 2022); Baymard luxury-goods & accessibility; Zalando corporate size-tech & FY2025; Plytix Zalando best practices; G&Co Hermès case study. Detail UI mikro tanpa fetch langsung ditandai inferensi/unverified.*
