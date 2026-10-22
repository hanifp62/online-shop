# Migrasi frontend React 19 + Inertia 3

**Tanggal:** 22 Oktober 2026. **Mandat:** pengguna menyetujui Laravel13 + React19 + Inertia3. **Status:** implementasi bertahap; bukan klaim seluruh frontend sudah dimigrasi atau siap produksi.

## Baseline dan keputusan

Baseline `14bdfab95cbb866ca8b6702741425a73fab32329`: Laravel13.36.0, Vite8.3.5, Blade dan JavaScript imperatif; tidak ada React/Inertia. Ricek lokal sebelum implementasi: `node --test` 485/485 lulus, tanpa skip; `npm run build` lulus (22 Oktober 2026). CI historis tetap pada laporan publikasi, bukan bukti perubahan baru.

Versi yang diverifikasi dari registry resmi: React/ReactDOM19.3.1, adapter React Inertia3.9.1, adapter Laravel3.5.2 (mendukung Laravel11.35/12/13), plugin React Vite6.1.2 (Vite8), Vitest5.0.5. Lockfile dipin; tidak meningkatkan Laravel atau mengganti auth/backend. Composer dipasang `--no-scripts` agar tidak bootstrap environment operasional; package discovery terjadi dalam harness terisolasi.

Strategi: native JSX + Inertia per kelompok halaman. HTML Blade tetap **fallback server-rendered yang aktif**, bukan HTML di props, iframe, atau `dangerouslySetInnerHTML`. Pada initial request fallback berada dalam root; setelah modul siap React melakukan client replacement, **bukan hydration/React SSR**. Handler fallback katalog/operations aktif saat initial HTML, lalu melepas ownership/listener bersama ketika React melakukan claim. Jika entry/chunk gagal atau pengguna sudah berinteraksi, fallback tetap menjadi pemilik dokumen. Form yang sudah diedit sebelum bundle siap tidak boleh ditimpa; fokus deklaratif `autofocus` yang belum disentuh diteruskan ke field native yang setara. Dua renderer memerlukan gate parity tersendiri; PHP HTML tests tidak membuktikan native React.

Alasan: mempertahankan SEO/no-JS dan rollback tanpa menambah daemon Node SSR yang belum tersedia. Penggantian fallback dengan SSR React merupakan keputusan deployment terpisah, bukan dilakukan diam-diam. Email, PDF, XML sitemap, API JSON, dan halaman error darurat tetap server-native karena bukan page interaktif React. Prototype statis tetap referensi, tidak ditimpa.

## Requirement dan acceptance criteria

| ID | Acceptance criteria |
|---|---|
| FR-FE-001 | Halaman yang tercatat selesai punya native React component, response Inertia bernama, dan pengujian DOM/props; memasang dependency saja tidak cukup. |
| FR-FE-002 | URL, filter berulang, pagination, locale lima bahasa, brand/aset/font, form fields, serta arti cart set-quantity tetap. |
| FR-FE-003 | Navigasi SPA dalam kelompok halaman menjaga canonical/title/lang, status, fokus dan Back/Forward; lintas kelompok yang belum dimigrasi menggunakan navigasi dokumen biasa. |
| NFR-FE-001 | Props DTO allowlist; tidak share model/session/config/oldinput/credential secara generik. Dual guards/CSRF/TOTP/permissions tidak diubah. Halaman privat/no-store/history harus diuji sebelum cutover. |
| NFR-FE-002 | Tes lama tidak dihapus, di-skip atau dilemahkan. Native renderer mempunyai tes sendiri; fallback tetap diuji. Race, retry/idempotency, escaping dan keyboard wajib dipertahankan. |
| NFR-FE-003 | Build mencakup JSX dan pemisahan bundle halaman. CI menjalankan test React selain seluruh gate yang sudah ada. |
| NFR-FE-004 | Rollback melalui konfigurasi frontend dan mode Blade per request; kegagalan bootstrap tidak menghapus fallback sebelum komponen berhasil dimuat. Tidak menimpa input yang sudah diedit. |
| NFR-FE-005 | Tidak menyebut framework baru otomatis mempercepat sistem; ukuran bundle dilaporkan dan beban/TPS tidak diklaim tanpa benchmark. |

## Urutan dan cakupan

