# Penyelesaian integrasi React 19 dan Inertia 3

**Task:** FE-COMP-20261023. **Tanggal laporan:** 23 Oktober 2026 WIB; timestamp eksekusi disimpan dalam UTC.

**Keputusan:** integrasi kode dan gate lokal diterima dalam cakupan berikut. Seluruh **20 komponen halaman interaktif** pada allowlist memakai JSX native. Ini bukan klaim setiap node HTML menjadi React, seluruh aksesibilitas terverifikasi, atau aplikasi sudah disetujui untuk produksi. Pada snapshot penutupan lokal ini, increment belum di-commit/push dan CI remote yang diperiksa masih milik baseline `016fb944c4d1d0dc380a014b9f99570167b106f0`. Pemeriksaan berikutnya atas runtime Artisan pengguna, perbaikan discovery lokal dan origin8001dicatat pada [laporan paritas localhost](LOCALHOST_PARITY_2026-10-23.md); bukti historis di bawah tidak diganti menjadi klaim CI source terbaru.

[Bukti dan indeks perintah](evidence-react-completion-2026-10-23/verification-index.json) · [Manifest source](evidence-react-completion-2026-10-23/source-manifest.json) · [Ledger keputusan/rework](../04-implementation/TEAM_SUPPORT_LEDGER.md) · [Requirement dan strategi renderer](../04-implementation/REACT_INERTIA_MIGRATION.md)

## 1. Cakupan frontend

Versi tetap: Laravel **13.36.0**, React/ReactDOM **19.3.1**, adapter React Inertia **3.9.1**, adapter Laravel **3.5.2**, Vite **8.3.5**. Increment ini tidak mengganti engine transaksi, menaikkan major dependency, atau menambahkan dependency baru.

| Kelompok | Komponen allowlist | Jumlah |
|---|---|---:|
| Storefront | `Storefront/Home`, `Catalog/Index`, `Products/Show` | 3 |
| Belanja | `Shop/Index` | 1 |
| Auth pelanggan | `Auth/Login`, `Register`, `ForgotPassword`, `ResetPassword`, `TotpChallenge` | 5 |
| Akun | `Account/Show`, `TotpEnrol`, `TotpRecovery`, `CartReminderUnsubscribe` | 4 |
| Admin | `Admin/Login`, `TotpChallenge`, `TotpEnrol`, `TotpRecovery`, `Dashboard`, `AuthEvents`, `OperationsSummary` | 7 |

Sembilan komponen baru menyelesaikan halaman yang masih Blade pada milestone sebelumnya: home, PDP, shop, dashboard, empat enrol/recovery TOTP, dan unsubscribe. Flag **`admin_ops_summary` tetap OFF bawaan**; allowlist bukan aktivasi fitur bisnis.

### Boundary yang sengaja dipertahankan

- Laravel tetap sumber kebenaran harga, stok, cart, order, permission, validasi, dan transisi status.
- JSX tidak menerima halaman HTML mentah, tidak memakai iframe, dan tidak memanggil renderer DOM legacy sebagai implementasi React.
- HTML awal tetap Blade untuk SEO, no-JS dan fallback. React mengganti root setelah modul siap, **bukan hydration atau React SSR**.
- Shell header/nav admin tetap server-rendered. Email, PDF, XML, invoice cetak, API JSON, error darurat, dan prototype statis tidak dipaksa menjadi React.
- Navigasi antargrup dan form mutasi native tetap full-document. Katalog dan refresh tabel dashboard memiliki navigasi dalam dokumen sesuai kontraknya.
- `frontend.enabled=false`, penghapusan komponen dari allowlist, dan `?frontend=blade` tetap jalur rollback. Input/interaksi sebelum bundle siap mempertahankan ownership fallback.

## 2. Perbaikan perilaku dan integritas

### Storefront dan PDP

Sumber utama: [StorefrontPageProps](../../app/Support/StorefrontPageProps.php), [komponen storefront](../../resources/js/components/storefront/), [Home](../../resources/js/pages/Storefront/Home.jsx), [PDP](../../resources/js/pages/Products/Show.jsx).

