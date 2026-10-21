# ZAFIRA — Enterprise Engineering Delivery Tracker

Dokumen resmi Sekretariat Tim Pengembang ZAFIRA (Engineering, Design, Audit, Security, Real-World Testing).
Diperbarui otomatis pada setiap siklus penyelesaian task.

---

## Susunan Tim Kerja (Virtual Engineering Division)

| Divisi | Peran & Tugas Pokok | Personel / Agen |
|---|---|---|
| **Lead & Secretariat** | Orkestrasi siklus, rekonsiliasi checklist fitur/subfitur, pembaruan dokumen. | Lead Orchestrator |
| **Research & R&D** | Riset standar industri perbankan/fintech, analisis perbandingan arsitektur. | Principal Researcher |
| **Software Architecture** | Desain batas sistem, CQRS projection, integritas data antar-service. | Chief Software Architect |
| **Engineering Division** | Implementasi kode bersih, modular, performan, low-overhead, sesuai arsitektur Laravel 13. | Core Backend/Frontend Engineer |
| **Design & HCI Division** | Audit interaksi 7 Tokoh IMK, konsistensi token visual *Bodoni Moda*/*Manrope*, palet kemewahan. | Principal HCI Designer |
| **Security Division** | Verifikasi zero secrets leak, mitigasi injection/CSRF/timing attacks, audit signature & authorization. | Security Reviewer |
| **Architecture & Audit** | Menjaga prinsip immutability, integritas relasi DB, pencegahan race-condition, audit dewan. | Architecture Auditor |
| **Real-World Testing Team** | Pengujian skenario dunia nyata, edge cases, data buruk, simulasi koneksi terputus & idempotensi. | QA & Real-World Tester |
| **DevOps** | Konfigurasi runtime, env, scheduler, queue, observabilitas, kesiapan deploy dan rollback. | DevOps Engineer |
| **QA** | Gerbang kualitas: suite penuh hijau, `git diff --check`, regresi, bukti eksekusi sebelum `task complete`. | QA Lead |
| **IT Support** | Runbook operator, panduan pemulihan insiden pembayaran, dokumentasi konfigurasi. | IT Support Lead |

---

## Dashboard Status Proyek & Fitur

Target Plan ID: `26abc11f-166b-408a-86aa-bf1c94aa41bd` (Baseline Codebase)

### Ringkasan Kemajuan Task
- **Task Selesai (Completed):** 8 task
- **Task Berjalan (In Progress):** 0 task
- **Task Tersisa (Remaining):** Diproses berurutan hingga tuntas

---

### Matriks Checklist Fitur & Subfitur

#### 1. Fitur: Ongkir & Voucher Transparan
- [x] **Subfitur 1.1:** Ongkir dan voucher divalidasi server-side secara transparan.
  - *Status:* SELESAI (Test coverage terverifikasi).

#### 2. Fitur: Kelola Pesanan
- [x] **Subfitur 2.1:** Riwayat perubahan status pesanan append-only (`order_status_history`).
  - *Status:* SELESAI (Migrasi + model + relasi + test tuntas).

#### 3. Fitur: Kelola Produk, Stok & Kategori
- [x] **Subfitur 3.1:** Tambah produk dan ubah kategori langsung dari halaman admin.
  - *Status:* SELESAI (Diverifikasi oleh test suite).
- [x] **Subfitur 3.2:** Penyesuaian stok langsung dari daftar + state kosong bersemangat.
  - *Status:* SELESAI (Empty-state bersemangat + auto-open form + inline stock editing + 5 unit tests).

#### 4. Fitur: Halaman Detail Produk
- [x] **Subfitur 4.1:** Galeri, ketersediaan, tombol share, penambahan ke keranjang.
  - *Status:* SELESAI (Fitur share + OG URL + button accessibility terverifikasi).

#### 5. Fitur: Stok Terkunci Sementara (Inventory Reservation)
- [x] **Subfitur 5.1:** Reservasi stok berjangka waktu (TTL 60 menit) dengan automatic release cron/job.
  - *Status:* SELESAI (`inventory_reservations` + `ReleaseExpiredReservations` + Schedule console + tests).

#### 6. Fitur: Konfirmasi Bayar Otomatis
- [x] **Subfitur 6.1:** Webhook pembayaran server-side Midtrans (verifikasi signature SHA512 + GET Status API + idempotency key).
  - *Status:* SELESAI (Tabel `payments`, `PaymentWebhookService`, `MidtransWebhookController`, 17 tests).
- [x] **Subfitur 6.2:** Ledger pembayaran (`payments`) sebagai sumber kebenaran status bayar.
  - *Status:* SELESAI (`hasSettledPayment()`, deteksi anomali, ringkasan ledger di detail admin, 7 test). Keputusan: `COUNCIL_DECISIONS.md` #001.
- [x] **Subfitur 6.3:** Ketahanan integrasi pembayaran (timeout, retry, circuit breaker, dead-letter).
  - *Status:* SELESAI (`connectTimeout`, retry ConnectionException, breaker Cache 5-gagal/1-menit, `payment_dead_letters` + replay/prune scheduler). 27 test webhook. Keputusan: `COUNCIL_DECISIONS.md` #002.

#### 7. Fitur: Muat Cepat di HP
- [x] **Subfitur 7.1:** Anggaran performa terukur + gambar responsif & lazy-load tanpa layout shift.
  - *Status:* SELESAI (varian WebP 600/896 untuk 8 aset lokal: hero HP 765 KiB → 43.6 KiB; `srcset`/`sizes` storefront, auth, katalog, detail; `Product::imageSrcset()`; width/height wajib; `MobilePerformanceBudgetTest` 4 test). Keputusan: `COUNCIL_DECISIONS.md` #003.

#### 8. Fitur: Anti Pembayaran Ganda
- [x] **Subfitur 8.1:** Idempotency pembuatan order pada submit checkout.
  - *Status:* SELESAI (server: unique key per user/scope tamu + payload hash 409 sudah ada; ditambah `CheckoutIdempotencyAntiDoubleChargeTest` 3 test: replay tanpa potong stok ganda, kunci sama payload beda → 409, keranjang kosong tanpa replay → 422; klien: test node klik ganda → 1 POST; perbaikan harness/guard `voucher_code` yang membuat 14 test node gagal). Keputusan: `COUNCIL_DECISIONS.md` #004.

#### 9. Fitur: Pengingat Keranjang
- [x] **Subfitur 9.1:** Pengingat keranjang tersimpan (opt-in, rate-limited).
  - *Status:* SELESAI (kolom `users.cart_reminder_opt_in` default mati + `cart_reminded_at`; command `cart-reminders:send` per jam: keranjang akun diam 24 jam–7 hari, email terverifikasi, produk aktif saja, maks 1×/minggu dan 1× per perubahan keranjang, klaim atomik; job `SendCartReminder` cek ulang opt-in sebelum kirim; toggle di Profil; berhenti via tautan bertanda tangan → halaman konfirmasi → POST; `CartReminderTest` 7 test). Keputusan: `COUNCIL_DECISIONS.md` #005.

#### 10. Fitur: Umpan Balik & Aksi Aman
- [x] **Subfitur 10.1:** Standar umpan balik aksi, konfirmasi aksi berbahaya, pesan galat manusiawi.
  - *Status:* SELESAI (`#feedback` belanja beralih `role=alert` + gaya `is-error` saat gagal, kembali `status` saat sukses; peta pesan HTTP 401/403/404/409/419/429/5xx berbahasa Indonesia di `shop/request.blade.php`; admin wajib konfirmasi sebelum membatalkan pesanan; halaman galat 403/404/419/429/500/503 bertema ZAFIRA dengan jalur keluar ke beranda/belanja tanpa detail internal; `HumaneErrorPagesTest` 3 test + node test konfirmasi & role). Keputusan: `COUNCIL_DECISIONS.md` #006.

#### 11. Fitur: Tampilan Khusus Konsol Admin
- [x] **Subfitur 11.1:** Bangun sistem tampilan konsol admin terpisah dari storefront.
  - *Status:* SELESAI (Layout konsol admin `admin.layouts.app` memakai kelas body `admin-console` dan memuat berkas CSS terpisah `resources/css/admin.css` yang didaftarkan ke `vite.config.js`; seluruh gaya konsol modular [header kerja, navigasi, remah roti, panel, stat-card, tabel berdensitas, formulir status, badge] dipusatkan di berkas tersebut tanpa inline `<style>` di layout/dashboard; storefront tidak memuat berkas ini; 716 test PHPUnit lolos + 290 node test lolos). Keputusan: `COUNCIL_DECISIONS.md` #007.
- [x] **Subfitur 11.2:** Penuhi WCAG 2.2 AA & operasi penuh keyboard pada konsol admin.
  - *Status:* SELESAI (Seluruh kontrol konsol admin berukuran minimal 44×44px; cincin fokus terlihat `:focus-visible` 3px dengan kontras rasio >4.5:1 terhadap latar; tabel log autentikasi `auth-events` dibungkus `tabindex="0" role="region"` dengan `caption` dan `th scope="col"`; ringkasan accordion `<summary>` min-height 44px; `@media (prefers-reduced-motion: reduce)` diterapkan di seluruh elemen konsol admin; 718 test PHPUnit lolos + 290 node test lolos). Keputusan: `COUNCIL_DECISIONS.md` #008.

#### 12. Fitur: Riwayat Pesanan
- [x] **Subfitur 12.1:** State machine order eksplisit + `order_status_history` sebagai audit trail transisi (R-10, ADR-07).
  - *Status:* SELESAI (Mesin transisi `Order::STATUS_FLOW` + `canTransitionTo()`; semua perubahan status lewat `CheckoutService::transition()`/`markPaid()` dengan CAS `where status = status`; tabel `order_status_history` append-only [model `OrderStatusHistory` menolak update/delete via `LogicException`], merekam `from_status`→`to_status` + `admin_id` + `created_at`; inisialisasi `pending` saat order dibuat; webhook menulis transisi `pending→paid` dengan `admin_id=null`; pelanggan kini melihat `status_history` pada `GET /orders/{id}` tanpa `admin_id`. 721 test PHPUnit lolos + 290 node test lolos). Keputusan: `COUNCIL_DECISIONS.md` #009.

#### 13. Fitur: Lacak Pengiriman
- [x] **Subfitur 13.1:** Tabel `shipments` + `shipment_tracking_events` (riwayat pelacakan berurutan, R-04) dengan keadaan "Resi belum tersedia" yang dijelaskan.
  - *Status:* SELESAI (Transisi `shipped` membuat shipment `pending_manifest` + event pertama; `completed` menandai `delivered`; event diurutkan `recorded_at`, lalu `id`; pesanan tanpa resi menampilkan label "Resi belum tersedia" + penjelasan; ringkasan tampil di `GET /orders/{id}`, pelacakan tamu `POST /orders/track`, dan detail admin. `ShipmentTrackingTest` 4 test; 725 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #010.
  - *Batas:* Belum ada form admin untuk mengisi kurir/resi dan belum ada integrasi API kurir; resi diisi lewat data.

#### 14. Fitur: Ajukan Retur
- [x] **Subfitur 14.1:** Pengajuan retur tercatat (`returns`/`return_items`): pilih barang, alasan, ringkasan, syarat kelayakan.
  - *Status:* SELESAI (Eligibility: status `completed`, jendela 7 hari dari timestamp `completed` riwayat, kuantitas sisa = pesanan dikurangi klaim non-rejected; `OrderReturnController` menyediakan `GET /returns`, `GET /orders/{id}/return-eligibility`, `POST /orders/{id}/returns`; nomor referensi `RET-...`; ringkasan + langkah berikutnya + validasi alasan `other` wajib berisi catatan; penguncian baris `lockForUpdate` cegah klaim ganda. `OrderReturnRequestTest` 5 test; 730 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #011.
  - *Batas:* Belum ada panel admin untuk menyetujui/menolak retur dan belum ada alur refund; ajuan hanya tercatat dan terbaca pelanggan.

#### 15. Fitur: Status Pengembalian Dana
- [~] **Subfitur 15.1:** State machine pengembalian dana (`diajukan→disetujui/ditolak→dana_dikirim→diterima`) berbekal ledger `payments`.
  - *Status:* SEBAGIAN (Tabel `refunds`, `Refund::STATUS_FLOW`, `RefundService` dengan kunci baris; pelanggan mengajukan via `POST /returns/{id}/refunds`, melihat via `GET /refunds`; admin meninjau via `PATCH /admin/refunds/{id}`; penolakan wajib alasan; total refund ≤ pembayaran `paid` tercatat. `RefundStateMachineTest` 5 test; 735 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #012.
  - *Batas (fail-closed):* `dana_dikirim` dan `diterima` sengaja ditolak sampai ada integrasi refund Midtrans + webhook terverifikasi. ID refund yang diketik admin bukan bukti uang bergerak.

#### 16. Fitur: Pemberitahuan Pesanan
- [x] **Subfitur 16.1:** Notifikasi pesanan per transisi status (idempoten).
  - *Status:* SELESAI (Tabel `order_notifications` unique `(order_id, status, channel)`; `CheckoutService::applyTransition` mengklaim slot lalu mengantrekan `OrderStatusChanged` setelah commit untuk `paid`, `shipped`, `completed`, `cancelled`; `processing` tidak memicu email; replay webhook/CAS gagal tidak mengirim ulang; klaim dilepas bila enqueue gagal. `OrderNotificationTest` 3 test; 738 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #013.
  - *Batas:* Hanya kanal email untuk pelanggan terdaftar; belum ada WhatsApp/push dan belum ada preferensi opt-out per status.


#### 17. Fitur: Ringkasan Penjualan
- [x] **Subfitur 17.1:** Ringkasan pesanan, nilai, dan tingkat sukses pembayaran per periode dibandingkan.
  - *Status:* SELESAI (Endpoint `GET /admin/reports/sales-summary`; membandingkan rentang berdurasi sama, menghitung paid value dari ledger `payments`, jumlah pesanan, paid orders, tingkat sukses, dan perubahan; validasi periode maks. 366 hari. `AdminSalesSummaryTest` 2 test; 740 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #014.
  - *Batas:* Basis waktu periode adalah `orders.created_at`, bukan waktu pembayaran; belum ada UI dashboard.


#### 18. Fitur: Daftar & Masuk — Login Sosial
- [x] **Subfitur 18.1:** Laravel Socialite & alur OAuth pelanggan (Google, Apple).
  - *Status:* SELESAI (`SocialAuthController`, tabel `social_accounts`, route `/auth/{google|apple}/redirect|callback`, tombol kondisional di halaman masuk/daftar; email terverifikasi wajib; tanpa tautan otomatis ke akun berkata sandi. `SocialLoginTest` 7 test; 747 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #015.
  - *Batas:* Butuh kredensial `GOOGLE_*`/`APPLE_*` di produksi; belum ada uji E2E dengan penyedia asli; belum ada UI tautkan/lepas akun.

#### 19. Fitur: Daftar & Masuk — Penautan Akun Sosial Aman
- [x] **Subfitur 19.1:** Tabel `social_accounts`, kolom `users.has_password`, penautan Google aman, dan guard lepas tautan terakhir.
  - *Status:* SELESAI (Flag `has_password` di tabel `users` & model, alur penautan aman via `session('social_link')`, proteksi benturan lintas akun, proteksi pelepasan tautan terakhir bila belum ada kata sandi, UI Akun Tertaut di `/akun`. `SocialLoginTest` 12 test; 752 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #016.
  - *Batas:* Penautan baru mencakup Google; Apple belum ditautkan dari profil; UI set kata sandi pertama untuk akun sosial murni belum ada.

#### 20. Fitur: Daftar & Masuk — Verifikasi Email
- [x] **Subfitur 20.1:** Daftar akun email + verifikasi tautan bertanda tangan.
  - *Status:* SELESAI (`User implements MustVerifyEmail`, `EmailVerificationController`, route `GET /email/verifikasi/{id}/{hash}` dengan `signed`+`auth`+`throttle:6,1`, `POST /email/verifikasi-ulang`, banner status + kirim ulang di `/akun`, pengiriman notifikasi fail-soft saat daftar. `EmailVerificationTest` 5 test; 757 PHPUnit + 290 node lolos). Keputusan: `COUNCIL_DECISIONS.md` #017.
  - *Batas:* Verifikasi non-blocking (tidak memblokir checkout); belum ada alur ganti email + re-verifikasi.

#### 21. Fitur: Daftar & Masuk — Middleware Verified & Pesan Galat
- [x] **Subfitur 21.1:** Middleware `verified` + pesan galat yang membimbing pada alur auth.
  - *Status:* SELESAI (`verified` pada penautan akun sosial, `verification.notice` terarah, tautan kedaluwarsa diarahkan ke kirim ulang, pesan login generik anti-enumerasi dengan petunjuk, pesan daftar menyebut email tujuan. `EmailVerificationTest` 7 test; 759 PHPUnit lolos). Keputusan: `COUNCIL_DECISIONS.md` #018.
  - *Batas:* Checkout tidak diblok verifikasi (keputusan #017); belum ada reset kata sandi.


#### 22. Fitur: Daftar & Masuk — Lupa Kata Sandi
- [x] **Subfitur 22.1:** Token pemulihan sekali pakai, kedaluwarsa, rate-limit, dan notifikasi konfirmasi.
  - *Status:* SELESAI (route `/lupa-kata-sandi` dan `/reset-kata-sandi`, `PasswordResetController`, issuer audit lama dipakai, formulir reset bergaya auth, link login, kebijakan broker TTL/single-use, `has_password` dan remember token diperbarui, notifikasi konfirmasi. `PasswordResetFlowTest` 8 test; 767 PHPUnit (4 skipped), node tests, build, dan diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #019.
  - *Batas:* Pengiriman email nyata/SMTP belum diuji; rate-limit memakai cache driver aplikasi.


#### 23. Fitur: Kesehatan Sistem — Health Check
- [x] **Subfitur 23.1:** Endpoint kesehatan DB, queue, cache, storage, dan status HTTP jujur.
  - *Status:* SELESAI (`GET /health` di luar middleware web, probe round-trip cache/storage dan konektivitas database/queue, 503 saat dependency gagal, respons minim dan tanpa kredensial. `HealthCheckTest` 4 test; 771 PHPUnit (4 skipped), node tests, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #020.
  - *Batas:* Probe queue tidak memastikan worker aktif; disk default harus mendukung tulis/hapus.


#### 24. Fitur: Penelusuran Insiden — Audit Log & Korelasi
- [x] **Subfitur 24.1:** Audit log append-only + korelasi request-id.
  - *Status:* SELESAI (`CorrelationIdMiddleware` prepend global, header `X-Request-Id` disanitasi, `Context` dibagikan, kolom `auth_events.request_id` via migrasi, model `AuthEvent` append-only, kolom + filter request_id di tabel admin. `IncidentCorrelationTest` 8 test + 1 filter; 780 PHPUnit (4 skipped), node tests, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #021.
  - *Batas:* Korelasi log aplikasi bergantung format channel; filter request_id hanya admin.

#### 25. Fitur: Hak Akses & Peran — RBAC & Policy
- [x] **Subfitur 25.1:** Peran admin/staff/pelanggan + policy terpusat & penegakan server.
  - *Status:* SELESAI (kolom `admins.role` dibatasi ke `admin`/`staff`, migrasi `2026_10_20_000002` memberi default admin untuk baris lama, Gate terpusat di `AppServiceProvider`, middleware `AuthorizeAdmin` mengevaluasi Gate untuk guard admin secara eksplisit, route admin dilindungi via `admin.can:*`, verifikasi role pada provisioning `--role`, pelanggan dibatasi via guard web dan isolasi data per `user_id`. `AdminRoleAuthorizationTest` 4 test; 784 PHPUnit (4 skipped), 290 node, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #022.
  - *Batas:* Belum ada granular permission per user atau UI manajemen peran; penugasan peran lewat CLI/DB.

#### 26. Fitur: Halaman Galat yang Membantu — Error Pages & error_id
- [x] **Subfitur 26.1:** Halaman galat dirancang (404/500/419/503) dengan error_id yang dapat dilacak ke log.
  - *Status:* SELESAI (seluruh error view 403, 404, 419, 429, 500, 503 diwariskan dari `errors.layout` dengan identitas visual ZAFIRA, tautan keluar ramah pengguna, dan tampilan kode kejadian `request_id` yang cocok dengan header respons HTTP serta log server tanpa membocorkan trace internal. `HumaneErrorPagesTest` 6 test; 787 PHPUnit (4 skipped), 290 node tests, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #023.
  - *Batas:* Error ID bergantung pada runtime PHP/Laravel; kegagalan level Nginx/web server sebelum menyentuh PHP tidak menyertakan layout Laravel.

#### 27. Fitur: Hak Akses & Peran — Threat Model STRIDE
- [x] **Subfitur 27.1:** Threat model STRIDE jalur uang & admin + verifikasi otorisasi berkelanjutan.
  - *Status:* SELESAI (`docs/03-architecture/THREAT_MODEL_STRIDE.md` memetakan kontrol ke kode nyata; `AuthorizationInventoryTest` 4 test/104 asersi menggagalkan CI bila route admin kehilangan `auth:admin`/TOTP/Gate terdefinisi, atau route uang kehilangan `auth`/throttle. 791 PHPUnit (4 skipped), 290 node tests, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #024.
  - *Batas:* Risiko terbuka tercatat di dokumen: rekonsiliasi harian, refund sandbox E2E, admin legacy tanpa TOTP, mutasi katalog belum diaudit, tanpa permission granular.

#### 28. Fitur: Ukur Sebelum Perbaikan — Indikator Baseline
- [x] **Subfitur 28.1:** Tetapkan indikator baseline & metode pengambilannya.
  - *Status:* SELESAI (`docs/05-quality/BASELINE_INDICATORS_METHODOLOGY.md` + `scripts/benchmarks/capture_baseline.sh` → `baseline_report.txt`; nilai acuan: 87 route, 791 PHPUnit, 290 node, ~135 KB aset, 18/18 route admin terlindungi). Keputusan: `COUNCIL_DECISIONS.md` #025.
  - *Batas:* Belum ada metrik performa perangkat nyata, uji beban, atau coverage.

#### 29. Fitur: Preferensi & Privasi Akun
- [x] **Subfitur 29.1:** Akses, ekspor, dan hapus data akun.
  - *Status:* SELESAI (`/akun/ekspor`, `DELETE /akun`, migrasi `000005` melindungi retur/refund dari cascade; 7 test baru; 798 PHPUnit, 290 node, build, diff check lolos). Keputusan: `COUNCIL_DECISIONS.md` #026.
  - *Batas:* Kebijakan retensi data pesanan belum ditetapkan; tanpa masa tenggang dan tanpa email konfirmasi hapus.

#### 30. Fitur: Ukur Sebelum Perbaikan
- [x] **Subfitur 30.1:** Baseline metrik bertanggal.
  - *Status:* SELESAI (snapshot `docs/05-quality/baselines/`, skrip menjalankan ulang test). Keputusan: `COUNCIL_DECISIONS.md` #027.
  - *Batas:* Tanpa cakupan, Lighthouse, dan uji beban.
- [x] **Subfitur 30.2:** Pembanding baseline vs kondisi terkini.
  - *Status:* SELESAI (`scripts/benchmarks/compare_baseline.py` membaca format snapshot, menghitung delta tiap indikator, dan memblokir regresi jika failure bertambah atau test berkurang). Keputusan: `COUNCIL_DECISIONS.md` #028.
  - *Batas:* Belum ada ambang batas untuk degradasi durasi atau ukuran bundel (hanya membandingkan nilai numerik).

#### 31. Fitur: Akun & Keamanan
- [x] **Subfitur 31.1:** TOTP opsional pelanggan.
  - *Status:* SELESAI (`CustomerTotpController`, migrasi `2026_10_20_000006`, tampilan tantangan/pendaftaran/kode cadangan, 6 test `CustomerTotpTest`). Keputusan: `COUNCIL_DECISIONS.md` #029.
  - *Batas:* Tanpa regenerasi kode cadangan, tanpa audit event, tanpa UI pemulihan admin.

#### 32. Fitur: Masuk dengan Google & Apple
- [x] **Subfitur 32.1:** Tombol penyedia di storefront + jaminan absen di konsol admin.
  - *Status:* SELESAI (`resources/views/auth/_social.blade.php`, test aksesibilitas & isolasi admin di `SocialLoginTest`). Keputusan: `COUNCIL_DECISIONS.md` #030.
  - *Batas:* SVG monokrom, tidak ada pemanggilan skrip pihak ketiga.

#### 33. Fitur: Verifikasi Email
- [x] **Subfitur 33.1:** Bukti isolasi verifikasi antar akun + persistensi lintas perangkat.
  - *Status:* SELESAI (test `EmailVerificationTest::test_verification_persists_across_devices_and_stays_isolated_per_account`). Keputusan: `COUNCIL_DECISIONS.md` #031.
  - *Batas:* Verifikasi non-blocking; tanpa alur ubah email.

#### 34. Fitur: Pemeriksaan Otomatis (CI)
- [x] **Subfitur 34.1:** Bangun pipeline CI: lint, test, validasi migrasi.
  - *Status:* SELESAI (`.github/workflows/ci.yml`; Pint, migrasi SQLite, PHPUnit 802, node 299). Keputusan: `COUNCIL_DECISIONS.md` #032.
  - *Batas:* Tanpa test MySQL/PG nyata, tanpa coverage, tanpa gerbang deploy.
- [x] **Subfitur 34.2:** Tambahkan uji aksesibilitas (storefront + admin) & validasi skema ke CI.
  - *Status:* SELESAI (`StorefrontA11yMarkupTest`, `AdminA11yLandmarkTest`, `DatabaseSchemaIntegrityTest`; perbaikan ARIA galat login). Keputusan: `COUNCIL_DECISIONS.md` #034.
  - *Batas:* Statis, tanpa axe/kontras/urutan fokus.
- [x] **Subfitur 34.3:** Jadikan CI syarat wajib gabung + lingkungan seragam.
  - *Status:* SELESAI (`.nvmrc`, `engines.node`, `scripts/ci/protect-main.sh`, `README.md` diselaraskan; `EnvironmentUniformityTest`). Keputusan: `COUNCIL_DECISIONS.md` #037.
  - *Batas:* Script perlindungan branch dijalankan pasca-push pertama.
- [x] **Subfitur 34.4:** Gerbang anti-drift skema DB vs migrasi + hentikan akses data via getcat.
  - *Status:* SELESAI (langkah CI anti-drift; asersi `DatabaseSchemaIntegrityTest`; migrasi drop `getcat`). Keputusan: `COUNCIL_DECISIONS.md` #038.
  - *Batas:* Rollback lama SQLite pra-ada gagal; runtime aplikasi kini bebas `getcat`.

#### 36. Fitur: Pemindaian Keamanan
- [x] **Subfitur 36.1:** Pemindaian Keamanan: rahasia tak bocor, audit dependensi & PII.
  - *Status:* SELESAI (4 langkah CI; `SecurityScanIntegrityTest`; `npm audit fix` → 0 kerentanan). Keputusan: `COUNCIL_DECISIONS.md` #039.
  - *Batas:* Regex, tanpa riwayat git; PII hanya `Log::*` satu baris.

#### 37. Fitur: Gaya Bahasa yang Mengikat
- [x] **Subfitur 37.1:** Tetapkan matriks nada bicara per konteks permukaan.
  - *Status:* SELESAI (`docs/02-product/VOICE_AND_TONE_MATRIX.md`; `VoiceAndToneConsistencyTest`). Keputusan: `COUNCIL_DECISIONS.md` #040.
  - *Batas:* Daftar frasa, bukan analisis semantik; belum divalidasi pengguna.

- [x] **Subfitur 37.2:** Jadikan panduan gaya bahasa gerbang wajib sebelum teks dipakai.
  - *Status:* SELESAI (CI, PR template, test negatif membuktikan gate memblokir frasa terlarang). Keputusan: `COUNCIL_DECISIONS.md` #041.
  - *Batas:* Daftar frasa; pemeriksaan semantik tetap manual.

#### 38. Fitur: Perbaikan Seluruh Teks Toko
- [x] **Subfitur 38.1:** Inventarisasi seluruh teks permukaan toko.
  - *Status:* SELESAI (`docs/04-implementation/STOREFRONT_TEXT_INVENTORY.md`; `StorefrontTextInventoryIntegrityTest`). Keputusan: `COUNCIL_DECISIONS.md` #042.
  - *Batas:* Peta teks, bukan perbaikan; temuan dikerjakan task berikutnya.
- [x] **Subfitur 38.2:** Nilai tiap teks dengan tiga kacamata (HCI, marketing, brand).
  - *Status:* SELESAI (`docs/04-implementation/STOREFRONT_TEXT_EVALUATION.md`, 13 temuan; `StorefrontTextEvaluationIntegrityTest`). Keputusan: `COUNCIL_DECISIONS.md` #044.
  - *Batas:* Penilaian ahli; belum uji pengguna.
- [x] **Subfitur 38.3:** Tulis ulang teks dan simpan daftar sebelum/sesudah.
  - *Status:* SELESAI (`docs/04-implementation/STOREFRONT_TEXT_REWRITE_LOG.md`; tes `tests/storefront-copy.test.mjs`, `tests/shop-cart-load.test.mjs`, `UnifiedPurchaseCartTest`). Review independen sesi `ses_ea6c3c89affetUTDvf7eO9KgVl`; keputusan `COUNCIL_DECISIONS.md` #045.
  - *Batas:* Belum diuji dengan pengguna atau seluruh bahasa di browser; pembayaran online tetap tidak tersedia.
  - *Verifikasi:* `php artisan test` EXIT=0 (835 passed, 4 skipped); `./vendor/bin/pint --test` EXIT=0; `node --test` EXIT=0 (302 passed); `git diff --check` EXIT=0.
  - *Continuous Evaluation Record:* Baseline E-01–E-13 ada di `STOREFRONT_TEXT_EVALUATION.md`; perubahan terkendali: teks storefront, kamus portal, fallback status, tes, log, indeks. Review dan tes mengungkapkan serta menutup tiga celah; belum ada regresi pada suite berjalan.
  - *Bukti keterbatasan:* tes 4 bahasa menguji nilai kamus, bukan interaksi browser; E-10 dan E-11–E-13 tidak diubah sesuai evaluasi.
  - *Approver:* integrator sesi ini; reviewer session berbeda dari author.
  - *Decision:* ACCEPTED.
- [ ] **Subfitur 38.4:** Uji kejelasan teks dengan pengguna nyata.
  - *Status:* KIT SIAP, SESI BELUM DIJALANKAN (`docs/04-implementation/STOREFRONT_COPY_USABILITY_TEST.md`; `StorefrontCopyUsabilityTestIntegrityTest`). Keputusan: `COUNCIL_DECISIONS.md` #046.
  - *Batas:* Agent tidak dapat merekrut partisipan; tabel hasil berstatus BELUM DIJALANKAN. Kejelasan teks bagi pengguna masih `TIDAK TERBUKTI` sampai operator menjalankan 5 sesi.

#### 41. Fitur: Bahasa yang Benar-Benar Berfungsi
- [x] **Subfitur 41.1:** Locale berfungsi: pilihan bahasa tersimpan (sesi+profil), seluruh teks dari katalog terjemahan tanpa string keras.
  - *Status:* SELESAI SEBAGIAN (Phase 0–3 selesai di `SetLocale`, `lang/*/zafira.php`, `users.locale`, `POST /locale`, `LocalePersistenceTest`, `TranslationCatalogParityTest`; `LOCALE_AND_TRANSLATION.md`; Keputusan #048). Phase 4 migrasi seluruh teks hardcoded di luar katalog masih terbuka.
  - *Verifikasi 21 Oktober:* Migrasi admin, email, error, validasi, JavaScript, dan storefront telah mendapat cakupan tes; kartu produk dan kutipan hadiah lolos review independen. Gate penuh: 924 PHP lulus (4 skip), 304 Node lulus, build/Pint/diff-check exit 0. Browser locale desktop/mobile dan keranjang Mandarin lulus pada SQLite sementara.
  - *Inventaris akhir 21 Oktober:* Audit independen kedua (view, JS, email, PDF, controller, request/rules, routes) berstatus MET untuk teks runtime pengguna. Blocker terakhir (alasan retur, status refund, status resi, tautan verifikasi, penolakan admin) sudah dipindah ke katalog; Keputusan #050. Gate: 929 PHP lulus (4 skip), 304 Node lulus, build/Pint/diff-check exit 0.
  - *Batas:* Ringkasan checkout tersimpan di catatan concierge tetap berlabel Indonesia untuk admin; pesan CLI/backup dan exception konfigurasi tetap Indonesia (bukan UI). Invoice/PDF Mandarin dan checkout belum diuji melalui browser. Terjemahan belum ditinjau penutur asli. Insiden APP_KEY belum dituntaskan sehingga status produksi tetap belum disetujui.
  - *Task 58d3cb6c:* SELESAI LOKAL — guard email tanpa klaim diuji regresi; full PHP 939 pass/4 skip, Node 304 pass, build/Pint/diff-check EXIT 0. Closure NgodingPakeAI menunggu integrator utama.

#### 53. Fitur: Klaim Jujur Tanpa Jebakan (R-53)
- [x] **Subfitur 53.1:** Setiap klaim komersial storefront terhubung ke kebijakan yang terlihat dan didukung kode; tanpa dark pattern.
  - *Status:* SELESAI LOKAL. Task `a48904ae`. Audit tidak menemukan hitung mundur, stok terbatas, harga coret palsu, confirm-shaming, atau langganan tersembunyi. Klaim ongkir/kemasan gratis, "Care for Life", dan retur "dari halaman pesanan" dihapus; bagian `#returns-policy` (7 hari, sesuai `OrderReturn::WINDOW_DAYS`) ditautkan dari footer. Keputusan: `COUNCIL_DECISIONS.md` #052. Tes: `CommercialClaimIntegrityTest`, `tests/commercial-claims.test.mjs`.
  - *Verifikasi (run final):* `php artisan test` EXIT 0 — 950 lulus, 4 skip, 26.006 assertions; `node --test` 305/305; `npm run build`, `pint --dirty --test`, `git diff --check` EXIT 0; tes R-53 + `StorefrontShopLocaleTest` 11 lulus. Satu run perantara gagal 3 tes outbox/schema karena berkas lane lain ditulis 13:45–13:46; hijau kembali setelah berkas stabil.
  - *Batas:* Review `code-reviewer` independen TIDAK TERSEDIA (tanpa tool subagent). Retur hanya API, tanpa UI pelanggan. Ongkir fallback 0 (`config/checkout.php` tidak ada); tanpa klaim cakupan. Terjemahan belum ditinjau penutur asli. Belum diverifikasi di browser.

