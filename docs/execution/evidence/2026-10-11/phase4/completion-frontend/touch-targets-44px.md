# Audit target sentuh 44 × 44 px

Tanggal eksekusi: 2026-10-12 (folder bukti mengikuti mandat 2026-10-11). Server: `http://127.0.0.1:8015`. Playwright Chromium headless. Tidak mengubah aplikasi, .env, database, git, atau deployment.

## Metode dan batas

Snapshot halaman `/` dan `/prototype/`, viewport 390×844 dan 1366×768, setelah scroll untuk memicu konten. Semua kontrol terlihat secara CSS di seluruh dokumen, bukan hanya viewport pertama. Menu/modal tertutup dan tab Studio/Akun/Atelier belum dibuka: **NOT RUN**, bukan lulus. Warna/tema/lang default. Selektor penuh dan CSS class, padding, min-width/height, pseudo-element, serta setiap instance tercatat di `a11y-targets-contrast-raw.json`. Label diperiksa sebagai area aktivasi tambahan, bukan tombol mandiri. Skip-link offscreen disertakan untuk keadaan fokus. Count = instance undersized dijumlahkan pada kedua viewport, bukan jumlah DOM unik. Minimum W dan H independen.

44×44 merupakan standar internal; bukan klaim pelanggaran otomatis WCAG 2.2 AA 2.5.8 (24px dengan pengecualian).

## Usulan per selector

Kode fix: **H** = pseudo-element transparan terpusat minimal 44×44 tanpa mengubah visual; wajib cek collision dan ancestor overflow. **M** = min-height:44px (input/select; hindari pseudo pada replaced element). **D** = grup padat: perlu menyediakan slot 44px, lalu pertahankan ikon/glyph pada ukuran semula. Tidak mungkin menjaga posisi seluruh grup sekaligus memperoleh hit area 44px non-overlap. **L** = label fokus ke kontrol terkait; jangan dihitung sebagai kontrol independen, perbaiki kontrol terkait.

Tidak ditemukan pseudo-element perluasan target. Nav ::after hanya garis dekoratif 1px, bukan target 44px. Padding sudah termasuk bounding rect; jangan menambahnya lagi saat menghitung area. Tidak ada checkbox/radio terlihat dengan label lebih besar dalam snapshot.

### storefront

| Selector | Min area px | Count | Viewport | Area efektif / usulan |
|---|---:|---:|---|---|
| `a.skip-link` | 183.9×42.4 | 2 | 390, 1366 | Rect termasuk padding; H |
| `button.icon-button.mobile-menu-button` | 37×37 | 1 | 390 | Rect termasuk padding; H |
| `a.brand` | 131.9×22 | 2 | 390, 1366 | Rect termasuk padding; H |
| `button.icon-button` | 37×37 | 4 | 390, 1366 | Rect termasuk padding; H |
| `button#btn-header-profile.icon-button` | 37×37 | 2 | 390, 1366 | Rect termasuk padding; H |
| `button.filter-pill.active` | 70.5×34.4 | 2 | 390, 1366 | Rect termasuk padding; H |
| `button.filter-pill` | 74.1×34.4 | 24 | 390, 1366 | Rect termasuk padding; H |
| `input#live-catalog-search-input[type=search]` | 340×40 | 2 | 390, 1366 | Rect termasuk padding; M |
| `button.wishlist-button` | 32×32 | 24 | 390, 1366 | Rect termasuk padding; H |
| `h3 > button` | 81.6×20.7 | 24 | 390, 1366 | Rect termasuk padding; H |
| `button.product-add` | 36×36 | 24 | 390, 1366 | Rect termasuk padding; H |
| `a.text-link.light` | 340×16 | 2 | 390, 1366 | Rect termasuk padding; H |
| `div > label` | 42.6×12.8 | 2 | 390, 1366 | Rect termasuk padding; L |
| `input#monogram-input[type=text]` | 154.3×42.6 | 2 | 390, 1366 | Rect termasuk padding; M |
| `button.foil.active` | 20×20 | 2 | 390, 1366 | Rect termasuk padding; D |
| `button.foil` | 20×20 | 4 | 390, 1366 | Rect termasuk padding; D |
| `button#btn-ribbon-gold.button` | 141.1×36 | 2 | 390, 1366 | Rect termasuk padding; H |
| `button#btn-ribbon-sapphire.button` | 168.2×36 | 2 | 390, 1366 | Rect termasuk padding; H |
| `div.footer-links > a` | 28.5×24 | 14 | 390, 1366 | Rect termasuk padding; D |
| `div.footer-links > button` | 88.6×24 | 2 | 390, 1366 | Rect termasuk padding; D |
| `label.footer-pref-label` | 121.9×16.8 | 4 | 390, 1366 | Rect termasuk padding; L |
| `select#welcome-footer-lang-select.zafira-lang-select` | 238×30 | 2 | 390, 1366 | Rect termasuk padding; M |
| `select#welcome-footer-theme-select.zafira-theme-select` | 187×30 | 2 | 390, 1366 | Rect termasuk padding; M |
| `button.text-link` | 86.5×17.6 | 2 | 390, 1366 | Rect termasuk padding; H |
| `div > a` | 15×12.8 | 6 | 390, 1366 | Rect termasuk padding; H |
| `button.lang-btn.active` | 23.7×20 | 1 | 1366 | Rect termasuk padding; D |
| `button.lang-btn` | 23.7×20 | 3 | 1366 | Rect termasuk padding; D |
| `button.theme-btn.active` | 20×20 | 1 | 1366 | Rect termasuk padding; D |
| `button.theme-btn` | 20×20 | 2 | 1366 | Rect termasuk padding; D |
| `div.desktop-only-flex > button` | 106.7×15.4 | 1 | 1366 | Rect termasuk padding; H |
| `nav.desktop-nav > a` | 51.2×14 | 6 | 1366 | Rect termasuk padding; H |
| `button.icon-button.wishlist-trigger` | 37×37 | 1 | 1366 | Rect termasuk padding; H |

