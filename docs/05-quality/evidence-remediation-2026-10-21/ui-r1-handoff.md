# UI R1 handoff — REM-20261021 / FR-REM-005

Tanggal: 2026-10-21. Status: **R1 diperbaiki; Node GREEN; menunggu verifikasi PHP/browser serial dan re-review main/UI0ad**. Bukan persetujuan release.

## Diagnosis dan perubahan

1. **ParseError welcome / unexpected endforeach**
   - Fakta source compiler: `vendor/laravel/framework/src/Illuminate/View/Compilers/BladeCompiler.php:432–436`, `storePhpBlocks()` memakai regex `/(?<!@)@php(.*?)@endphp/s`.
   - Assignment inline `@php($storefrontAppCopy = ...)` yang ditambahkan sebelum blok PHP kartu ikut ditangkap sampai `@endphp` kartu. Markup dan pembuka `@foreach` berada dalam raw block, sementara `@endforeach` tertinggal untuk compiler. Ini akar masalah, bukan kesalahan penutup loop.
   - Assignment dipindahkan ke blok PHP awal welcome yang sudah ada. Injection copy PDP memakai blok PHP berpasangan juga. `@json` tetap menerima variabel sederhana, tidak ekspresi dengan koma.
   - Guard Node dengan regex storePhpBlocks yang diverifikasi gagal pada source sebelum fix, lalu lulus. Guard ini **bukan compiler PHP penuh**; main tetap harus membuktikan render PHP, terutama Task3Remediation.

2. **Navigasi katalog penuh dengan home bounded 24**
   - Batas existing diverifikasi pada `StorefrontController::FEATURED_PRODUCT_LIMIT = 24`; tidak mengubah controller, cache, limit, atau melakukan full hydrate.
   - Chip Tas Tangan home/PDP dan category cards memakai `kategori=tas-tangan` (slug kategori), bukan pencarian teks `q=Tas Tangan`. Chip terbaru memakai `urut=terbaru`; Saphir tetap `q=Saphir`.
   - Category pills dinamis menuju `category=<cat_id>`, parameter numerik canonical yang didukung CatalogFilterRequest/CatalogService. Category cards editorial lain memakai slug `kategori`.
   - Form pencarian inline home kini GET `route('catalog.index')`, `q`, maxlength 100, label yang berasosiasi dan tombol submit; bukan filter featured subset.
   - Home menampilkan note bahwa hingga 24 kreasi adalah pilihan beranda, tautan Lihat semua yang selalu ada, serta empty state yang tidak menyatakan katalog penuh kosong.
   - `data-catalog-url` menandai home bounded; restore query lokal legacy tidak lagi menyaringnya menjadi false empty. Legacy matcher/filter untuk surface lain dan kasus regresinya dipertahankan. Guest favorite preview tetap terbatas kartu tampil dengan note/exit katalog yang jelas.

3. **Retry checkout melampaui native validation gate**
   - Submitter akun/tamu mendapat `formNoValidate = Boolean(pendingCheckout)` setelah aksi selesai, bukan `novalidate` permanen pada form.
   - Saat pending/uncertain, retry mengirim key dan payload semula walaupun visible fields diedit menjadi kosong/email invalid. Sesudah sukses atau rejection definitif 409/422, bypass dihapus dan validasi native kembali aktif.
   - Tes timeout guest kini melewati model browser-submit gate sebelum memanggil handler; input required/email invalid diuji, bukan hanya pemanggilan langsung onsubmit.
   - Ditambah simulasi constraint gate akun dan tamu, hasil sukses/definitive rejection, serta equality payload replay. Simulasi ini bukan bukti implementasi native browser; main perlu klik/Enter di browser.

4. **Copy dan policy boundary**
   - `table_js.saved_refresh_failed` ditambahkan untuk kelima `admin_ui.php`, sesuai key yang sudah dipakai ADMIN-UI owner. Tidak mengedit admin-table/prototype source.
   - Recovery intro admin/pelanggan menjelaskan: halaman kode dapat dibuka sekali dalam 5 menit setelah aktivasi; batas tersebut untuk membuka halaman, bukan masa berlaku kode tersimpan. Source kedua controller memakai STATE_TTL 300 dan pull-once. Tidak mengklaim kode kedaluwarsa 5 menit.
   - Shop hanya menawarkan cancel pada status pending. Paid/processing menampilkan penjelasan tidak dapat dibatalkan di surface ini, tanpa janji refund otomatis, dan tautan bantuan ke home#services. Mengikuti STATUS_FLOW/Order::isCancellable existing; tidak mengubah financial/backend policy.

## File ditulis khusus R1 (daftar persis)

Source UI:
- `resources/views/welcome.blade.php`
- `resources/views/products/show.blade.php`
- `resources/views/partials/product-footer.blade.php`
- `resources/views/shop/index.blade.php`
- `resources/js/app.js`

