# Hasil dashboard, profil, tema dan stok lokal

**Task:** UI-LOCAL-20261025. **Status:** ACCEPTED untuk lingkup lokal yang diuji, tanpa deployment atau publikasi Git. Pemeriksaan final25Oktober2026UTC (26OktoberWIB). Baseline source `208ee9669352a826c703c7e3d318962b8e171da8`.

[Requirement dan AC](../04-implementation/ADMIN_PROFILE_THEME_STOCK_2026-10-25.md), [ledger](../04-implementation/TEAM_SUPPORT_LEDGER.md), [ringkasan machine-readable dan hash83file sumber](evidence-ui-local-2026-10-25/verification.json).

## Perubahan yang tersedia

1. **Dashboard admin:** sidebar Sapphire, topbar dengan tema/profil/logout, kartu ringkasan dari data server, dan tabel pesanan didahulukan. Tujuh region snapshot, form eksternal, guard draf, history dan rollback Blade tetap dipertahankan. Menu mengikuti permission dan feature flag; tidak ada grafik/angka contoh atau pencarian dekoratif.
2. **Profil admin:** `/admin/profil` (`admin.profile.show`). Identitas dan peran read-only, status keamanan, tautan enrolment/audit sesuai kewenangan, dan form kata sandi melalui endpoint existing. Guard admin/TOTP, no-store, CSRF dan DTO whitelist tetap berlaku.
3. **Profil pelanggan:** `/akun` existing kini memiliki identitas berinisial, section kontak/keamanan/privasi/riwayat dan pemilih tema. Menu beranda membedakan login tamu dengan profil pengguna masuk pada React dan Blade. Riwayat dilabeli concierge/janji temu, bukan pesanan yang tidak tersedia di halaman tersebut.
4. **Tema:** satu initializer pada19head, preferensi light/dark/system konsisten lintas navigasi, perubahan OS, tab dan BFCache. Dark mode bukan sekadar mengganti `--ink`: permukaan, placeholder, ikon, status, fokus, form, katalog, belanja, akun/auth/admin memakai pasangan semantik. Aset, brand dan tipografi storefront dipertahankan. QR TOTP tetap memiliki backing putih agar dapat dipindai.
5. **Stok lokal:**12produk aktif ID1–12 yang sebelumnya stok0 diisi menjadi10unit per produk melalui command guarded. Tidak mengubah harga, user, kredensial, order atau atribut produk lain. Tidak ada seed/reset DB atau flush cache bersama.

### Tampilan yang diverifikasi

Screenshot admin/akun memakai **fixture sintetis terisolasi**, bukan data pelanggan lokal.

- [Admin terang](evidence-ui-local-2026-10-25/admin-light.png)
- [Admin gelap](evidence-ui-local-2026-10-25/admin-dark.png)
- [Profil admin mobile](evidence-ui-local-2026-10-25/admin-profile.png)
- [Profil pelanggan mobile](evidence-ui-local-2026-10-25/customer-profile.png)
- [Storefront gelap native8001 dengan stok tersedia](evidence-ui-local-2026-10-25/storefront-dark.png)

## Baseline dan hasil pengukuran

- Baseline katalog dark: judul sekitar **1,10:1**, harga sekitar **1,11:1** pada permukaan hardcoded yang tidak ikut tema. Input pencarian beranda memiliki placeholder **3,97:1** pada putih; foreground input juga terlalu terang.
- Gate final: **336pengukuran** solid-surface text/input/placeholder/icon/fokus lulus ambang **4,5:1teks**, **3:1ikon/fokus** pada kedua renderer/tema. Tidak memakai pengecualian teks besar untuk meloloskan sampel.
- Reviewer mencocokkan perbaikan hover tombol tambah: sekitar **11,378:1light**, **9,262:1dark**. Fokus sidebar tetap-gelap sekitar **15,157:1** pada16sampel.
- Reflow **320/390/1440px**,78kombinasi13halaman×2renderer×3mode,234sel reflow. Tidak ditemukan overflow halaman pada matriks tersebut; tabel lebar scroll di region sendiri.
- Port8001existing:6routepublik×2tema merespons200 dan rootReact ready.12/12tombol tambah produk beranda aktif, CTA PDP aktif, formulir checkout tamu tersedia. Tidak membuat pesanan atau mengubah kredensial pengguna untuk verifikasi ini.

## Continuous Evaluation Record

