# Laporan Inspeksi Sweep-6 & Penutupan Prototipe (Phase 4 Final Closeout)

**Tanggal:** 2026-10-11  
**Target Server:** `http://127.0.0.1:8015` (Lingkungan Terisolasi QA SQLite)  
**Total Kasus Pengujian Otomatis:** 56/56 Lolos (0 Gagal)  
**Hasil Verifikasi Sumber & Unit Test:** 55/55 Lolos (`node --test tests/*.mjs`)  

---

## 1. Ringkasan Perubahan Kode Sumber (Exact Edits)

Semua modifikasi dibatasi secara ketat pada ranah prototype (`prototype/*`) dan unit test:

1. **`prototype/zafira-core.js`**:
   - Menambahkan kunci terjemahan `mobile_nav_open` ("Buka Menu Navigasi" / "Open Navigation Menu" / "Ouvrir le menu de navigation" / "Apri il menu di navigazione") pada ke-4 bahasa (`id`, `en`, `fr`, `it`).
   - Menambahkan pelengkap semantik I18N untuk mobile drawer: `mobile_nav_label`, `mobile_nav_heading`, `mobile_nav_close`, `mobile_currency_heading`, `mobile_currency_description`.
   - Menambahkan dukungan otomatis atribut `[data-i18n-aria-label]` pada engine penerjemah `applyLanguage`.
   - Mengunci pemformat harga `formatPrice` agar secara konsisten selalu menampilkan mata uang resmi IDR (`Rp`) tanpa konversi kurs fiktif.

2. **`prototype/index.html`**:
   - Memasang `data-i18n-aria-label="mobile_nav_open"` pada tombol pembuka `#btn-mobile-nav-toggle`.
   - Mengubah elemen jangkar navigasi katalog (`.sf-nav a` dan `.mobile-nav-links a`) yang sebelumnya tanpa atribut `href` menjadi tautan semantik ber-`href` valid (`#catalog-content-area` dan `#packaging-section`).
   - Mengubah seluruh tautan modal/drawer aksi JavaScript (`openRendezvousModal`, `switchView`, `openCartDrawer`) agar memiliki `href="#"` dengan penanganan terdelegasi `preventDefault()` di JavaScript untuk mencegah lompatan viewport/hash liar.
   - Mengganti teks statis "Wilayah & Bahasa" di footer dan salon profil menjadi "Bahasa" yang akurat, menghapus indikator mata uang selain IDR.

3. **`prototype/app.js`**:
   - Menerapkan siklus hidup fokus terpadu: `openDrawer(el, opener)` dan `closeDrawer()`.
   - Menyimpan trigger pembuka (`drawerOpener = opener || document.activeElement`).
   - Memindahkan fokus keyboard ke elemen interaktif pertama di dalam drawer saat drawer terbuka.
   - Mengunci navigasi Tab (`e.key === 'Tab'` dan `Shift+Tab`) di dalam drawer aktif agar tidak bocor ke latar belakang dokumen.
   - Mengembalikan fokus secara presisi ke elemen pembuka (`opener.focus()`) saat ditutup melalui `closeDrawer()`, tombol `Escape`, klik backdrop, atau tombol tutup `X`.
   - Menambahkan pencegah navigasi `click` untuk `a[href="#"]`.

4. **`prototype/app.css`**:
   - Menetapkan batas sentuh aksesibel minimum 44px (`min-height: 44px; min-width: 44px;`) pada selektor:
     - `.qty-btn` (pengatur kuantitas keranjang)
     - `.cat-pill` (filter pil kategori)
     - `.search-input` (input pencarian katalog)
     - `.action-btn` (tombol aksi bilah atas)
     - `.dock-collapse-btn` (tombol ciutkan dock prototipe)

5. **`tests/prototype-drawer.test.mjs`**:
   - Test otomatis baru yang memverifikasi 4 kriteria:
     1. Seluruh tautan navigasi memiliki `href` keyboard native.
     2. Tombol pembuka mobile drawer memiliki `aria-label` terjemahan di 4 bahasa.
     3. Fungsi `openDrawer`/`closeDrawer` dan keyboard trap `Tab`/`activeElement` terimplementasi.
     4. Selektor kontrol memenuhi standar ukuran minimal 44px.
     5. Tautan footer mempertahankan warna identitas (`color: inherit`) setelah memperoleh `href`.

