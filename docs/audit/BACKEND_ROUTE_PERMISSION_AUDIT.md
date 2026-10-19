# Audit Permission dan Keamanan Route Backend

Tanggal audit: 11 Oktober 2026  
Ruang lingkup: route Laravel dari `php artisan route:list`, controller, model, middleware, migration, dan Feature tests. View hanya diperiksa untuk pola XSS; tidak diubah.  
Label: **VERIFIED** = dibuktikan kode/perintah/test; **INFERENCE** = disimpulkan dari pola kode tetapi belum dibuktikan runtime eksternal; **UNKNOWN** = tidak ada cukup bukti.

## Ringkasan

- **VERIFIED:** 29 route terdaftar: 26 route aplikasi, 2 route filesystem Laravel, 1 health route Laravel.
- **VERIFIED:** seluruh 11 endpoint `admin.*` terlindungi `auth:admin`; test guest dan akun guard `web` menguji semua endpoint tersebut.
- **VERIFIED:** mass assignment dikendalikan melalui `$fillable`; harga produk checkout dihitung ulang dari DB; `user_id` concierge guest diisi dari sesi, bukan input.
- **VERIFIED:** ditemukan dua jalur input malformed yang menghasilkan HTTP 500: array email saat normalisasi, dan array password pada login. Diperbaiki dengan normalisasi hanya pada string dan aturan `string` password; test regresi mencakup register/login/concierge/newsletter serta dua login.
- **VERIFIED:** status concierge kini dibatasi transisi: `new` → contacted/confirmed/cancelled; contacted → confirmed/cancelled; confirmed → completed/cancelled; status terminal hanya dapat tetap.
- **UNKNOWN:** nilai konfigurasi debug deployment tidak dibuka/ditampilkan; perlu pemeriksaan konfigurasi environment saat deploy.
- **INFERENCE:** Laravel web middleware menerapkan session, cookie, dan CSRF pada route aplikasi `web`; route middleware pada endpoint filesystem internal tidak setara dan harus tidak diberi arti upload aplikasi.

## Matriks route

Kolom data merangkum data baca/tulis. Validasi mencatat batas di controller atau statusnya. Semua route `web` bersifat CSRF-protected oleh middleware Laravel **VERIFIED dari konfigurasi pipeline**, tetapi test di bawah menguji perilaku route, bukan token browser.

