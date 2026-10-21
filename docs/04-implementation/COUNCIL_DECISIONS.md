# ZAFIRA — Cross-Functional Council Decisions & Minutes

Dokumen permanen pencatatan keputusan rapat pleno antar-divisi (Research, Software Architecture, Engineering, Audit, Security, Design/HCI, Testing, Secretariat).
Setiap keputusan fitur, subfitur, dan perubahan sistem wajib dibahas, disetujui, dan dipublikasikan di sini.

---

## Keputusan #054: Token Brand dan Gerbang Anti-Literal (R-55)

**Tanggal:** 2026-10-21  
**Task:** `375aeae0-252f-4373-be81-7e932a77318c` — `baseline-codebase/warna-huruf-brand-terkunci-otomatis/token-warna-tipografi-brand-terpusat`  
**Status:** SELESAI LOKAL; review independen tidak tersedia, bukan persetujuan produksi.

- **AC-R55-01:** Pakai token `:root` yang sudah ada; tidak mengubah nilai palet atau nama brand.
- **AC-R55-02:** Guard Node memindai CSS, JS, Blade di `resources/`; literal hex brand dan face asing menggagalkan CI.
- **AC-R55-03:** Allowlist email, metadata browser, dan monospace teknis dijelaskan per file; font PDF tetap untuk glyph zh/latin.
- **Keputusan:** Jangan jalankan/ubah generator skill. Ganti hanya nilai identik; pertahankan varian halaman. Tidak mengubah `package.json` karena CI sudah menjalankan Node tests.
- **Lensa audit:** konsistensi Nielsen dan beban pengenalan Krug dijaga tanpa redesain; tidak mengklaim hasil uji pengguna.
- **Bukti:** RED 17 literal nyata (`brand-red2.log`, exit 1), GREEN 2 tes. Full PHP 956 lulus/4 skip/27.027 assertions; Node 307 lulus; build, Pint dirty, diff-check exit 0.
- **Skill:** `tdd-workflow`, `ponytail`; `task-observer` dimuat pada sesi awal. Subagent reviewer tidak tersedia; browser/pixel comparison belum dilakukan.
- **Rincian dan batas:** [BRAND_TOKEN_LOCK.md](BRAND_TOKEN_LOCK.md). Cakupan tidak termasuk palet prototype `public/`; warna mendekati token, RGB/HSL, dan shorthand `font` belum dijaga.

## Keputusan #053: Teks UI Membimbing, Konsisten, dan Terlokalisasi (R-54)

**Tanggal:** 2026-10-21  
**Terkait Task:** `baseline-codebase/teks-yang-membimbing/teks-ui-membimbing-konsisten-terlokalisasi` (`12220d08-1e3a-45d9-b741-ce478a3f448a`)  
**Status Putusan:** DISETUJUI LOKAL (review independen TIDAK TERSEDIA; lihat Batas)

### Acceptance Criteria R-54
- **AC-R54-01:** Gambar produk beralt bermakna meski `product_alt` kosong; `GuidingMicrocopyTest::test_product_images_without_description_keep_a_meaningful_name`.
- **AC-R54-02:** Pesan pemulihan memuat penyebab + langkah konkret di 5 locale; `test_recovery_messages_include_the_next_action_in_every_locale`.
- **AC-R54-03:** Tidak ada label tombol/tautan samar di `lang/*`; hasil audit sumber (nol temuan).
- **AC-R54-04:** Paritas katalog terjaga; `CustomerMessagesLocaleTest` dan `TranslationCatalogParityTest` hijau dalam suite penuh.

**Skill:** `tdd-workflow`, `verification-before-completion`, `accessibility`.

### 1. Temuan
`product_alt` dirender mentah sehingga produk tanpa alt menghasilkan `alt=""`; tiga pesan pemulihan (`auth.reset_invalid`, `customer_totp.invalid_recovery`, `customer_totp.invalid`) tidak memberi langkah berikutnya. Label samar: nol temuan.

### 2. Keputusan
1. Fallback `product_alt ?: product_title` di lapisan presentasi (controller + view), bukan accessor model, agar form admin tidak menyimpan judul sebagai alt.
2. Tiga pesan ditulis ulang di 5 locale; kunci dan placeholder tidak berubah.
3. Placeholder dialog/quick-view `alt=""` dibiarkan karena JS mengisi nama produk saat dibuka.
4. Regresi dikunci TDD (RED lalu GREEN) di `tests/Feature/GuidingMicrocopyTest.php`. Rincian: `GUIDING_MICROCOPY_AUDIT.md`.

### 3. Bukti
`php artisan test` EXIT=0 (956 passed, 4 skipped, 27027 assertions); `node --test tests/*.test.mjs` 305/305; `npm run build`, `pint --dirty --test`, `git diff --check` EXIT=0.

### 4. Batas
Review `code-reviewer` independen tidak dijalankan (tanpa tool subagent). Tanpa uji screen reader/browser. Terjemahan belum ditinjau penutur asli. Audit label samar terbatas pada katalog `lang/*`. Berkas outbox/migrasi lane lain tidak disentuh.

---

## Keputusan #052: Klaim Komersial Jujur Tanpa Dark Pattern (R-53)

**Tanggal:** 2026-10-21  
**Terkait Task:** `baseline-codebase/klaim-jujur-tanpa-jebakan/klaim-jujur-bebas-dark-pattern` (`a48904ae-66c7-4571-9dd5-4aaeb1ec5544`)  
**Status Putusan:** DISETUJUI (review independen TIDAK TERSEDIA pada harness ini; lihat Batas)

### Acceptance Criteria R-53
- **AC-R53-01:** Teks manfaat komersial pada lima locale tidak menjanjikan manfaat tanpa kebijakan; diperiksa `CommercialClaimIntegrityTest` dan `commercial-claims.test.mjs`.
- **AC-R53-02:** Beranda merender kebijakan retur dan footer menautkan `#returns-policy`; diuji oleh `CommercialClaimIntegrityTest`.
- **AC-R53-03:** Jangka retur mengikuti `OrderReturn::WINDOW_DAYS`, dan surel tidak menjanjikan formulir retur yang belum tersedia; diuji oleh `CommercialClaimIntegrityTest`.
- **AC-R53-04:** Audit sumber tidak menemukan urgensi palsu, confirm-shaming, harga coret palsu, atau langganan tersembunyi; hasil terbatas pada audit sumber, bukan pembuktian browser menyeluruh.

**Skill:** `task-observer`, `laravel-development-workflow`, `tdd-workflow`, `verification-before-completion`. Review independen dilewati karena tool subagent tidak tersedia; bukan digantikan klaim persetujuan reviewer.

### 1. Temuan Audit
Audit storefront (`resources/views`, `public/js/zafira-core.js`), katalog `lang/{id,en,fr,it,zh}/shop_ui.php` dan `mail_ui.php`:
- **Tidak ditemukan** hitung mundur palsu, klaim stok terbatas, harga coret fiktif, confirm-shaming, maupun langganan tersembunyi.
- **Ditemukan** klaim tanpa dukungan kode: pengiriman/kemasan "complimentary/free", "Care for Life", garansi seumur hidup implisit (`atelier_desc`, `atelier_step_3`, `heritage_2_desc`, JS `cart_service_note`).
- **Ditemukan** klaim retur menyesatkan di surel: `mail_ui.order.status.completed.body` menyatakan retur dapat diajukan "dari halaman pesanan", padahal tidak ada UI retur di storefront (`orders.show` hanya JSON).

### 2. Keputusan
1. **Klaim tanpa dukungan dihapus, bukan diganti janji baru.** Teks diganti menjadi faktual/netral: kemasan "ilustratif; tanyakan concierge untuk opsi dan biaya"; "Care for Life" menjadi "Care guidance"; ongkir "mengikuti ringkasan checkout / ditampilkan sebelum pembayaran".
2. **Kebijakan retur dibuat terlihat.** Bagian `#returns-policy` ditambahkan di `welcome.blade.php`; footer storefront dan `partials/product-footer.blade.php` menautkannya; label footer `shipping_returns` mencantumkan "(7 hari)".
3. **Isi kebijakan mengikuti kode, bukan aspirasi.** Sumber fakta `app/Models/OrderReturn.php` (`WINDOW_DAYS`) dan `OrderReturnController`: hanya pesanan akun berstatus selesai, maksimal 7 hari, kuantitas yang sudah diajukan tidak dapat diajukan ulang kecuali ditolak, permintaan ditinjau (refund tidak otomatis), tidak ada formulir retur storefront sehingga pelanggan menghubungi concierge.
4. **Kunci katalog dipindah** dari `shop_ui.footer.returns_policy` ke `shop_ui.welcome.returns_policy` di 5 locale. Alasan: `StorefrontShopLocaleTest::test_product_page_renders_every_label_from_each_catalog` mewajibkan setiap kunci `footer` dirender di PDP, sedangkan paragraf kebijakan hanya tampil di beranda.
5. **Surel diselaraskan**: `order.status.completed.body` menunjuk concierge dan syarat retur situs, tanpa mengklaim alur mandiri yang tidak ada.
6. **Regresi dikunci TDD (RED lalu GREEN):** `tests/Feature/CommercialClaimIntegrityTest.php` dan `tests/commercial-claims.test.mjs`.

### 3. Bukti Verifikasi
Run final (`r53-final2-php.log`): `php artisan test` **EXIT=0** — 950 passed, 4 skipped (fault-injection MySQL), 26.006 assertions; `vendor/bin/pint --dirty --test` **EXIT=0**; `node --test tests/*.test.mjs` EXIT=0 (305/305); `npm run build` EXIT=0; `git diff --check` EXIT=0. Tes terarah R-53 + `StorefrontShopLocaleTest`: 11 passed, 5.538 assertions.

Catatan run perantara: satu run (`r53-final-php.log`) mencatat 3 kegagalan pada `OrderNotificationOutboxTest`/`DatabaseSchemaIntegrityTest` plus Pint gagal, karena berkas outbox/migrasi lane lain sedang ditulis pukul 13:45–13:46. Run final setelah berkas itu stabil kembali hijau. Lane R-53 tidak menyentuh berkas tersebut.

### 4. Batas & Keamanan
- **Review independen tidak tersedia:** harness sesi ini tidak menyediakan tool subagent, sehingga review `code-reviewer` oleh pihak lain TIDAK DIJALANKAN. Yang ada hanya self-review diff.
- Alur retur masih **API-only**; tidak ada UI retur pelanggan. Kebijakan menyatakan hal ini apa adanya.
- `config/checkout.php` tidak ada sehingga ongkir fallback 0; **tidak ada klaim cakupan pengiriman** yang dibuat.
- Terjemahan 5 locale belum ditinjau penutur asli; uji hanya nilai katalog.
- **Belum ada verifikasi browser** untuk bagian `#returns-policy`.
- `CheckoutService` tidak disentuh (dipegang reviewer lain).

---

## Keputusan #051: Penguncian Guard Email Notifikasi & Batas Safe Release R-39