1. Fondasi dan katalog publik: filter, chips, pagination, quick view, cart/favorite.
2. Auth dasar: login/register/forgot/reset, tanpa mengganti mesin auth. Navigasi dokumen pada POST menjaga semantik redirect dan credential tidak diingat.
3. Akun/privasi dan TOTP: gate terpisah untuk data sensitif, one-time recovery, encrypted history, prefetch, logout dan pergantian identitas.
4. Admin read-only (auth-events/operasional), lalu tujuh panel dashboard beserta draft/race/query state, mutasi dan invoice.
5. Storefront/PDP dan checkout: pertahankan visual, personalization inquiry yang terpisah dari pembelian, serialisasi cart, frozen checkout payload dan idempotency.
6. Browser parity dan seluruh suite; hasil hanya berlaku pada source yang benar-benar diuji.

Inventaris: 13 halaman auth/account/admin-auth; tiga halaman bisnis admin ditambah invoice HTML di controller; storefront welcome/catalog/product/shop. Scope privat dan checkout tidak dianggap selesai dari keberhasilan katalog.

## Gate keamanan dan HCI

- TOTP enrol GET membuat pending secret; recovery GET mengonsumsi one-time codes. Jangan prefetch/cache visit. Flash data v3 tidak disimpan pada history, tetapi initial HTML dan lifecycle tetap wajib diuji; encrypted history saja tidak membuktikan sekali tampil.
- `Vary: X-Inertia` mencegah pencampuran representasi. Halaman berisi CSRF atau variasi auth tidak boleh public-cache.
- Tidak memakai bridge generik yang mengubah HTML error menjadi sukses; perpindahan ke halaman Blade melalui anchor penuh.
- Norman/Nielsen: signifier dan feedback; Shneiderman/Watson: keyboard, dialog/fokus, target44px; Cooper: draft/preferensi tidak hilang; Engelbart: ringkasan informasi; Brown: parity bertahap; Krug: label/arah navigasi jelas. Ini kriteria audit, bukan endorsement tokoh atau sertifikasi WCAG.

## Sumber primer

Diakses 22 Oktober 2026:
- https://laravel.com/docs/13.x/starter-kits
- https://inertiajs.com/docs/v3/installation/client-side-setup
- https://inertiajs.com/docs/v3/installation/server-side-setup
- https://inertiajs.com/docs/v3/security/history-encryption.md
- https://inertiajs.com/docs/v3/data-props/flash-data.md
- https://inertiajs.com/docs/v3/advanced/server-side-rendering.md
- Metadata `npm view` dan `composer show inertiajs/inertia-laravel 3.5.2 --all --format=json`.

## Review dan batas bukti

Inventaris auth: `5c4e3425-7333-4f3d-9bc7-b51a4b62eb8b`; admin dan review arsitektur awal: `232f9a3d-4f9c-4e5a-a8fa-1a1d081c1169`. Review arsitektur katalog **bersyarat**, bukan approval runtime: lindungi handoff input, serialisasi cart, head/lang/canonical, vary/cache, dan bukti native terpisah. Resource live serial oleh integrator.

**Penutupan milestone lokal:** 11 komponen masuk allowlist: Catalog/Index; Auth/Login, Register, ForgotPassword, ResetPassword, TotpChallenge; Account/Show; Admin/Login, TotpChallenge, AuthEvents, OperationsSummary. Feature operations tetap mati bawaan. Source review independen selesai per slice; PHP1.247pass+3exactskip, React110, Node495, Python373, browser17checks + cleanup lulus. [Laporan lengkap dan bukti](../05-quality/REACT_INERTIA_INTEGRATION_2026-10-22.md).

**Seluruh migrasi masih PARTIAL.** Beranda, PDP, checkout/shop, dashboard admin tujuh panel, TOTP enrol/recovery kedua guard, dan konfirmasi unsubscribe masih Blade. Shell admin read-only juga tetap Blade, sedangkan konten utamanya React; navigasi antarhalaman admin dokumen penuh. Email/PDF/XML/error darurat/invoice HTML noninteraktif tetap server-native secara sengaja.

Tidak ada commit/push migrasi, CI remote baru, deployment, atau klaim branch protection/provider nyata/TPS/seluruh aksesibilitas/zero-defect. Semua penugasan telah handoff, tidak ada agent background. Siklus berikutnya harus mengambil slice dari urutan §3, menetapkan scope penulis, lalu melewati parity dan gate baru—bukan menganggap milestone ini menyelesaikan seluruh frontend.
