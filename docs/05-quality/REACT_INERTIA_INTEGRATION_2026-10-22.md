# Integrasi React 19 + Inertia 3 — milestone frontend

**Tanggal:** 22 Oktober 2026. **Baseline Git:** `14bdfab95cbb866ca8b6702741425a73fab32329`.
**Keputusan:** ACCEPTED untuk cakupan lokal di bawah; **migrasi seluruh frontend masih PARTIAL**. Belum ada commit/push migrasi, run GitHub baru, deployment, atau persetujuan produksi. Tidak ada pekerjaan agent berjalan di background setelah handoff milestone ini.

## 1. Yang benar-benar diterapkan

Laravel tetap **13.36.0**. Dependency terkunci: React/ReactDOM **19.3.1**, `@inertiajs/react` **3.9.1**, `inertiajs/inertia-laravel` **3.5.2**, plugin React Vite **6.1.2**, Vitest **5.0.5**, jsdom **26.1.0**; Vite lock **8.3.5**. Versi klien dan adapter server tidak harus bernomor sama.

| Komponen native | Cakupan dan status |
|---|---|
| `Catalog/Index` | Filter, kategori berulang, pagination, locale, canonical/title, quick view, cart SET dan favorit; native browser teruji |
| `Auth/Login`, `Auth/Register`, `Auth/ForgotPassword` | Form native POST, feedback/error focus, password toggle, old input allowlist, tautan OAuth dokumen penuh |
| `Auth/ResetPassword` | Token melalui `page.flash`, bukan regular props; native validation retry teruji; reset sukses dengan token terbit belum diuji browser pada milestone ini |
| `Auth/TotpChallenge`, `Admin/TotpChallenge` | Native challenge/recovery submission, string OTP enam digit; verifikasi melalui recovery code fixture nyata teruji |
| `Admin/Login` | Native POST, guard/rate limiter/backend tetap; browser login nyata fixture |
| `Account/Show` | Profil, resend, social links, ringkasan TOTP, privasi, ekspor, konfirmasi hapus dan riwayat owner maksimal 10; simpan→GET baru/ekspor/cancel delete/logout→Back teruji |
| `Admin/AuthEvents` | Konten native dengan shell admin Blade; filter/pagination navigasi dokumen, context kedua renderer disanitasi; logout→Back teruji |
| `Admin/OperationsSummary` | Konten native, refresh manual 15 detik termasuk body, snapshot lama dipertahankan saat gagal. Feature flag `admin_ops_summary` **tetap mati bawaan**; feature PHP dan komponen teruji, browser live panel ini belum diuji |

Total **11 komponen halaman** pada allowlist. Angka ini tidak berarti seluruh halaman atau seluruh shell aplikasi sudah React. Tidak ada HTML Blade dikirim sebagai props, iframe, atau `dangerouslySetInnerHTML` untuk menyamarkan migrasi.

## 2. Arsitektur, keamanan, dan rollback

- `FrontendPage` memilih renderer per allowlist/config. Initial HTML tetap berisi fallback Blade nyata untuk SEO/no-JS. React menggantinya memakai `createRoot`; **bukan hydration atau React SSR**. Tidak menambah daemon Node yang belum diprovisikan.
- Handoff melindungi draft/autofill, interaksi, fokus, dan dialog terbuka. Katalog serta operations memiliki handler fallback mandiri; handler global dilepas ketika React mengklaim root. Fokus `autofocus` server yang belum disentuh diteruskan ke field native setara; aktivitas pengguna tetap menang.
- SPA hanya untuk katalog. Transisi antar-shell, OAuth, ekspor, dan semua mutasi auth/akun memakai navigasi dokumen/native form. Data credential tidak masuk remembered form state.
- Props memproyeksikan field eksplisit, bukan model/session/old-input mentah. Context auth-event historis disanitasi **di JSON dan fallback HTML**, bukan hanya setelah React terpasang.
- Reset token memakai flash Inertia v3, yang menurut kontrak adapter tidak disimpan dalam history props. Token tetap ada pada URL reset dan DOM form sesuai alur backend; tidak diklaim hilang dari seluruh permukaan browser.
- History terenkripsi; devtools Inertia dan SSR nonaktif. Dokumen privat disembunyikan seluruhnya saat `pagehide`, termasuk shell di luar root, kemudian diperiksa ulang lewat reload saat persisted restore. Listener native menghapus kunci history melalui `router.clearHistory()`.
- Representasi page diberi `no-store, private` dan `Vary: X-Inertia`. Rollback SPA hanya mengubah **GET page eksplisit yang sukses** menjadi location visit; API, POST, 4xx/5xx tidak disamarkan menjadi sukses.
- URL katalog berasal dari canonical service yang memuat pagination efektif dan filter tervalidasi. Kategori berulang tidak hilang; parameter unknown/malformed tidak dipantulkan. Version reload dan rollback mempertahankan raw request URI.
- **Rollback seluruh halaman:** set `ZAFIRA_REACT_ENABLED=false`, lalu bangun ulang cache konfigurasi melalui prosedur deployment yang berlaku. **Rollback satu request:** `?frontend=blade`. Template `.env.example` hanya ditambah toggle nonrahasia tanpa membaca/menampilkan dotenv; environment operasional tidak disentuh.
- Encrypted history memerlukan secure context (`HTTPS`, atau localhost untuk fixture). Belum menguji deployment HTTP non-localhost dan tidak merekomendasikannya.