**Tanggal:** 2026-10-21  
**Terkait Task:** `baseline-codebase/konsisten-di-semua-saluran/bahasa-konsisten-lintas-saluran-surel-notifikasi-p` (`58d3cb6c`)  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
1. **Guard Notifikasi Tanpa Email:** `CheckoutService::notifyCustomer` memvalidasi keberadaan `$email` sebelum melakukan klaim ke tabel `order_notifications`. Jika email kosong/null, tidak ada entri klaim yang dimasukkan dan tidak ada pesan diantrekan, mencegah penguncian semu status pengiriman notifikasi.
2. **Prioritas Locale Concierge:** `StorefrontController` tetap menggunakan `app()->getLocale()`, mempertahankan urutan evaluasi `SetLocale` (query > session > user). Nilai ini tidak diubah menjadi `user-first` karena permintaan concierge storefront dapat ditentukan oleh parameter eksplisit pengunjung.
3. **Pencatatan R-39:** Status rilis aman R-39 dicatat sebagai selesai di tingkat uji lokal dan drill rollback; ketiadaan deploy produksi dicatat transparan sebagai `TIDAK TERBUKTI DI PRODUKSI`.

### Batas & Keamanan
- Tidak ada rotasi `APP_KEY` tanpa koordinasi karena dapat merusak dekripsi payload tersimpan.
- Terjemahan bahasa asing diuji via catalog parity dan assert teks, bukan native speaker review.

---

## Keputusan #001: Ledger Pembayaran (`payments`) sebagai Sumber Kebenaran Status Bayar

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/konfirmasi-bayar-otomatis/ledger-pembayaran-tabel-payments-sebagai-sumber-ke` (`5ab3814b-c285-4d02-bd53-54dffc26b13d`)  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### 1. Pernyataan Masalah
Sistem saat ini mengandalkan `orders.status = 'paid'` sebagai status pembayaran. Jika ada manipulasi manual, race condition, atau kegagalan transaksi sebagian, sistem tidak memiliki mekanisme verifikasi apakah pesanan benar-benar dibayar di payment gateway.

### 2. Keputusan Rapat Pleno Antar-Divisi
1. **Model Proyeksi & Kebenaran Tunggal (Software Architecture & Research):**
   - Tabel `payments` adalah **Source of Truth** mutlak yang bersifat *append-only*.
   - Kolom `orders.status` berfungsi sebagai **Materialized Projection** yang harus selalu bersesuaian dengan ledger.
   - Tambahkan contract method pada `App\Models\Order`:
     - `hasSettledPayment(): bool` — Mengembalikan `true` hanya jika terdapat entri `payments` dengan `outcome = 'paid'` atau status settlement yang valid.
     - `reconcilePaymentStatus(): bool` — Memastikan status order selaras dengan ledger. Jika order berstatus `paid` tanpa bukti bayar di ledger, sistem menandai flag anomali.
2. **Audit dan Immutability (Audit Team):**
   - Menjaga proteksi immutability pada `App\Models\Payment` agar tidak ada riwayat pembayaran yang bisa disunting atau dihapus.
   - Tambahkan panel riwayat transaksi pembayaran pada modal detail order di Admin Dashboard agar auditor dan staf atelier dapat melihat nomor transaksi Midtrans, metode, dan nominal riil.
3. **Keamanan & Kontrol Akses (Security Team):**
   - Pertahankan larangan konfirmasi manual status `paid` oleh operator (`Order::MANUAL_BLOCKED = ['paid']`). Status hanya dapat berubah melalui webhook terverifikasi signature atau rekonsiliasi sistem berizin.
4. **Protokol Uji Nyata (Real-World Testing Team):**
   - Menguji 5 skenario ekstrem: webhook sukses, webhook duplikat, nominal tidak cocok, pesanan kedaluwarsa/batal, dan deteksi manipulasi status tanpa ledger.

### 3. Hasil Eksekusi dan Revisi Rapat
- `Order::hasSettledPayment()` dan `Order::hasPaymentAnomaly()` ditambahkan. Status lunas hanya valid jika ledger memiliki `outcome = 'paid'` dengan nominal sama dengan total pesanan.
- Rencana `reconcilePaymentStatus()` dibatalkan. Alasannya, fungsi itu akan memperbaiki status tanpa audit manusia. Deteksi anomali cukup untuk task ini; rekonsiliasi tetap masuk cakupan R-20.
- JSON detail admin (`GET /admin/orders/{id}`) kini memuat `payment.settled`, `payment.anomaly`, dan ringkasan ledger. Payload mentah dan `signature_key` tidak diekspos.
- Badge "Bukti bayar" di tabel admin dibatalkan setelah 7 test regresi gagal. Test tersebut mengunci kolom, teks kosong, dan label badge yang sudah berjalan. Tampilan tabel dipertahankan.
- Audit independen menemukan query berulang dan akses payload `null`. Keduanya diperbaiki dan diuji.

### 4. Verifikasi
- `tests/Feature/PaymentLedgerSourceOfTruthTest.php`: 7 test, 25 assertion, lulus.
- Suite penuh: 688 test, 5698 assertion, 4 skipped, lulus.
- `git diff --check`: bersih.

### 5. Batas yang Diketahui
- Refund, pembayaran parsial, dan penyesuaian total belum memiliki alur ledger. Saat fitur itu dibuat, aturan nominal harus ditinjau ulang.
- Uji end-to-end sandbox Midtrans belum dilakukan.

---

## Keputusan #002: Ketahanan Notifikasi Pembayaran Midtrans

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/konfirmasi-bayar-otomatis/ketahanan-integrasi-pembayaran-timeout-retry-dead` (`e566b7b2-d7fc-43a1-adb8-ca689fcfc6a4`)  
**Status Putusan:** DITERIMA DENGAN BATAS OPERASIONAL

### Pertimbangan lintas fungsi
- **Research / Software Architecture:** Status API tetap sumber kebenaran; ledger append-only dan transisi order tetap idempoten. Retry hanya untuk kegagalan koneksi (2 percobaan, timeout 5 detik); 5 kegagalan beruntun membuka circuit selama 1 menit.
- **Engineering / DevOps:** Webhook mengembalikan 503 agar gateway mengirim ulang. Notifikasi bertanda tangan sah yang gagal Status API disimpan sebagai dead-letter terenkripsi; scheduler memutar ulang 50 entri tiap 5 menit, maksimum 10 percobaan. Saat circuit terbuka replay ditunda. Entri selesai dihapus setelah 30 hari.
- **Security / Audit:** Simpan body asli terenkripsi karena signature harus diverifikasi ulang saat replay; batasi body 16 KiB dan ID transaksi 64 karakter. Signature gagal pada replay tidak ditutup otomatis. Order dengan dead-letter terbuka tidak dibatalkan saat reservasi kedaluwarsa. Review keamanan independen mengidentifikasi risiko denial of settlement dan retensi data; mitigasi tersebut diuji.
- **Design/HCI / IT Support:** Tidak ada perubahan antarmuka. Log critical untuk replay gagal permanen harus dipantau operator; rekonsiliasi manual tetap perlu saat gangguan melampaui batas percobaan.
- **QA / Real-World Testing:** 27 test webhook (146 assertion) dan suite 698 test (5762 assertion, 4 skipped) lulus; `git diff --check` bersih. Sandbox Midtrans dan worker/scheduler produksi belum diuji.

### Batas dan tindak lanjut
- Transaksi yang baru mengirim webhook setelah reservasi habis masih dapat tertolak; integrasi expiry dengan status gateway memerlukan uji sandbox.
- Rotasi `APP_KEY` harus mempertahankan `APP_PREVIOUS_KEYS` agar dead-letter terenkripsi dapat dibaca.
- Cache lintas worker, scheduler, log critical, dan antrean wajib dipantau di produksi. Tidak ada job queue tambahan yang tidak dipakai.

---

## Keputusan #003: Anggaran Performa HP & Gambar Responsif

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/muat-cepat-di-hp/muat-cepat-di-hp-anggaran-performa-terukur-gambar` (`53db8f22-793e-44bd-a1bc-a5e320f9cb93`)  
**Status Putusan:** DISETUJUI

### Baseline terukur
- 8 aset lokal JPG 896×1200, masing-masing 726–899 KiB; hero LCP 765 KiB dipreload penuh di HP.
- Gambar produk Unsplash disajikan satu lebar tanpa `srcset`.

### Keputusan
- **Research / Engineering:** Generate WebP `-600` (≤ 80 KiB) dan WebP penuh (≤ 170 KiB) dengan `cwebp`; JPG dipertahankan sebagai fallback. Hero HP turun 765 → 43.6 KiB (−94%).
- **Software Architecture:** Tanpa komponen baru. `<picture>` hanya untuk hero; gambar lain memakai `srcset`/`sizes` agar selektor CSS tetap berlaku. `Product::imageSrcset()` hanya membuat kandidat untuk URL CDN dengan parameter `w=`.
- **Design/HCI:** Identitas visual tidak berubah; crop `object-fit` tetap. Semua `<img>` punya `width`/`height` (anti-CLS); hanya gambar LCP memakai `fetchpriority="high"`, sisanya `loading="lazy"`.
- **QA:** `MobilePerformanceBudgetTest` menjaga anggaran ukuran file, dimensi, lazy-load, dan srcset. Suite 702 test lulus; `npm run build` sukses.

### Batas
- Belum ada pengukuran Lighthouse/CWV lapangan di perangkat nyata; anggaran dijaga di level ukuran aset dan markup.
- Unggahan admin (`/storage/...`) belum dibuat varian otomatis; disajikan apa adanya.

---

## Keputusan #004: Idempotency Checkout (Anti Double-Charge)

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/anti-pembayaran-ganda/idempotency-pembuatan-order-anti-double-charge-pad` (`8dc7f296-f636-44ce-ac85-f7d6b0ec51b3`)  
**Status Putusan:** DISETUJUI

### Temuan
- **Engineering / Architecture:** Idempotency sisi server sudah ada (unique `user_id+idempotency_key`, `guest_scope_hash+idempotency_key`, payload hash → 409, replay saat keranjang kosong). Tidak perlu komponen baru.
- **QA:** Celah ada di bukti, bukan di kode: belum ada test khusus member untuk replay stok, dan 14 test node klien gagal karena listener voucher membaca `form.voucher_code` tanpa guard.

### Keputusan
- Tambah `CheckoutIdempotencyAntiDoubleChargeTest` (member) dan test node "klik ganda → tepat 1 POST" untuk member dan tamu.
- Listener voucher memakai optional chaining agar halaman tidak gagal boot bila field tidak ada.
- **Security:** Kunci tetap dibangkitkan klien (`crypto.randomUUID`), divalidasi 8–64 karakter, dibatasi per pemilik; tidak dapat dipakai lintas akun.

### Batas
- Belum ada uji beban paralel nyata di MySQL produksi selain `MysqlCheckoutRetryTest` (dilewati tanpa MySQL).

---

