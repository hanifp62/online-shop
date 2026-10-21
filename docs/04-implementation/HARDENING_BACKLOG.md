# Backlog Hardening ZAFIRA

**Tanggal pemeriksaan:** 2026-10-19. **Status:** usulan, bukan izin implementasi.

## Tambahan audit statis 2026-10-19 (belum dipromosikan menjadi task)

- **Data/query:** audit `ses_ead07b06fffet0NMDGaXGA3I4K` menemukan N+1 pada `StorefrontController.php:161–163`; query tanpa batas di beranda dan sitemap; potensi race limit voucher per pengguna (`CheckoutService.php:283–308`) dan default address (`AddressController.php:59–67`). Daftar index FK/komposit masih hipotesis performa: ukur `EXPLAIN` pada engine dan dataset representatif sebelum DDL. Tidak ada benchmark atau tes konkurensi.
- **Harga/tanggal:** audit `ses_ead06a8b2ffeAw0Nl3yssGZaXZ` menguatkan HB-002: admin menerima pecahan dan nilai yang melampaui kapasitas `DECIMAL(10,2)`, sedangkan checkout menerima rupiah utuh. Tanggal admin belum seragam dalam memberi label UTC. Tidak ada bukti pembayaran nominal berbeda.
- **Gambar/storage:** audit `ses_ead06a8b1ffeLaOWV5LHrciy53` tidak menemukan endpoint upload atau SSRF; validasi `product_image` pada `AdminProductController.php:21,65` hanya string 1000 karakter. Skema URL/host berpotensi memicu mixed content dan permintaan browser ke host pelacak; pilih kebijakan host sesuai aset nyata sebelum membatasi.
- **Parity storefront/prototype:** audit `ses_ead094177ffea0tw3pWbS3gI4P` menemukan kurs EUR/USD tetap di `public/js/zafira-core.js:1514–1528`, sementara prototype menampilkan IDR. Kontrak mata uang harus diperiksa sebelum menyamakan formatter; pola ARIA radiogroup dan toggle bahasa berbeda, efek browser belum diuji.
- **Inventaris kode:** audit `ses_ead06a8b1ffdKcw9MQNtOyKhE4` tidak menemukan route tanpa handler. Komponen Blade dan file prototype yang tampak tanpa referensi langsung ternyata digunakan. Jangan menghapus artefak root tak terlacak atau aset build tanpa konfirmasi asal dan rantai pemuatan.
- **Config/PDF:** audit sebelumnya melaporkan proxy trust kosong bisa menyatukan bucket rate limit TOTP di balik proxy; report/invoice berisi PII tanpa audit unduhan. Header host/HSTS dan batas waktu PDF perlu diputuskan sesuai konfigurasi deploy aktual. Tidak ada verifikasi edge/runtime.

Semua butir di atas **temuan audit statis**, bukan pekerjaan R-44, dan tidak memberi izin mengubah skema, autentikasi, retensi, atau konfigurasi produksi.

## Cakupan dan batas bukti

Dokumen ini memeriksa seluruh 15 baris **Backlog di luar R-44** dan empat butir **Temuan audit yang ditunda** dalam [R44_SESSION_LEDGER.md](R44_SESSION_LEDGER.md) (baris 62 saat diperiksa). Tidak ada perubahan aplikasi, tes yang dijalankan, migrasi, server, browser, pembacaan `.env`, atau perintah NgodingPakeAI dalam pekerjaan ini. Nomor baris merujuk source saat diperiksa, bukan skema atau konfigurasi deployment aktif.

Klasifikasi:
- **TERBUKTI KODE:** kondisi atau ketiadaan implementasi terbukti pada source. Bukan bukti insiden, eksploitasi, atau besaran dampak runtime.
- **SUDAH DIPERBAIKI:** source terbaru mengatasi klaim lama; keberhasilan tes tetap belum diverifikasi ulang.
- **SALAH:** ditolak sebagai defect yang telah terbukti. Untuk klaim runtime yang belum diuji, label ini tidak berarti kebalikannya sudah terbukti; batas tersebut disebutkan secara eksplisit.

Severity menilai dampak potensial, bukan insiden teramati. Urutan: Tinggi → Sedang → Rendah; dependensi dalam setiap kelompok dicantumkan. Semua tes di bawah merupakan **rencana RED**, belum ditulis atau dijalankan. Usulan file baru belum ada kecuali disebut sebagai perluasan tes yang sudah tersedia.

Requirement mengacu pada [REQUIREMENTS_TRACEABILITY.md](../02-product/REQUIREMENTS_TRACEABILITY.md) (baris 34 saat diperiksa). Gunakan ID dan lingkupnya, bukan status historis dokumen tersebut: klaim lama “tidak ada tabel Order” sudah tidak menggambarkan source terkini.

## Matriks seluruh temuan ledger

| Sumber ledger | Temuan | Klasifikasi | Tujuan |
|---|---|---|---|
| :95 | Harga pecahan admin berbeda dengan checkout | TERBUKTI KODE | HB-002 |
| :96 | Reset/ubah password customer belum tersedia | TERBUKTI KODE | HB-004 |
| :97 | Password admin non-TOTP tidak mencabut sesi lain | TERBUKTI KODE | HB-003 |
| :98 | Slug null saat update dan race create | TERBUKTI KODE | HB-006 |
| :99 | Transisi concierge tanpa CAS | TERBUKTI KODE | HB-007 |
| :100 | Limiter concierge hanya per IP | TERBUKTI KODE | HB-008 |
| :101 | Facet menyertakan produk nonaktif | TERBUKTI KODE | HB-009 |
| :102 | CSP tanpa endpoint laporan; HSTS tidak di aplikasi | TERBUKTI KODE | HB-010; edge belum diperiksa |
| :103 | Tidak ada script npm test dan konfigurasi CI yang ditemukan | TERBUKTI KODE | HB-011; CI eksternal belum diketahui |
| :104 | FR-014 bcrypt 12 tidak terbukti, menunjuk PHPUnit | SALAH | Koreksi K-01; bukti source mendukung default 12, produksi belum diketahui |
| :105 | Cascade user → orders/items/reviews | TERBUKTI KODE | HB-001 |
| :106 | CHECK status/rating tidak didefinisikan | TERBUKTI KODE | HB-005 |
| :107 | Empat indeks komposit tidak didefinisikan | TERBUKTI KODE | HB-012; severity diturunkan, performa belum diukur |
| :108 | Backup ikut cascade user | TERBUKTI KODE | HB-013 |
| :109 | Rollback carts hanya memeriksa carts | SUDAH DIPERBAIKI | Koreksi K-02 |
| :64 | Wildcard tujuh tabel admin tidak di-escape | TERBUKTI KODE | HB-014 |
| :65 | Skeleton dan tombol coba lagi belum ada | TERBUKTI KODE | HB-015; R-45 |
| :66 | Pemilihan massal hanya pesanan | TERBUKTI KODE | HB-016; keputusan cakupan, bukan defect R-44 |
| :67 | Overflow filter pada 360px | SALAH | Koreksi K-03; belum terbukti, bukan dinyatakan bebas overflow |