- **Requirement:** FR-ADM/PROFILE/STOCK-20261025, NFR-THEME/SAFE-20261025.
- **Baseline:** shell terpusat1100px tanpa sidebar; profil pelanggan sudah ada; tema hanya diinisialisasi beranda dan memiliki patch warna saling menimpa; probe DB lokal mengonfirmasi12aktif/12stoknol.
- **Controlled change:** shell/admin CSS dan tampilan; route/controller/DTO profil baru; layout akun/menu; initializer tema dan token terpusat; command stok local-only beserta tes; harness browser tambahan. Tidak mengubah coordinator, algoritme checkout, skema database atau dependency.
- **Commit/diff:** belum commit. Hash source final83file tersimpan pada `verification.json`; docs dan screenshot tidak termasuk hash source.
- **Ricek:** daftar perintah di bawah benar-benar dieksekusi integrator. Log besar tetap private/ignored di `scratch/react-migration/ui-local/` karena dapat berisi form fixture.

| Gate | Perintah / metode | Hasil |
|---|---|---|
| Node | `node --test` | **667pass**,0fail, EXIT0 |
| React | `npm run test:react` | **255pass**,19file, EXIT0 |
| PHP seluruh file | `scripts/amadis/launch.sh test` dengan152file dipartisi8kelompok serial, JUnit per kelompok | **1367pass +3skip engine MySQL**,160809assertion, seluruh8EXIT0 |
| PHP delta terakhir | Harness, AccountPrivacyTest/InertiaAccountTest/AuthTest/PasswordResetFlowTest | **32pass**,393assertion, EXIT0; bukan tambahan ke total suite |
| Stok terarah | `php vendor/bin/phpunit --do-not-cache-result tests/Unit/PrepareLocalStockTest.php` | **70pass**,222assertion, EXIT0; juga tercakup suite penuh |
| Format | `vendor/bin/pint --test` | **421file**, EXIT0 |
| Python | `scratch/ci-validation/venv/bin/python scripts/ci/run_python_tests.py` | **434test**, EXIT0 |
| Build | `npm run build` | EXIT0 |
| UI browser | `bash scripts/amadis/acceptance_ui_local.sh … 8044` | **78cases/2149checks**,0fail,250screenshot,336contrastPASS, unexpected events0, cleanup confirmed, EXIT0 |
| Regresi native | `bash scripts/amadis/acceptance_react.sh … 8043` | **31mandatoryPASS**, unavailable0, unexpected console0, cleanupPASS, EXIT0 |
| Native pengguna | Probe browser terisolasi pada existing8001, tanpa login pengguna |12produk tersedia, CTA PDP dan form checkout ada, pageerror0, EXIT0 |
| Whitespace | `git --no-pager diff --check` | EXIT0 |

Run UI final18:31:25–18:32:20UTC. Suite31 mencakup cart delta/undo, checkout frozen retry/idempotency, akun update/export/logout, TOTP, serta dashboard atomic snapshot/draf/history. Empat fault terkontrol yang sengaja diinjeksi sesuai kontrak, bukan diabaikan sebagai console umum.

**Kegagalan yang tidak disembunyikan:** invocation PHP monolitik timeout240detik dan bukan PASS. Bukti lengkap diperoleh dari8partisi tanpa duplikasi/kelalaian file, telah dicrosscheck reviewer. Filter PHP gabungan ditolak guard, fixture TOTP memerlukan cookie dan persistensi handler yang benar, dan beberapa assertion layout lama perlu diselaraskan ke DOM baru tanpa menghapus perilaku yang diuji. UI R1/R2 menemukan target kecil/fokus/kontras; evaluator fokus semula salah menghitung SVG di dalam kontrol sebagai backdrop outline luar. Perbaikannya punya49unit test dan tetap menolak gambar yang benar-benar memotong outline. Dua timeout Blade ditelusuri ke anak drawer yang belum visible saat shell mulai transisi; harness menunggu visibility dan memverifikasi fokus, tidak memakai sleep atau melonggarkan gate.

### Crosscheck independen

Reviewer `9d344186-90d3-4d26-a7c6-a532fae97073`, bukan author, memberikan **APPROVE scoped** setelah memeriksa sumber, koreksi, geometri harness,152filePHP/JUnit, matriks/screenshot, inventaris44Blade/19head dan integritas assertion. Follow-up akhir menyetujui penggantian warna status auth/error penghapusan dengan token semantik. Reviewer tidak menjalankan ulang DB/browser/PHP: hasil runtime di atas dieksekusi integrator dan dicocokkan melalui artefak oleh reviewer.