#### 55. Fitur: Warna & Tipografi Brand Terkunci Otomatis (R-55)
- [x] **Subfitur 55.1:** Token brand terpusat + gerbang anti-literal warna dan face asing.
  - *Status:* SELESAI LOKAL. Task `375aeae0`. Sumber token tetap blok `:root` `resources/css/app.css`; generator skill tidak dijalankan (mengubah palet). Guard `tests/brand-tokens.test.mjs` memindai `resources/{css,js,views}` dan gagal pada hex warna brand atau face di luar Bodoni Moda/Manrope/fallback yang ada. Keputusan: `COUNCIL_DECISIONS.md` #054; rincian `BRAND_TOKEN_LOCK.md`.
  - *Verifikasi (run final):* RED 17 literal nyata (exit 1); GREEN 2 tes (exit 0). `php artisan test` EXIT 0 — 956 lulus, 4 skip, 27.027 assertions; `node --test` 307/307; `npm run build`, `pint --dirty --test`, `git diff --check` EXIT 0.
  - *Batas:* Review `code-reviewer` independen TIDAK TERSEDIA. Guard hanya memindai `resources/` (bukan palet `public/prototype/app.css`); warna mendekati token, `rgb()`/`hsl()`, shorthand `font:`, dan `rgba()` opasitas belum dijaga. Tanpa verifikasi browser/piksel.