## 3. Bukti eksekusi

Seluruh PHP, database fixture, dan browser aplikasi dijalankan serial oleh integrator, melalui harness terisolasi. Tidak ada DB operasional, provider pembayaran, email eksternal, atau data pelanggan nyata digunakan.

| Gate | Hasil terakhir yang dieksekusi |
|---|---|
| `scripts/amadis/launch.sh test --log-junit=…/phpunit-react-closure.xml` | **1.247 passed, 3 skipped, 159.143 assertions, EXIT=0**, 254,83 detik |
| `scripts/ci/check_test_report.py` dengan tiga ID MySQL exact | PASS: 1.250 total / 1.247 lulus / 3 pengecualian engine; bukan kelulusan MySQL |
| `node --test` | **495 passed**, tanpa skip, EXIT=0 |
| `npm run test:react` | **110 passed**, tujuh file, EXIT=0 |
| `scratch/ci-validation/venv/bin/python scripts/ci/run_python_tests.py` | **373 passed**, tanpa skip/error, EXIT=0; Python lokal 3.14, bukan bukti Linux 3.12 |
| `npm run build` | PASS; entry React/Inertia **351,66 KB / 110,26 KB gzip**, chunk halaman terpisah |
| Native Chromium acceptance | **17 checks PASS**, console error 0 pada konteks normal yang dimonitor, supervisor EXIT=0, cleanup PASS |
| Browser acceptance lama + checker | PASS: 76 produk / 10 halaman, 3 checkout / 2 dibuat / 1 ditolak, tidak ada page-content focus leak |
| Pint | **397 file PASS**, EXIT=0 |
| Actionlint, secret scanner, PII scanner, `git diff --check` | EXIT=0 |
| Composer/npm audit | Tidak ada advisory/vulnerability saat dieksekusi |
| Composer validate | Valid dengan warning exact pin Dompdf/Inertia; `--strict` EXIT=1. Pin disengaja untuk kompatibilitas, tidak dilonggarkan demi menghapus warning |

Bukti aman: [PHP](evidence-react-inertia-2026-10-22/php-summary.json), [native browser](evidence-react-inertia-2026-10-22/browser-native.json), [timestamp native](evidence-react-inertia-2026-10-22/browser-native-commands.tsv), [browser lama](evidence-react-inertia-2026-10-22/browser-legacy.json), dan [manifest source](evidence-react-inertia-2026-10-22/source-manifest.json). [Ringkasan pemeriksaan lain](evidence-react-inertia-2026-10-22/transcript-checks.json) ditranskripsi dari perintah yang benar-benar dieksekusi, bukan raw log baru yang dikarang. [Riwayat browser](evidence-react-inertia-2026-10-22/browser-run-history.json) memisahkan `flow_status` dari exit supervisor; khusus R6, flow PASS tidak berarti acceptance PASS.

### Apa yang dibuktikan 17 checks

Native root benar-benar terpasang sebelum interaksi; pagination/Back/Forward SPA; filter kategori berulang; locale/head/canonical; dialog keyboard/return focus; cart quantity 2; layout 375px tanpa horizontal document overflow; native auth validation; halaman auth lain; chunk tertunda tidak menghapus draft; entry gagal tetap interaktif; no-JS dan Blade override; admin encrypted history/logout→Back; profil tersimpan pada GET baru/ekspor/cancel delete/logout→Back; field error konfirmasi reset; customer TOTP recovery valid lalu reuse ditolak; admin TOTP recovery valid; console konteks normal bersih.