**Rekap:** 16 TERBUKTI KODE, 1 SUDAH DIPERBAIKI, 2 SALAH sebagai defect terbukti. HB-016 adalah usulan fitur, bukan perbaikan wajib.

## Antrean prioritas Tinggi

### HB-001 — Lindungi riwayat transaksi dari cascade penghapusan user

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi, risiko kehilangan data finansial pada penghapusan user.
- **Requirement:** NFR-003; kebijakan retensi/anonymisasi: **requirement baru diperlukan**.
- **Bukti:** `database/migrations/2026_10_12_000003_create_orders_table.php:13` menetapkan cascade user; `database/migrations/2026_10_12_000004_create_order_items_table.php:13` cascade order; `database/migrations/2026_10_12_000006_create_reviews_table.php:13–15` cascade order/user. `database/migrations/2026_10_19_000001_add_guest_checkout_to_orders_table.php:13` membuat user nullable tanpa penggantian eksplisit aksi FK. Tidak ditemukan endpoint hapus user; ini risiko skema, bukan aksi HTTP yang terbukti dapat dijangkau.
- **Fix minimal:** putuskan retensi order dan review secara terpisah. Setelah disetujui, ganti FK user dengan SET NULL atau RESTRICT sesuai kebijakan; jangan menghapus cascade item→order tanpa keputusan tersendiri. Pertahankan snapshot transaksi.
- **File usulan:** migrasi baru `database/migrations/*_preserve_order_history_on_user_deletion.php`; `tests/Feature/OrderHistoryRetentionTest.php`. Model/view hanya jika kebijakan menghasilkan relasi nullable yang belum ditangani.
- **RED pertama:** `OrderHistoryRetentionTest.php`: buat user, order, item, review; hapus user; assert order/item tetap ada dan review mengikuti kebijakan tertulis. Jangan mengasumsikan review wajib disimpan sebelum keputusan privasi.
- **Risiko migrasi:** tinggi; FK/DDL dapat mengunci tabel, tipe/nullability harus cocok, data yang sudah terhapus tidak dipulihkan oleh migrasi. Verifikasi skema aktif, backup, dan pemulihan sebelum deploy.
- **Persetujuan:** **WAJIB eksplisit pengguna**, perubahan delete cascade produksi. Dependensi: keputusan retensi, lalu HB-013 memakai keputusan yang konsisten.

### HB-002 — Selaraskan harga produk dengan rupiah bulat checkout

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi, produk tersimpan dapat ditolak checkout.
- **Requirement:** FR-011; aturan harga rupiah bulat yang eksplisit: **requirement baru diperlukan**.
- **Bukti:** `app/Http/Controllers/AdminProductController.php:19,63` menerima numeric nonnegatif tanpa melarang pecahan; `app/Services/CheckoutService.php:73–79` menolak pecahan IDR.
- **Fix minimal:** validasi nilai rupiah bulat pada create/update tanpa pembulatan diam-diam. Pertahankan kompatibilitas representasi decimal berakhiran `.00`; gunakan kontrak parsing checkout sebagai acuan, bukan float.
- **File usulan:** `app/Http/Controllers/AdminProductController.php`; perluasan `tests/Feature/AdminManagementTest.php`. Input harga view hanya bila masih mengiklankan pecahan.
- **RED pertama:** `AdminManagementTest.php`: kirim harga `125000.50` ke create dan update; assert validation error `product_price`, jumlah/baris lama tidak berubah. Tambahkan penerimaan `125000` dan `125000.00`, lalu kompatibilitas checkout.
- **Risiko migrasi:** tidak perlu migrasi untuk validasi; inventaris harga pecahan lama diperlukan sebelum penyesuaian data. Jangan membulatkan massal.
- **Persetujuan:** persetujuan aturan harga sebelum implementasi; koreksi data produksi memerlukan izin tersendiri. Dependensi: tidak ada.

### HB-003 — Cabut sesi admin lain setelah perubahan password

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi, sesi aktif lama tidak memiliki pemeriksaan pencabutan terkait perubahan password.
- **Requirement:** FR-014, NFR-002; kebijakan sesi lintas perangkat: **requirement baru diperlukan**.
- **Bukti:** `app/Http/Controllers/AdminDashboardController.php:197–220` mengganti password/remember token, tetapi logout/regenerasi hanya pada cabang TOTP dan tidak mencabut perangkat lain. `bootstrap/app.php:15–28` tidak menambahkan pemeriksaan auth-session; pencarian source aplikasi tidak menemukan `auth.session`/`logoutOtherDevices`. `tests/Feature/AdminTotpTest.php:480–489` hanya mengunci keberlanjutan sesi legacy dan rotasi token. `tests/Feature/SessionHardeningTest.php:31–45` menguji rotasi saat login, bukan pencabutan sesi kedua setelah perubahan password.
- **Fix minimal:** tentukan apakah sesi pengubah dipertahankan; tambahkan pemeriksaan perubahan hash/sesi khusus guard admin dengan mekanisme framework yang sesuai. Jangan menghapus sesi web pelanggan yang berbagi cookie. Rotasi remember token saja tidak mencabut sesi yang sudah aktif.
- **File usulan:** `app/Http/Controllers/AdminDashboardController.php`, `routes/web.php` dan/atau middleware admin yang sesuai; perluasan `tests/Feature/SessionHardeningTest.php`, `tests/Feature/AdminTotpTest.php`.
- **RED pertama:** `SessionHardeningTest.php`: dua session ID admin non-TOTP nyata; A mengganti password, B berikutnya ditolak `/admin`; sesi customer tetap dapat membuka `/akun`. Ulangi untuk TOTP, ingat perangkat, dan perilaku A yang disetujui. Gunakan driver sesi persisten terisolasi, bukan sekadar `actingAs`.
- **Risiko migrasi:** tidak otomatis perlu skema; perubahan dapat mengeluarkan admin atau merusak dua guard bila implementasi salah.
- **Persetujuan:** **WAJIB eksplisit pengguna**, perubahan perilaku auth/session. Dependensi: kebijakan sesi sebelum menyesuaikan tes legacy; jangan melemahkan tes dua guard.