Author ADM `91841add-ffd6-4b69-8e1e-33c737d8574d`; PROFILE `3da3363f-e918-48c9-874c-e847ae7b0847`; STOCK `eb07b042-500d-4cca-b3e5-eb411549c025`; THEME-INIT `2a67127e-e980-4121-bf92-9436be0b5ad1`; QA harness `597353b6-346b-4262-9fd3-cfce5dca9ebb`; integrasi/CSS oleh Zed utama (ID tidak diekspos). Seluruh penugasan dilepas, tidak ada writer aktif.

## Stok simulasi dan penggunaan

[Receipt apply](evidence-ui-local-2026-10-25/stock-receipt.json) mencatat setiap ID, slug dan before/after. Database terverifikasi `local`, driverMySQL, endpointloopback.12produk tetap aktif, semuanya stok10pada probe akhir; preview ulang menghasilkan eligible0. Command hanya mengubah kolom `stock`, dengan transaksi/rowlock dan validasi seluruh pilihan sebelum update. Produk nonaktif/stokpositif dipertahankan.

Untuk simulasi berikutnya, preview lebih dahulu dan pilih ID eksplisit:

```sh
php artisan stock:prepare-local --id=1 --id=2 --quantity=10
php artisan stock:prepare-local --id=1 --id=2 --quantity=10 --apply
```

Tanpa `--apply`, tidak ada update. Maksimal20ID dan quantity1–100. Command menolak environment non-local, koneksi remote/URL/split/ambigu, dan MySQL non-InnoDB. Stok positif tidak ditambah atau ditimpa. Beranda/rekomendasi dapat menyimpan snapshot hingga300detik; katalog/PDP membaca stok baru. Pada verifikasi final8001, cache lama sudah tidak menutupi stok baru. Listener existing8000/8001 tidak dihentikan atau diganti.

## Lensa HCI dan keputusan desain

| Lensa | Pemeriksaan dan hasil scoped |
|---|---|
| Norman | Profil dan theme diberi label/tujuan nyata; initials berasal dari akun. Tidak ada search dekoratif. |
| Nielsen | Empty/loading/error dan draf tetap tersedia; drawer Escape kembali ke trigger. |
| Shneiderman | Target sampel44px, kontrol keyboard, feedback status dan undo cart diuji. |
| Cooper | `/akun` dan endpoint password existing dipakai kembali, tanpa input identitas ulang atau auth paralel. |
| Engelbart | Overview memakai hitungan nyata dan langsung menuju tabel kerja. |
| Brown | Referensi diadaptasi hanya pada fitur feasible existing; tidak menambah metrik rekaan. |
| Krug | Sidebar, topbar, breadcrumb dan section profil memisahkan navigasi dari pekerjaan. |
| Watson | Semantik nav/heading/form, fokus, keyboard dan336contrast sample diverifikasi. Screen reader manusia belum diuji. |

Nama tokoh adalah lensa kerja, bukan endorsement. ENERGY1/RHYTHM2/MOTION1 untuk konsol: hierarki berasal dari spacing, type dan tabel, bukan animasi baru.

## Delivery gate antislop, scoped pada perubahan ini