Locale:
- `lang/id/shop_ui.php`
- `lang/en/shop_ui.php`
- `lang/fr/shop_ui.php`
- `lang/it/shop_ui.php`
- `lang/zh/shop_ui.php`
- `lang/id/admin_ui.php`
- `lang/en/admin_ui.php`
- `lang/fr/admin_ui.php`
- `lang/it/admin_ui.php`
- `lang/zh/admin_ui.php`
- `lang/id/account_ui.php`
- `lang/en/account_ui.php`
- `lang/fr/account_ui.php`
- `lang/it/account_ui.php`
- `lang/zh/account_ui.php`

Tests:
- `tests/storefront-remediation.test.mjs`
- `tests/shop-cart-load.test.mjs`
- `tests/task4.test.mjs`

Evidence dalam direktori ini:
- `ui-r1-node-before-test-alignment.log` (171 pass, 1 assertion lama yang masih mewajibkan local pressed filter)
- `ui-r1-node-green.log`
- `ui-r1-handoff.md`

Tidak mengubah controller/routes/admin-table/prototype source, file author lane lain, dependency atau database. Daftar ini adalah delta R1, bukan keseluruhan UI GREEN terdahulu (lihat ui-handoff.md).

## Tes dan koreksi prescription

- Seluruh kasus remediasi sebelumnya dipertahankan; sekarang **51 tes storefront-remediation** dengan tambahan guard Blade, navigasi bounded home/category, copy lima bahasa dan false-empty prevention. Tes checkout lama diperkuat dengan native-gate simulation; tidak dibuang.
- `task4` sebelumnya memaksa `#live-catalog-search-input` dan `data-filter=Semua aria-pressed=true`. Itu kontrak implementasi lama yang salah untuk home bounded. Diganti pemeriksaan label/input yang terkait, grup navigasi kategori berlabel, URL canonical, label kategori dan tidak adanya pressed state palsu. Pemeriksaan status count/PDP search lainnya tetap ada.
- Model regex Blade dan tes markup/copy adalah bukti statis; VM request/form adalah bukti perilaku dalam simulasi. Tidak menyamakan keduanya dengan browser/PHP.

## Continuous Evaluation Record

- Task: REM-20261021 / FR-REM-005; R1 reviewer UI0ad dan laporan runtime main.
- Baseline: main melaporkan ParseError PHP; tidak saya ulang. Tiga tes R1 awal Node (raw Blade blocks, bounded home routing, category suggestions) gagal sebelum perubahan (EXIT=1).
- Controlled change: daftar file R1 di atas, tanpa full hydrate/rewrite.
- Ricek akhir: command 15 suite Node persis tercatat di header `ui-r1-node-green.log`, @ `2026-10-21T13:52:12.441341+00:00`: **172 tests, 172 pass, 0 fail/cancel/skip, EXIT=0**.
- `node --check resources/js/app.js` -> EXIT=0; `git --no-pager diff --check` -> EXIT=0.
- Skill: `debugging-and-error-recovery`, serta lensa `accessibility`/`frontend-ui-engineering` dan disiplin `incremental-implementation`/`git-workflow-and-versioning` dari putaran awal. Tidak browser-testing karena browser dibatasi ke main; tidak commit.
- Ricek source memakai compiler Laravel, CatalogFilterRequest/CatalogService, Order::STATUS_FLOW/isCancellable dan kedua recovery controller sebagai sumber independen terhadap asumsi UI. Ini tidak menggantikan reviewer independen.
- Lensa HCI: Norman/Krug—kategori/search sesuai cakupan dan affordance link; Nielsen—status simpan-vs-refresh dan batas hasil jelas; Shneiderman—retry tetap memberi closure tanpa duplikasi; Cooper—payload tidak perlu diketik ulang; Engelbart—status UI sesuai data canonical; Brown—reuse brand/API tanpa full hydrate; Watson—label form, semantic navigation, constraint gate normal dan replay terpisah. Keamanan: TTL page retrieval tidak disamakan dengan expiry kode; paid-cancel tidak menjanjikan refund.
- Author/reviewer acceptance: author GREEN belum menerima persetujuan ulang UI0ad/main. Decision: **READY FOR RE-REVIEW / PHP + browser verification pending**.

## Tidak diperiksa / risiko tersisa

- **Tidak menjalankan PHP sama sekali pada putaran R1**, DB, server, browser, install, build atau commit; tidak membaca `.env`. Riwayat accidental PHP read-only putaran pertama tetap tercatat di handoff awal, tidak disembunyikan.
- Root Blade fix didukung source compiler dan guard Node, tetapi parse/render PHP setelah fix: **belum saya verifikasi**. Main perlu mengulang tes Task3Remediation yang gagal, lalu render home/PDP.
- Native form constraint behavior pada klik/Enter dan `formNoValidate` memerlukan crosscheck browser main; simulasi tidak membuktikan kompatibilitas engine.
- Batas awal yang belum berubah: action shop masih global-lock dengan deadline, replay in-memory halaman, belum persist lintas reload, belum klaim WCAG/viewport/AT nyata. API real/session dan financial policy tetap gate lane/main.