### HB-004 — Sediakan pemulihan dan perubahan password customer

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi sebagai celah kemampuan pemulihan akun, bukan bypass keamanan yang terbukti.
- **Requirement:** FR-007; detail reset, pengiriman, dan sesi: **requirement baru diperlukan**.
- **Bukti:** `routes/web.php:117–150` tidak mendefinisikan reset/change password customer; `app/Http/Controllers/ProfileController.php:30–37` tidak menerima password. Ketidakhadiran fitur tidak berarti route admin dapat dipakai customer.
- **Fix minimal:** gunakan password broker Laravel untuk reset customer dan endpoint authenticated terpisah untuk perubahan dengan current password. Tetapkan respons antienumerasi, token sekali pakai/kedaluwarsa, throttle, dan kebijakan sesi. Jangan membuat sistem token baru.
- **File usulan:** `routes/web.php`; controller customer reset/change password baru; view customer terkait; `config/auth.php` hanya jika broker perlu penyesuaian; `tests/Feature/CustomerPasswordRecoveryTest.php`, `tests/Feature/CustomerPasswordChangeTest.php`.
- **RED pertama:** `CustomerPasswordRecoveryTest.php`: request reset customer menghasilkan notifikasi broker; token valid mengubah hash, token bekas/kedaluwarsa ditolak, email tidak dikenal mendapat respons seragam. `CustomerPasswordChangeTest.php`: password lama salah ditolak; perubahan valid mengikuti kebijakan sesi yang disetujui, tanpa mengubah guard admin.
- **Risiko migrasi:** periksa tabel token broker yang tersedia sebelum menambah migrasi; jangan membuat ulang tabel. Risiko pengiriman email dan token bocor memerlukan review keamanan.
- **Persetujuan:** **WAJIB eksplisit pengguna**, fitur auth/session baru. Dependensi: kontrak sesi dari HB-003 dapat menjadi acuan, tetapi implementasi customer tidak boleh menyalin pencabutan global dua guard.

### HB-005 — Tambahkan pertahanan integritas status dan rating di database

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi untuk integritas data; belum terbukti ada data invalid.
- **Requirement:** NFR-003, FR-013; batas rating: **requirement baru diperlukan** bila belum dinyatakan sebagai aturan bisnis tersendiri.
- **Bukti:** kolom status tanpa CHECK di `database/migrations/2026_10_12_000003_create_orders_table.php:14` dan `database/migrations/2026_08_31_000000_create_concierge_requests_table.php:20`; rating di `database/migrations/2026_10_12_000006_create_reviews_table.php:16`. Tidak ditemukan migrasi susulan yang menambahkan CHECK tersebut. Validasi aplikasi bukan constraint DB.
- **Fix minimal:** inventaris data invalid dan daftar status resmi dari model; tambahkan CHECK domain status dan rating 1–5 melalui migrasi baru. CHECK tidak menggantikan CAS atau memaksakan urutan transisi.
- **File usulan:** `database/migrations/*_add_order_concierge_review_checks.php`; `tests/Feature/DatabaseDomainConstraintsTest.php`.
- **RED pertama:** `DatabaseDomainConstraintsTest.php`: insert/update langsung status di luar domain atau rating 0/6 ditolak DB; nilai sah tetap diterima. Jalankan kelak pada engine/versi target produksi, bukan hanya SQLite.
- **Risiko migrasi:** tinggi; data lama invalid menggagalkan pemasangan; dukungan/enforcement CHECK bergantung engine/versi. Tidak boleh membuang atau menormalkan data tanpa keputusan.
- **Persetujuan:** **WAJIB eksplisit pengguna**, constraint produksi. Dependensi: domain status disahkan; dapat dipisah per tabel agar rollout kecil. HB-007 tetap perlu.

## Tambahan hardening penutupan R44 (2026-10-19)

Sumber: laporan integrator; hasil pengujian dan efektivitas perbaikan **TIDAK TERBUKTI** dalam sesi dokumentasi ini. Entri HB-017–HB-020 adalah usulan, bukan izin implementasi. Semua skenario RED berikut belum dijalankan.

### HB-017 — Batasi argumen launcher artisan ke allowlist dan perketat regex direktori root

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi untuk keamanan launcher QA; berpotensi eksekusi subperintah arbitrer di luar alur pengujian.
- **Requirement:** NFR-002, mandat keamanan harness Amadis.
- **Bukti:** `scripts/amadis/launch.py:736–739` meneruskan argumen `artisan + args[1:]` tanpa memvalidasi terhadap allowlist perintah yang disetujui; `scripts/amadis/guard.php:9–12` dan `scripts/amadis/launch.py:625–633` hanya memeriksa apakah nama direktori root diawali dengan `amadis-`, bukan pola ketat `^amadis-[a-f0-9]{24}$`.
- **Fix minimal:** tambahkan allowlist eksplisit untuk perintah artisan yang diizinkan (misalnya `amadis:provision`, `amadis:cache-config`, `migrate`, `db:seed`, `test`, `amadis:lifecycle-snapshot`) pada `scripts/amadis/launch.py`; perketat validasi format nama root di `guard.php` dan `launch.py` menggunakan regex exact match `^amadis-[a-f0-9]{24}$`.
- **File usulan:** `scripts/amadis/launch.py`, `scripts/amadis/guard.php`; `tests/Feature/AmadisLauncherGuardTest.php` atau tes mandiri verifikasi launcher.
- **RED pertama:** jalankan `launch.sh artisan key:generate` atau perintah di luar allowlist dan pastikan ditolak dengan pesan error yang jelas; jalankan dengan direktori `amadis-arbitrary` atau path traversal dan pastikan ditolak oleh guard.
- **Risiko migrasi:** tidak memerlukan migrasi database; dapat menghentikan script QA yang memanggil perintah artisan tak terdaftar bila allowlist terlalu sempit.
- **Persetujuan:** **WAJIB eksplisit pengguna** bila menyangkut boundary eksekusi dan keamanan harness. Dependensi: tidak ada.

### HB-018 — Cegah hard-link dan manipulasi file kredensial via O_NOFOLLOW dan verifikasi fstat