## Keputusan #005: Pengingat Keranjang Tersimpan (Opt-in, Rate-limited)

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/pengingat-keranjang/pengingat-keranjang-tersimpan-opt-in-rate-limited` (`db78265e-8920-4163-85b1-1865bc42f2fd`)  
**Status Putusan:** DISETUJUI

### Keputusan
- **Research / Design (Cooper "polite software"):** Default mati; pelanggan mengaktifkan sendiri dari Profil. Hanya akun dengan email terverifikasi. Keranjang tamu tidak diingatkan (tidak ada izin kontak).
- **Engineering:** Command `cart-reminders:send` per jam. Jendela keranjang diam 24 jam–7 hari. Maksimal 1 email per minggu dan hanya bila keranjang berubah sejak pengingat terakhir. Klaim memakai `UPDATE ... WHERE` atomik agar dua worker tidak mengirim ganda. Produk nonaktif tidak disebut.
- **Security / Audit:** Tautan berhenti bertanda tangan (`signed` + throttle). GET hanya konfirmasi; perubahan lewat POST bertanda tangan agar pemindai tautan email tidak memberhentikan langganan. Header `List-Unsubscribe` disertakan. Isi email tidak memuat harga atau data alamat.
- **QA (temuan review):** Job antrean memeriksa ulang opt-in sebelum kirim. Kegagalan dispatch mengembalikan `cart_reminded_at` ke nilai sebelumnya, bukan `null`.

### Batas
- Belum ada pengukuran konversi; belum ada pengingat untuk tamu; pengiriman nyata butuh queue worker dan SMTP produksi.

---

## Keputusan #006: Standar Umpan Balik, Konfirmasi Aksi Berbahaya & Pesan Galat Manusiawi

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/umpan-balik-aksi-aman/standarkan-umpan-balik-aksi-konfirmasi-aksi-berbah` (`5298dc16-4ee4-40da-b6ec-6f05af78ef3e`)  
**Status Putusan:** DISETUJUI

