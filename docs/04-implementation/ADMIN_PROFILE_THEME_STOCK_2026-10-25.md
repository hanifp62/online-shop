# Dashboard, profil, tema dan stok simulasi lokal

Task `UI-LOCAL-20261025`, baseline `208ee9669352a826c703c7e3d318962b8e171da8`. Mandat pengguna: adaptasi referensi dashboard/sidebar dan profil ke fitur ZAFIRA, kontras dark mode lintas aplikasi, serta stok tersedia di database localhost untuk simulasi. Tidak ada izin reset database, penggantian kredensial atau deployment.

**Penutupan:** ACCEPTED scoped, [laporan hasil dan batas bukti](../05-quality/ADMIN_PROFILE_THEME_STOCK_2026-10-25.md). Requirement di bawah tetap menjadi kontrak awal, bukan klaim produksi.

## Requirement dan acceptance criteria

| ID | Kebutuhan | Gate penerimaan |
|---|---|---|
| FR-ADM-20261025 | Dashboard kerja dengan sidebar, topbar dan ringkasan nyata | Navigasi hanya tujuan nyata dan sesuai permission/flag; desktop dan mobile berfungsi; tujuh region snapshot, dirty-draft guard dan riwayat tetap lolos regresi; Blade fallback tetap tersedia. |
| FR-PROFILE-20261025 | Menu profil admin dan pelanggan | Pelanggan menuju `/akun` yang sudah ada; identitas, detail kontak, keamanan, privasi dan riwayat diberi hierarki jelas. Admin mendapat profil terlindungi guard admin/TOTP/no-store, tanpa membocorkan credential atau memperluas role. |
| NFR-THEME-20261025 | Tema terang/gelap/otomatis lintas aplikasi | Preferensi konsisten di beranda, katalog, PDP, belanja, auth/akun dan admin. Pengukuran computed text/input/placeholder/icon yang relevan mencapai 4,5:1 teks biasa dan 3:1 komponen/teks besar; tidak mengklaim seluruh WCAG dari sampel. Fokus keyboard terlihat, target baru minimal44px, layout320px tanpa overflow halaman. |
| FR-STOCK-20261025 | Stok simulasi pada database lokal pengguna | Diagnosis read-only lebih dulu. Jika benar nol, update terbatas produk eksplisit, hanya stok nol, preserve stok positif/transaksi/atribut lain; local-only guard, preview, receipt sebelum/sesudah, locking dan uji idempotensi. Ketersediaan dibuktikan di native8001. |
| NFR-SAFE-20261025 | Tidak ada regresi dan perubahan tersembunyi | Tidak membaca dotenv/secret; tidak reset DB; resource PHP/DB/browser/build serial; reviewer berbeda dari author. Validasi terarah lalu suite regresi dan batas bukti ditulis. |

## Baseline dan hipotesis

- Git bersih pada main, sama dengan origin/main; inspeksi25Okt16:12UTC menemukan listener existing8000 dan8001, tidak diubah.
- Shell `resources/views/admin/layouts/app.blade.php` berisi header/nav terpusat1100px; tabel dan angka dari DTO nyata. Hipotesis: ubah shell/hierarki, bukan coordinator atau data transaksi.
- `Account/Show.jsx` sudah memiliki kontak, TOTP, privasi dan riwayat concierge. Drawer beranda lebih menonjolkan preferensi daripada profil. Hipotesis: akses jelas dan section navigation mengatasi kebutuhan tanpa auth paralel.
- Initializer tema ditemukan hanya di welcome; beberapa permukaan putih memakai `--ink` yang berubah menjadi terang pada dark mode. Hipotesis kontras wajib dibuktikan lewat browser sebelum dan sesudah.
- Screenshot stok habis bukan bukti DB. Diagnosis/model/receipt menunggu lane STOCK dan integrator.

## Kontrak desain

Reading this as: dashboard kerja untuk pengelola atelier, bahasa visual Sapphire/Linen ZAFIRA, ENERGY1 / RHYTHM2 / MOTION1. Storefront mempertahankan komposisi, aset dan tipografi existing.

- Sidebar Sapphire: navigasi stabil, bukan menu dekoratif. Topbar: konteks halaman, tema dan profil yang dapat dipakai.
- Manrope untuk data/admin; Bodoni Moda tetap pada identitas brand dan storefront. Angka memakai tabular-nums, tanpa font/dependensi baru.
- Ringkasan mengambil data nyata; tidak ada angka proyek, grafik/foto/avatar fiktif dari referensi.
- Gold terbatas pada identitas/penanda aktif. Permukaan dan foreground memakai pasangan semantic agar tema tidak bergantung pada override satu elemen.
- Spacing membedakan navigasi, overview dan tabel; tabel bisa scroll di region sendiri tanpa memaksa halaman melebar. Menu mobile disclosure keyboard-friendly.
- Profil menggunakan inisial dari identitas yang nyata, tidak membuat aset foto. Aksi berisiko tetap terpisah dan dikonfirmasi.
- Tidak menambah animasi masuk/scroll, library desain, atau refactor transaksi.