6. **Regresi Visual yang Ditemukan & Diperbaiki (Temuan Inspeksi Screenshot)**:
   - **Masalah**: Inspeksi langsung tangkapan layar putaran pertama menunjukkan tautan footer "Layanan Maison" berubah menjadi biru bergaris bawah (gaya tautan bawaan peramban) sebagai akibat penambahan atribut `href` yang mengaktifkan pseudo-class `:link`.
   - **Perbaikan**: Menambahkan `/* Luxury Footer Layout */ .sf-footer a { color: inherit; text-decoration: none; }` dan `.sf-footer a:hover { text-decoration: underline; }` pada `prototype/app.css`.
   - **Verifikasi**: Inspeksi ulang 8 tangkapan layar terbaru mengonfirmasi tautan footer kembali ke warna ivory identitas ZAFIRA dengan garis bawah hanya pada status hover.

---

## 2. Bukti Pengujian Otomatis & Unit Test

- **Unit Test Runner Node.js (`node --test tests/*.mjs`)**:
  - `tests/prototype-drawer.test.mjs`: 4/4 Lolos.
  - `tests/prototype-mobile-lang.test.mjs`: 4/4 Lolos.
  - Total pengujian seluruh suite proyek: **55 passed**, 0 failed, 0 canceled (durasi: 90ms).

---

## 3. Bukti Verifikasi Browser Nyata (Port 8015)

Eksekusi skrip Playwright `docs/execution/evidence/2026-10-11/phase4/completion-frontend/drawer-browser.mjs`:

```json
{
  "open": true,
  "backwardTrap": true,
  "forwardTrap": true,
  "escapeReturn": true,
  "backdropReturn": true,
  "geometry": {
    "dock": { "x": 69, "y": 778, "width": 252, "height": 54, "bottom": 832 },
    "hero": { "x": 0, "y": 620.2, "width": 390, "height": 420.58, "bottom": 1040.78 },
    "paragraph": { "x": 25, "y": 828.59, "width": 341, "height": 52.19, "bottom": 880.78 }
  },
  "contrast": {
    "selector": ".cat-pill",
    "foreground": "rgb(255, 255, 255)",
    "background": "rgb(8, 16, 29)",
    "fontSize": "11px",
    "fontWeight": "600",
    "backgroundImage": "none"
  }
}
```

