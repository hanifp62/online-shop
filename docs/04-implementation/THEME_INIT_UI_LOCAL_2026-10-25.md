# Handoff THEME-INIT: UI-LOCAL-20261025

## Status dan kepemilikan

- Requirement: **NFR-THEME-20261025**, **NFR-SAFE-20261025**, [kontrak induk](ADMIN_PROFILE_THEME_STOCK_2026-10-25.md).
- Author: lane THEME-INIT, Zed; ID sesi tidak diekspos. Mulai dari inspeksi working tree yang sudah berisi perubahan ADM/PROFILE/STOCK, bukan tree bersih.
- Baseline HEAD: `208ee9669352a826c703c7e3d318962b8e171da8`.
- Mandat pengguna memindahkan ownership edit head ke lane ini setelah author ADM/PROFILE/STOCK berhenti. UI mereka tidak diedit di luar include head.
- Status: **READY FOR INDEPENDENT REVIEW**. Reviewer dan approver belum ditugaskan dalam sesi ini; tidak mengklaim ACCEPTED untuk seluruh fitur/tema.
- Tidak menjalankan PHP, DB, build, server, browser, commit, sync eksternal, atau membaca dotenv. Tidak menambah dependensi.

## Kontrak runtime

`resources/views/components/theme-preference.blade.php` menjalankan script sinkron sebelum stylesheet/bundle. Tidak memerlukan bundle untuk mengubah preferensi.

- `window.ZafiraTheme.get()` mengembalikan `light | dark | system`.
- `window.ZafiraTheme.set(value)` menerima tiga nilai tersebut; nilai lain diabaikan.
- `window.ZafiraTheme.sync()` menyamakan kontrol yang sudah ada dengan preferensi aktif.
- Event kompatibel `zafira:theme-changed` pada `window` membawa `{ theme, effectiveTheme }`.
- `data-theme` dan `style.colorScheme` berisi resolusi `light | dark`; `data-theme-setting` dan `data-theme-preference` berisi pilihan yang sama, termasuk `system`.
- Canonical storage: `zafira_theme`. Legacy `zafira-theme` dimigrasikan hanya jika canonical tidak ada. Canonical yang ada tetapi invalid memakai system, bukan legacy. Legacy dihapus sesudah persistence canonical berhasil. Jika write gagal, nilai legacy yang terbaca tetap digunakan.
- Startup tidak menulis default system. Hanya pilihan pengguna/migrasi yang disimpan; perubahan OS dan canonical storage tidak menulis balik preferensi. Reset tidak menghidupkan kembali legacy pada navigasi berikutnya.
- System mengikuti OS; pilihan light/dark tetap. Storage event mengabaikan sessionStorage dan key lain. `pageshow` membaca ulang preferensi untuk restorasi BFCache.
- Kontrol `select[data-theme-select]`, `.zafira-theme-select`, dan `[data-set-theme]` disinkronkan. `defaultSelected` disamakan sehingga inisialisasi bukan dirty form bagi `canTakeOver`. Observer hanya menangani penambahan elemen, bukan translasi.
- Handler capture mendahului interceptor fallback loader; tidak menghentikan propagasi atau mengambil alih guard interaksi Blade. Fokus dan field lain tidak disentuh.

## Perubahan terkontrol

1. **Head aplikasi:** include baru di 18 template: `account/{cart-reminder-unsubscribed,show,totp-enrol,totp-recovery}`, `auth/{login,password-email,password-reset,register,totp}`, `admin/{login,totp,totp-enrol,totp-enrol-recovery}`, `catalog/index`, `products/show`, `shop/index`, `errors/layout`, `welcome` (semua `.blade.php` di `resources/views`). Shell `admin/layouts/app.blade.php` sudah memiliki include dan tidak diedit. Total 19 head ditemukan oleh tes inventaris dinamis. Email/PDF/prototype standalone tidak disentuh.
2. **Welcome:** hanya tema inline dipindahkan ke komponen; logika locale query/server/legacy dipertahankan. Patch harga dark inline `!important` lama dihapus sesuai mandat.
3. **React:** `resources/js/components/storefront/StorefrontProvider.jsx` membaca preferensi secara lazy sebelum render dan berlangganan owner head melalui `resources/js/lib/storefront-preferences.js`. Tidak lagi menjalankan effect penulis tema sendiri. Fallback bundle-only diuji, termasuk cleanup listener dan StrictMode.
4. **Fallback lama:** `public/js/zafira-core.js` mendelegasikan get/set/sinkronisasi ke owner head bila tersedia; tidak memasang listener OS atau handler tema kedua. Locale tetap dimiliki engine lama. Fallback tanpa head tetap tersedia. `prototype/` dan `public/prototype/` tidak diedit.
5. **CSS:** `resources/css/app.css` hanya mendapat dua import tepat setelah Tailwind: `./profile.css`, lalu `./theme.css`. Tidak menulis `theme.css` atau mengubah stylesheet lain.
6. **Tes:** tambah `tests/theme-preference.test.mjs` dan `tests/react/theme-preference.spec.jsx`. `tests/zafira-theme.test.mjs` membaca komponen yang diekstrak dari welcome. Dua selector guest di `tests/react/storefront-ui.spec.jsx` diganti ke `header.login_aria`; assertion lain tetap.

## Continuous Evaluation Record

### Baseline dan hipotesis