### prototype

| Selector | Min area px | Count | Viewport | Area efektif / usulan |
|---|---:|---:|---|---|
| `a.sf-brand` | 122.4×40.3 | 1 | 390 | Rect termasuk padding; H |
| `button.btn-gold` | 89×34 | 6 | 390, 1366 | Rect termasuk padding; H |
| `button.btn-outline` | 115.4×40 | 6 | 390, 1366 | Rect termasuk padding; H |
| `button.view-mode-btn.active` | 180×24 | 2 | 390, 1366 | Rect termasuk padding; D |
| `button.view-mode-btn` | 159.8×24 | 2 | 390, 1366 | Rect termasuk padding; D |
| `button.proto-btn` | 79.5×19 | 4 | 390, 1366 | Rect termasuk padding; H |
| `li > a` | 65.9×15 | 8 | 390, 1366 | Rect termasuk padding; D |
| `form > input[type=email]` | 151×34 | 2 | 390, 1366 | Rect termasuk padding; M |
| `label.footer-pref-label` | 125.4×17.3 | 4 | 390, 1366 | Rect termasuk padding; L |
| `select#proto-footer-lang-select.zafira-lang-select` | 198×31 | 2 | 390, 1366 | Rect termasuk padding; M |
| `select#proto-footer-theme-select.zafira-theme-select` | 194×31 | 2 | 390, 1366 | Rect termasuk padding; M |
| `button.lang-opt.active` | 20×13 | 1 | 1366 | Rect termasuk padding; D |
| `button.lang-opt` | 20×13 | 3 | 1366 | Rect termasuk padding; D |
| `button.theme-opt.active` | 20×20 | 1 | 1366 | Rect termasuk padding; D |
| `button.theme-opt` | 20×20 | 2 | 1366 | Rect termasuk padding; D |
| `a.announcement-rv-btn` | 139.3×16.5 | 1 | 1366 | Rect termasuk padding; H |
| `button#sound-toggle-btn.sound-toggle-btn` | 100.7×30 | 1 | 1366 | Rect termasuk padding; H |
| `button#shortcuts-toggle-btn.sound-toggle-btn` | 34.5×20 | 1 | 1366 | Rect termasuk padding; H |
| `nav.sf-nav > a` | 58.1×22.1 | 5 | 1366 | Rect termasuk padding; H |
| `button#btn-view-storefront.dock-btn.active` | 96.8×27 | 1 | 1366 | Rect termasuk padding; H |
| `button#btn-view-studio.dock-btn` | 106.8×38 | 1 | 1366 | Rect termasuk padding; H |
| `button#btn-view-account.dock-btn` | 95.1×38 | 1 | 1366 | Rect termasuk padding; H |
| `button#btn-view-admin.dock-btn` | 82.8×27 | 1 | 1366 | Rect termasuk padding; H |

## Proposed diff — belum diterapkan

Tambahkan rule terbatas pada selector yang diaudit, setelah responsive overrides. Jangan gunakan global `button::before`.

```diff
--- resources/css/app.css
+++ resources/css/app.css (usulan)
+/* Transparent hit areas: preserve painted dimensions. */
+.icon-button::before, .wishlist-button::before, .product-add::before,
+.desktop-nav a::before {
+  content: ""; position: absolute; left: 50%; top: 50%;
+  width: max(100%, 44px); height: max(100%, 44px);
+  transform: translate(-50%, -50%);
+}
+#live-catalog-search-input, #monogram-input,
+.zafira-lang-select, .zafira-theme-select { min-height: 44px; }
--- prototype/app.css
+++ prototype/app.css (usulan)
+.sf-nav a::before {
+  content: ""; position: absolute; left: 50%; top: 50%;
+  width: max(100%, 44px); height: max(100%, 44px);
+  transform: translate(-50%, -50%);
+}
+.sf-footer input[type="email"], .zafira-lang-select,
+.zafira-theme-select { min-height: 44px; }
```

Untuk setiap selector H lainnya, gunakan rule pseudo yang sama dan `position:relative` hanya bila computed position masih static. Jangan menimpa absolute/fixed positioning wishlist/product-add. Untuk link/button judul produk, batasi ke container produk; `h3 > button` bukan selector global yang aman. Untuk ribbon, tambahkan pseudo tanpa mengubah inline height. Untuk skip-link, cukup `min-height:44px;box-sizing:border-box`. Untuk brand, pseudo di anchor relatif menjaga glyph tetap. Untuk dock gunakan perluasan vertikal (lebar sudah >44).

D memerlukan perubahan ruang: `.foil-options { gap:24px }` memberi jarak pusat 44px pada swatch 20px; pseudo 44px kemudian tidak overlap. Theme/language switcher saat ini gap 2–4px: gunakan slot grid 44px untuk tiap pilihan; ukuran ikon/glyph tetap. Footer links vertikal height 24px + gap 9px = 33px: naikkan gap minimum 20px sebelum perluasan 44px. Filter pill height 34.4px perlu perluasan 4.8px atas/bawah; pastikan row gap minimum 9.6px saat wrap. Ini mengubah spacing, bukan warna/font/bentuk; tanpa perubahan spacing klaim “aman tanpa perubahan visual” tidak dapat dipenuhi pada grup padat.

Validasi setelah persetujuan: elementFromPoint di pusat dan tepi target, cek clipping/collision seluruh pasangan, screenshot diff kedua viewport, keyboard/focus, drawer dan menu terbuka. Semua usulan **belum diuji**, belum diterapkan.