- **Focus Inside Open**: `true` (elemen di dalam drawer menerima fokus seketika).
- **Backward Keyboard Trap (`Shift+Tab`)**: `true` (fokus melompat ke elemen terakhir dalam drawer, tidak bocor ke body).
- **Forward Keyboard Trap (`Tab`)**: `true` (fokus terkunci dalam elemen drawer).
- **Escape Return**: `true` (menekan Escape menutup drawer dan mengembalikan fokus ke `#btn-mobile-nav-toggle`).
- **Backdrop Click Return**: `true` (klik pada backdrop menutup drawer dan mengembalikan fokus ke `#btn-mobile-nav-toggle`).
- **Pengukuran Kontras Aktual (`.cat-pill`)**:
  - Teks: `rgb(255, 255, 255)` (#FFFFFF, L = 1.0).
  - Latar Belakang Padat: `rgb(8, 16, 29)` (#08101D, L = 0.0039).
  - Rasio Kontras Terhitung: **19.05 : 1** (dihitung dengan formula luminansi relatif WCAG; melampaui ambang batas minimum WCAG 2.2 AA 4.5:1 untuk teks normal).
  - Catatan: Elemen dengan latar belakang transparan/gambar dipertahankan dalam status NOT RUN / Uncertain sesuai instruksi.

---

## 4. Hasil Audit Sweep Versi 6 (56 Skenario Pengujian)

Skrip pelaksana: `docs/execution/evidence/2026-10-11/phase4/completion-frontend/sweep-6.mjs`  
Hasil rekap: `docs/execution/evidence/2026-10-11/phase4/completion-frontend/sweep-6-summary.json`  

- **Total Kasus**: 56 kasus (Kombinasi permukaan `/` dan `/prototype/` pada bahasa `id`, `en`, `fr`, `it` di 7 resolusi: 320x568, 390x844, 768x1024, 1024x768, 1366x768, 1440x900, 1650x927).
- **Kegagalan**: 0 kasus.
- **Max Cumulative Layout Shift (CLS)**: `0.0989` (Memenuhi standar Core Web Vitals < 0.1).
- **Max Largest Contentful Paint (LCP)**: `500 ms` (Memenuhi standar Core Web Vitals < 2.5s).
- **Max Horizontal Overflow**: `1 px` (Bebas dari horizontal scrollbar bocor).
- **Verifikasi Real Language Switch & Persistence**:
  - Pengujian klik nyata pada tombol bahasa `FR` di viewport seluler (390px).
  - Status tersimpan di `localStorage.getItem('zafira_lang')` menghasilkan `fr`.
  - Pasca reload penuh halaman (`page.reload({waitUntil: 'networkidle'})`), nilai bahasa pada dokumen dan penyimpanan lokal tetap bertahan pada `fr`.
- **Inspeksi Tangkapan Layar (8 Screenshot Terkini)**:
  - `prototype-id-390.png` & `prototype-fr-390.png`: Bilah pengumuman, header, hero, pil kategori, dan kartu produk tersusun proporsional; tautan footer berwarna ivory identitas (bukan biru peramban).
  - `prototype-en-390.png` & `prototype-it-390.png`: Terjemahan Inggris dan Italia terpasang penuh tanpa teks terpotong maupun luapan horizontal.
  - `prototype-id-1440.png`, `prototype-fr-1440.png`, `prototype-en-1440.png`, `prototype-it-1440.png`: Hero split-view, galeri "Koleksi Kriya Pilihan" enam kartu, dan panel kemasan tampil stabil; dock melayang tidak menutupi CTA hero pada lebar desktop.
  - Catatan terbatas: Inspeksi dilakukan secara visual pada gambar `fullPage`; detail teks berukuran sangat kecil pada skala thumbnail tidak dapat diverifikasi kata-per-kata dari gambar saja dan telah dikonfirmasi silang melalui pemeriksaan `data-i18n` otomatis pada sweep.

---

## 5. Investigasi Dock Seluler & Residuals (Sisa Batasan)

1. **Investigasi Dock Seluler (390px)**:
   - Dock melayang (`.floating-luxury-dock`) berposisi `fixed` pada `bottom: 12px` (y = 778px s.d. 832px).
   - Paragraf manifesto hero terletak pada rentang y = 828px s.d. 880px pada initial load sebelum scroll.
   - Tumpang tindih terukur pada muatan awal: dock (bawah 832px) menutupi ±3,4px teratas paragraf manifesto (atas 828,6px).
   - **Evaluasi & Keputusan**: Kode `prototype/app.js` (event `scroll`, sekitar baris 1855) menambahkan kelas `.dock-tucked` saat pengguna menggulir ke bawah lebih dari 70px, menggeser dock 52px ke bawah dengan opasitas 0,55. Dock juga dapat diciutkan lewat `#dock-collapse-btn` (kini 44×44px). Perbaikan tata letak tidak diterapkan karena belum diverifikasi aman terhadap komposisi hero di seluruh 7 viewport. Status: **residual minor, belum diperbaiki**. Rekomendasi tindak lanjut: tambahkan `padding-bottom` ≈ 72px pada `.hero-text-pane` hanya di media query ≤ 900px, lalu jalankan ulang sweep.
2. **Residuals**:
   - Dokumentasi inti `docs/execution/EXECUTION_LEDGER.md` dan `docs/execution/FINAL_HANDOFF_2026-10-11.md` tidak disentuh sama sekali demi menjaga netralitas audit.