- Identitas Bodoni Moda/Manrope, palet, section, aset dan tujuh override gambar dipertahankan. Primary query PDP tetap segar; related products dan featured home tetap mengikuti cache/invalidation yang ada.
- Numeric ID produk dibawa dari query featured yang sudah ada. Lookup ID duplikat pada proyeksi React dihapus, tanpa mengubah batas **24 produk** atau TTL cache.
- Mobile menu berada di luar background `inert`; dialog punya Escape, trap dan return focus. Fokus cart tidak hilang ketika fresh GET mendahului respons DELETE.
- Cart memakai relative delta terantre dan harga server. Tidak ada retry otomatis delta. Undo memakai `removed_quantity` server dan tidak muncul kembali setelah drawer ditutup.
- Favorit akun memakai numeric ID; favorit guest memakai slug. Tidak ada penggabungan otomatis dengan data lokal lama.
- Size/monogram menuju inquiry atelier, bukan atribut cart atau order. Preview foil/ribbon tidak dipresentasikan sebagai atribut pembelian tersimpan.
- Locale POST 204 diserialkan. Jika pilihan pertama berhasil dan pilihan berikutnya gagal, URL/storage mengikuti locale terakhir yang diterima server.
- Draft concierge tetap ada saat dialog ditutup. Pengunci request per draft bertahan saat berpindah inquiry; respons lama tidak boleh menghapus edit baru, menutup dialog yang dibuka ulang, atau memicu POST ganda.
- Kontrol suara yang tidak mempunyai API implementasi dihapus. Shortcut pencarian menunjuk input yang benar. Tidak menambahkan aset suara atau mengklaim fitur yang belum ada.

### Checkout

Sumber utama: [shop-client](../../resources/js/lib/shop-client.js), [Shop/Index](../../resources/js/pages/Shop/Index.jsx), [fallback checkout](../../resources/views/shop/index.blade.php).

- Payload allowlist dan idempotency key dibekukan **di memori**, sebelum kontrol dinonaktifkan.
- Network/timeout/5xx/respons sukses malformed mempertahankan payload/key yang sama. Retry mengabaikan edit form berikutnya dan cart yang sudah kosong.
- Penolakan definitif 409/422 membersihkan pending. Sukses yang sah bersifat terminal meskipun refresh tampilan berikutnya gagal.
- Validasi sukses guest mensyaratkan token 64 lowercase hex, atau explicit null bersama `tracking_recovery: unavailable`. Field token hilang/invalid bukan sukses yang boleh menghapus state retry.
- Struktur item order divalidasi sebelum pending dibersihkan, termasuk pada fallback. Tracking token tidak disimpan di URL/localStorage.
- Cart checkout memakai SET quantity, terpisah dari delta storefront. Mutasi cart dan penghapusan alamat diblokir saat checkout pending.
- Form ulasan multi-item mempunyai legend/nama aksesibel produk dan mengirim ID produk yang sesuai. Browser belum memiliki fixture completed-order; regresi DOM dan backend tetap diuji.
- Copy bahwa pembayaran online/konfirmasi otomatis belum tersedia dipertahankan.

### Dashboard dan manajemen informasi

Sumber utama: [DTO dashboard](../../app/Support/AdminDashboardPageProps.php), [Dashboard](../../resources/js/pages/Admin/Dashboard.jsx), [coordinator](../../resources/js/lib/admin-dashboard.js), [dispatcher awal](../../resources/js/lib/frontend-traversal.js).

