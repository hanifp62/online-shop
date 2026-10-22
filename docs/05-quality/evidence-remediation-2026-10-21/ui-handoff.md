# Handoff UI — REM-20261021 / FR-REM-005

Tanggal: 2026-10-21. Status: **GREEN Node; siap crosscheck independen oleh integrator, belum ACCEPTED/browser-ready**.

Takeover dari author `5fd492be-ec59-44e0-b2ce-e395616324e6` yang berhenti (dikonfirmasi pengguna). Tes parsial dipertahankan dan diperluas, bukan ditulis ulang. Identitas sesi takeover tidak tersedia melalui tool; tidak mengarang ID reviewer/author. Ledger bersama tetap milik integrator.

## Ownership aktual (write)

Source:
- `resources/js/app.js`
- `resources/css/app.css`
- `public/js/zafira-core.js`
- `resources/views/welcome.blade.php`
- `resources/views/products/show.blade.php`
- `resources/views/partials/product-footer.blade.php`
- `resources/views/catalog/index.blade.php`
- `resources/views/shop/request.blade.php`
- `resources/views/shop/index.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/totp.blade.php`
- `resources/views/auth/password-email.blade.php`
- `resources/views/auth/password-reset.blade.php`
- `resources/views/account/show.blade.php`
- `resources/views/account/totp-enrol.blade.php`
- `lang/id/shop_ui.php`, `lang/en/shop_ui.php`, `lang/fr/shop_ui.php`, `lang/it/shop_ui.php`, `lang/zh/shop_ui.php`

Tests: `tests/storefront-remediation.test.mjs`, `tests/storefront-locale.test.mjs`, `tests/task3.test.mjs`.
Evidence: `ui-node-progress.log`, `ui-node-green.log`, `ui-handoff.md` dalam direktori ini.

Tidak mengedit controller, routes, admin-table, prototype source, config, dependency, migration atau test lane lain. Perubahan file-file tersebut terlihat di working tree dari lane lain; tidak diambil alih atau direvert.

## Kontrak dan hasil per temuan

| Audit | Implementasi / invariant |
|---|---|
| UI-01 | CSS canonical membatalkan dock band dan body fixed-height milik prototype; document/window menjadi scroller. Body overlay `no-scroll` tetap tersedia. Katalog mendapat override scoped shell; source prototype tidak berubah. Font Bodoni Moda/Manrope dan token brand dipertahankan. Model CSS Node bukan computed layout browser. |
| UI-03 | Atribut `data-favorites-url` hanya di-render untuk akun terautentikasi pada home/PDP. GET/POST `/favorites`, DELETE `/favorites/{product_id}`; numeric ID untuk API. Hydration/toggle/refresh tab diserialkan. Tidak membaca/menulis/memigrasikan local wishlist saat authenticated, tidak merge otomatis. Gagal/hasil tidak pasti harus direkonsiliasi GET sebelum mutasi berikutnya; feedback aman dan `aria-busy`. Guest tetap lokal dengan toast kebijakan eksplisit. `data-favorites-page` menuju `route('shop.index')#favorit`, sehingga daftar favorit akun tidak dibatasi kartu home/PDP. |
| UI-04 | Form header home dan PDP melakukan GET `route('catalog.index')` dengan `q`, maksimal 100 karakter. Enter maupun tombol submit memakai navigasi native. Suggestion adalah link katalog, tidak dicegat handler lama. Tidak ada hasil/status search global berdasarkan kartu parsial. Filter koleksi lokal home tetap terpisah. |
| UI-05 | Locale POST diserialkan; queue selesai dahulu, lalu reload sekali bila ada persist yang berhasil. `?locale` lama dibuang, hash dipertahankan. Bila pilihan terakhir gagal sesudah pilihan sebelumnya berhasil, reload memakai locale terakhir yang dikonfirmasi, bukan pilihan gagal. Gagal tanpa persist sukses tidak reload. Shell tanpa endpoint tetap apply in-place. |
| UI-07 | SET katalog memakai queue global lintas produk. Payload disnapshot pada submit; semua intent diterima sesuai urutan. Versi feedback mencegah sukses/galat lama menggantikan status intent terakhir. Tidak mengandalkan abort untuk membatalkan mutasi server. |
| UI-08 | `shopRequest` memakai deadline 10 detik untuk fetch + JSON body (`Promise.race` dan AbortController), cleanup timer pada semua hasil. Timeout tidak diberi HTTP status dan memberi copy hasil tidak pasti. JSON sukses yang tidak terbaca juga tidak ditandai sebagai rejection definitif. Checkout retry mempertahankan key/payload; edit form tidak membuang replay, dan mutasi tas/penghapusan alamat yang akan meresetnya ditolak selama pending. 409/422 definitif tetap membuang replay sesuai kontrak existing. |
| UI-09 | Cart membedakan 419, 429, 5xx, transport, stock 422 terverifikasi, dan 4xx/422 lain. Pesan stok dicocokkan persis terhadap dua pesan resmi `customer_messages.cart.*` yang diinjeksi ke copy Blade, bukan pencarian kata atau seluruh 422. Server message mentah tidak direfleksikan oleh cart catch. Tidak auto-retry rejection bisnis. |
| UI-10 | Fokus hanya summary alert, bukan status sukses; mendukung summary di dalam form, sibling parent yang hanya memiliki satu auth form, atau asosiasi eksplisit `data-error-summary`. Fokus sekali, tabindex -1. Form auth tambahan diaktifkan untuk enhancement; input teks auth/account diberi conditional aria-invalid/describedby dan pesan ber-ID yang terkait label. |

## Koreksi tes (tidak melemahkan kriteria)