### Keputusan
- **Design/HCI (Nielsen #1, #9; Shneiderman):** Satu wilayah umpan balik per halaman. Galat diumumkan `role=alert` dengan warna galat; sukses tetap `role=status` polite.
- **Design/HCI (Norman, pencegahan galat):** Aksi tak dapat diurungkan wajib dikonfirmasi: kosongkan tas, batalkan pesanan (pelanggan dan admin), hapus alamat. Batal konfirmasi mengembalikan pilihan status admin.
- **Security / Audit:** Halaman galat tidak menampilkan jejak teknis; 419 menegaskan pesanan yang sudah dibuat tidak hilang; 500 mengarahkan cek Pesanan sebelum ulang checkout (selaras anti double-charge #004).
- **QA:** Test PHP untuk halaman galat dan peta pesan, node test untuk konfirmasi admin dan peran ARIA umpan balik.

### Batas
- Undo penghapusan item keranjang belum ada; konfirmasi tetap memakai `window.confirm` bawaan peramban.

---

## Keputusan #007: Pemisahan Sistem Tampilan Konsol Admin dari Storefront

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/tampilan-khusus-konsol-admin/bangun-sistem-tampilan-konsol-admin-terpisah-dari` (`1bf75da0-08f9-4686-a1ed-cc6a4ccc0cc9`)  
**Status Putusan:** DISETUJUI

### Keputusan
- **Software Architecture / DevOps:** Tampilan konsol admin tidak lagi memuat gaya inline yang bercampur. Dibuat berkas terdedikasi `resources/css/admin.css` yang di-bundle via Vite sebagai input terpisah, dimuat hanya pada `admin.layouts.app`.
- **Design/HCI (Shneiderman, Engelbart, Cooper):** Konsol mengadopsi kelas body `admin-console` dengan tata letak kerja berdensitas informasi tinggi, kartu ringkasan metrik (`stat-grid`), panel berkonten terfokus, target sentuh kontrol minimal 44×44px, dan tabel data yang mudah dipindai dengan angka tabular.
- **Performance / Security:** Storefront publik tidak memuat `admin.css` (terverifikasi via assertion), menjaga ukuran bundel storefront tetap ramping tanpa mengekspos gaya internal admin.
- **QA:** Regresi diverifikasi di `AdminNavigationTest::test_admin_console_uses_its_own_visual_system_separate_from_storefront` dan `AdminTablesTest`.

### Batas
- Halaman login dan TOTP admin masih berbagi layout auth dasar; konsol kerja terpisah dimulai dari `admin.layouts.app`.

---

## Keputusan #008: Aksesibilitas WCAG 2.2 AA & Operasi Penuh Keyboard Konsol Admin

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/tampilan-khusus-konsol-admin/penuhi-wcag-2-2-aa-operasi-penuh-keyboard-pada-kon` (`c24bd5d8-3087-4896-b969-667a00a483b9`)  
**Status Putusan:** DISETUJUI

### Keputusan
- **Design/HCI & Aksesibilitas (WCAG 2.2 AA):**
  - **Kriteria 2.1.1 (Keyboard):** Semua elemen interaktif (tautan, tombol, input, select, summary accordion) dapat dioperasikan penuh dengan keyboard.
  - **Kriteria 2.4.7 (Focus Visible):** Cincin fokus seragam `:focus-visible` 3px menggunakan warna kontras tinggi (`--focus-ring`) dengan offset 3px.
  - **Kriteria 2.5.8 (Target Size Minimum):** Target interaktif berukuran minimal 44×44px di seluruh konsol admin.
  - **Kriteria 1.3.1 (Info and Relationships):** Tabel log autentikasi memiliki `caption` semantik dan setiap header tabel memiliki atribut `scope="col"`. Wadah gulir horizontal dapat difokus via keyboard (`tabindex="0" role="region" aria-label="..."`).
  - **Kriteria 2.3.3 (Animation from Interactions):** Mendukung preferensi gerak rendah `prefers-reduced-motion: reduce`.
- **QA:** Diverifikasi via `AdminNavigationTest::test_auth_events_table_is_keyboard_scrollable_and_semantic` dan `AdminNavigationTest::test_admin_console_has_visible_focus_and_reduced_motion`.

---

## Keputusan #009: State Machine Order Eksplisit + Audit Trail Riwayat Status (R-10, ADR-07)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/riwayat-pesanan/state-machine-order-eksplisit-order-status-history` (`421bd91b-92a8-49cc-a048-867b2fed1a44`)
**Status Putusan:** DISETUJUI

### Keputusan
- **Software Architecture:** Semua transisi status order wajib lewat `CheckoutService::transition()` / `markPaid()`; tidak ada penulisan `orders.status` langsung dari controller/model lain. Mesin dinyatakan eksplisit di `Order::STATUS_FLOW` + `canTransitionTo()`.
- **Data / Audit:** `order_status_history` append-only; model menolak update/delete. Setiap baris merekam `from_status` → `to_status`, `admin_id` (null untuk transisi sistem/webhook), `created_at`.
- **Security / Privacy:** `GET /orders/{id}` pelanggan hanya mengekspos `status_history` tanpa `admin_id`; identitas operator tetap internal.
- **QA:** Regresi ditambah `OrderStatusHistoryTest` (lifecycle penuh, transisi ilegal ditolak tanpa jejak, eksposur timeline pelanggan).

### Batas
- `paid` tetap tertutup dari operator manual; hanya webhook pembayaran tepercaya yang dapat menandai lunas.

---

## Keputusan #010: Tabel Shipments + Riwayat Pelacakan Berurutan (R-04)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/lacak-pengiriman/tabel-shipments-riwayat-pelacakan-berurutan-r-04-d` (`42df196b-f016-4038-836e-b56589e67a14`)
**Status Putusan:** DISETUJUI

### Keputusan
- **Arsitektur/Data:** Satu `shipments` per order (`hasOne`), event di `shipment_tracking_events` diurutkan `recorded_at` lalu `id` agar event kurir yang terlambat tetap tampil kronologis.
- **Engineering:** Shipment dibuat di dalam `CheckoutService::applyTransition` saat `shipped` (satu jalur transisi, tetap atomik dengan CAS); `completed` menandai `delivered`.
- **Design/HCI (Norman, Nielsen):** Status tanpa resi tidak kosong: label "Resi belum tersedia" + penjelasan manusiawi (visibility of system status).
- **Security/Privacy:** Ringkasan pengiriman hanya lewat endpoint yang sudah dibatasi pemilik (`/orders/{id}`), token tamu (`/orders/track`), atau admin.

### Batas
- Belum ada UI admin untuk input kurir/resi dan belum ada integrasi API kurir.

---

## Keputusan #011: Pengajuan Retur Tercatat (returns/return_items)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/ajukan-retur/pengajuan-retur-tercatat-returns-return-items-pili` (`cb69bc3d-a86d-40e4-af18-f5471479b7c8`)
**Status Putusan:** DISETUJUI

### Keputusan
- **Arsitektur:** Model `OrderReturn` (tabel `returns`) + `ReturnItem` (`return_items`); ajuan hanya dari order milik sendiri, dalam status `completed`.
- **Data:** Jendela retur 7 hari dihitung dari timestamp `completed` di `order_status_history` (selalu ada), bukan `delivered_at`. Kuantitas yang masih dapat diretur = kuantitas pesanan dikurangi jumlah klaim berstatus selain `rejected` (rejected membebaskan kembali).
- **Engineering:** `lockForUpdate` pada baris order mencegah klaim ganda pada kiriman bersamaan; nomor referensi `RET-YYYYMMDD-<orderId>-XXXX`.
- **Design/HCI (Shneiderman, Nielsen):** Closure lewat nomor referensi + `next_step`; error kelayakan informatif (bukan kode galat). Alasan `other` wajib catatan (error prevention).
- **Security:** Validasi kuantitas ≤ sisa; kepemilikan diperiksa di setiap endpoint.

### Batas
- Belum ada peninjauan (approve/reject) oleh admin dan belum ada alur refund.

---

## Keputusan #012: State Machine Pengembalian Dana dengan Bukti Ledger

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/status-pengembalian-dana/state-machine-pengembalian-dana-diajukan-disetujui` (`a639590b-4618-49db-8c7b-5fe9a9b282bc`)
**Status Putusan:** DISETUJUI SEBAGIAN (fail-closed)

### Keputusan
- **Arsitektur/Data:** Tabel `refunds` terpisah; `Refund::STATUS_FLOW` menegakkan `diajukan → disetujui|ditolak`, `disetujui → dana_dikirim`, `dana_dikirim → diterima`. Semua transisi lewat `RefundService` dengan `lockForUpdate`.
- **Keuangan/Audit:** Total refund non-ditolak per order tidak boleh melebihi jumlah ledger `payments` ber-outcome `paid`. Status yang menyatakan uang bergerak (`dana_dikirim`, `diterima`) wajib bukti dari penyedia dan menulis baris ledger append-only.
- **Security:** Selama belum ada API/webhook refund Midtrans yang terverifikasi, kedua status tersebut **ditolak** oleh service. Admin tidak boleh mengklaim dana terkirim hanya dengan mengetik ID (sejalan dengan #001/#002: `paid` tidak bisa diset manual).
- **Design/HCI:** Label status dalam bahasa manusia (`status_label`); penolakan wajib alasan agar pelanggan memahami keputusan.

### Batas
- Pencairan dan konfirmasi dana belum aktif; perlu integrasi refund Midtrans + uji sandbox.
- Belum ada UI admin/pelanggan untuk refund (hanya endpoint JSON).

---

## Keputusan #013: Notifikasi Pesanan Idempoten per Transisi Status

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemberitahuan-pesanan/notifikasi-pesanan-per-transisi-status-idempoten`
**Status Putusan:** DISETUJUI

### Keputusan
- **Arsitektur/Data:** Ledger `order_notifications` dengan unique key `(order_id, status, channel)` sebagai klaim idempoten. Pemicu berada di satu jalur transisi (`CheckoutService::applyTransition`), sehingga webhook ulang dan CAS gagal tidak menghasilkan email kedua.
- **Engineering/DevOps:** `OrderStatusChanged` di-queue dengan `afterCommit()` agar email tidak terkirim untuk transaksi yang rollback. Bila enqueue melempar error, klaim dihapus agar retry tetap bisa mengirim.
- **Design/HCI:** Hanya status bermakna bagi pelanggan (`paid`, `shipped`, `completed`, `cancelled`); `processing` tidak dikirim untuk mengurangi kebisingan. Pesan memakai bahasa manusia dan menyebut langkah berikutnya.

### Revisi dari code review (sama siklus)
- `RefundService::request` kini juga mengunci baris retur; `RefundController::update` memeriksa guard `admin` secara eksplisit selain middleware rute.

### Batas
- Email saja; belum ada kanal lain dan preferensi opt-out per status.

---

## Keputusan #014: Ringkasan Penjualan Berbasis Ledger

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/ringkasan-penjualan/ringkasan-penjualan-pesanan-nilai-tingkat-sukses-b`
**Status Putusan:** DISETUJUI

### Keputusan
- **Produk/Data:** Endpoint admin `GET /admin/reports/sales-summary` membandingkan periode terpilih dengan periode sebelumnya sepanjang durasi yang sama; menampilkan jumlah pesanan, pesanan berbayar, nilai pembayaran, tingkat sukses, dan persentase perubahan.
- **Keuangan/Audit:** Nilai pendapatan dan pesanan terbayar berasal dari ledger `payments` dengan `outcome=paid`, bukan status order atau total pesanan.
- **Security:** Periode memakai validasi yang sama dengan laporan order: rentang maksimal 366 hari; endpoint berada di grup admin dengan TOTP.
- **QA:** Uji periode berjalan vs periode sebelumnya dan validasi batas periode ditambahkan.

### Batas
- Nilai dihitung dari waktu pembuatan pesanan, bukan waktu pembayaran. Perbandingan memakai durasi tepat; periode kalender sebelumnya tidak dipilih otomatis.
- Belum ada UI dashboard untuk endpoint ini.

---

## Keputusan #015: Login Sosial Google & Apple (Socialite)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/daftar-masuk/pasang-laravel-socialite-alur-oauth-pelanggan-goog`
**Status Putusan:** DISETUJUI (fail-closed tanpa kredensial)

### Keputusan
- **Arsitektur:** `laravel/socialite` ^5.31 + `socialiteproviders/apple` ^6.1 (dry-run bersih, tanpa advisory). Tabel `social_accounts` (unik `provider+provider_id` dan `user_id+provider`); token OAuth tidak disimpan.
- **Security:**
  - Google memakai state sesi bawaan Socialite. Apple memakai `stateless()->cookieNonce()` karena callback `form_post` lintas situs tidak membawa cookie sesi `SameSite=lax`; route `auth/apple/callback` dikecualikan dari CSRF dan dilindungi nonce terenkripsi.
  - Akun baru hanya dibuat dari email yang diverifikasi penyedia. Email yang sudah terdaftar dengan kata sandi **tidak** ditautkan otomatis (cegah pre-account hijacking); pengguna diminta masuk dengan kata sandi.
  - Sesi diregenerasi setelah login; route di grup `guest` dengan `throttle:login`; provider dibatasi `google|apple`.
- **HCI:** Tombol "Lanjutkan dengan Google/Apple" hanya tampil bila `client_id` dan `redirect` terisi. Kegagalan/pembatalan kembali ke halaman masuk dengan pesan ramah.
- **QA:** `SocialLoginTest` 7 test (tombol tersembunyi, redirect ber-state, akun baru, akun tertaut, tolak tautan otomatis, email belum terverifikasi, state palsu).

### Batas
- Belum diuji end-to-end dengan kredensial Google/Apple asli. Apple butuh HTTPS dan kunci `.p8`.

---

## Keputusan #016: Penautan & Pelepasan Akun Sosial (`social_accounts` + `has_password`)

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/daftar-masuk/tabel-social-accounts-penautan-akun-aman-lewat-ema`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Skema & Status:** Kolom `users.has_password` (boolean, default `true`). Pengguna yang mendaftar via sosial otomatis disetel `has_password = false`. Model `User` meng-cast `has_password` ke boolean dan menetapkan default attribute `true`.
- **Penautan Akun (Auth Mode):**
  - Pengguna terautentikasi dapat menautkan akun Google dari halaman akun (`GET /akun/tautan/google`). Sesi menyimpan penanda `social_link = 'google'`.
  - Callback memvalidasi bahwa `provider_id` belum dipakai oleh akun lain. Identitas ganda lintas akun ditolak dengan galat ramah.
  - Callback tanpa intent penautan saat login tidak akan menukar user yang sedang aktif.
  - Penautan Apple dinonaktifkan dari antarmuka karena Apple mengirim callback POST lintas situs tanpa cookie sesi SameSite=lax.
- **Pelepasan Tautan (Guard Kunci):**
  - Route `DELETE /akun/tautan/{provider}` dilindungi auth.
  - **Hard invariant:** Akun dengan `has_password = false` dilarang melepas tautan sosial terakhirnya (mencegah akun terkunci permanen).
- **HCI:** Halaman akun (`/akun`) menampilkan daftar "Akun Tertaut" dengan status tertaut, tombol hapus tautan (konfirmasi native), dan tombol tautkan Google bila belum terhubung.
- **QA:** 5 test baru di `SocialLoginTest` (total 12 test): flag `has_password`, penautan dari halaman akun, penolakan identitas yang dipakai akun lain, isolasi callback saat login, dan guard tautan terakhir tanpa kata sandi. 752 PHPUnit lolos.

### Batas
- Penautan Apple belum didukung karena arsitektur callback POST lintas-situs Apple.
- UI reset/set kata sandi untuk akun `has_password=false` belum dibangun.

---

## Keputusan #017: Verifikasi Email Pendaftaran via Tautan Bertanda Tangan

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/daftar-masuk/daftar-akun-email-verifikasi-tautan-bertanda-tanga`  
**Status Putusan:** DISETUJUI (verifikasi lembut / non-blocking)

### Keputusan
- **Arsitektur:** `User` mengimplementasikan `MustVerifyEmail` (notifikasi `VerifyEmail` bawaan Laravel, tanpa tabel baru). `EmailVerificationController` menangani `verify` dan `resend`.
- **Security:**
  - Route `GET /email/verifikasi/{id}/{hash}` memakai middleware `signed` + `auth` + `throttle:6,1`; `EmailVerificationRequest` mencocokkan id pengguna dan `sha1(email)` sehingga tautan tidak bisa dipakai akun lain.
  - Tautan kadaluarsa mengikuti `auth.verification.expire`; tanda tangan rusak atau email berbeda menghasilkan 403.
  - `POST /email/verifikasi-ulang` dibatasi `throttle:6,1` dan hanya untuk akun yang belum terverifikasi.
  - Pendaftaran mengirim notifikasi di dalam `try/catch` + `report()`: kegagalan SMTP tidak boleh menggagalkan akun yang sudah dibuat.
- **Keputusan Produk (penting):** Verifikasi bersifat **non-blocking**. Pelanggan tetap bisa masuk dan checkout; verifikasi hanya menjadi gerbang untuk fitur email lanjutan (mis. pengingat keranjang yang sudah memfilter `email_verified_at`). Alasan: memblokir checkout pada maison dengan volume rendah berisiko kehilangan order lebih besar daripada risiko email palsu, dan jalur pembayaran sudah terverifikasi lewat ledger.
- **HCI:** Halaman `/akun` menampilkan banner status "Email belum terverifikasi" dengan alamat tujuan dan tombol "Kirim ulang tautan" (target sentuh ≥44px, `role="status"`).
- **QA:** `EmailVerificationTest` 5 test (tautan bertanda tangan terkirim saat daftar, verifikasi sukses, tautan dirusak/milik akun lain ditolak 403, tamu diarahkan ke login, kirim ulang hanya untuk yang belum terverifikasi). Total 757 PHPUnit + 290 node lolos.

### Batas
- Belum ada halaman khusus "verifikasi tertunda"; status dan aksi menyatu di `/akun`.
- Perubahan alamat email belum didukung (field email masih read-only), jadi belum ada alur re-verifikasi setelah ganti email.

---

## Keputusan #018: Middleware `verified` Terarah & Pesan Galat Auth yang Membimbing

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/daftar-masuk/middleware-verified-pesan-galat-yang-membimbing-pa`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Cakupan `verified`:** Hanya dipasang pada `GET /akun/tautan/{provider}` (menautkan identitas baru ke akun). Checkout, pesanan, dan retur tetap tanpa `verified` sesuai #017 (non-blocking, cegah kehilangan order).
- **`verification.notice`:** Route `/email/verifikasi-perlu` mengarahkan ke `/akun` dengan pesan langkah pemulihan (buka tautan email / kirim ulang), bukan halaman kosong.
- **Tautan kedaluwarsa/rusak:** `InvalidSignatureException` pada `verification.verify` untuk pengguna login dirender sebagai redirect ke `/akun` dengan instruksi "Kirim ulang tautan". Tautan milik akun lain / hash email salah tetap 403 (tidak dibocorkan).
- **Security (anti-enumerasi):** Pesan gagal login tetap identik untuk email terdaftar maupun tidak; ditambah petunjuk huruf besar/kecil dan jalur login Google/Apple.
- **HCI (Nielsen #9):** Pesan sukses pendaftaran menyebut alamat email tujuan tautan verifikasi.
- **QA:** `EmailVerificationTest` kini 7 test (gerbang tautan sosial, pesan login identik). 759 PHPUnit lolos.

### Batas
- Belum ada alur "lupa kata sandi"; petunjuk login belum bisa mengarahkan ke reset.

## Keputusan #019: Pemulihan Kata Sandi dengan Token Sekali Pakai

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/lupa-password/lupa-password-token-sekali-pakai-kedaluwarsa-rate`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Broker resmi Laravel:** `Password::broker('users')` mengelola token, masa berlaku, validasi, dan penghapusan setelah pemakaian. Penerbitan memakai `PasswordResetTokenIssuer` agar audit event tetap tercatat.
- **Anti-enumerasi:** Formulir email selalu menjawab bahwa tautan akan dikirim jika email terdaftar. Pengiriman email gagal secara fail-soft; kegagalan dilaporkan tanpa mengungkap keberadaan akun.
- **Rate-limit:** Broker throttle tetap berlaku; middleware `throttle:password-reset` menambah batas per email dan IP.
- **Validasi kata sandi:** Gunakan kebijakan yang sama dengan pendaftaran (minimal delapan karakter, huruf besar/kecil, angka, simbol).
- **Sesudah reset:** Token sekali pakai dihapus broker, `has_password` menjadi `true`, `remember_token` dirotasi, dan notifikasi konfirmasi dikirim.
- **QA:** `PasswordResetFlowTest` mencakup tampilan/link, respons anti-enumerasi, sukses, token salah/kedaluwarsa/terpakai, flag password, notifikasi, broker throttle, dan rate limit. 767 PHPUnit (4 skipped), 290 node, build Vite, dan `git diff --check` lolos.

### Batas
- Pengiriman notifikasi diuji dengan fake; kredensial SMTP/provider nyata dan deliverability belum diuji.

## Keputusan #020: Health Check Publik dengan Status Dependensi

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/kesehatan-sistem/health-check-health-status-db-queue-cache-storage`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Endpoint `/health`:** Menjalankan probe nyata untuk database, queue, cache (round-trip), dan storage (write/read/delete sementara). Memakai route tambahan `then` pada routing bootstrap agar endpoint berada di luar middleware `web` dan tidak membutuhkan sesi.
- **Status jujur:** Semua probe sukses menghasilkan HTTP 200; satu atau lebih gagal menghasilkan HTTP 503. Respons hanya mengembalikan status generik per dependency, tanpa pesan driver atau konfigurasi sensitif.
- **Operasional:** Respons `Cache-Control: no-store`; log kegagalan hanya menyimpan nama probe dan kelas exception. Cache dan storage meninggalkan tidak ada artefak probe pada jalur sukses.
- **QA:** `HealthCheckTest` 4 test; full suite 771 PHPUnit (4 skipped), node tests, build Vite, dan `git diff --check` lolos.

### Batas
- Probe storage memerlukan izin tulis/hapus pada disk default. Queue size adalah probe konektivitas backend, bukan bukti worker aktif.

## Keputusan #021: Audit Log Append-only & Korelasi Request-ID

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/penelusuran-insiden/audit-log-append-only-korelasi-request-id-untuk-pe`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Request-ID:** Middleware `CorrelationIdMiddleware` (dipasang sebagai prepend global) menerima header `X-Request-Id` bila aman (`8–64` karakter `[A-Za-z0-9_-]`), selain itu membuat UUID baru. ID dipasang ke `request()->attributes('request_id')`, dibagikan ke `Context` (otomatis ikut ke log dan job antrean), dan dikembalikan pada header respons.
- **Korelasi audit:** `AuthEvent.request_id` diisi dari request-id yang telah disanitasi; header mentah tidak dipercaya. Kolom baru via migrasi terpisah `2026_10_20_000001`, diindeks.
- **Append-only:** Model `AuthEvent` menolak `update` dan `delete` per baris via listener `updating`/`deleting`. Retensi tetap lewat `auth-events:prune` (hapus massal). Baris audit tidak bisa dipalsukan oleh kode aplikasi.
- **Admin UI:** Tabel log menampilkan kolom Request ID dan mendukung filter `request_id` (regex `[A-Za-z0-9_-]+`, maks 64) agar admin bisa menelusuri insiden dari ID yang dilaporkan pengguna.
- **QA:** `IncidentCorrelationTest` 8 test + 1 test filter admin; fixture `AuthEventFlowsTest` disesuaikan memakai query builder untuk backdating. 780 PHPUnit (4 skipped), 290 node, build Vite, dan `git diff --check` lolos.

### Batas
- Korelasi penuh lintas request-id di log aplikasi bergantung pada format log yang menyertakan context (default Laravel sudah menyertakannya bila channel memuatnya).
- Enumerasi request-id lewat filter admin tidak dibatasi rate (hanya admin terautentikasi + TOTP).

## Keputusan #022: RBAC Peran Admin/Staff & Policy Server-Side

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/hak-akses-peran/rbac-peran-admin-staff-pelanggan-policy-terpusat-p`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Role:** Kolom `admins.role` dibatasi aplikasi ke `admin`/`staff`; migrasi `2026_10_20_000002` memberi baris lama peran admin agar akses yang ada tidak terputus. `User` tetap merepresentasikan pelanggan melalui guard web.
- **Policy terpusat:** Gate di `AppServiceProvider` menetapkan kemampuan. `AuthorizeAdmin` mengevaluasi Gate terhadap guard admin secara eksplisit, bukan guard web default.
- **Staff:** Dapat membuka dashboard operasional, mengelola pesanan, katalog, dan concierge. Pengelolaan kategori, subscriber, refund, laporan, serta akses audit log hanya untuk admin.
- **Pencegahan privilege escalation:** Role tidak dikenal ditolak; perintah provisioning memvalidasi role dan mendukung `--role=staff`. Refund controller tidak lagi memakai pemeriksaan ad-hoc.
- **Pelanggan:** Guard admin memisahkan pelanggan dari konsol. Endpoint pesanan pelanggan telah membatasi query berdasarkan `user_id`; akses data pelanggan lain berakhir 404.
- **QA:** `AdminRoleAuthorizationTest` 4 test; full suite 784 PHPUnit (4 skipped), 290 node, Vite build, dan `git diff --check` lolos.

### Batas
- Role staff saat ini memiliki akses luas ke operasi pesanan, katalog, dan concierge; belum ada permission per staf atau UI manajemen role. Perlu diberikan role secara eksplisit saat provisioning.
- Navigation hides audit log from staff; individual dashboard actions are not yet hidden, but every mutation is rejected server-side by Gate.

## Keputusan #023: Halaman Galat Manusiawi dengan Traceable error_id

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/halaman-galat-yang-membantu/halaman-galat-dirancang-404-500-419-503-dengan-err`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Layout Terpadu:** Seluruh halaman galat (403, 404, 419, 429, 500, 503) memakai `resources/views/errors/layout.blade.php` dengan palet warna Maison (*Linen, Sapphire, Gold*), tipografi *Bodoni Moda* dan *Manrope*, serta tautan keluar (*Emergency Exit*) ke beranda dan belanja.
- **Traceable error_id:** Mengambil atribut `request_id` yang dihasilkan dan divalidasi oleh `CorrelationIdMiddleware`. Ditampilkan sebagai "Kode kejadian: <code>...</code>" agar pelanggan dapat menyalin dan melaporkannya ke concierge/dukungan tanpa mengekspos detail stack trace atau pengecualian internal.
- **Keamanan:** Bila header `X-Request-Id` yang dikirim dari klien tidak valid atau mengandung karakter berbahaya (misal XSS `<script>`), middleware menggantinya dengan UUID v4 baru. Nilai yang diecho ke HTML telah diescape via Blade `{{ $errorId }}`.
- **QA:** `HumaneErrorPagesTest` diperluas mencakup pencocokan header vs konten halaman, sanitasi payload XSS, dan rendering error 500 saat `app.debug = false`. Full suite 787 PHPUnit (4 skipped), 290 node tests, Vite build, dan `git diff --check` lolos.

### Batas
- Error ID hanya muncul jika request melewati `CorrelationIdMiddleware` (seluruh request HTTP web). Jika server web (Nginx/Apache) gagal sebelum PHP dijalankan, halaman galat fallback web server yang akan tampil.

## Keputusan #024: Threat Model STRIDE Jalur Uang & Admin + Pagar Otorisasi Berkelanjutan

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/hak-akses-peran/threat-model-stride-jalur-uang-admin-verifikasi-ot`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Dokumentasi STRIDE Terverifikasi:** Diterbitkan `docs/03-architecture/THREAT_MODEL_STRIDE.md` yang memetakan ancaman STRIDE pada aset jalur uang (checkout, pembayaran webhook Midtrans, pembatalan, retur/refund) dan konsol admin. Setiap kontrol menunjuk ke implementasi nyata (bukan rencana). Risiko sisa yang belum ditutup dicatat secara transparan (rekonsiliasi harian, sandbox refund E2E, audit log mutasi produk, admin legacy tanpa TOTP).
- **Pagar Berkelanjutan (Automated Invariant Gate):** Dibuat test inventarisasi route `tests/Feature/AuthorizationInventoryTest.php` yang mengaudit konfigurasi route aktual setiap kali test dijalankan:
  1. Setiap route `admin/*` (kecuali login/logout) wajib memiliki middleware `auth:admin`, `EnsureAdminTotp`, dan tepat satu `admin.can:<gate>`.
  2. Gate yang dipakai oleh route admin wajib terdefinisi pada `Gate::abilities()`.
  3. Route jalur uang pelanggan (`checkout.store`, `checkout.quote`, `orders.show`, `orders.cancel`, `returns.store`, `refunds.store`, `orders.track`, `webhooks.midtrans`) wajib mempertahankan middleware `auth` dan/atau `throttle` yang sesuai.
  4. Tidak ada route transisi refund di luar prefiks `admin/`.
  5. Route tulis uang publik (`checkout.store`, `orders.track`, `webhooks.midtrans`) wajib memiliki rate limit (`throttle:*`).
- **QA:** 4 test pada `AuthorizationInventoryTest` memvalidasi 104 asersi terhadap seluruh routing table. Full suite 791 PHPUnit (4 skipped), 290 node tests, build Vite, dan `git diff --check` lolos.

### Batas
- Audit route bersifat statis terhadap tabel routing runtime Laravel; tidak memverifikasi izin level database jika ada query mentah langsung tanpa melalui model ber-scope.

## Keputusan #025: Indikator Baseline & Metode Pengambilan

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/ukur-sebelum-perbaikan/tetapkan-indikator-baseline-metode-pengambilannya`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Indikator:** struktural (route 87, migrasi 43, file PHP 83, view 35, file test 97), mutu (PHPUnit 791/6.338 asersi/4 skipped; node 290), ukuran aset build (~135 KB), kesehatan `/health`, dan rasio route admin terlindungi (18/18). Definisi, metode, nilai acuan, dan ambang regresi ada di `docs/05-quality/BASELINE_INDICATORS_METHODOLOGY.md`.
- **Metode:** `scripts/benchmarks/capture_baseline.sh` menghasilkan `scripts/benchmarks/baseline_report.txt` (commit + waktu UTC) dari perintah nyata; nilai PHPUnit dibaca dari log JUnit, bukan diketik manual.
- **QA:** skrip dijalankan dua kali dan lolos `bash -n`; angka 18 route admin dicek silang dengan `route:list`.

### Batas
- Belum ada metrik performa nyata (Lighthouse perangkat asli, p95 latensi, uji beban MySQL) dan metrik bisnis (konversi). Cakupan kode belum diukur karena driver coverage tidak dipastikan tersedia.

## Keputusan #026: Preferensi & Privasi Akun (Akses, Ekspor, Hapus)

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/preferensi-privasi/preferensi-privasi-akun-akses-ekspor-hapus`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- **Ekspor:** `GET /akun/ekspor` (auth, throttle 6/menit) mengunduh JSON data milik akun itu saja: profil, alamat, pesanan + item, retur, refund, ulasan, favorit, concierge, tautan sosial, status newsletter. Tanpa hash kata sandi, token, atau kolom internal; header `no-store`.
- **Hapus:** `DELETE /akun` (auth, throttle 5/menit). Konfirmasi dengan kata sandi, atau frasa `HAPUS AKUN SAYA` bagi akun sosial tanpa kata sandi. Ditolak selama ada pesanan `pending/paid/processing/shipped`, retur `pending/approved`, atau refund `diajukan/disetujui/dana_dikirim`.
- **Retensi pembukuan:** migrasi `2026_10_20_000005` mengubah `returns.user_id` dan `refunds.user_id` menjadi nullable + `nullOnDelete` (sebelumnya cascade yang akan menghapus catatan uang). Pesanan, retur, dan refund tetap ada tanpa tautan akun. Alamat, favorit, ulasan, tautan sosial, cadangan, keranjang, sesi, dan langganan newsletter dihapus. Data kontak concierge dianonimkan.
- **Bug yang ditemukan lewat test:** `logout()` menyimpan ulang model dan menghidupkan kembali baris yang sudah dihapus. Logout kini dilakukan sebelum `delete()`.
- **UI:** blok "Privasi & Data Anda" di halaman akun, target 44px, konfirmasi sebelum hapus.
- **QA:** 7 test baru (`AccountPrivacyTest`). Full suite 798 PHPUnit (4 skipped), 290 node, build, diff check lolos.

### Batas
- Pesanan menyimpan nama/email/telepon pelanggan untuk kewajiban pembukuan dan pelacakan; ini belum dibatasi masa simpan. Perlu kebijakan retensi dari pemilik bisnis.
- Ulasan dihapus, bukan dianonimkan. Tidak ada masa tenggang atau pembatalan penghapusan. Tidak ada email konfirmasi penghapusan.
- Log audit hanya mencatat id akun (`Log::info`), belum masuk tabel `auth_events`.

## Keputusan #031: Bukti Isolasi & Persistensi Verifikasi Email

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/verifikasi-email/bukti-isolasi-verifikasi-antar-akun-persistensi-st`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Status verifikasi disimpan di kolom `users.email_verified_at`, bukan di sesi, sehingga terbaca sama di semua perangkat segera setelah login baru.
- Tautan bertanda tangan milik akun lain dan tautan dengan email berbeda ditolak `403` (`EmailVerificationRequest`); dibuktikan dalam test regresi.
- Test baru `test_verification_persists_across_devices_and_stays_isolated_per_account` membuktikan perangkat A memverifikasi, perangkat B (sesi bersih) membaca status itu, dan akun lain tetap belum terverifikasi.

### Batas
- Verifikasi tetap non-blocking (akun belum terverifikasi masih bisa berbelanja); hanya penautan sosial yang dikunci.
- Belum ada alur ubah email beserta pembatalan status verifikasi lama.

---

## Keputusan #032: Pipeline CI (Lint, Test, Validasi Migrasi)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemeriksaan-otomatis/bangun-pipeline-ci-lint-test-validasi-migrasi`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Pipeline `.github/workflows/ci.yml` berjalan pada push ke `main` dan setiap PR: `pint --test` (lint), `migrate --force` ke SQLite sementara (validasi migrasi), `php artisan test` (802 tes), dan `node --test` (299 tes) setelah `npm run build`.
- Kode diformat `pint` agar lint hijau sejak commit pertama; perubahan bersifat style-only.
- `concurrency: cancel-in-progress` menghindari antrean CI yang menumpuk pada satu ref.

### Batas
- CI hanya memvalidasi migrasi (fresh), bukan menjalankan test terhadap MySQL/PG sungguhan; coverage tidak dihitung.
- Tidak ada gerbang rilis/`deploy` di CI; penerapan tetap manual.

---

## Keputusan #033: Inventaris Nama Brand Lama (Amadis)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/satu-nama-toko/inventaris-seluruh-kemunculan-nama-brand-lama-amad`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Inventaris lengkap dicatat di `docs/04-implementation/AMADIS_LEGACY_BRAND_INVENTORY.md` dengan enam kelas (A–F).
- Permukaan produk (views, routes, controller, model, mail, `.env.example`) terverifikasi bebas "Amadis".
- Bukti historis di `docs/execution/**` tidak ditulis ulang; nama harness isolasi internal tidak diganti pada task ini.

### Batas
- Belum ada tes regresi otomatis; direncanakan pada task penyeragaman berikutnya.

---

## Keputusan #034: Uji Aksesibilitas & Validasi Skema di CI

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemeriksaan-otomatis/tambahkan-uji-aksesibilitas-storefront-admin-valid`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `StorefrontA11yMarkupTest` memeriksa halaman HTML publik dan akun: `lang`, `<title>`, tepat satu `<main>`, `<h1>`, `alt` pada gambar, nama aksesibel pada tautan/tombol, label terprogram pada field, dan galat login yang terhubung ke field.
- `AdminA11yLandmarkTest` menerapkan kontrak yang sama pada halaman HTML admin (login, dasbor, auth events).
- `DatabaseSchemaIntegrityTest` memastikan setiap model Eloquent punya tabel, primary key, kolom `$fillable`/`$casts`, dan kolom timestamp (menghormati `UPDATED_AT = null` pada tabel append-only).
- Temuan nyata diperbaiki: field email/kata sandi login pelanggan kini memakai `aria-invalid` dan `aria-describedby` ke pesan galat per field (setara admin).
- CI menjalankan langkah terpisah "Uji Aksesibilitas & Skema" sebelum suite penuh.

### Batas
- Pemeriksaan statis dari HTML terender, bukan axe/browser: kontras warna, urutan fokus, dan ukuran target tidak diuji.
- `cart.show`, `orders.index`, dan `admin.orders.index` adalah endpoint JSON; hanya kontrak JSON-nya yang diuji.

---

## Keputusan #035: Penggantian Nama Brand Lama menjadi ZAFIRA di Kode & Konfigurasi

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/satu-nama-toko/ganti-nama-brand-lama-menjadi-zafira-di-kode-konfi`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Satu-satunya teks "AMADIS" yang dapat tampil di UI (deskripsi produk seeder demo) diganti menjadi "ZAFIRA".
- `BrandNameConsistencyTest` memindai views, routes, `app/Http`, `app/Mail`, `app/Notifications`, `public`, dan `.env.example`; build gagal bila "amadis" muncul kembali.
- Nama harness isolasi (`AMADIS_*`, `config/amadis.php`, `scripts/amadis/*`, kelas `AmadisDemoSeeder`, slug `amadis-demo-*`) dipertahankan: diganti berarti memutus guard *fail closed*, 24 tes Python, dan jejak bukti historis.

### Batas
- Data demo yang sudah ter-seed di basis data lama tetap memuat teks lama sampai di-seed ulang.
- Suite Python `tests/python` memiliki 8 failure + 8 error yang sudah ada sebelum perubahan ini (terverifikasi dengan seeder HEAD); tidak dijalankan di CI.

---

## Keputusan #036: Kunci Nama Brand di CI & Pembersihan Metadata Dokumen

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/satu-nama-toko/kunci-nama-brand-pemeriksaan-otomatis-bersihkan-do`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- CI menjalankan langkah "Kunci nama brand" (`BrandNameConsistencyTest`) sebelum suite penuh.
- Judul/metadata dokumen `docs/demo/*` memakai satu nama "ZAFIRA" (sebelumnya "ZAFIRA AMADIS"); tes baru menolak nama ganda kembali.
- Perintah harness (`scripts/amadis/*`, `AMADIS_ROOT`) di runbook tetap, karena itu nama teknis yang dijalankan. `docs/execution/**` tidak diubah (bukti historis).

### Batas
- Dokumen di luar `docs/demo` masih dapat menyebut "Amadis" sebagai nama harness; ini disengaja.

---

## Keputusan #037: CI Syarat Wajib Gabung & Penyeragaman Lingkungan

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemeriksaan-otomatis/jadikan-ci-syarat-wajib-gabung-lingkungan-seragam`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `.nvmrc` menyematkan Node 22 LTS; `package.json` memuat `engines.node: ">=22"`; `.github/workflows/ci.yml` memakai `node-version-file: .nvmrc`.
- PHP disematkan di `composer.json` (`^8.4.1`) dan CI (`8.4`); `README.md` diperbarui mencerminkan PHP 8.4+ dan Laravel 13 (sebelumnya tercantum 8.2+ dan Laravel 12).
- `scripts/ci/protect-main.sh` disediakan untuk mengaktifkan branch protection GitHub (`required_status_checks: ["php"]`, menolak force-push dan deletion). Dijalankan oleh maintainer setelah commit pertama didorong dan CI lulus minimal sekali (GitHub API menolak konteks check yang belum pernah mengirim status).
- `EnvironmentUniformityTest` mengunci ketiga penyematan ini.

### Batas
- Branch protection belum diterapkan ke remote GitHub karena remote masih di commit `7fff6fe` (belum ada push baru, belum ada status check 'php'). Dijalankan pasca-push.

---

## Keputusan #038: Gerbang Anti-Drift Skema DB vs Migrasi & Hentikan getcat

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemeriksaan-otomatis/gerbang-anti-drift-skema-db-vs-migrasi-hentikan-ak`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- CI menambah langkah "Gerbang anti-drift skema": setelah `migrate --force` di SQLite, `migrate:status` harus bebas baris `Pending`, selain itu build gagal.
- `DatabaseSchemaIntegrityTest` menambah dua asersi: (1) semua file migrasi di `database/migrations/*` sudah diterapkan di skema uji; (2) `app/**/*.php` dilarang memanggil procedure `getcat` (`CALL/EXEC getcat`) — akses kategori hanya lewat `Category::getById()`.
- Migrasi forward baru `2026_10_20_000030_drop_legacy_getcat_procedure.php` menghapus procedure `getcat` di MySQL/MariaDB; `down()` membuatnya kembali agar reversibel.

### Batas
- Rollback migrasi lama di SQLite (mis. drop kolom `role` di `admins`) sudah gagal sebelum perubahan ini; tidak diperbaiki karena di luar cakupan (produksi memakai MySQL, forward-only).
- `getcat` tetap ada di `database/migrations/*` sebagai artefak historis dan di `docs/execution/**`; yang dihentikan adalah akses runtime aplikasi.

---

## Keputusan #039: Pemindaian Keamanan (Rahasia, Dependensi, PII)

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/pemindaian-keamanan/pemindaian-keamanan-rahasia-tak-bocor-audit-depend`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- CI menjalankan `composer audit --locked`, `npm audit --audit-level=high`, pemindaian pola rahasia pada file terlacak (`git grep`), dan pemindaian pola PII pada panggilan `Log::*` di `app/`.
- `npm audit fix` dijalankan: `shell-quote` 1.9.0→1.12.0 (kritis), `source-map-js` 1.2.1→1.2.2 (tinggi), `concurrently` 10.0.5→10.0.6; hasil 0 kerentanan, `npm run build` lulus. Composer: tidak ada advisory.
- `SecurityScanIntegrityTest` menjalankan pemindaian rahasia dan PII yang sama di tes lokal, mengabaikan `vendor/`, `node_modules/`, `.env*`, `docs/execution/**` (bukti historis), `tests/python/` (sentinel), serta `.agents/.claude/.codex` (aset skill pihak ketiga).
- Tidak ada rahasia, kunci pribadi, atau token terlacak ditemukan. `.env`, `.env.production`, dan dump SQL lokal sudah di `.gitignore`.

### Batas
- Pemindai berbasis regex pola umum, bukan pengganti gitleaks/trufflehog; tidak memeriksa riwayat git.
- Pemindai PII hanya menangkap `Log::*` satu baris; log multi-baris dan `logger()` tidak tercakup.
- Token NgodingPakeAI sempat terpapar di chat: rotasi tetap disarankan.

---

## Keputusan #040: Matriks Nada Bicara per Konteks Permukaan

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/gaya-bahasa-yang-mengikat/tetapkan-matriks-nada-bicara-per-konteks-permukaan`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `docs/02-product/VOICE_AND_TONE_MATRIX.md` menetapkan 4 pilar suara dan nada untuk 6 permukaan: storefront, checkout, notifikasi transaksional, concierge, konsol admin, dan pesan galat. Dokumen ini juga memuat glosarium istilah wajib dan dilarang.
- `VoiceAndToneConsistencyTest` menolak frasa terlarang di `resources/views/**/*.blade.php` dan memastikan dokumen matriks ada.
- Pemindaian `lang/`, `resources/`, dan `public/js` tidak menemukan frasa terlarang; tidak ada salinan yang perlu diubah.

### Batas
- Tes hanya mencocokkan daftar frasa terlarang, bukan menilai nada secara semantik; tinjauan manusia tetap wajib.
- Salinan di JS/lang belum tercakup tes otomatis (sudah diperiksa manual, bersih).
- Matriks ini belum divalidasi dengan riset pengguna nyata.

---

## Keputusan #041: Jadikan Panduan Gaya Bahasa Gerbang Wajib

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/gaya-bahasa-yang-mengikat/jadikan-panduan-gaya-bahasa-gerbang-wajib-sebelum`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `VoiceAndToneConsistencyTest` kini memindai Blade, bahasa (`lang/`), JavaScript sumber (`resources/js`), dan JavaScript publik (`public/js`) untuk frasa terlarang; permukaan yang belum ada ikut otomatis saat dibuat.
- CI menjalankan test sebagai langkah "Gerbang gaya bahasa".
- `.github/pull_request_template.md` meminta pemeriksaan matriks dan test untuk PR yang mengubah teks.
- Gate diuji negatif: menambahkan sementara `banting harga` ke `resources/js/search-match.js` membuat test gagal; file dipulihkan lalu suite kembali lulus (825 passed).

### Batas
- Hanya daftar frasa eksplisit, bukan pemeriksaan semantik/nuansa. Reviewer tetap wajib membaca matriks.

---

## Keputusan #042: Inventarisasi Seluruh Teks Permukaan Toko

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/inventarisasi-seluruh-teks-permukaan-toko`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `docs/04-implementation/STOREFRONT_TEXT_INVENTORY.md` memetakan 39 berkas Blade ke 6 klaster (storefront, akun, admin, surel, galat, parsial) dengan teks kunci verbatim (title/h1/h2/tombol/subjek surel).
- `StorefrontTextInventoryIntegrityTest` memastikan setiap berkas Blade tercatat dan dokumen tetap sinkron (gagal bila berkas baru tidak didokumentasikan).
- Inventarisasi mencatat temuan perbaikan: title beranda "Flagship v3.9" (label internal), tombol "Buat pesanan pending" (istilah teknis), campuran Prancis–Indonesia, tombol generik "Lihat"/"Hapus".

### Batas
- Inventarisasi adalah peta, bukan perbaikan teks; perbaikan ditangani task berikutnya di halaman "Perbaikan Seluruh Teks Toko".

---

## Keputusan #043: Cadangan Terjadwal, Retensi & Uji Pulih Terverifikasi

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/cadangan-pemulihan/cadangan-pemulihan-backup-terjadwal-retensi-uji-re`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `App\Services\DatabaseBackupService`: cadangan SQLite via `VACUUM INTO`, MySQL/MariaDB via `mysqldump --single-transaction` (sandi lewat `MYSQL_PWD`, tidak di argumen proses). Hasil dikompresi gzip, izin 0600, disertai berkas `.sha256`.
- Perintah `php artisan db:backup [--verify]` dan `php artisan db:backup-verify [file]`; dijadwalkan harian 02:00 (`withoutOverlapping`, `onOneServer`).
- Retensi: simpan `BACKUP_KEEP` (bawaan 14) cadangan terbaru per koneksi; lokasi `BACKUP_PATH` (bawaan `storage/app/backups`).
- Uji pulih: checksum wajib cocok; SQLite dipulihkan ke berkas sementara lalu jumlah baris tiap tabel dibandingkan dengan sumber. Basis data produksi tidak disentuh.
- `DatabaseBackupTest` (7 uji): checksum, pulih cocok, cadangan dirusak ditolak, sumber berubah terdeteksi, retensi, perintah, jadwal.

### Batas
- Uji pulih MySQL/MariaDB belum didukung: `verify()` menolak dengan `RuntimeException` (fail-closed, 2026-10-21) agar tidak melaporkan "uji pulih lulus" palsu. Cadangan tetap dibuat; `db:backup --verify` keluar gagal sebelum retensi.
- Penjadwalan harian 02:00 di `routes/console.php` secara otomatis menyematkan flag `--verify` hanya ketika driver basis data bawaan adalah `sqlite`. Untuk koneksi non-SQLite (MySQL/MariaDB), penjadwal menjalankan `db:backup` biasa tanpa klaim verifikasi agar retensi tetap berjalan bersih tanpa galat harian.
- Verifikasi pemulihan MySQL/MariaDB sesungguhnya tetap menjadi batasan rilis (memerlukan instans basis data sementara/kontainer terpisah untuk eksekusi impor dump dan perbandingan baris); sistem tidak pernah secara diam-diam menandai MySQL terverifikasi.
- `DatabaseBackupTest` kini 14 kasus uji (termasuk penolakan dump MySQL, kegagalan perintah dengan `--verify`, jadwal terisolasi per driver `sqlite`/`mysql`/`mariadb`, dan retensi cadangan MySQL tanpa verifikasi).
- Cadangan disimpan di disk lokal; salinan off-site (S3/rsync) dan enkripsi at-rest menjadi tanggung jawab operator.
- Belum ada alarm bila cadangan gagal selain log scheduler.

---

## Keputusan #044: Evaluasi Teks Toko dengan Tiga Kacamata

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/nilai-tiap-teks-dengan-tiga-kacamata-hci-marketing`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `docs/04-implementation/STOREFRONT_TEXT_EVALUATION.md` menilai 13 temuan (E-01–E-13) dengan lensa HCI, marketing, dan brand; skor dampak 1–5.
- Skor 5 (wajib diperbaiki): label versi "Flagship v3.9" di title beranda, istilah "pending" di checkout, istilah "token" di pelacakan tamu.
- Skor 4: "Tas pembelian standar" vs "Tas Belanja", path "(/akun)" di tombol portal.
- Teladan dipertahankan: tagline footer, penutup concierge, judul halaman galat.
- Review silang independen mengoreksi kutipan baris, pemilih bahasa, dan cakupan (`catalog/labels.php`, pesan JS).
- `StorefrontTextEvaluationIntegrityTest` memastikan E-01–E-13 tercatat dan setiap berkas yang dirujuk ada.

### Batas
- Penilaian ahli internal, belum diuji dengan pengguna nyata. Perbaikan teks dikerjakan task berikutnya.

---

## Keputusan #045: Penulisan Ulang Teks Permukaan Toko & Pelurusan Istilah

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/tulis-ulang-teks-dan-simpan-daftar-sebelum-sesudah`
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Menulis ulang teks permukaan toko sesuai temuan E-01 s/d E-09 di `docs/04-implementation/STOREFRONT_TEXT_REWRITE_LOG.md`:
  - `welcome.blade.php`: Hapus label versi `v3.9` dari title beranda; ganti `Buka Portal Klien Lengkap (/akun)` menjadi `Buka Ruang Klien`; beri `<span lang="fr">` pada teks beraksen Prancis pita.
  - `shop/index.blade.php`: Ganti status teknis mentah `pending` dan `token` pelacakan tamu dengan bahasa pelanggan (`menunggu pembayaran`, `kode pelacakan`); nyatakan batas pembayaran secara jujur tanpa janji konfirmasi otomatis; fallback status tak dikenal diubah menjadi "Status belum tersedia" agar nilai teknis baru tidak bocor.
  - Penyeragaman istilah: `Tas pembelian standar` / `Pembelian standar` diganti menjadi `Tas Belanja` dan `Pembelian katalog` di seluruh tampilan terkait serta toast personalisasi dinamis di `resources/js/app.js`.
  - Dukungan i18n: Selaraskan kunci `profile_full_portal` di 4 bahasa pada `public/js/zafira-core.js` agar penggantian bahasa tidak menimpa kembali label baru.
- Review independen (sesi `ses_ea6c3c89affetUTDvf7eO9KgVl`) mengidentifikasi celah penimpaan i18n, inkonsistensi toast JS, dan potensi kebocoran fallback status; seluruh temuan telah diselesaikan dan diverifikasi.
- Regresi dikawal lewat `tests/storefront-copy.test.mjs`, `tests/shop-cart-load.test.mjs`, dan `UnifiedPurchaseCartTest`.

### Batas
- Belum diuji langsung dengan pengguna nyata di perangkat berbeda.
- Pembayaran online tetap tidak tersedia (alur toko tetap berorientasi concierge/katalog).
- Kamus multibahasa selain kunci yang diperbaiki tidak diubah.

---

## Keputusan #046: Kit Uji Kejelasan Teks Toko dengan Pengguna Nyata

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/perbaikan-seluruh-teks-toko/uji-kejelasan-teks-dengan-pengguna-nyata`
**Status Putusan:** DISETUJUI — KIT SIAP, SESI BELUM DIJALANKAN

### Keputusan
- `docs/04-implementation/STOREFRONT_COPY_USABILITY_TEST.md` menyediakan kit uji siap jalan: tujuan per frasa E-01–E-09, kriteria 5 partisipan (3 klien VIP, 2 tamu pertama kali), persetujuan & privasi, naskah moderator, 5 skenario, 5 pertanyaan pemahaman dengan kriteria lulus ≥4/5, tabel hasil kosong, dan aturan keputusan.
- Agent tidak merekrut pengguna dan tidak mengarang hasil. Tabel hasil berstatus **BELUM DIJALANKAN** sampai operator manusia menjalankan sesi.
- `StorefrontCopyUsabilityTestIntegrityTest` memastikan dokumen ada, menyebut E-01–E-09, memuat status BELUM DIJALANKAN, dan setiap frasa "Sesudah" yang diuji benar-benar ada di `resources/views`, `resources/js`, atau `public/js`.

### Batas
- Sesi uji dengan pengguna nyata **belum dijalankan**; kejelasan teks masih `TIDAK TERBUKTI` dari sisi pengguna.
- E-10 tidak diuji karena tidak diubah.

---

## Keputusan #047: Tugas Terjadwal & Paritas Konfigurasi Antar-Lingkungan

**Tanggal:** 2026-10-20
**Terkait Task:** `baseline-codebase/tugas-rutin-lingkungan-seragam/tugas-terjadwal-paritas-konfigurasi-antar-lingkung`
**Status Putusan:** DISETUJUI — TERVERIFIKASI LOKAL

### Keputusan
- `ScheduledTasksAndEnvParityTest` menggagalkan CI bila `config/*.php` memakai kunci `env()` literal yang tidak tercatat di `.env.example`.
- Tes yang sama mengunci 6 perintah terjadwal, keberadaan perintahnya, frekuensi, `withoutOverlapping`, dan `onOneServer` untuk backup.
- `SCHEDULED_TASKS_AND_ENV_PARITY.md` mendokumentasikan cron `schedule:run`, queue worker, dan prosedur paritas lokal/staging/produksi tanpa secret nyata.

### Batas
- Belum diverifikasi pada server staging/produksi nyata.
- Kunci internal `AMADIS_*` dicatat sebagai komentar; `BrandNameConsistencyTest` hanya mengecualikan baris kunci tersebut. Paritas hanya memeriksa nama kunci, bukan nilai, `.env`, atau secret manager.

---

## Keputusan #048: Locale katalog dan penyimpanan pilihan bahasa

**Tanggal:** 2026-10-21  
**Status:** DISETUJUI — Phase 0–3 selesai, Phase 4 terbuka

### Keputusan
- Default dan fallback locale adalah `id`; request memakai urutan query, session, profil pengguna, lalu default.
- Katalog memakai file `lang/{locale}/zafira.php` dengan whitelist locale dan tes paritas key.
- Pilihan pengguna login disimpan pada session dan kolom nullable `users.locale`; endpoint dilindungi CSRF dan throttle.

### Batas
- Teks hardcoded di admin, email, error, validasi, JavaScript, checkout, akun, dan permukaan storefront non-katalog belum dimigrasikan.

---

## Keputusan #049: Locale dokumen cetak admin (laporan PDF & slip pesanan)

**Tanggal:** 2026-10-21  
**Terkait Task:** `6632663a-34b3-4fda-a868-25d56ae7eafa` (locale penuh)  
**Status:** DISETUJUI

### Masalah
Judul, baris, dan status laporan PDF sudah diterjemahkan, tetapi footer Dompdf masih mencetak `Halaman {PAGE_NUM} dari {PAGE_COUNT}` di semua bahasa. Tanggal slip pesanan memakai `d M Y H:i` sehingga nama bulan selalu Inggris.

### Keputusan
- **Engineering:** `PdfDocumentService::render()` menerima parameter ketiga `$pagination` dengan default `id`; placeholder Dompdf (`{PAGE_NUM}`, `{PAGE_COUNT}`) dipertahankan apa adanya karena diisi oleh canvas, bukan oleh `trans()`. `AdminOrderController` mengirim `trans('admin_ui.report.pagination')`.
- **Product/Design:** Tanggal slip memakai format numerik `Y-m-d H:i`, bukan `translatedFormat`. Urutan ISO menghindari ambiguitas hari/bulan serta nama bulan Inggris.
- **QA:** Katalog lima locale menambah `report.pagination`; paritas key dan placeholder dijaga `AdminResidualLocaleTest`. Bukti PDF nyata ditambahkan di `AdminOrderReportTest::test_report_pdf_pagination_and_title_are_localized_in_real_pdf` (ekstraksi teks PDFKit, lima locale, memastikan pagination `id` tidak muncul di locale lain).

### Verifikasi
- `AdminOrderReportTest` + `AdminResidualLocaleTest`: 28 test, 640 assertion, lulus.
- `vendor/bin/pint --test`: lulus.

### Batas
- Ekstraksi teks PDF hanya berjalan di macOS (PDFKit via `swiftc`); di CI lain test tersebut di-skip.
- Tampilan cetak (print preview browser, ukuran kertas non-A4) belum diverifikasi manual.
- Mata uang tetap `Rp` dengan format `1.234.567` di semua locale; belum ada lokalisasi mata uang.

---

## Keputusan #050: Label status domain dari katalog dan batas teks tersimpan

**Tanggal:** 2026-10-21  
**Terkait Task:** `6632663a-34b3-4fda-a868-25d56ae7eafa` (locale penuh)  
**Status:** DISETUJUI

### Masalah
Audit inventaris akhir menemukan label alasan retur, status refund, status/penjelasan resi, pesan tautan verifikasi kedaluwarsa, dan penolakan akses admin masih berupa literal Indonesia di model, `bootstrap/app.php`, dan middleware.

### Keputusan
- **Engineering:** Kode tersimpan dan aturan validasi tidak berubah (`OrderReturn::REASONS` tetap sumber kode; status refund/pengiriman tetap string yang sama). Label dibaca saat runtime lewat `OrderReturn::reasons()`, `Refund::statusLabel()`, dan `customer_messages.*`. Konstanta publik `Refund::STATUS_LABELS` dan `Shipment::AWB_PENDING_EXPLANATION` dihapus setelah grep membuktikan tidak ada pemanggil lain.
- **Product:** Catatan checkout concierge, catatan anonimisasi akun, nama bawaan `Pelanggan`, pesan CLI/backup, dan exception konfigurasi bukan UI aktif; dicatat sebagai batas, bukan blocker.
- **QA:** `RemainingLocaleBlockersTest` menguji JSON endpoint terautentikasi dengan `?locale=` untuk lima locale, nilai non-`id` berbeda dari `id`, substitusi nomor resi, dan fallback kode tak dikenal.

### Verifikasi
- Review independen: WARN (dua MEDIUM, satu LOW pada kekuatan tes); semua ditutup oleh tes tambahan.
- Audit inventaris ulang: MET untuk teks runtime pengguna.
- Gate penuh: 929 PHP lulus, 4 skip; 304 Node lulus; build, Pint, `git diff --check` exit 0.

### Batas
- Ringkasan checkout yang disimpan dalam catatan concierge tetap berlabel Indonesia untuk admin.
- Terjemahan `en/fr/it/zh` belum ditinjau penutur asli.

---

## Keputusan #030: Tombol Penyedia Sosial Storefront & Jaminan Absen di Admin

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/masuk-dengan-google-apple/tombol-penyedia-di-storefront-jaminan-absen-di-kon`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Tombol sosial di storefront (`resources/views/auth/_social.blade.php`) memenuhi standar WCAG 2.2 AA (target sentuh 50px, label eksplisit, ikon inline dengan `aria-hidden="true"`).
- Konsol admin secara mutlak tidak menampilkan tombol atau jalur login sosial (ADR-14); diuji secara otomatis lewat regression test di `SocialLoginTest`.
- Penyedia yang tidak aktif (`client_id` kosong) disembunyikan otomatis tanpa elemen kosong.

### Batas
- Logo Apple/Google disajikan dalam SVG monokromatik terpadu; brand-guidelines warna penuh masing-masing penyedia tidak dimuat dari CDN luar untuk privasi.

---

## Keputusan #029: TOTP Opsional untuk Pelanggan

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/akun-keamanan/tawarkan-totp-opsional-untuk-pelanggan-tanpa-memak`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- TOTP bersifat opt-in dari halaman Akun; tidak pernah dipaksa. Akun tanpa TOTP tetap masuk satu langkah.
- Login kata sandi (`Auth::validate`) dan login sosial menunda sesi sampai kode kedua benar (tantangan berlaku 5 menit, maksimal 5 percobaan).
- Rahasia terenkripsi di DB; rahasia pendaftaran hanya di sesi terenkripsi sampai kode terkonfirmasi. 8 kode cadangan di-hash, sekali pakai, ditampilkan sekali.
- Anti-replay (CAS pada `totp_last_used_at`); mematikan TOTP wajib kode valid sehingga sesi curian tidak bisa melepas lapisan kedua.

### Batas
- Belum ada pemulihan bila perangkat dan kode cadangan hilang (perlu bantuan admin, belum ada UI).
- Event audit auth untuk TOTP pelanggan belum dicatat; tidak ada regenerasi kode cadangan.

---

## Keputusan #028: Pembanding Baseline vs Kondisi Terkini

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/ukur-sebelum-perbaikan/siapkan-pembanding-baseline-vs-kondisi-terkini`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- Tambah `scripts/benchmarks/compare_baseline.py` sebagai pembanding dua snapshot eksplisit atau snapshot tertua-vs-terbaru.
- Laporan mencakup indikator struktural, jumlah/assertion/durasi/skips/failures PHPUnit, serta jumlah/lolos/gagal Node.
- Keluar kode 1 bila kegagalan bertambah atau jumlah test turun; jika hanya ada satu snapshot, laporkan kebutuhan baseline kedua tanpa gagal.

### QA
- Self-check dengan satu snapshot selesai; pesan kebutuhan snapshot kedua ditampilkan dan exit 0.
- Perbandingan dua snapshot riil belum dapat dibuktikan sampai snapshot berikutnya tersedia.

## Keputusan #027: Baseline Metrik Bertanggal

**Tanggal:** 2026-10-20  
**Terkait Task:** `baseline-codebase/ukur-sebelum-perbaikan/ambil-simpan-baseline-metrik-bertanggal`  
**Status Putusan:** DISETUJUI SECARA BULAT (UNANIMOUS)

### Keputusan
- `capture_baseline.sh` kini menjalankan ulang PHPUnit dan node test (bukan membaca junit lama), mencatat commit + jumlah berkas belum di-commit, dan menyimpan snapshot bertanggal di `docs/05-quality/baselines/` plus salinan terkini di `scripts/benchmarks/baseline_report.txt`.
- Snapshot pertama: 89 rute, 44 migrasi, 98 berkas test, 798 PHPUnit (0 gagal, 4 skipped, 46.8s), 290 node lolos, aset build 135.574 byte.

### Batas
- Belum ada metrik cakupan, Lighthouse perangkat nyata, atau beban MySQL.