| Method | URI / nama | Middleware; auth/authorization | Validasi | Data baca / tulis | Success / failure | Test coverage |
|---|---|---|---|---|---|---|
| GET\|HEAD | `/` `home` | web; publik | — | Produk, kategori, cache katalog | 200; error DB/render | `StorefrontTest`, `BrandHeritageTest`, `StorefrontCacheTest` |
| GET\|HEAD | `/produk/{product}` `products.show` | web; publik | slug via route binding | Produk dan kategori; produk terkait | 200; 404 slug tidak ada | `StorefrontTest`, `BrandHeritageTest` |
| GET\|HEAD | `/sitemap.xml` `sitemap` | web; publik | — | slug produk | XML 200 | `StorefrontTest`, `LegacyPatchTest` |
| POST | `/concierge` `concierge.store` | web, throttle:concierge; publik | email, service, tanggal/waktu, notes, order item/quantity/size/monogram | Baca harga/nama produk dari DB; tulis ConciergeRequest; antre email; user_id dari sesi | JSON 201/form redirect; 422 validasi | `StorefrontTest`, `AdminManagementTest`, `LegacyPatchTest`, audit test |
| POST | `/newsletter` `newsletter.store` | web, throttle:newsletter; publik | email | Subscriber upsert idempoten; antre welcome email jika baru | JSON 200/form redirect; 422 validasi | `StorefrontTest`, `StorefrontCacheTest`, audit test |
| GET\|HEAD | `/register` `register` | web, guest(web) | — | Render form | 200; redirect bila login | `AuthTest` |
| POST | `/register` `register.store` | web, guest(web), throttle:register | name unik, email, password kompleks/confirmed, profil opsional | Tulis User dan backup observer; login | Redirect; 422 validasi | `AuthTest`, `LegacyPatchTest`, audit test malformed email |
| GET\|HEAD | `/login` `login` | web, guest(web) | — | Render form | 200; redirect bila login | `AuthTest` |
| POST | `/login` `login.process` | web, guest(web), throttle:login | email, password string, remember boolean | Baca User; mutasi sesi auth | Redirect; error credential/422 | `AuthTest`, audit test malformed credentials |
| POST | `/logout` `logout` | web, auth(web) | — | Hapus auth web; invalidasi sesi hanya jika admin tidak login | Redirect home | `AuthTest`, `LegacyPatchTest` |
| GET\|HEAD | `/akun` `account.show` | web, auth(web) | — | Profil user login; Concierge hanya `user_id` sendiri | 200; guest redirect | `LegacyPatchTest`, `AdminManagementTest`, audit test IDOR |
| PATCH | `/akun` `account.update` | web, auth(web) | name unik dan field profil tervalidasi | Hanya user login; observer mencatat snapshot | Redirect; 422 validasi | `LegacyPatchTest`, `AdminManagementTest`, audit test mass assignment |
| GET\|HEAD | `/admin/login` `admin.login` | web, guest(admin) | — | Render login admin | 200; redirect jika admin terautentikasi | `LegacyPatchTest` |
| POST | `/admin/login` `admin.login.process` | web, guest(admin), throttle:login | email dan password string | Baca Admin; mutasi sesi guard admin | Redirect dashboard; error kredensial/422 | `LegacyPatchTest`, audit test unauth dan malformed password |
| GET\|HEAD | `/admin` `admin.dashboard` | web, auth:admin | status enum, q/uq max 255 | Baca statistik, users, backups, concierge, kategori, produk, subscribers | 200; 302 login/422 filter invalid | `AdminManagementTest`, `LegacyPatchTest`, audit test guard |
| POST | `/admin/categories` `admin.categories.store` | web, auth:admin | cat_title wajib, unik, max 255 | Tulis kategori; invalidate cache katalog | Redirect; 422 | `LegacyPatchTest`, audit test guard |
| PATCH | `/admin/categories/{category}` `admin.categories.update` | web, auth:admin | cat_title unik | Update kategori; invalidate cache | Redirect; 404/422 | `LegacyPatchTest`, `StorefrontCacheTest`, audit test guard |
| DELETE | `/admin/categories/{category}` `admin.categories.destroy` | web, auth:admin | Tolak bila produk memakai kategori | Hapus kategori; invalidate cache | Redirect; error bila dipakai | `LegacyPatchTest`, audit test guard |
| POST | `/admin/products` `admin.products.store` | web, auth:admin | field produk tervalidasi, kategori exists, harga numeric ≥0, slug unik | Tulis produk; brand server-set ZAFIRA; invalidate cache | Redirect; 422 | `AdminManagementTest`, `BrandHeritageTest`, audit test guard |
| PATCH | `/admin/products/{product}` `admin.products.update` | web, auth:admin | Partial fields; kategori exists; harga ≥0; slug unik | Update produk; invalidate cache lama/baru | Redirect; 404/422 | `LegacyPatchTest`, `StorefrontCacheTest`, audit test guard |
| DELETE | `/admin/products/{product}` `admin.products.destroy` | web, auth:admin | — | Hapus produk; invalidate cache | Redirect; 404 | `AdminManagementTest`, audit test guard |
| PATCH | `/admin/concierge/{concierge}` `admin.concierge.update` | web, auth:admin | Transisi status sah sesuai peta | Update status request terikat | Redirect; error invalid transition/404 | `LegacyPatchTest`, audit test transition dan guard |
| PATCH | `/admin/password` `admin.password.update` | web, auth:admin | Password lama guard admin; password baru kompleks/confirmed | Update password admin terautentikasi | Redirect; 422 | `LegacyPatchTest`, audit test guard |
| DELETE | `/admin/subscribers/{subscriber}` `admin.subscribers.destroy` | web, auth:admin | — | Hapus subscriber terikat | Redirect; 404 | `AdminManagementTest`, audit test guard |
| POST | `/admin/logout` `admin.logout` | web, auth:admin | — | Hapus guard admin; invalidate sesi jika web guard tidak aktif | Redirect login | `LegacyPatchTest`, audit test guard |
| GET\|HEAD | `/prototype` `prototype` | web; publik | — | Redirect static prototype | Redirect `/prototype/` | Belum ada test route khusus |
| GET\|HEAD | `/storage/{path}` `storage.local` | middleware route kosong; valid signed URL untuk disk private, atau public disk | Validasi signature/path traversal oleh Laravel | Baca file disk local; tidak upload | 200/404/403 | UNKNOWN test coverage khusus |
| PUT | `/storage/{path}` `storage.local.upload` | middleware route kosong; validasi signed upload URL oleh Laravel | Signature wajib dan path traversal ditangani | Tulis raw body ke disk local | 204/403/404 | UNKNOWN test coverage khusus; bukan route upload controller aplikasi |
| GET\|HEAD | `/up` | middleware route kosong; health | — | Status health framework | 200 sehat | Tidak ditemukan test khusus |

## Pemeriksaan kontrol