- Home tidak lagi diasumsikan merender seluruh katalog: DATA lane membatasi home. Tes lama yang melarang query bounded diganti kontrak GET header ke katalog penuh.
- Destructuring hasil regex suggestion tertukar (full match vs capture tag); dikoreksi sehingga link valid tidak gagal palsu.
- Regex body Blade sebelumnya berhenti pada `>` dalam `app()->getLocale()` sebelum mencapai atribut favorites; diganti tag matcher yang memahami blok Blade.
- Pemeriksaan field error tidak lagi bergantung satu baris atau hanya memeriksa pesan yang kebetulan sudah ada. Sekarang menguji input teks editable di semua view terkait, label, conditional invalid/describedby, dan ID pesan, termasuk markup multiline; guard jumlah input mencegah vacuous pass.
- Kontrak 422 diperketat: malformed/non-stock tidak boleh dinyatakan sebagai stok. Ditambah kasus lokal/nonlokal dan server detail sensitif.
- Tes statis header pada `task3` diselaraskan ke search katalog penuh; assertion perilaku matcher legacy lainnya dipertahankan. Stub location pada tes locale lama mendapat `reload()` agar meniru browser, bukan melempar TypeError palsu.
- Komentar tes membedakan VM behavioral tests dari pemeriksaan Blade/copy/model CSS statis; tidak mengklaim semua tes behavioral/browser.

## Continuous Evaluation Record

- Requirement: FR-REM-005; NFR-REM-003/004/005/006. Scope audit UI-01/03/04/05/07/08/09/10.
- Baseline: RED `node --test tests/storefront-remediation.test.mjs` EXIT=1 pada handoff sebelum GREEN; baseline integrator di `node-red.log`.
- Hipotesis: state lokal akun, indeks kartu parsial, copy server yang tidak reload, mutasi tak terurut, timeout hanya/nonexistent request, klasifikasi galat berbasis raw message, summary scope form, dan viewport prototype menyebabkan temuan.
- Controlled change: hanya file ownership di atas; source prototype/admin/backend dipertahankan.
- Verification: `node --test --test-reporter=tap` dengan daftar 15 file persis di header `ui-node-green.log`, @ `2026-10-21T12:36:33.944744+00:00` -> **162 tests, 162 pass, 0 fail/cancel/skip, EXIT=0**. Termasuk **46 tes remediasi**, 8 temuan UI dalam scope.
- Ricek syntax: `node --check resources/js/app.js` dan `node --check public/js/zafira-core.js` -> EXIT=0; `git --no-pager diff --check` -> EXIT=0.
- Manual review 2026-10-21: source/diff dibandingkan dengan audit dan invariant; tidak independen dari author GREEN. Reviewer independen/approver: **belum tersedia**, integrator perlu menugaskan reviewer berbeda dari kedua author.
- Skills: `accessibility`, `frontend-ui-engineering`, `git-workflow-and-versioning`, `incremental-implementation`. Browser-testing tidak dipakai karena pengguna melarang browser; tidak commit meski skill generik menyarankannya.
- Lensa HCI (review source, bukan validasi usability): Norman—state favorites/hasil search sesuai signifier; Nielsen—error/status dan hasil tidak pasti eksplisit; Shneiderman—feedback/fokus/retry terarah; Cooper—tidak perlu mengulang payload/reconcile manual favorit; Engelbart—kontinuitas data akun lintas surface; Brown—reuse brand/API tanpa rewrite; Krug—cakupan search penuh dan petunjuk next action; Watson—label/error/fokus/ARIA diuji VM, pembaca layar belum diuji. Kleppmann/Lamport—serial writes, ketidakpastian mutasi dan invarian idempotensi; keamanan—tidak merefleksikan raw cart server detail.
- Decision: **siap REVIEW; bukan penerimaan release atau klaim WCAG penuh**.

## Tidak diperiksa / risiko tersisa

- Tidak menjalankan browser/server/DB, instalasi, build, commit atau pembacaan `.env`. Tidak menjalankan suite PHP/Blade compile/render. **Pengecualian proses yang telah dilaporkan:** satu run regresi Node awal menyertakan `tests/catalog-replacement.test.mjs` sebelum diketahui suite itu mengeksekusi PHP read-only untuk require file bahasa. Tidak ada DB/server pada perintah tersebut. Suite itu dikeluarkan dari seluruh run final; jangan menyebut tidak pernah menjalankan PHP secara absolut.
- Perlu integrator memverifikasi Blade ter-render, API/session sungguhan, viewport/scroll/header, overlay scroll lock, keyboard/AT, lima bahasa, reflow/zoom dan kontras visual. Bukti browser/WCAG: **TIDAK TERBUKTI**.
- `action()` shop tetap mengunci aksi main secara global; kini dibatasi deadline request, bukan diubah menjadi loading per-region. Ini batas perbaikan minimal, bukan klaim rekomendasi per-region audit sudah dipenuhi.
- Replay checkout disimpan di memori halaman seperti desain existing, bukan persist lintas reload/tab. Jangan mengklaim pemulihan lintas reload; perlu desain penyimpanan yang mempertimbangkan PII sebelum memperluasnya.
- Serialisasi mutasi menjamin urutan request pada satu halaman, bukan isolasi semua perangkat/tab. Favorit direfresh saat tab kembali terlihat; tidak polling.
- Klasifikasi stok memakai pesan resmi karena API existing belum menyediakan code bisnis terstruktur; perubahan pesan harus tetap diinjeksi melalui kontrak copy Blade.
- Timeout/batas UI tidak membuktikan server membatalkan write; karena itu checkout mempertahankan replay. Backend/idempotensi nyata tetap gate lane TXN/integrator.
