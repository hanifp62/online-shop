# Audit kontras warna

Server dan viewport sama dengan `touch-targets-44px.md`. Bukti computed styles: `a11y-targets-contrast-raw.json`; daftar pasangan: `contrast-pairs.json`. CSS rgba dikompositkan pada background ancestor solid. Nilai dibulatkan ke RGB 8-bit sebelum rasio; hasil dekat ambang perlu verifikasi presisi/screenshot. Rumus luminansi sRGB WCAG.

Normal: 4.5:1 (1.4.3 AA). Large: ≥24px atau ≥18.66px bold ≥700, 3:1. Non-text UI: 3:1 (1.4.11 AA). Logo/dekorasi dapat dikecualikan; tabel rasio bukan otomatis klaim pelanggaran.

## Ketidakpastian penting

Snapshot default, bukan semua state/tema. Hover, focus, disabled, open modal, dark mode dan tab lain **NOT RUN**. Ancestor background saja tidak cukup untuk positioned image sibling, glass atau overlay. Hero storefront, header transparan, category cards, editorial banner, mockup monogram/bag dan badge overlay gambar **NOT RUN**. Angka estimasi solid untuk area tersebut sengaja tidak dinyatakan PASS/FAIL. Hero prototype judul/manifes berada pada panel ivory solid, dapat dihitung; caption pada foto NOT RUN. Diskon tidak ditemukan pada snapshot: NOT RUN.

## Seluruh pasangan teks teramati

Count menjumlahkan observasi kedua viewport. Contoh selector/text membantu menautkan pasangan; raw JSON menyimpan semua instance.