Persiapan TOTP menggunakan alur enrol/recovery **Blade yang sudah ada** dengan akun sintetis. Itu tidak membuktikan halaman enrol/recovery sudah React. Admin recovery reuse tidak diuji browser dalam milestone ini. Kegagalan aset yang disengaja berada pada context terpisah dari penghitung console normal.

## 4. CI/CD dan koreksi yang ditemukan selama integrasi

Workflow `quality` kini mewajibkan `npm run test:react` setelah `npm ci`; job `browser` menambah native acceptance secara serial. Summary hanya diterbitkan setelah browser **dan supervisor cleanup** sukses. Tidak mengunggah log server, credentials, screenshot auth, trace/HAR, atau JUnit mentah. Kontrak Node menolak beberapa bentuk gate opsional/suppression; bukan bukti mutation coverage semua kemungkinan pelemahan workflow.

Riwayat kegagalan tidak diubah menjadi PASS:

1. Dirty/autofill fallback semula kehilangan handler; ownership dan handler mandiri diperbaiki dengan regression RED→GREEN.
2. Rollback tab SPA semula mendapat HTML biasa; now explicit location visit pada boundary aman.
3. Adapter server menghilangkan kategori berulang dari `page.url`; browser menemukan request2/props2/url1. Resolver dan canonical diperbaiki. Koreksi canonical pertama sempat menghilangkan `page=2`; reviewer dan browser menangkapnya, kemudian memakai canonical service efektif.
4. Copy kondisi yang tidak aktif ikut inert JSON dan mematahkan tes lama: fuzzy notice, filter aktif, serta email belum terverifikasi. DTO kini tidak mengirim peringatan yang tidak berlaku; assertion lama tetap dipertahankan.
5. Fixture auth baru semula tidak membangun schema SQLite memory, tidak mengirim asset version, dan fixture social-only menulis password NULL ke kolom NOT NULL. Fixture diperbaiki, bukan backend/guard/schema.
6. Supervisor Darwin menemukan `getpgid=ESRCH` sebelum exit terlihat melalui `waitid(WNOWAIT)`. Probe innocuous mereproduksinya. Konfirmasi dibatasi 100 ms, interval 1 ms, **tanpa signal/reap selama identitas unknown**; deadline/mismatch/reaped tetap gagal. Review independen dan seluruh 373 tes Python lulus sesudahnya.
7. Harness browser diperkuat: selector admin nyata, console admin ikut dihitung, update profil harus lolos GET baru, dan reset harus gagal pada field konfirmasi yang benar. Service-worker context teramati mengekspos final POST200/fromWorker tanpa redirect terpisah; harness menerima hanya jalur worker itu atau POST302→GET200, selalu menunggu dokumen baru dan hasil flow. Tidak menyimpulkan worker secara langsung mengintersep POST dari source-nya.

**CI remote terakhir yang hijau sebelum perubahan ini bukan bukti migrasi ini.** Belum ada run GitHub untuk diff migrasi. Branch protection, release candidate baru, staging, worker/alert, rollback deployment, dan RPO/RTO tetap mengikuti gate operasional sebelumnya.

## 5. Review independen dan lensa evaluasi

- Fondasi/katalog/auth awal/admin read-only/CI/supervisor: reviewer `5e4f992e-3b1e-4fa3-901e-e6757310731e`, berbeda dari author. Temuan P2 diperiksa ke source, diperbaiki, lalu disetujui scoped.
- Profil/auth lanjutan/reset dan harness security: reviewer `80fc81fd-e4f5-4d24-9c39-6ea212324b83`, berbeda dari author `3dfa68f3-7658-4759-9d8a-d59a980bdeb8` dan `947a46fe-273d-4036-a982-8120f371469e`. Approve source/React dan closure P2; runtime PHP/browser dilakukan integrator, tidak diklaim dijalankan reviewer.
- Supervisor: diagnosis/author `bad798dd-0836-4469-8b2a-669bff557319`; reviewer5e berbeda sesi. Perubahan tidak melonggarkan identitas proses.