- Tujuh region kategori, produk, pesanan, pengguna, audit, concierge dan subscriber diperbarui sebagai satu snapshot. Query allowlist, sorting, batas pencarian/page, clamp halaman dan preservasi filter panel lain tetap server-owned.
- Consent draft diperiksa sebelum request **dan** tepat sebelum commit. Edit yang dibuat ketika request berjalan tidak dapat dibuang menggunakan consent lama.
- Form tambah kategori/produk, laporan dan password tetap mounted. Pilihan kategori draft tidak diam-diam diganti oleh refresh snapshot.
- Inertia adalah satu-satunya penulis history. Setiap client commit memiliki marker unik; callback dan URL saja tidak cukup. Entry tersimpan didekripsi melalui API publik dan marker diverifikasi sebelum data lokal diganti.
- Dispatcher traversal didaftarkan **sebelum** Inertia menginisialisasi listener. Probe Chromium menunjukkan listener `popstate` pada Window berjalan menurut urutan registrasi, sehingga listener capture yang dipasang belakangan tidak cukup untuk mencegah restore/remount core.
- Back yang dibatalkan/gagal memulihkan URL rendered melalui replace, tanpa history entry tambahan. Kegagalan history/crypto yang tidak terkonfirmasi mempertahankan data/draft dan menghentikan navigasi tabel.
- Order JSON mutation tidak direplay bila refresh sesudah save gagal. Transisi manual tetap dibatasi server; tidak menambahkan jalur manual `paid`.
- Density, selection/reset, clipboard feedback, error/retry, fokus dan panel keamanan tetap tersedia. PDF laporan/invoice tetap navigasi native.
- Jika bundle gagal atau pengguna sudah berinteraksi, loader dashboard mengaktifkan legacy sekali, mempertahankan form, dan tidak menjadi pemilik DOM React.

### TOTP dan unsubscribe

- Material enrol/recovery berada dalam top-level Inertia flash, **bukan normal props/history**. QR berupa data-URL image; tidak menginjeksi SVG mentah ke DOM.
- Prefetch ditolak sebelum membuat pending secret atau mengonsumsi recovery code.
- Stale-version dan ketiga mode rollback diputuskan **sebelum `pull()` state recovery**. GET dokumen berikutnya masih dapat menampilkan code satu kali.
- Respons perubahan versi mempertahankan raw URI dan `Vary: X-Inertia`. No-store dan private lifecycle tetap aktif.
- Browser memeriksa material terhadap history yang benar-benar didekripsi, bukan `JSON.stringify(ArrayBuffer)` yang dapat memberi false PASS.
- Signed unsubscribe GET tidak memutasi preferensi; POST mempunyai completion JSX tanpa form submit ulang. Signature, idempotensi dan fallback diuji PHP.

## 3. Hasil validasi akhir

| Gate | Hasil | Batas bukti |
|---|---|---|
| PHP penuh | **1.286 pass, 0 failure/error, 3 exact engine skips; 160.073 assertion** | SQLite isolated; bukan rerun MySQL untuk diff baru |
| JUnit fail-closed | **PASS, 1.289 testcase** | Tiga skip adalah ID `MysqlCheckoutRetryTest` yang sudah diizinkan sebelumnya |
| Node | **556 pass, 0 skip** | Termasuk state/race, core Inertia nyata + WebCrypto, fallback dan kontrak CI |
| React/Vitest | **232 pass, 15 file** | DOM/jsdom; tidak menggantikan browser |
| Python | **402 pass, 0 skip/error** | Termasuk 29 tes collector dan transport AF_UNIX |
| Pint | **406 file PASS** | Bukan klaim static analysis/typecheck menyeluruh |
| Production build | **PASS, 669 modules** | Entry Inertia 353,43 KB, gzip 110,82 KB; bukan benchmark TPS/performa pengguna |
| Composer/npm audit | **0 advisory pada eksekusi** | Snapshot pemeriksaan; exact-pin warning Composer tetap dilaporkan |
| Actionlint, scanner secret/PII, diff whitespace | **PASS** | Scanner adalah heuristic, bukan pembuktian nihil kebocoran secara universal |
| Browser legacy | **PASS: 76 produk, 10 halaman, 3 checkout, 2 created, 1 rejected** | Fixture sintetis, Chromium lokal |
| Browser native R8 | **29 mandatory PASS, unexpected console error 0, supervisor 0, cleanup PASS** | Dua optional unavailable; tidak dihitung PASS |
| Collector live | **PASS: 5 job, 4 artifact, kandidat terverifikasi offline** | Run baseline 39825513680/016fb94, **bukan CI diff ini** |