| Surface / contoh | FG / effective BG | Rasio | Kriteria | Hasil |
|---|---|---:|---|---|
| storefront: a.skip-link Lewati ke konten utama; button.filter-pill.active Semua | #ffffff / #263a5d | 11.38:1 | 4.5:1 normal | PASS |
| storefront: p Complimentary signature ; button Rendez-vous privat | #d5d8dc / #17263f | 10.60:1 | 4.5:1 normal | PASS |
| storefront: span.brand-name ZAFIRA | #b2b2b1 / #f3efe7 | NOT RUN (solid-only estimate 1.85) | 4.5:1 normal | NOT RUN |
| storefront: span.eyebrow.light La Collection Première · | #c1c3c7 / #08101d | NOT RUN (solid-only estimate 10.80) | 4.5:1 normal | NOT RUN |
| storefront: h1#hero-title Lahir dari Cahaya,; span ZAFIRA | #ffffff / #08101d | NOT RUN (solid-only estimate 19.05) | 3:1 large | NOT RUN |
| storefront: p Keanggunan Paris bertemu | #e1e2e4 / #08101d | NOT RUN (solid-only estimate 14.70) | 4.5:1 normal | NOT RUN |
| storefront: a.button.button-light Jelajahi koleksi; span Temui pengrajin | #20201d / #ffffff | NOT RUN (solid-only estimate 16.33) | 4.5:1 normal | NOT RUN |
| storefront: span L’Esprit Parisien; span.marquee-bullet · | #f3efe7 / #263a5d | 9.92:1 | 4.5:1 normal | PASS |
| storefront: span.eyebrow L’Univers ZAFIRA | #263a5d / #f3efe7 | NOT RUN (solid-only estimate 9.92) | 4.5:1 normal | NOT RUN |
| storefront: h2 Temukan dunia Anda; h2#heritage-title Tiga tanah, satu Maison; h3 L’Esprit Parisien | #20201d / #f3efe7 | NOT RUN (solid-only estimate 14.24) | 3:1 large | NOT RUN |
| storefront: p Setiap koleksi dirancang; label Pilihan Pita Sutra:; span Layanan kemasan kado adi | #6e6a62 / #f3efe7 | NOT RUN (solid-only estimate 4.69) | 4.5:1 normal | NOT RUN |
| storefront: span.category-number 01 | #bcbcbb / #20201d | NOT RUN (solid-only estimate 8.59) | 4.5:1 normal | NOT RUN |
| storefront: span Warisan Nusantara | #ffffff / #20201d | NOT RUN (solid-only estimate 16.33) | 4.5:1 normal | NOT RUN |
| storefront: h3 Indonesia; h2 Songket & Sutra, | #ffffff / #20201d | NOT RUN (solid-only estimate 16.33) | 3:1 large | NOT RUN |
| storefront: span.eyebrow Les Nouveautés | #263a5d / #faf8f3 | 10.72:1 | 4.5:1 normal | PASS |
| storefront: h2 Objek-objek penuh pesona | #20201d / #faf8f3 | 15.39:1 | 3:1 large | PASS |
| storefront: span 12; button.filter-pill Busana Wanita | #6e6a62 / #faf8f3 | 5.07:1 | 4.5:1 normal | PASS |
| storefront: span.product-badge Ikon Maison | #20201d / #fdfaf5 | NOT RUN (solid-only estimate 15.69) | 4.5:1 normal | NOT RUN |
| storefront: span Tas Tangan | #6e6a62 / #fffdf9 | 5.30:1 | 4.5:1 normal | PASS |
| storefront: button Sac Séraphine; p Rp18.750.000 | #20201d / #fffdf9 | 16.08:1 | 4.5:1 normal | PASS |
| storefront: span Depuis 2026 | #ffffff / #17263f | 15.16:1 | 4.5:1 normal | PASS |
| storefront: span.eyebrow.light L’Histoire de la Maison | #c5c9cf / #17263f | 9.12:1 | 4.5:1 normal | PASS |
| storefront: h2 Seperti safir, | #ffffff / #17263f | 15.16:1 | 3:1 large | PASS |
| storefront: p ZAFIRA lahir dari keyaki | #aeb3bc / #17263f | 7.20:1 | 4.5:1 normal | PASS |
| storefront: p.story-signature Parisian in spirit. Ital | #b69a64 / #17263f | 5.63:1 | 4.5:1 normal | PASS |
| storefront: span.heritage-number I; span 01; span.service-icon 01 | #b69a64 / #f3efe7 | 2.35:1 | 4.5:1 normal | FAIL |
| storefront: span.heritage-proof Kain Lyon & Biella · pot; div “Untuk Anda, dengan peng; span Atur rendez-vous | #20201d / #f3efe7 | 14.24:1 | 4.5:1 normal | PASS |
| storefront: span#monogram-preview-display.monogram-preview ZA | #c7a665 / #583a28 | NOT RUN (solid-only estimate 4.42) | 4.5:1 normal | NOT RUN |
| storefront: span.bag-lock Z | #dac08a / #75603a | NOT RUN (solid-only estimate 3.41) | 4.5:1 normal | NOT RUN |
| storefront: p Pratinjau konseptual | #7f7970 / #ddd2c3 | 2.89:1 | 4.5:1 normal | FAIL |
| storefront: label Inisial Anda; span.control-label Warna foil | #20201d / #ddd2c3 | 10.95:1 | 4.5:1 normal | PASS |
| storefront: span#welcome-monogram-counter [ 2 / 3 ] | #b69a64 / #d9ccba | 1.71:1 | 4.5:1 normal | FAIL |
| storefront: h2#packaging-heading Kemasan Kado Ikonik & Se | #08101d / #f3efe7 | 16.61:1 | 3:1 large | PASS |
| storefront: button#btn-ribbon-gold.button Pita Emas (Ori) | #0f1c33 / #b89858 | 6.21:1 | 4.5:1 normal | PASS |
| storefront: button#btn-ribbon-sapphire.button Pita Safir (Saphir) | #ffffff / #0f1c33 | 17.01:1 | 4.5:1 normal | PASS |
| storefront: small Atelier Jakarta; p Sentuh stempel untuk mel | #e5d3aa / #08101d | 12.91:1 | 4.5:1 normal | PASS |
| storefront: button#welcome-wax-seal.wax-seal-badge Z | #fef2f2 / #881337 | 8.75:1 | 3:1 large | PASS |
| storefront: span.eyebrow.light Le Journal · Koleksi Kap | #c7c7c7 / #20201d | NOT RUN (solid-only estimate 9.66) | 4.5:1 normal | NOT RUN |
| storefront: p.editorial-lede Palembang bertemu Lyon d | #d7d7d6 / #20201d | NOT RUN (solid-only estimate 11.34) | 4.5:1 normal | NOT RUN |
| storefront: span.eyebrow.light La Lettre ZAFIRA | #c9ced7 / #263a5d | 7.20:1 | 4.5:1 normal | PASS |
| storefront: h2 Masuk ke dunia Maison. | #ffffff / #263a5d | 11.38:1 | 3:1 large | PASS |
| storefront: p Terima cerita atelier, u | #b3bac6 / #263a5d | 5.83:1 | 4.5:1 normal | PASS |
| storefront: small Dengan berlangganan, And | #78859b / #263a5d | 3.05:1 | 4.5:1 normal | FAIL |
| storefront: span.brand-name ZAFIRA | #ffffff / #181916 | 17.65:1 | 3:1 large | PASS |
| storefront: span.brand-est Maison de Lumière | #ffffff / #181916 | 17.65:1 | 4.5:1 normal | PASS |
| storefront: p Parisian in spirit. Ital | #878786 / #181916 | 4.91:1 | 4.5:1 normal | PASS |
| storefront: h3 Koleksi | #b69a64 / #181916 | 6.55:1 | 4.5:1 normal | PASS |
| storefront: a Wanita; button Rendez-vous | #979896 / #181916 | 6.09:1 | 4.5:1 normal | PASS |
| storefront: label.footer-pref-label Wilayah & Bahasa | #e5d3aa / #181916 | 11.96:1 | 4.5:1 normal | PASS |
| storefront: span © 2026 ZAFIRA MAISON. SE; a Ketentuan | #80817f / #181916 | 4.51:1 | 4.5:1 normal | PASS |
| storefront: input#live-catalog-search-input[type=search]::placeholder Cari sutra, tenun, kriya | #8f8f8d / #fefefd | 3.21:1 | 4.5:1 normal | FAIL |
| storefront: input#newsletter-email[type=email]::placeholder nama @ contoh.id… | #a8b0be / #263a5d | 5.21:1 | 4.5:1 normal | PASS |
| storefront: button.lang-btn.active ID | #b69a64 / #27354c | 4.59:1 | 4.5:1 normal | PASS |
| storefront: span.lang-divider / | #5d6879 / #27354c | 2.19:1 | 4.5:1 normal | FAIL |
| storefront: button.lang-btn EN | #b3b8c1 / #27354c | 6.20:1 | 4.5:1 normal | PASS |
| storefront: a Koleksi; span.brand-est Maison de Lumière | #e1e1e0 / #f3efe7 | NOT RUN (solid-only estimate 1.14) | 4.5:1 normal | NOT RUN |
| storefront: span.brand-name ZAFIRA | #e1e1e0 / #f3efe7 | NOT RUN (solid-only estimate 1.14) | 3:1 large | NOT RUN |
| storefront: div.hero-edition Édition No. 01 | #b5b7bb / #08101d | NOT RUN (solid-only estimate 9.49) | 4.5:1 normal | NOT RUN |
| storefront: span Découvrir | #dedad2 / #796a4b | NOT RUN (solid-only estimate 3.79) | 4.5:1 normal | NOT RUN |
| storefront: small#monogram-hint Maksimal 3 inisial A–Z · | #6e6a62 / #ddd2c3 | 3.61:1 | 4.5:1 normal | FAIL |
| prototype: a.skip-to-content Lewati ke Konten Koleksi; span.sf-eyebrow L'Âme de la Maison ZAFIR; small Atelier Jakarta; p Sentuh stempel lilin unt; div Atelier: Paris · Lyon ·  | #e5d3aa / #08101d | 12.91:1 | 4.5:1 normal | PASS |
| prototype: span Complimentary signature  | #ebeced / #08101d | 16.11:1 | 4.5:1 normal | PASS |
| prototype: span.sf-brand-title ZAFIRA | #08101d / #fefcf9 | 18.61:1 | 4.5:1 normal | PASS |
| prototype: span.sf-brand-sub La Maison de Lumière | #8a7868 / #fefcf9 | 4.13:1 | 4.5:1 normal | FAIL |
| prototype: span.badge-count.cart-count-badge 1; button.cat-pill.active Semua | #ffffff / #08101d | 19.05:1 | 4.5:1 normal | PASS |
| prototype: span Création No. 09 · Broder | #e5d3aa / #0f1c33 | 11.53:1 | 4.5:1 normal | PASS |
| prototype: span.hero-eyebrow La Collection Première ·; span Les Autres Pièces | #b89858 / #f8f6f1 | 2.53:1 | 4.5:1 normal | FAIL |
| prototype: h1.hero-title Lahir dari Cahaya,; h3.diptych-title Kebaya Lumière; h3 Koleksi Kriya Pilihan; h2 Kemasan Kado Ikonik & Se | #08101d / #f8f6f1 | 17.64:1 | 3:1 large | PASS |
| prototype: p.hero-manifesto Keanggunan Paris bertemu; p.diptych-quote “Bordir tambal-tangan 60; p Kebaya kontemporer denga; span Savoir-Faire; label Pilihan Pita Sutra: | #6b665c / #f8f6f1 | 5.28:1 | 4.5:1 normal | PASS |
| prototype: span Studio Kriya & Savoir-Fa; button.btn-gold Studio Kriya & Savoir-Fa | #08101d / #b89858 | 6.96:1 | 4.5:1 normal | PASS |
| prototype: span Rendez-vous privat; strong 60+ Jam; button.btn-outline Tambah | #08101d / #f8f6f1 | 17.64:1 | 4.5:1 normal | PASS |
| prototype: span Paris 48.85°N; p Parisian in spirit. Ital | #babcc0 / #08101d | 10.02:1 | 4.5:1 normal | PASS |
| prototype: span.sep / | #b89858 / #ffffff | NOT RUN (solid-only estimate 2.74) | 4.5:1 normal | NOT RUN |
| prototype: button.cat-pill Warisan Nusantara; p.product-desc-snippet Gaun sutra crepe de chin | #6b665c / #fffdfa | 5.62:1 | 4.5:1 normal | PASS |
| prototype: span Tampilan Editorial; span.product-price Rp 15.900.000 | #08101d / #fffdfa | 18.77:1 | 4.5:1 normal | PASS |
| prototype: span Tampilan Galeri | #6b665c / #f2eee5 | 4.93:1 | 4.5:1 normal | PASS |
| prototype: span.diptych-caption-tag Warisan Nusantara · 60h ; span.badge-creations Kapsul Adibusana | #ffffff / #293447 | NOT RUN (solid-only estimate 12.53) | 4.5:1 normal | NOT RUN |
| prototype: span.diptych-origin Brokat: Calais, Prancis  | #8a7868 / #f8f6f1 | 3.91:1 | 4.5:1 normal | FAIL |
| prototype: h2.sf-quote “Setiap jahitan adalah d; span ZAFIRA; h3 ZAFIRA | #ffffff / #08101d | 19.05:1 | 3:1 large | PASS |
| prototype: span.sf-stat-value 60+ Jam | #e5d3aa / #08101d | 12.91:1 | 3:1 large | PASS |
| prototype: span.sf-stat-label Bordir Tangan / Helai Ad | #b5b7bb / #08101d | 9.49:1 | 4.5:1 normal | PASS |
| prototype: span.product-cat Busana Wanita | #8a7868 / #fffdfa | 4.16:1 | 4.5:1 normal | FAIL |
| prototype: h4.product-title Robe Élan | #181816 / #fffdfa | 17.51:1 | 4.5:1 normal | PASS |
| prototype: button.proto-btn Pita Emas (Ori) | #0f1c33 / #b89858 | 6.21:1 | 4.5:1 normal | PASS |
| prototype: button.proto-btn Pita Safir (Saphir) | #ffffff / #0f1c33 | 17.01:1 | 4.5:1 normal | PASS |
| prototype: div#interactive-wax-seal.wax-seal-badge Z | #fef2f2 / #881337 | 8.75:1 | 3:1 large | PASS |
| prototype: div “Pour Madame Aurelia, de | #181816 / #f8f6f1 | 16.46:1 | 4.5:1 normal | PASS |
| prototype: a Rendez-vous privat; span Bordir Tangan Kebayoran | #dadbdd / #08101d | 13.75:1 | 4.5:1 normal | PASS |
| prototype: div © 2026 ZAFIRA MAISON. SE | #777c83 / #08101d | 4.53:1 | 4.5:1 normal | PASS |
| prototype: input[type=email]::placeholder nama @ contoh.id… | #757575 / #1c232f | 3.42:1 | 4.5:1 normal | FAIL |
| prototype: button.lang-opt.active ID | #e5d3aa / #19212d | 10.97:1 | 4.5:1 normal | PASS |
| prototype: span.lang-divider · | #535861 / #19212d | 2.26:1 | 4.5:1 normal | FAIL |
| prototype: button.lang-opt EN | #afb1b5 / #19212d | 7.54:1 | 4.5:1 normal | PASS |
| prototype: span.curr-opt.active IDR | #e5d3aa / #2f2e2a | 9.21:1 | 4.5:1 normal | PASS |
| prototype: kbd ? | #ffffff / #343b46 | 11.29:1 | 4.5:1 normal | PASS |
| prototype: a Koleksi; span Rendez-vous privat; span.profile-label Profil | #181816 / #fefcf9 | 17.36:1 | 4.5:1 normal | PASS |
| prototype: span.sf-brand-title ZAFIRA | #08101d / #fefcf9 | 18.61:1 | 3:1 large | PASS |
| prototype: span.dock-brand ZAFIRA | #ffffff / #161e2a | 16.76:1 | 4.5:1 normal | PASS |
| prototype: span.dock-ver v4.1 · Multi-Lang | #e5d3aa / #333432 | 8.48:1 | 4.5:1 normal | PASS |
| prototype: span Studio PDP | #94a3b8 / #161e2a | 6.54:1 | 4.5:1 normal | PASS |
| prototype: input#sf-search-input.search-input[type=search]::placeholder Cari sutra, tenun, kriya | #757575 / #fffdfa | 4.54:1 | 4.5:1 normal | PASS |