- **Klasifikasi / severity:** TERBUKTI KODE / Tinggi; risiko eksfiltrasi kredensial uji atau penulisan kredensial ke file di luar target yang terisolasi.
- **Requirement:** NFR-002, integritas kredensial dan zero secret leakage.
- **Bukti:** `scripts/amadis/artisan.php:37–40` tidak melakukan verifikasi penolakan hard-link (`nlink == 1`), mode file, atau flag `O_NOFOLLOW` saat memuat dan menulis kredensial/file runtime terisolasi; review fixture `amadis:r44-fixtures` mencatat NO-GO sebagian karena ketiadaan perlindungan ini.
- **Fix minimal:** terapkan pembukaan file kredensial/runtime dengan flag `O_NOFOLLOW`, verifikasi menggunakan `fstat` bahwa target adalah file reguler (`S_ISREG`), dan pastikan `st_nlink == 1` sebelum operasi baca/tulis di `scripts/amadis/artisan.php`.
- **File usulan:** `scripts/amadis/artisan.php`; tes verifikasi izin file di `scripts/amadis/guard.php` atau test suite pendukung.
- **RED pertama:** tes pembacaan file bertaut hard-link (`st_nlink > 1`) atau symlink; pastikan ditolak seketika dengan error keamanan sebelum konten disentuh.
- **Risiko migrasi:** tidak perlu migrasi DB; dapat menggagalkan lingkungan yang sengaja menggunakan hard-link untuk sharing fixture bila tidak dirancang benar.
- **Persetujuan:** **WAJIB eksplisit pengguna** untuk penanganan file kredensial dan secret storage. Dependensi: perbaikan path secret pada HB-017 / fixture audit.

### HB-019 — Tetapkan target sentuh minimum 44×44px eksplisit pada .admin-nav a dan .admin-work-nav a

- **Klasifikasi / severity:** TERBUKTI KODE (statis) / Sedang; potensi pelanggaran WCAG 2.2 Target Size (Minimum) 2.5.8 (Level AA).
- **Requirement:** NFR-005, panduan Dewan HCI (Ben Shneiderman & Jakob Nielsen) terkait target sentuh aksesibel.
- **Bukti:** penelusuran CSS/Blade menemukan selektor tautan navigasi admin `.admin-nav a` dan `.admin-work-nav a` tidak memiliki deklarasi `min-height: 44px`, `min-width: 44px`, atau padding yang menjamin area sentuh efektif mencapai 44×44 piksel pada seluruh viewport. Ukuran nyata di browser: **TIDAK TERBUKTI** (belum diukur dengan Playwright/CDP).
- **Fix minimal:** tambahkan `min-height: 44px`, `display: inline-flex`/`flex`, dan `align-items: center` dengan padding yang memadai pada CSS navigasi admin tanpa merusak hierarki visual dan tata letak desktop.
- **File usulan:** `resources/css/app.css` (atau file CSS komponen admin yang relevan); `resources/views/admin/dashboard.blade.php`.
- **RED pertama:** tes Playwright visual/bounding box yang memeriksa `boundingBox()` dari setiap elemen `.admin-nav a` dan `.admin-work-nav a` menghasilkan tinggi dan lebar ≥ 44 piksel.
- **Risiko migrasi:** tidak ada risiko data; risiko regresi visual atau pergeseran tata letak (layout shift) pada sidebar/header admin.
- **Persetujuan:** tidak memerlukan izin khusus keamanan/auth; verifikasi visual dan aksesibilitas wajib dilakukan. Dependensi: tidak ada.

### HB-020 — Putuskan gerbang Prettier yang tidak reproducible

- **Klasifikasi / severity:** TERBUKTI KODE / Rendah–Sedang; gerbang format tidak dapat direproduksi dari checkout bersih.
- **Requirement:** NFR-005, hanya bila proyek memang memilih format gate repo.
- **Bukti:** `command -v prettier` **EXIT=1**. `npx --no-install prettier --version` **EXIT=0**, versi `3.9.10`, kemungkinan berasal dari cache; sumbernya **TIDAK TERBUKTI**. `package.json` dan `package-lock.json` tidak memuat `prettier`; binary lokal, konfigurasi, dan script format juga tidak ada.
- **Fix minimal:** jika proyek menginginkan format gate repo, pin/install Prettier, konfigurasi, dan script secara lokal. Jika tidak, hapus klaim bahwa Prettier adalah gate wajib.
- **File usulan:** hanya bila keputusan format gate disetujui: `package.json`, `package-lock.json`, konfigurasi Prettier.
- **Persetujuan:** keputusan tim diperlukan sebelum menambah dependency atau menghapus klaim gate. Belum ada implementasi atau verifikasi ulang.

## Antrean prioritas Sedang

### HB-006 — Pertahankan slug dan tangani benturan create secara aman

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang.
- **Requirement:** FR-011.
- **Bukti:** `app/Http/Controllers/AdminProductController.php:64,79–80` mengizinkan dan menyimpan null; `:35–44,52` memeriksa slug sebelum insert, menyisakan race. `database/migrations/2026_09_21_000002_add_unique_to_product_slug.php:17–18` sudah memiliki unique: risiko race adalah insert gagal, **bukan** duplikasi slug tersimpan.
- **Fix minimal:** tolak slug null/kosong saat update atau pertahankan slug lama sesuai kontrak yang disetujui; create slug otomatis menggunakan retry terbatas hanya untuk konflik unique slug. Konflik slug eksplisit menjadi validation error. Jangan menelan exception DB lain.
- **File usulan:** `app/Http/Controllers/AdminProductController.php`; `tests/Feature/AdminProductSlugTest.php`.
- **RED pertama:** `AdminProductSlugTest.php`: update null tidak menghapus slug; dua create judul sama yang melewati pemeriksaan sebelum insert tetap menghasilkan dua slug berbeda tanpa 500. Harness konkurensi harus memakai koneksi/proses terpisah; dua request serial saja tidak membuktikan race tertutup.
- **Risiko migrasi:** tidak diperlukan untuk fix aplikasi; inventaris slug null lama terpisah, tanpa mengganti URL massal.
- **Persetujuan:** keputusan kontrak update diperlukan; tidak ada gate auth/cascade. Dependensi: tidak ada.

### HB-007 — Jadikan transisi concierge atomik

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang.
- **Requirement:** FR-013.
- **Bukti:** `app/Http/Controllers/AdminDashboardController.php:174–180` memvalidasi model yang sudah dibaca, lalu update tanpa syarat status lama. Contoh CAS tersedia di `app/Services/CheckoutService.php:326–329`.
- **Fix minimal:** update berdasarkan ID dan status awal, periksa affected rows; balas konflik ramah saat status berubah. Jangan melaporkan sukses bila nol baris berubah.
- **File usulan:** `app/Http/Controllers/AdminDashboardController.php`; `tests/Feature/ConciergeTransitionConcurrencyTest.php`.
- **RED pertama:** `ConciergeTransitionConcurrencyTest.php`: A dan B membaca status sama; A commit; B mencoba transisi dari snapshot lama; assert B mendapat konflik, status A tidak tertimpa. Satu tes memakai model stale deterministik; konkurensi DB target menyusul.
- **Risiko migrasi:** tidak perlu; kontrak respons konflik perlu selaras UI.
- **Persetujuan:** tidak memerlukan gate khusus auth/skema; sepakati respons konflik. Dependensi: tidak perlu menunggu HB-005, tetapi daftar status harus konsisten.