| Lensa wajib | Bukti/keputusan dalam scope |
|---|---|
| Norman, Nielsen | Kontrol tidak menjadi inert saat bundle gagal; feedback mutasi dan state native jelas; rollback benar-benar tersedia |
| Shneiderman, Watson | Dialog keyboard, Escape dan return focus diuji; labels/semantik/error focus ditinjau; bukan sertifikasi pembaca layar/WCAG penuh |
| Cooper | Draft, autofill, dan fokus pengguna tidak ditimpa; snapshot operasi lama tidak dibuang saat refresh gagal |
| Engelbart | Panel read-only mempertahankan arti metrik/unknown/partial, bukan mengubahnya menjadi klaim kesehatan menyeluruh |
| Brown, Krug | Migrasi bertahap dengan brand dan model mental tetap; native POST menghindari perubahan alur auth yang tidak perlu |
| Fowler, Beck, Kleppmann, Lamport | Increment + regression, URL/history konsisten, cart SET terserialisasi, one-use recovery pelanggan dibuktikan |
| Schneier, Shostack, Janca, Hunt | DTO/flash/CSRF/guard, context sanitization kedua renderer, evidence tidak menyalin secret |
| Hightower, Forsgren, Vogels | Gate CI mandatory, supervisor owned-process, failure tidak dipalsukan sebagai PASS |
| Gregg, Knuth, Purbo | Ukuran bundle dilaporkan; tidak mengklaim React menaikkan TPS atau mempercepat jaringan lambat tanpa pengukuran |

Nama tokoh adalah lensa metode, **bukan endorsement atau anggota tim sungguhan**. Skill relevan dan alasan tidak memakai skill redesign/animasi/native/mobile tercatat pada ledger. Memanggil semua skill yang tidak relevan tidak dilakukan.

## 6. Belum selesai / tidak diperiksa

**Frontend yang masih Blade:** beranda `welcome`, PDP `products/show`, belanja/checkout `shop/index`, dashboard admin tujuh panel, enrol/recovery TOTP pelanggan dan admin, serta halaman konfirmasi unsubscribe pengingat cart. Templat email/PDF/XML/error darurat dan invoice HTML noninteraktif memang tetap server-native; tidak dihitung sebagai halaman React selesai.

**Gate lanjutan:**
- Migrasi halaman tersisa dengan parity mutasi/draft/idempotency; jangan menyebut seluruh aplikasi React dari allowlist ini.
- Browser live operations-summary saat feature flag diaktifkan di lingkungan terisolasi yang sesuai; flag tidak dinyalakan global diam-diam.
- Reset sukses dengan token yang diterbitkan, OAuth provider nyata, admin recovery reuse browser, serta pergantian identitas lintas tab.
- Event BFCache `persisted` nyata tidak dipastikan terpicu; unit/source menguji branch, Chromium logout→Back diuji aktual. Safari/Firefox belum diuji.
- Matriks lengkap lima locale/viewport, screen reader, kontras/zoom/target seluruh halaman, dan performance profiling belum lengkap. Error boundary bootstrap generik masih memakai copy pemulihan Indonesia bila page tidak memasok override.
- MySQL/InnoDB terbaru, load/TPS, p95/p99, availability/SLO, staging dan deployment produksi belum diuji pada diff ini.

**Trade-off:** base React/Inertia sekitar 110 KB gzip ditambah chunk halaman, sedangkan bundle lama tetap dipertahankan untuk halaman yang belum dimigrasi. Initial page membawa fallback HTML dan props sehingga payload total dapat lebih besar. Ini keputusan paritas/rollback, **bukan klaim optimasi performa**.

## Continuous Evaluation Record

- **Requirement:** FR-FE-001/002/003, NFR-FE-001…005; panel operasional juga FR-REM-007.
- **Baseline:** Blade/imperative JS, Node485/build lulus; CI lama dipisahkan dari diff baru.
- **Controlled change:** 11 page components, DTO/root/middleware, build/test dependencies, mandatory CI/browser gates, dan bounded supervisor fix; bisnis transaksi, schema, serta provider tidak ditulis ulang.
- **Verification:** tabel §3 dan artefak aman; RED, rework, dan batas bukti pada §4 serta ledger.
- **Crosscheck:** reviewer berbeda dari author per slice; source approval tidak menggantikan runtime.
- **Decision:** **ACCEPTED — milestone lokal terbatas; PARTIAL — seluruh migrasi; BLOCKED — penerimaan produksi sampai gate eksternal dan cakupan tersisa dipenuhi.**