## Non-text UI — border input/select

Border alpha dikompositkan; rasio terhadap adjacent background. Kontur yang merupakan satu-satunya penanda kontrol harus ≥3:1. Border dekoratif dengan penanda alternatif memerlukan evaluasi manual sebelum klaim WCAG.

| Surface / selector | Border efektif / BG | Rasio | Hasil (3:1) |
|---|---|---:|---|
| storefront `input#live-catalog-search-input[type=search] border` | #d3d1cc / #faf8f3 | 1.44:1 | FAIL |
| storefront `input#monogram-input[type=text] border` | #20201d / #ddd2c3 | 10.95:1 | PASS |
| storefront `input#newsletter-email[type=email] border` | #ffffff / #263a5d | NOT RUN (border absent) | NOT RUN |
| storefront `select#welcome-footer-lang-select.zafira-lang-select border` | #464745 / #181916 | 1.89:1 | FAIL |
| storefront `select#welcome-footer-theme-select.zafira-theme-select border` | #464745 / #181916 | 1.89:1 | FAIL |
| prototype `input[type=email] border` | #39404a / #08101d | 1.82:1 | FAIL |
| prototype `select#proto-footer-lang-select.zafira-lang-select border` | #39404a / #08101d | 1.82:1 | FAIL |
| prototype `select#proto-footer-theme-select.zafira-theme-select border` | #39404a / #08101d | 1.82:1 | FAIL |
| prototype `input#sf-search-input.search-input[type=search] border` | #e5e3e0 / #fefcf9 | 1.25:1 | FAIL |

## Prioritas

- Storefront: monogram counter 1.71, preview helper 2.89, hint 3.61, newsletter consent 3.05, catalog placeholder 3.21: gagal normal 4.5.
- Gold on linen 2.35: gagal normal maupun large bila membawa informasi; decorative numbering dapat exempt.
- Prototype: gold on ivory 2.53; taupe origin 3.91; category 4.16; newsletter placeholder 3.42: gagal normal.
- White on sapphire dan dark text on gold solid lulus. Footer legal 4.51 storefront / 4.53 prototype sangat dekat batas.
- Ganti warna teks bermasalah dengan token muted/ink yang sudah lulus pada background sama; jangan ubah gold dekoratif global.
- Contrast icon/swatch/focus ring lengkap **NOT RUN**; input borders merupakan subset non-text, bukan audit seluruh iconography.

Tidak mengubah warna. Tidak ada klaim full WCAG compliance. Gambar/gradient/glass memerlukan sampling pixel terburuk di glyph atau background solid sebelum dapat diberi verdict.