Perintah dan timestamp yang direkam tersedia di [verification-index](evidence-react-completion-2026-10-23/verification-index.json), [PHP](evidence-react-completion-2026-10-23/php-execution.json), [Python final](evidence-react-completion-2026-10-23/python-typed-final.json), [browser native](evidence-react-completion-2026-10-23/native-browser-final-commands.tsv), dan [collector live final](evidence-react-completion-2026-10-23/baseline-collector-final.json). Waktu presisi beberapa gate statis tidak direkam terpisah; hasilnya berasal dari transkrip tool, bukan timestamp yang direka.

### Click-through browser native

Daftar exact 29 stage berada di [summary](evidence-react-completion-2026-10-23/native-browser-final.json). Kelompok tambahan membuktikan:

1. Home mobile menu keyboard/inert/Escape/return focus, tema terang/gelap dan overflow; pencarian, tautan kategori, locale 204.
2. PDP monogram ke inquiry, retensi draft tanpa cart write, tambah/plus/remove/undo cart.
3. Guest checkout: server benar-benar membuat order sebelum response dibuang; retry memakai payload/key dan order ID yang sama; jumlah order bertambah **tepat satu** menurut snapshot admin independen.
4. Account: alamat baru, voucher invalid 422, checkout, order dan favorit tambah/hapus.
5. Dashboard: tujuh region, external draft, inline consent, late edit, Back gagal dan retry, cancellation satu POST, refresh native sebelum reload, serta logout/Back privat.
6. TOTP pelanggan/admin: enrol/recovery native, seluruh recovery code tidak ada dalam decrypted history/storage, reload dan Back tidak menampilkan ulang material, challenge dan penolakan reuse kode pelanggan.

Empat event yang diizinkan dicatat, bukan disamarkan: abort response checkout yang sengaja diinjeksi, console terkait abort tersebut, voucher negatif 422, dan `ERR_ABORTED` saat navigasi ekspor diserahkan ke download manager Chromium. Pengecualian terikat request/origin/path/method/stage, waktu armed dan jumlah maksimum; error lain tetap menggagalkan gate. Final verdict dibuat setelah browser ditutup dan monitor didrain.

**Tidak dijalankan sebagai browser flow:** `shop-account-completed-order-review` dan `signed-unsubscribe-native-confirm-complete`, karena fixture tidak menyediakan kondisi tersebut. Tes PHP/DOM ada; itu bukan pengganti klaim browser. Browser lainnya, screen reader, seluruh kombinasi locale/viewport, operasi feature-enabled, dan klik setiap kontrol fallback baru belum diperiksa menyeluruh dalam increment ini.

## 4. CI/CD dan collector bukti

[Collector](../../scripts/ci/collect_publication.py) memerlukan repository, run ID, SHA, attempt dan event eksplisit. Ia tidak membangun deployment atau mengubah GitHub.

- Run/job harus cocok identitas dan tepat lima job wajib sukses. Snapshot job stale mempunyai bounded consistency wait, bukan langsung dianggap sukses.
- Empat artifact diperiksa nama/identity/expiry/digest/ukuran. Unduhan dilakukan satu per artifact, bukan mass download dengan timeout yang terus dinaikkan.
- Redirect hanya HTTPS, maksimal tiga hop, tidak drain body tanpa batas, tidak meneruskan Host lama atau credential lintas origin.
- Content-Length opsional; chunked dan close-delimited didukung. Deadline diterapkan pada setiap receive, termasuk framing chunk. DNS/TLS setup tetap mempunyai batas platform yang tidak dijamin sebagai hard wall-clock deadline universal.
- Timeout transport sebelum deadline boleh retry GET maksimal satu kali dengan deadline semula dan cleanup partial file. HTTP errors tidak diretry atau salah disebut timeout.
- Struktur ZIP MySQL mengikuti path producer yang sebenarnya; path tak dikenal, traversal, symlink, duplikasi dan extra entries ditolak.
- Release verifier offline wajib lulus sebelum output diterbitkan. Output hanya kandidat terverifikasi dan summary allowlist, bukan token atau raw log.