### HB-008 — Evaluasi limiter concierge lintas IP tanpa mengorbankan NAT bersama

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang; IP-only terbukti, bypass/volume serangan belum diuji.
- **Requirement:** NFR-002, FR-005; ambang dan identitas: **requirement baru diperlukan**.
- **Bukti:** `routes/web.php:71` menggunakan tiga request per jam berdasarkan IP; `routes/web.php:99–101` memasang limiter pada route concierge.
- **Fix minimal:** pertahankan batas IP, tambahkan batas akun untuk user login. Untuk tamu, putuskan kebijakan terlebih dahulu; email tidak terverifikasi tidak boleh menjadi satu-satunya identitas atau alat memblokir korban. Jangan menyebut throttle sebagai autentikasi.
- **File usulan:** `routes/web.php`; perluasan `tests/Feature/RescueBackendAuditTest.php` atau `tests/Feature/ConciergeRateLimitTest.php`.
- **RED pertama:** `ConciergeRateLimitTest.php`: akun sama melampaui ambang dari IP berbeda mendapat 429; batas IP lintas akun tetap bekerja; kasus tamu mengikuti kebijakan yang disahkan.
- **Risiko migrasi:** tidak perlu; risiko false positive, NAT bersama, proxy dan cache store harus diperiksa saat verifikasi deployment.
- **Persetujuan:** setujui ambang/kebijakan penyalahgunaan sebelum perubahan; bila mengubah auth/session, **wajib izin eksplisit**. Dependensi: keputusan identitas tamu.

### HB-009 — Batasi facet warna/material ke produk aktif

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang.
- **Requirement:** FR-002.
- **Bukti:** `app/Services/CatalogService.php:41–42` mengambil semua produk; query hasil `:52` sudah mensyaratkan `is_active=true`.
- **Fix minimal:** tambahkan predicate aktif pada dua query facet. Jangan memperluas menjadi facet dinamis menurut seluruh filter tanpa requirement.
- **File usulan:** `app/Services/CatalogService.php`; perluasan `tests/Feature/CatalogAdvancedFiltersTest.php`.
- **RED pertama:** `CatalogAdvancedFiltersTest.php`: warna/material unik milik produk nonaktif tidak muncul di opsi; nilai produk aktif tetap muncul. Fixture harus menggunakan nilai unik agar sumber lain tidak menyamarkan kegagalan.
- **Risiko migrasi:** tidak perlu; periksa cache katalog bila ada agar hasil lama tidak tertinggal.
- **Persetujuan:** tidak perlu gate khusus. Dependensi: tidak ada.

### HB-010 — Tetapkan pelaporan CSP dan pemilik HSTS

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang untuk celah konfigurasi aplikasi, bukan bukti header deployment tidak aman.
- **Requirement:** NFR-002.
- **Bukti:** `app/Http/Middleware/SecurityHeaders.php:21–26` memasang CSP report-only secara kondisional; `:31–51` sengaja belum memasang endpoint laporan dan tidak memasang HSTS. CSP report-only **tidak** memblokir resource. HSTS dapat dipasang reverse proxy; konfigurasi edge aktif belum tersedia.
- **Fix minimal:** verifikasi pemilik HSTS lebih dahulu; pasang pada satu lapisan HTTPS yang disepakati. Bila pelaporan CSP dibutuhkan, sediakan endpoint terbatas, validasi payload, throttle, dan redaksi URL/data sensitif sebelum mengaktifkan arahan pelaporan. Jangan mengubah menjadi CSP enforce pada slice ini.
- **File usulan:** `app/Http/Middleware/SecurityHeaders.php`, `config/security.php`; endpoint/rute laporan hanya setelah disetujui; perluasan `tests/Feature/CspReportOnlyTest.php`, `tests/Feature/SecurityHeadersTest.php`; konfigurasi edge hanya jika tersedia dan ditetapkan sebagai pemilik.
- **RED pertama:** `CspReportOnlyTest.php`: saat pelaporan diaktifkan, header menunjuk endpoint yang benar, payload invalid ditolak, payload berlebih dibatasi, data sensitif tidak tersimpan. `SecurityHeadersTest.php`: HSTS ada pada HTTPS sesuai kontrak; bila edge pemiliknya, assertion harus menjadi pemeriksaan deployment, bukan tes PHP palsu.
- **Risiko migrasi:** tidak perlu; HSTS menetap di browser, `includeSubDomains`/preload dapat memutus subdomain non-HTTPS. Laporan CSP memiliki risiko privasi.
- **Persetujuan:** **wajib persetujuan kebijakan HTTPS/retensi laporan** sebelum aktivasi; jangan mengaktifkan preload otomatis. Dependensi: inventaris TLS/proxy dan endpoint laporan.

### HB-011 — Sediakan pintu masuk tes dan CI yang memakai suite nyata

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang, celah otomasi repo.
- **Requirement:** **requirement baru diperlukan** untuk gate CI; NFR-005 hanya kaitan kompatibilitas lingkungan, bukan mandat CI.
- **Bukti:** `package.json:5–8` hanya memiliki script dev/build. Tidak ditemukan konfigurasi GitHub Actions, GitLab CI, Jenkins, Azure Pipelines, atau CircleCI pada penelusuran repo. Ini tidak membuktikan CI eksternal tidak ada. Suite Node nyata tersedia, misalnya `tests/admin-table.test.mjs:1`.
- **Fix minimal:** tambahkan script tes yang memanggil runner Node bawaan pada suite yang cocok; pilih CI sesuai remote/platform dan kontrak database pengujian proyek. Pakai perintah PHP/build yang sudah tersedia; jangan menambah framework tes baru.
- **File usulan:** `package.json`; satu konfigurasi CI sesuai platform yang dikonfirmasi; `tests/Unit/CiEntrypointsTest.php` bila diperlukan untuk kontrak script.
- **RED pertama:** `CiEntrypointsTest.php`: assert script `test` tersedia dan menunjuk suite nyata, bukan no-op. Setelah izin eksekusi, gate integrasi harus gagal pada assertion sengaja rusak dan meneruskan exit nonzero runner. Tes statis tidak membuktikan CI berjalan.
- **Risiko migrasi:** tidak perlu; konfigurasi CI harus memakai database terisolasi dan tidak mewarisi kredensial produksi.
- **Persetujuan:** konfirmasi platform CI dan izin layanan/secrets; jangan membuat atau mengunggah secret. Dependensi: kontrak database tes sebelum eksekusi CI.