#### 54. Fitur: Teks UI Membimbing & Terlokalisasi (R-54)
- [x] **Subfitur 54.1:** Alt gambar bermakna, pesan galat dengan penyebab + langkah perbaikan, tanpa label samar, 5 locale.
  - *Status:* SELESAI LOKAL. Task `12220d08`. Fallback alt `product_alt ?: product_title` (controller, katalog, PDP); tiga pesan pemulihan ditulis ulang di `lang/*/customer_messages.php`. Keputusan: `COUNCIL_DECISIONS.md` #053; rincian `GUIDING_MICROCOPY_AUDIT.md`. Tes: `GuidingMicrocopyTest`.
  - *Verifikasi:* `php artisan test` EXIT 0 — 956 lulus, 4 skip, 27.027 assertions; `node --test` 305/305; `npm run build`, `pint --dirty --test`, `git diff --check` EXIT 0.
  - *Batas:* Review `code-reviewer` independen TIDAK TERSEDIA. Tanpa uji screen reader/browser. Terjemahan belum ditinjau penutur asli. Audit label samar terbatas pada katalog `lang/*`.

#### 39. Fitur: Cadangan & Pemulihan
- *Status R-39:* SELESAI LOKAL / BELUM TERBUKTI PRODUKSI. Feature flag, cohort, rollback, dan migration guard memiliki bukti lokal di `SAFE_RELEASE_R39.md`; tidak ada deploy produksi.
- [x] **Subfitur 39.1:** Backup terjadwal, retensi & uji restore terverifikasi.
  - *Status:* SELESAI (`DatabaseBackupService`; `db:backup --verify` harian 02:00; `db:backup-verify`; `config/backup.php`; `DatabaseBackupTest` 7 uji). Keputusan: `COUNCIL_DECISIONS.md` #043.
  - *Batas:* Uji pulih MySQL hanya checksum + isi dump; off-site & enkripsi tanggung jawab operator.