Pengujian live read-only pertama berhasil pada **22 Oktober UTC 21:40:22–21:40:47**. Setelah pengetatan typing, rerun source final juga berhasil **22:02:10–22:03:13 UTC**, untuk run baseline yang sama. Tidak ada upload source, perubahan branch protection, deploy, atau mutasi database operasional. [Runbook](../04-implementation/CI_CD_RELEASE_RUNBOOK.md) menjelaskan pemakaian dan batas operasional.

## 5. Continuous evaluation dan review silang

Baseline: 11 komponen halaman, PHP1.247/React110/Node495/Python373 dan native17 stage pada milestone yang sudah dipublikasikan. Increment menambah sembilan halaman, coverage, dan collector; **bertambahnya tes tidak dianggap peningkatan kapasitas transaksi**.

Kegagalan yang ditemukan tidak dihapus dari riwayat:
- Review menemukan menu inert, fokus cart, locale partial failure, key favorit, draft/request concierge, malformed checkout dan ulasan multi-item; diperbaiki dengan regresi terarah.
- Core Inertia nyata mengungkap identical-page replace no-op, quota error yang ditelan, dan stale validation bag. Chromium kemudian mengungkap registration-order popstate yang tidak direproduksi oleh oracle JSDOM awal.
- Browser R1–R6 tetap tersimpan sebagai FAIL. R7/R8 kemudian lulus; gate tidak dipenuhi hanya dari sub-stage yang sudah berhasil.
- Full PHP pertama memiliki 18 non-passing cases. Query ID duplikat diperbaiki. Assertion visibilitas dipindah ke node/panel nyata karena copy JSON inert bukan UI; positive oracle diperkuat agar tidak bisa dipenuhi JSON saja. API test direct-View disesuaikan ke HTTP/viewData tanpa mematikan React atau melemahkan cache/query/invalidation checks.
- Collector live mengungkap Host redirect yang salah, path ZIP wildcard MySQL, dan klasifikasi body timeout yang memintas retry. Setelah koreksi terarah, full Python dan live collector lulus.
- Pengetatan typing memperkenalkan import namespace yang bekerja melalui `python -m unittest`, tetapi gagal pada canonical runner yang entry path-nya `scripts/ci`. Test setup kini memasukkan root proyek eksplisit; kegagalan import disimpan, lalu canonical full Python kembali **402 PASS** pada22:01:59–22:02:10UTC. Tidak ada test yang di-skip untuk menutupnya.

**Diagnostik editor:** source collector baru mencapai0error/0warning; file tesnya0error tetapi masih memiliki warning lint fixture (style, penamaan parameter dan binding closure loop). Diagnostik Python legacy pada launcher/fixture/skrip lain juga masih dilaporkan editor dan tidak diubah dalam migrasi ini. Dengan demikian, kelulusan runtime di atas **bukan klaim diagnostics repo-wide bersih**. Tidak memakai suppression atau menghapus pengujian untuk menyembunyikannya.

| Reviewer independen | Cakupan dan keputusan |
|---|---|
| `6d30f473-7c19-4452-a1c8-8137b8ce2c8d` | HOME/PDP, delapan regresi dan lifecycle concierge; APPROVE scoped |
| `8ed5648d-904a-4e6a-a256-7bd2153da3bd` | Dashboard/core history, dispatcher awal, fallback loader dan adaptasi oracle PHP; APPROVE scoped |
| `593990c9-0b4a-44c2-b98a-c8975e3269fa` | Checkout, oracle/wiring browser, monitor dan evidence R7; APPROVE scoped |
| `2db9d795-9a7a-4268-97af-6497475acc59` | SECURITY, Vary middleware dan collector; APPROVE scoped, termasuk 29 tes +35 probe final collector |

Author/reviewer berbeda. Hasil reviewer bukan diklaim sebagai eksekusi parent; bukti browser/PHP penuh dijalankan integrator secara serial. Rincian penugasan, kegagalan dan resumption ada pada ledger.

[Crosscheck penutupan independen](evidence-react-completion-2026-10-23/independent-closure-review.json),22:05–22:07UTC:84/84hash source dan inventaris cocok,20komponen unik tersedia,131tautan pada5dokumen valid,18JSON evidence valid pada snapshot review, serta angka/batas klaim konsisten. Ini review metadata, bukan rerun suite atau persetujuan produksi. Seluruh penugasan agent telah handoff dan ditutup.