### HB-012 — Buktikan kebutuhan empat indeks komposit sebelum DDL

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang untuk ketiadaan definisi; severity Tinggi performa pada ledger **tidak terdukung pengukuran**.
- **Requirement:** FR-010 untuk daftar admin; NFR-001 hanya relevan query storefront. Target performa admin: **requirement baru diperlukan**.
- **Bukti:** `database/migrations/2026_10_12_000003_create_orders_table.php:14` dan `database/migrations/2026_08_31_000000_create_concierge_requests_table.php:20` tidak mendefinisikan pasangan status/created_at; empat komposit berikut tidak ditemukan dalam migrasi: `orders(status,created_at)`, `concierge_requests(status,created_at)`, `products(is_active,stock)`, `products(product_cat,is_active)`. Query terkait: `app/Http/Controllers/AdminDashboardController.php:77–82,122–123,170`; `app/Services/CatalogService.php:52,61`. Indeks tunggal/FK tidak sama dengan empat komposit itu; skema live belum diperiksa.
- **Fix minimal:** ambil EXPLAIN dan baseline pada engine/data representatif setelah izin. Promosikan hanya indeks yang memperbaiki query nyata; urutan kolom, selectivity, sorting, dan indeks yang sudah ada harus diperhitungkan. Jangan menjanjikan empat indeks sekaligus mempercepat sistem.
- **File usulan:** hanya bila bukti mendukung, `database/migrations/*_add_measured_query_indexes.php`; `tests/Feature/QueryIndexesTest.php`; hasil benchmark pada dokumen bukti yang disetujui di pekerjaan berikutnya.
- **RED pertama:** `QueryIndexesTest.php`: assert komposit yang telah dipilih tersedia dengan urutan kolom tepat pada engine target. Test struktur akan gagal sebelum penambahan, tetapi **bukan** tes latensi. Acceptance performa harus membandingkan EXPLAIN, rows examined, dan waktu sebelum/sesudah dengan dataset sama.
- **Risiko migrasi:** sedang–tinggi; build indeks dapat mengunci tabel, memakai ruang disk, dan menambah biaya tulis.
- **Persetujuan:** izin benchmark serta DDL produksi sebelum rollout. Dependensi: baseline dahulu; domain/status HB-005 perlu sinkronisasi bila menyentuh tabel sama.

### HB-013 — Putuskan retensi backup user sebelum mengubah cascade

- **Klasifikasi / severity:** TERBUKTI KODE / Sedang; bukan otomatis defect karena backup berisi data pribadi.
- **Requirement:** NFR-003; retensi audit/penghapusan data pribadi: **requirement baru diperlukan**.
- **Bukti:** `database/migrations/2026_09_14_000003_create_user_info_backups_table.php:16` memakai cascade user. Pencarian admin menampilkan snapshot melalui `app/Http/Controllers/AdminDashboardController.php:130–135,165`.
- **Fix minimal:** tentukan kebutuhan audit versus penghapusan PII. Bila snapshot perlu dipertahankan, gunakan relasi nullable dengan redaksi/retensi terukur; bila cascade memang kebijakan yang diinginkan, dokumentasikan dan tutup item tanpa perubahan skema. Jangan menjadikan retensi selamanya sebagai default.
- **File usulan:** migrasi baru `database/migrations/*_adjust_user_backup_retention.php` hanya bila kebijakan berubah; `tests/Feature/UserBackupRetentionTest.php`; model/view audit hanya bila relasi nullable belum ditangani.
- **RED pertama:** `UserBackupRetentionTest.php`: setelah kebijakan disetujui, hapus user dan assert snapshot bertahan teredaksi atau terhapus sesuai kontrak. Bila kebijakan tetap cascade, assertion dapat sudah lulus; item bukan defect yang perlu dipaksa merah.
- **Risiko migrasi:** tinggi untuk privasi dan retensi; backup terhapus tidak dapat dipulihkan oleh perubahan FK.
- **Persetujuan:** **WAJIB eksplisit pengguna** untuk mengubah delete cascade. Dependensi: keputusan retensi HB-001; klasifikasi data snapshot tersendiri tetap diperlukan.

### HB-017 — Notifikasi status pesanan dapat hilang bila dispatch antrean gagal saat commit

- **Klasifikasi / severity:** TERBUKTI KODE & IMPLEMENTASI SELESAI (2026-10-21) / Tinggi.
- **Requirement:** FR-008 notifikasi status; kontrak pengiriman *at-least-once*.
- **Bukti & Akar Masalah:** `app/Services/CheckoutService.php` `notifyCustomer()` sebelumnya mengklaim slot `order_notifications` lalu memanggil `Mail::to()->queue()`. Karena `OrderStatusChanged` memakai `afterCommit()`, dispatch nyata baru terjadi saat transaksi commit. Kegagalan push antrean pada fase commit menyebabkan pesanan tetap commit tetapi notifikasi hilang selamanya karena klaim unik sudah terisi tanpa tanda kirim.
- **Solusi Diimplementasikan (Lazy Outbox + Lease Atomik):**
  - Migrasi aditif `database/migrations/2026_10_21_000030_add_dispatched_at_to_order_notifications_table.php` menambah nullable `dispatched_at`. Baris historis di-backfill ke `created_at` hanya untuk mencegah pengiriman ulang massal; status pengiriman historis **TIDAK TERBUKTI**. Lakukan rekonsiliasi audit manual sebelum tindakan pemulihan historis.
  - Migrasi aditif `database/migrations/2026_10_21_000040_add_lease_to_order_notifications_table.php` menambah `lease_token` dan `lease_expires_at` nullable untuk klaim bersyarat atomik dan pemulihan setelah lease kedaluwarsa.
  - `OrderNotificationDispatcher` mengklaim hanya bila pending dan lease kosong/kedaluwarsa menggunakan satu conditional UPDATE; queue push terjadi di luar transaksi DB. Claim/read/lookup/push gagal dilaporkan (`report`) tanpa melempar keluar callback pasca-commit atau menghentikan batch. Lease dilepas hanya jika klaim berhasil, dan penyelesaian/pelepasan dibatasi token pemilik. Kegagalan pelepasan dilaporkan terpisah; lease 10 menit menjadi jalur pemulihan.
  - `CheckoutService::notifyCustomer()` mempertahankan klaim unik dan `DB::afterCommit`; rollback tidak memicu dispatch.
  - `orders:retry-notifications` mengambil maksimal 100 baris pending yang lebih tua dari 5 menit, belum memiliki lease aktif, memiliki order/email/status/channel yang dapat dikirim. Baris tanpa email tidak dihitung ke batch; jika email/order tersedia kemudian, baris dapat diproses pada cron berikutnya. Scheduler tetap setiap 5 menit dengan `withoutOverlapping()`.