- Node tema/locale baseline lulus. Vitest storefront baseline: **52 lulus, 2 gagal**, EXIT=1; dua kegagalan memakai selector guest `welcome.profile_aria` yang sudah tidak cocok dengan Chrome milik lane PROFILE.
- Provider semula menginisialisasi state `system`, membaca storage dalam effect, lalu effect tema lain langsung menyimpan state render awal. Hipotesis: mount dapat menimpa dark tersimpan sebelum state pembacaan diterapkan.
- Tes runtime awal: **9 gagal**, EXIT=1 karena initializer belum ada; sesudah implementasi **9 lulus**, EXIT=0.
- Tes React tema awal: **5 gagal**, EXIT=1; sesudah integrasi **5 lulus**, EXIT=0, termasuk memastikan render pertama bukan system ketika dark tersimpan.
- Tes delegasi core baru gagal sebelum perubahan (**1 gagal dari 12**), lalu lulus.
- Inspeksi loader mengungkap penghentian bubbling saat chunk belum siap. Tes reproduksi terarah **1 gagal**, EXIT=1; handler capture memperbaikinya tanpa mengubah loader atau guard ownership.

### Verifikasi akhir

| Gate | Waktu UTC 2026-10-25 | Hasil |
|---|---|---|
| Node, 9 file tema/locale/brand/profile/admin-shell | 16:51:00.725–16:51:01.080 | **51 lulus**, 0 gagal, EXIT=0 |
| Vitest, 9 file tema/storefront/handoff/profile/account/admin-shell | 16:51:00.761–16:51:01.993 | **96 lulus**, 0 gagal, EXIT=0 |
| `node --check public/js/zafira-core.js` | batch verifikasi akhir | EXIT=0 |
| `node --check resources/js/lib/storefront-preferences.js` | batch verifikasi akhir | EXIT=0 |
| `git --no-pager diff --check` | batch verifikasi akhir | EXIT=0 |

Perintah test lengkap, timestamp dan exit disimpan di:

- `scratch/react-migration/theme-init-ui-local-20261025/node-tests.json`
- `scratch/react-migration/theme-init-ui-local-20261025/node-tests.log`
- `scratch/react-migration/theme-init-ui-local-20261025/react-tests.json`
- `scratch/react-migration/theme-init-ui-local-20261025/react-tests.log`

Tes khusus baru berjumlah **13 Node + 6 React**. Membuktikan DOM/state dan kontrak sumber, bukan paint/layout browser. Perbandingan baseline: kedua kegagalan guest tertutup tanpa menghapus assertion; startup dark tidak ditimpa system; sinkronisasi tidak memicu false-dirty; interaksi pengguna tetap mengunci fallback. Tidak ada source aplikasi diubah setelah verifikasi akhir, kecuali dokumen handoff ini.

## Skill, lensa, dan delivery gate

Dipakai: `antislop` DURING (session override), `accessibility`, `frontend-patterns`, `browser-testing-with-devtools`, `git-workflow-and-versioning`, `incremental-implementation`. Workflow browser/build/commit dari skill sengaja tidak dieksekusi karena larangan eksplisit lane. Skill redesign, animasi, aset generatif, dan backend tidak diterapkan: tidak ada desain baru, motion, aset, atau logika server dalam scope.

Arah desain diwarisi dari kontrak induk: Sapphire/Linen, Bodoni Moda/Manrope; ENERGY1/RHYTHM2/MOTION1 untuk admin, storefront existing dipertahankan. Tidak menciptakan keputusan visual baru.

Pemeriksaan oleh author, **NON-INDEPENDENT**, bukan persetujuan dewan/reviewer:

- Norman/Krug: label dan lokasi kontrol existing dipertahankan; pilihan dan status kontrol konsisten.
- Nielsen/Cooper: preferensi tersimpan tidak hilang saat mount; system dan pilihan eksplisit dibedakan; kegagalan storage tidak memblokir UI.
- Shneiderman/Watson: kontrol native dipertahankan, sinkronisasi tidak memindah fokus atau mengubah draft lain, guard handoff tetap aktif.
- Engelbart: tidak mengubah data/fitur dashboard atau menambah beban input.
- Brown: runtime bekerja tanpa bundle; tidak menambah dependency atau UI dekoratif.

Delivery gate scoped: **PASS** fungsi kontrol/retensi preferensi/handoff berdasarkan 19 tes tema baru dan regresi di atas; **PASS** scope/no-new-assets/no-new-copy/no-new-CSS-rules berdasarkan diff. Gate visual responsif, contrast, real keyboard/screen reader dan no-flash browser **TIDAK DIPERIKSA**, bukan PASS WCAG atau PASS visual seluruh produk.

## Tidak diperiksa dan risiko tersisa untuk integrator

1. **`resources/css/theme.css` belum ada pada saat handoff.** Import sudah disiapkan sesuai mandat; integrator wajib menyediakan file sebelum build.
2. **Head katalog, shop, dan layout error belum memuat `resources/css/app.css`.** Runtime di head sudah aktif, tetapi import theme/profile dari app.css tidak otomatis mencapai halaman itu. Integrator perlu memilih pemuatan stylesheet yang tepat tanpa mengubah layout existing secara tidak sengaja.
3. PHP/Blade compile, DB, build, browser nyata, computed contrast, 320px/reflow, CSP deployment, dan paint/no-flash belum diuji. Synchronous script ordering diuji secara statis dan perilaku state diuji di JSDOM, bukan bukti visual.
4. Belum ada reviewer independen atau approval integrasi. Perubahan ADM/PROFILE/STOCK lain dalam working tree bukan hasil author lane ini. Tes atas integrasinya tidak menggantikan review lane masing-masing.
5. Tidak menambah theme widget pada halaman auth. Header shop juga tidak diubah; pemilih tema di sana dapat diputuskan pada lane UI berikutnya menggunakan copy terjemahan yang sudah ada.
6. Dokumen ini dan ringkasan lane perlu ditautkan ke indeks/ledger oleh integrator pemilik dokumentasi bersama. Log scratch bisa hilang; ringkasan hasil di atas adalah rekaman durable.