## Kepemilikan

Ledger otoritatif: `TEAM_SUPPORT_LEDGER.md`. ADM memiliki shell admin/CSS/admin dashboard read pages dan DTO dashboard; PROFILE memiliki route profil, controller/DTO profil, halaman akun/profil, Chrome dan lokalisasi profil; STOCK memiliki command stok dan tesnya; integrator memiliki theme global/initializer, evidence/browser, dokumentasi dan eksekusi shared resources. Perluasan boundary harus dicatat sebelum edit.

## Skill dan lensa

Dipakai: antislop DURING (session override), frontend-ui-engineering, accessibility, antislop-ui/human, incremental-implementation, laravel-patterns, browser-testing-with-devtools. Lane memuat testing/security skill relevan. design-taste-frontend hanya preserve-brand storefront, bukan resep dashboard. DevTools MCP tidak tersedia; gunakan Playwright existing, profil terisolasi. Skill animasi, video, aset generatif, framework native dan upgrade dependency tidak relevan. Jangan menambah tool untuk meniru referensi.

Lensa UI wajib: Norman (signifier profil), Nielsen (status/exit), Shneiderman (feedback/fokus), Cooper (alur kerja tanpa input ulang), Engelbart (ringkasan operasional nyata), Brown (referensi sesuai fitur yang feasible), Krug (menu/heading bisa dipindai), Watson (semantic/keyboard/kontras). Lensa data/auth: Beck, Kleppmann, Lamport, Schneier/Shostack/Janca/Hunt untuk regression, locking, local-only guard dan whitelist. Nama adalah lensa audit, bukan endorsement; bukti utama kode dan tes.

## Diagnosis selama integrasi

- Command filter regex gabungan ditolak harness (`Command not allowed`, EXIT2); parser hanya menerima satu nama filter. Gunakan filter tunggal atau seluruh suite, tidak melonggarkan guard.
- Tes TOTP profil pertama gagal karena cookie sesi fixture tidak terikat marker. Koreksi cookie membuat positive path lolos; negative case berikutnya masih menerima marker lama. Setelah dua run gagal pada test tersebut, perubahan dihentikan untuk diagnosis. Bukti `vendor/laravel/framework/src/Illuminate/Session/Store.php:114-116`: `loadSession()` menimpa atribut in-memory dari handler. Marker invalid baru harus disimpan ke handler sebelum request; bukan mengubah middleware atau menghapus assertion. Integrator melanjutkan hanya dengan perbaikan fixture yang berdasar bukti tersebut.
- Pengukuran kontras pertama setelah wiring masih menemukan patch dark lama app.css menimpa pasangan semantik baru. Token/patch dark duplikat dipindahkan ke satu owner theme.css; scrim foto tetap dipertahankan. Preview monogram adalah visual material yang menduplikasi input, bukan klaim semua kontras artwork telah lolos.

- UI browserR1/R2: evaluator focus keliru memperlakukan SVG di dalam kontrol sebagai gambar di belakang outline luar. Setelah diagnosis dua kegagalan, author QA memakai geometri band outline (49unit, termasuk12repro merah sebelum fix), tidak menurunkan ambang atau menghapus sampel. R3 menyisakan2timeout input keyboard Blade. Probe native8001 dan fixture membuktikan shell drawer sudah visible sementara anak masih hidden pada frame transisi, fokus gagal lalu Enter jatuh ke trigger. Setelah100ms anak visible dan semua tema/Escape bekerja. Harness kini menunggu visibility anak + memverifikasi fokus sebelum Enter, bukan menambah sleep atau mengubah auth/runtime.
- Suite PHP penuh dihentikan oleh batas240detik, tanpa bukti hasil lengkap. Verifikasi dipartisi secara serial ke8kelompok file seluruh suite152file, tetap harness terisolasi dan batas150detik/kelompok. Assertion copy yang bergantung layout lama diselaraskan ke lokasi label sidebar yang baru, tetap memeriksa terjemahan.

## Rencana verifikasi

1. Baseline screenshot/computed contrast dan stok read-only.
2. Tes baru per slice, lalu unit React/Node/PHP terisolasi dan Pint/build.
3. Browser native dan Blade fallback, kedua tema+system,320/390/desktop; navigasi/profil, validasi, focus/Escape, region tabel, cart/checkout terisolasi.
4. Review independen, receipt update lokal yang sudah diizinkan, konfirmasi8001 tanpa membuat transaksi pengguna secara diam-diam.
5. Catat hasil aktual, kegagalan, hal yang tidak diperiksa dan risiko tersisa pada evaluation record. Baseline CI sebelumnya bukan bukti diff ini.