- **Bukti Verifikasi (TDD RED -> GREEN):**
  - RED sebelum implementasi: tes overlap menghasilkan 2 push, dan 100 baris tanpa email menghalangi baris valid ke-101 (0 push).
  - GREEN terfokus: `OrderNotificationOutboxTest`, `OrderNotificationTest`, `CrossChannelLocaleTest`, `ScheduledTasksAndEnvParityTest`. Tes mencakup overlap, antrean gagal/lease dilepas, lease aktif/kedaluwarsa, token pemilik lama tidak mengubah lease penerus, 100 orphan tidak starvation, email yang dipulihkan jadi eligible, rollback, locale `fr`, dan dedupe setelah sukses.
  - Tindak lanjut review MEDIUM: baseline 3 tes fault gagal karena exception keluar dispatcher. Fault injector diperbaiki dari listener pasca-query ke `Connection::beforeExecuting` agar benar-benar mencegah query. Setelah containment, 24 tes terfokus lulus (129 assertions, EXIT=0). Tes mencakup claim gagal tetapi batch berlanjut, read gagal melepas lease, release gagal dilaporkan terpisah dan pulih lewat expiry.
  - Regresi slow-push memajukan waktu 11 menit saat sender pertama aktif, lalu benar-benar menjalankan dispatcher penerus: kedua pemanggilan queue terjadi (2 push). Ini bukti kemungkinan duplikasi, bukan jaminan exactly-once. Skill: `tdd-workflow`; review independen lanjutan masih diperlukan.
- **Desain & Batasan Sisa (Residual Risks):**
  - Semantik *at-least-once*, bukan exactly-once. Jika queue push sukses tetapi proses gagal sebelum update `dispatched_at`, lease berakhir setelah 10 menit dan retry dapat mengirim duplikat. Push yang berlangsung lebih lama dari lease juga dapat overlap; durasi lease perlu disesuaikan dengan batas waktu push terukur.
  - Retry menggunakan grace window 5 menit, batch maksimal 100, scheduler setiap 5 menit.
  - Baris tanpa email tetap pending; bila email ditambahkan kemudian, baris menjadi eligible otomatis.
  - Kegagalan queue diuji melalui database queue SQLite tanpa tabel jobs, bukan outage Redis/MySQL/Postgres atau worker live.
  - Migrasi diuji pada SQLite saja; migrasi `down()` dan operasi DDL engine produksi belum diverifikasi.
  - Tidak ada pengiriman ulang massal otomatis untuk baris lama; riwayat email terkirim perlu rekonsiliasi manual karena backfill bukan bukti pengiriman.


## Antrean prioritas Rendah

### HB-014 — Perlakukan wildcard pencarian admin sebagai karakter literal

- **Klasifikasi / severity:** TERBUKTI KODE / Rendah, ketepatan hasil pencarian; bukan SQL injection yang terbukti.
- **Requirement:** FR-010; kontrak literal pencarian: **requirement baru diperlukan** bila wildcard memang belum dilarang.
- **Bukti:** `app/Http/Controllers/AdminDashboardController.php:85–102,118–138` membungkus input dengan `%` untuk concierge, user, kategori, produk, pesanan, audit, subscriber. Query memakai binding, tetapi `%`/`_` tetap operator LIKE. Pola escape lokal tersedia di `app/Services/CatalogService.php:54–57`.
- **Fix minimal:** sesuaikan tujuh jalur dengan escape `!`, `%`, `_` dan klausul ESCAPE eksplisit yang sudah dipakai katalog. Jangan mengganti dengan interpolasi SQL mentah atau menambah layanan pencarian.
- **File usulan:** `app/Http/Controllers/AdminDashboardController.php`; perluasan `tests/Feature/AdminTablesTest.php`.
- **RED pertama:** `AdminTablesTest.php`: dataset literal `%`, `_`, `!` dan pembanding tanpa karakter itu; tiap parameter `q`, `uq`, `kq`, `pq`, `oq`, `bq`, `sq` hanya mengembalikan kecocokan literal. Sertakan kombinasi filter dan pagination.
- **Risiko migrasi:** tidak perlu; perubahan semantik pencarian bagi operator yang sengaja memakai wildcard.
- **Persetujuan:** konfirmasi pencarian literal sebelum mengubah kontrak. Dependensi: tidak ada.

### HB-015 — Tambahkan skeleton dan aksi coba lagi tanpa merusak state tabel

- **Klasifikasi / severity:** TERBUKTI KODE / Rendah; direncanakan R-45, bukan kegagalan acceptance R-44.
- **Requirement:** FR-010, NFR-203; acceptance skeleton/retry: **requirement baru diperlukan**.
- **Bukti:** `resources/js/admin-table.js:140–150` menampilkan pesan error tanpa tombol; `:181–189` sudah mengumumkan loading dan `aria-busy`, tetapi belum skeleton. Jadi klaim “tidak ada umpan balik loading” akan salah.
- **Fix minimal:** tambah indikator visual noninteraktif dan retry GET terakhir yang gagal; pertahankan filter, draft, pagination, pembatalan request, serta urutan respons. Hindari skeleton yang mencuri fokus atau terbaca sebagai data sungguhan.
- **File usulan:** `resources/js/admin-table.js`, `resources/views/admin/dashboard.blade.php`, `resources/views/admin/components/table-controls.blade.php` bila indikator dibagi; perluasan `tests/admin-table.test.mjs`.
- **RED pertama:** `tests/admin-table.test.mjs`: fetch tertunda menampilkan skeleton dan busy; fetch gagal menampilkan tombol retry; klik retry memakai URL/filter gagal yang sama; sukses menghapus error/skeleton; abort tidak menghasilkan error palsu. Tambahkan keyboard, fokus dan `aria-live` assertion.
- **Risiko migrasi:** tidak perlu; risiko fokus hilang, layout shift, respons usang menimpa state. Browser 360px tetap perlu pada pekerjaan berikutnya.
- **Persetujuan:** setujui acceptance R-45; tidak perlu gate auth/skema. Dependensi: HB-014 dapat berjalan terpisah; perubahan semantik pencarian bukan syarat UI retry.

### HB-016 — Pertahankan batas pemilihan massal sampai aksi nyata disetujui