## 6. HCI, kualitas sistem dan batas penerimaan

Lensa profesional adalah pertanyaan audit, bukan orang yang benar-benar memberikan endorsement.

| Dimensi | Bukti perubahan | Yang tidak boleh disimpulkan |
|---|---|---|
| Performance | Query ID duplikat hilang; featured tetap terbatas; chunk halaman terpisah | React otomatis lebih cepat, TPS tertentu, atau p95 produksi membaik |
| Continuous availability | Fallback/rollback, deadline transport, error/retry dan cleanup diuji | Uptime/SLO, failover host, worker/scheduler produksi sudah terbukti |
| Data integrity | Frozen checkout/key, satu order pada response loss, delta/SET terpisah, no replay order mutation | Semua konkurensi engine/eksternal telah diuji ulang |
| Ease of use | Draft/fokus/menu, status, recovery, nama form ulasan dan locale diperbaiki | Sertifikasi WCAG atau uji semua perangkat/screen reader |
| Modular growth | DTO eksplisit, module boundary, page chunks dan adapter versi terkunci | Migrasi berikutnya bebas risiko; upgrade Inertia harus mengulang oracle core dan browser |
| Controlled processing | Server transition authority, one-time recovery, exact CI identities, bounded retry dan fail-closed | CD/approval/proteksi main sudah aktif |

**Antislop DURING, session override:** identitas/aset tidak direka atau di-redesign. Design read storefront ENERGY2/RHYTHM3/MOTION2 dan dashboard ENERGY1/RHYTHM1/MOTION1 dipertahankan. Purpose gate: palet/font untuk kontinuitas brand, layout yang sama untuk mempertahankan model mental, indikator status/fokus untuk feedback dan kontrol pengguna, tanpa dekorasi atau klaim performa baru.

Gate lokal fungsi/resiliensi/keyboard/reflow untuk skenario tercatat **PASS scoped**, dengan evidence per stage. Riset/benchmark, kontras seluruh elemen, semua tema/breakpoint/kontrol dan pengujian pembaca layar **TIDAK TERBUKTI secara menyeluruh**; tidak diberikan PASS universal atau klaim WCAG. Ini batas coverage nyata, bukan penggantian pengujian dengan nama tokoh atau skill.

Skill digunakan sesuai domain: incremental-implementation, git-workflow-and-versioning, laravel-patterns/tdd/verification, frontend-ui-engineering/patterns, accessibility, antislop core/UI/human, review, debugging, CI automation, dan documentation-and-adrs. Chrome DevTools MCP tidak tersedia; harness Playwright isolated digunakan. Skill native mobile, animasi baru, desain aset dan platform lain tidak relevan dan tidak diterapkan paksa.

## 7. Gate yang masih memerlukan keputusan atau lingkungan eksternal

1. Commit/push increment dan CI Linux/MySQL/release untuk **SHA baru**. Collector baseline tidak menggantikannya.
2. Reviewer manusia independen dan aktivasi/drill branch protection berizin. Snapshot terakhir belum membuktikan enforcement aktif.
3. Target staging, pemilik operasi, TLS/storage/database engine, deployment lock, promosi, reload worker, N−1 dan rollback nyata.
4. Konfigurasi sandbox OAuth/Midtrans, secret manager dan izin provider. Jangan kirim secret melalui chat/dokumentasi.
5. Worker/scheduler/cache bersama, penerima alert, restore/recovery drill, RPO/RTO dan rekonsiliasi transaksi pada lingkungan target.
6. Workload, target volume, SLI/SLO, batas load test, perangkat/browser/screen-reader dan pengguna uji. Gunakan [analisis TPS/MSI](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md) sebagai model berlabel, bukan hasil benchmark produksi.

Tidak ada dasar teknis untuk menjanjikan nihil cacat pada semua kondisi. Hasil yang dapat dipertanggungjawabkan adalah source yang direview, gate lokal yang benar-benar dijalankan, kegagalan yang tetap terlacak, dan batas penerimaan di atas.