| Area | Hasil |
|---|---|
| Guard admin | **VERIFIED** `routes/web.php` grup `auth:admin`; Feature test audit mencoba guest dan user biasa pada semua 11 endpoint admin. Endpoint admin redirect ke `/admin/login`. |
| Mass assignment | **VERIFIED** Model memakai allowlist `$fillable`. Controller create/update memakai data tervalidasi; profil tidak menerima email/password/user_id; admin tidak dapat diambil dari input storefront. |
| Harga/role dari klien | **VERIFIED** harga checkout dihitung ulang dari tabel `products`; `name`/`price` dari order item diabaikan; user_id diambil dari sesi. Tidak ada field role pada alur registrasi. |
| IDOR | **VERIFIED** riwayat akun disaring `where('user_id', $request->user()->id)` dan test existing memastikan baris guest/user lain tidak muncul. Operasi concierge individual hanya admin, dengan ID binding. |
| CSRF | **VERIFIED** mutasi route aplikasi masuk grup `web`, bukan pengecualian CSRF. Test framework tidak mensimulasikan verifikasi token browser secara penuh. |
| XSS | **VERIFIED** dua unescaped output JSON-LD: `resources/views/welcome.blade.php` dan `resources/views/products/show.blade.php` memakai `{!! $jsonLd !!}`. JSON dihasilkan controller; encoding JSON tidak otomatis menjadikan `</script>` aman. **Risiko perlu ditangani pemilik views**; tidak diubah karena ownership. Pemeriksaan juga menemukan interpolasi nama produk/kategori/email ke string `onsubmit` admin, perlu encoding konteks JS/HTML oleh pemilik view. |
| Rate limits | **VERIFIED** login/admin login 10 per menit per IP; register 5 per jam; concierge 3 per jam; newsletter 5 per hari. Throttle storefront merujuk alamat IP dan proxy tepercaya mengikuti konfigurasi `trustedproxy`. Batas global per-IP dapat berbagi bucket antar user. |
| Debug exposure | **UNKNOWN** environment deployment tidak diverifikasi. Jangan mengandalkan kode lokal sebagai bukti `APP_DEBUG=false` pada produksi. `/up` hanya health route framework. |
| Upload | **VERIFIED** tidak ditemukan form upload aplikasi; produk menerima URL gambar string. Route `storage.local.upload` adalah fitur signed upload Laravel dan bukan bagian controller produk. **UNKNOWN** apakah aplikasi/deployment mengaktifkan disk local `serve` berisiko sesuai versi konfigurasi runtime. |
| Status Concierge | **VERIFIED** validasi enum awal menerima sembarang lompatan; test sebelum fix membuktikan terminal reopen diterima. Kini ada transisi eksplisit; test mencakup reopen terminal, skip `new`→`completed`, mundur `confirmed`→`contacted`, pembatalan, dan lifecycle penuh. |
| Catalog N+1 | **VERIFIED** storefront list/detail memuat `category` via `with`; produk terkait juga eager-load kategori. Dashboard memakai `withCount`, `with`, dan `take`; homepage cache koleksi dan kategori. `sitemap()` membaca semua slug tanpa pagination; **INFERENCE** jumlah produk besar menaikkan memori seiring katalog. |
| PII admin | **VERIFIED** dashboard admin membaca email, telepon, alamat pelanggan dan backup; hanya berada di balik admin guard. Retensi backup tidak dibatasi pada jalur yang diaudit. |

## Perubahan dan bukti

- **VERIFIED** `app/Http/Controllers/Controller.php`: normalisasi email hanya jika nilai scalar string.
- **VERIFIED** Auth controllers: validasi password harus string agar array tidak sampai ke hasher.
- **VERIFIED** `AdminDashboardController`: peta transisi concierge eksplisit.
- **VERIFIED** `tests/Feature/BackendPermissionAuditTest.php`: 10 test, 97 assertions lulus (`php artisan test --filter=BackendPermissionAuditTest`, exit code 0).
- **VERIFIED** suite penuh: `php artisan test` → 73 passed, 346 assertions, exit code 0.
- **VERIFIED** format: `vendor/bin/pint --test` → `{"tool":"pint","result":"passed"}`, exit code 0.
- **VERIFIED** `php artisan route:list` → `Showing [29] routes`, exit code 0.
- **VERIFIED** transisi `new` → `completed` dan `confirmed` → `contacted` ditolak dalam test runtime. Test tersebut ditulis setelah implementasi peta; bukti gagal sebelum fix untuk transisi hanya tersedia pada kasus reopen status terminal.

## Tindak lanjut

1. Pemilik view meninjau JSON-LD agar aman untuk konteks `<script>` dan atribut event handler inline.
2. Pemilik deploy membuktikan `APP_DEBUG=false`, konfigurasi trusted proxies, dan kebijakan storage route.
3. Pertimbangkan pagination untuk sitemap bila produk tumbuh; ukur query/memori sebelum perubahan.
4. Tambahkan test khusus framework storage signed upload hanya bila fitur tersebut menjadi bagian kontrak produk.