#### 40. Fitur: Tugas Rutin & Lingkungan Seragam
- [x] **Subfitur 40.1:** Tugas terjadwal & paritas konfigurasi antar-lingkungan.
  - *Status:* SELESAI DI KODE (`ScheduledTasksAndEnvParityTest`: semua literal `env()` di `config/*.php` wajib ada di `.env.example`; 6 jadwal, perintah, frekuensi, `withoutOverlapping`, dan `onOneServer` backup dikunci; `SCHEDULED_TASKS_AND_ENV_PARITY.md`). Keputusan: `COUNCIL_DECISIONS.md` #047.
  - *Batas:* Belum diverifikasi pada server staging/produksi nyata; tes tidak membaca `.env` dan tidak memeriksa cron, worker, atau secret manager aktual.

#### 35. Fitur: Satu Nama Toko
- [x] **Subfitur 35.1:** Inventaris seluruh kemunculan nama brand lama (Amadis).
  - *Status:* SELESAI (`AMADIS_LEGACY_BRAND_INVENTORY.md`; 508 berkas, nol di permukaan pelanggan). Keputusan: `COUNCIL_DECISIONS.md` #033.
  - *Batas:* Inventaris saja; penggantian dan tes regresi di subfitur berikutnya.
- [x] **Subfitur 35.2:** Ganti nama brand lama menjadi Zafira di kode & konfigurasi.
  - *Status:* SELESAI (deskripsi seeder demo → ZAFIRA; `BrandNameConsistencyTest`). Keputusan: `COUNCIL_DECISIONS.md` #035.
  - *Batas:* Nama harness internal tetap; DB demo lama perlu seed ulang.
- [x] **Subfitur 35.3:** Kunci nama brand: pemeriksaan otomatis + bersihkan dokumen metadata.
  - *Status:* SELESAI (langkah CI "Kunci nama brand"; judul `docs/demo/*` → ZAFIRA). Keputusan: `COUNCIL_DECISIONS.md` #036.
  - *Batas:* Nama harness di perintah runbook tetap.

---

## Log Siklus Kerja & Audit Tim
*(Catatan verifikasi real-world dan audit per task akan dicatat di bawah ini)*