| Rule | Hasil / bukti |
|---|---|
| R-01 | PASS: Sapphire/Linen/Gold existing, tanpa gradient baru. |
| R-02 | PASS: copy baru tidak memakai em dash; teks historis tidak direwrite. |
| R-03 | PASS:234sel reflow320/390/1440, target kontrol sampel44px. |
| R-04 | PASS: ikon existing untuk akun/tas, tanpa ikon dekoratif baru. |
| R-05 | PASS: ringkasan dan urutan tabel mengikuti pekerjaan atelier. |
| R-06 | PASS: Manrope/admin, Bodoni identitas/storefront; brand guard lulus. |
| R-07 | PASS: tidak menambahkan pola latar dekoratif. |
| R-08 | PASS: tidak menambahkan panah dekoratif. |
| R-09 | PASS: status berasal dari data server, bukan badge klaim baru. |
| R-10 | PASS: tidak menambah lapisan glassmorphism. |
| R-11 | PASS: radius kontrol/panel konsisten, tidak semua dipill-kan. |
| R-12 | PASS: tidak menambah shadow pada setiap panel. |
| R-13 | PASS: tidak menambah glow; dot dekoratif profil fallback dihapus. |
| R-14 | PASS: kartu adalah hitungan kerja nyata, tabel tetap hierarki utama. |
| R-15 | PASS: label profil, logout, kata sandi dan tabel menyebut aksi nyata. |
| R-16 | PASS: tidak menambahkan buzzword pemasaran. |
| R-17 | PASS: metric server dan receipt stok, tanpa angka referensi palsu. |
| R-18 | PASS: tidak menambah testimonial. |
| R-19 | PASS: tidak menambah animasi; transisi existing diperiksa bersama keyboard. |
| R-20 | PASS: warna/font/aset ZAFIRA tetap; screenshot tersimpan. |
| R-21 | PASS: light/dark/system dipilih pengguna dan dipersistenkan. |
| R-22 | PASS: tidak membuat ilustrasi baru. |
| R-23 | PASS: avatar hanya initials dari identitas, bukan foto buatan. |
| R-24 | PASS: menu tujuan nyata dengan permission/flag; matriks navigasi lulus. |
| R-25 | PASS scoped:336sample text/control/fokus lulus; bukan klaim semua piksel. |
| R-26 | PASS scoped: kontrol baru profil/tema/menu punya perilaku teruji. |
| R-27 | PASS: feedback/error/empty/draft preservation tetap diuji suite31. |
| R-28 | PASS: tidak menambah FAQ. |
| R-29 | PASS: palet brand existing dan warna status semantik. |
| R-30 | PASS: struktur referensi diminta pengguna, tidak menyalin identitas/angka referensi. |
| R-31 | PASS: alasan utama tercatat pada kontrak desain sebelum implementasi. |
| R-32 | PASS scoped: Tab/Enter/Escape/focus restore dan outline terukur. |
| R-33 | PASS: perubahan langsung pada sumber, bukan script patch CSS. |
| R-34 | PASS scoped: matriks native/Blade light/dark/system lulus. |
| R-35 | PASS scoped: build dan click-through berikut dieksekusi, bukan code-only claim. |
| R-36 | PASS: tidak ada klaim compliance/performa/produksi baru tanpa bukti. |
| R-37 | PASS: arah brand/reference dan tiga dials ditulis sebelum perubahan. |
| R-38 | PASS: data nyata atau fixture sintetis yang dilabeli jelas. |

Click-through: pemilihtema→tema+persistensi lintasroute/OS; menu mobile→Enterbuka/Escapetutup/fokusbalik; profiladmin→`/admin/profil`→section; akunpelanggan→drawer→`/akun`→section; katalog→quickview→Escape/fokusbalik; passwordtoggle→show/hide; wishlist→selected/unselected; addhover→ikon tetap terlihat. Suite31 juga menjalankan filter/paginasi/history, perubahan akun, export/logout, cartdelta/undo, checkoutretry, dan TOTP pada fixture terisolasi. Tidak mengklaim semua kontrol historis diperiksa satu-persatu oleh manusia.

## Tidak diperiksa dan risiko tersisa

- Tiga uji deadlock/retry/lock-wait MySQL tetap skipped oleh suite SQLite. Tidak ada klaim tes konkurensi InnoDB baru dari apply stok tunggal.
- Tidak menguji screen reader manusia, semua kombinasi data ekstrem, semua piksel foto/gradient, browser selain Chromium, perangkat fisik, load/performance produksi, CSP deployment atau seluruh WCAG2.2.
- Monogram adalah visual material dengan input/label pendamping; warna foil artistik tidak dinyatakan memenuhi rasio teks UI dalam semua pilihan.
- Email/PDF dan prototype standalone tidak dimigrasikan ke runtime tema aplikasi. QR tetap light agar scan stabil.
- Tidak menggunakan sesi admin/pelanggan nyata pada8001. Verifikasi privat/transaksi menggunakan fixture sendiri; tidak membuat order baru di DB pengguna.
- Tidak ada commit/push, runCIremote finalSHA, deployment, atau perubahan proteksi branch pada task ini. CI historis bukan bukti diff baru.
- Screenshot hanya sampel visual;250screenshot lengkap dan log mentah berada pada ignored scratch dan dapat hilang. Ringkasan permanen serta receipt aman disimpan di folder evidence dokumen ini.