- **Klasifikasi / severity:** TERBUKTI KODE / Rendah, **fitur opsional**, bukan defect.
- **Requirement:** FR-010 sebagai konteks; aksi massal per domain: **requirement baru diperlukan**.
- **Bukti:** `resources/views/admin/dashboard.blade.php:274–284` menyediakan pemilihan pesanan. `docs/04-implementation/R44_SESSION_LEDGER.md:75–89` sengaja mengecualikan tabel lain karena tidak ada aksi backend; audit snapshot tidak boleh dimutasi massal.
- **Fix minimal:** tidak ada perubahan sekarang. Jika dipilih, ambil satu aksi berguna pada satu tabel; definisikan otorisasi per objek, batas batch, konfirmasi, hasil parsial, dan pemulihan. Jangan memasang checkbox tanpa aksi.
- **File usulan:** setelah persetujuan domain saja: view dashboard, `resources/js/admin-table.js`, route/controller aksi terkait; `tests/Feature/AdminBulkActionTest.php`, `tests/admin-table.test.mjs`. Jangan menambah endpoint mutasi audit.
- **RED pertama:** `AdminBulkActionTest.php`: aksi yang baru disetujui memproses hanya ID terotorisasi, menolak ID asing/ukuran batch berlebih, dan melaporkan kegagalan parsial sesuai kontrak. Tes tersebut baru sah setelah aksi dipilih; tidak ada failing test bermakna untuk “checkbox di semua tabel” sekarang.
- **Risiko migrasi:** belum diperlukan; aksi hapus berpotensi menghapus banyak data.
- **Persetujuan:** keputusan produk wajib; aksi destruktif dan perubahan cascade/auth memerlukan **izin eksplisit**. Dependensi: endpoint dan kontrak aksi sebelum UI; HB-007 sebelum bulk transisi concierge bila fitur itu dipilih.

## Koreksi temuan yang tidak menjadi tiket implementasi

### K-01 — Bcrypt 12: jangan gunakan override PHPUnit sebagai bukti produksi

**Klasifikasi: SALAH sebagai defect konfigurasi yang terbukti.** `phpunit.xml:24` menetapkan `BCRYPT_ROUNDS=4` untuk tes. Default framework terpasang ada di `vendor/laravel/framework/config/hashing.php:18,31–34`: driver bcrypt dan rounds 12 bila tidak dioverride. `app/Models/Admin.php:30` dan `app/Models/User.php:52` memakai cast `hashed`. Tidak ditemukan `config/hashing.php` aplikasi yang mengganti default tersebut.

Ini mengoreksi inferensi “round produksi 4” atau “source tidak mendukung 12”, **bukan** membuktikan semua hash produksi berkost 12. Konfigurasi deployment/cached config dan hash lama belum diperiksa. Pernyataan ledger “produksi belum terverifikasi” tetap valid sebagai batas audit. Verifikasi berikutnya cukup melaporkan driver/cost terpilih dan metadata algoritma/cost hash uji terisolasi, tanpa menampilkan password/hash/kredensial. Tidak perlu mengedit vendor atau menaikkan rounds suite tes massal.

### K-02 — Guard rollback carts sudah memeriksa kedua tabel

**Klasifikasi: SUDAH DIPERBAIKI.** `database/migrations/2026_10_19_000002_create_carts_tables.php:31–40` menolak rollback bila `carts` **atau** `cart_items` berisi baris, sebelum drop kedua tabel.

`tests/Feature/CartMigrationRollbackTest.php:16–83` sudah mencakup cart+item, cart tanpa item, item orphan, dan rollback kosong. Kasus orphan/DDL destruktif dibatasi SQLite pada `:53–55,76–78`; bukan bukti portabilitas MySQL. Tidak ada tes dijalankan ulang dan tidak ada tiket fix baru diperlukan untuk klaim lama.

### K-03 — Overflow 360px belum dapat diputuskan dari source

**Klasifikasi: SALAH bila disebut defect terbukti.** Ledger sendiri menandainya TIDAK TERBUKTI di `docs/04-implementation/R44_SESSION_LEDGER.md:67`. `resources/views/admin/components/table-controls.blade.php:10–14` memakai flex-wrap dan kontrol 44px; aturan itu tidak membuktikan layout seluruh form bebas overflow.

Tidak dibuat HB perbaikan CSS tanpa reproduksi. Setelah izin browser tersedia, ukur form tujuh tabel pada viewport 360px, dengan label panjang, filter aktif, zoom, dan keyboard. Assertion yang diusulkan untuk tes browser baru `tests/e2e/admin-filter-layout.spec.*`: bounding box form/kontrol tidak keluar viewport, scroll horizontal halaman tidak muncul karena form, dan semua kontrol dapat digunakan. Bedakan scroll tabel yang disengaja dari overflow form. Tes belum dibuat atau dijalankan; keadaan runtime tetap **belum diketahui**.

## Gate pelaksanaan dan penerimaan

1. Setujui requirement baru dan kebijakan item yang dipilih. Dokumen ini tidak menyetujui seluruh backlog sekaligus.
2. **Minta izin eksplisit** sebelum perubahan delete cascade, constraint produksi, atau auth/session. HB-001, HB-003, HB-004, HB-005, dan perubahan skema HB-013 tidak boleh dimulai sebagai perbaikan otomatis.
3. Rekam baseline dan tulis RED sesuai item. Untuk item discovery/fitur opsional, selesaikan keputusan terlebih dahulu; jangan mengarang kegagalan tes.
4. Gunakan migrasi baru, bukan menyunting migrasi lama yang sudah dipakai. Periksa skema/data aktual secara terotorisasi; rencanakan backup, lock, rollback, dan privasi.
5. Jalankan verifikasi terisolasi dan engine target bila relevan, setelah izin eksekusi. Catat perintah, exit code, dan bukti; nama tes dalam dokumen bukan klaim lulus.
6. Review independen sebelum implementasi diterima. Audit dokumen ini merupakan pemeriksaan source oleh satu penulis, bukan persetujuan reviewer lain.

## Metode dan hal yang tidak diperiksa

- Skill `planning-and-task-breakdown` dipakai untuk urutan, dependensi, dan batas slice. Skill implementasi/TDD eksekusi/browser tidak dijalankan karena tugas hanya investigasi dan satu dokumen; rencana RED tetap dicatat.
- Lensa audit: integritas/konkurensi untuk FK dan CAS; keamanan/privasi untuk sesi, CSP, dan retensi; signifier, status, pemulihan, serta kontrol pengguna untuk retry/bulk. Ini lensa kerja, bukan klaim para tokoh mengaudit atau menyetujui hasil.
- Tidak memverifikasi database aktif, data produksi, query plan, latensi, external CI, TLS/HSTS edge, cost hash deployment, email reset nyata, ataupun overflow 360px.
- Tidak mengubah ledger, matriks requirement, atau indeks dokumentasi agar batas **satu file baru saja** tetap dipenuhi. Temuan fokus AJAX pada ledger :60 berada di luar dua kelompok yang diminta dan tidak diperluas menjadi tiket di sini.
