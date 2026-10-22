# Audit Komprehensif ZAFIRA — 21 Oktober 2026

**Permintaan:** mempelajari, mereview, mengaudit, dan menganalisis proyek secara mendalam, kemudian melaporkan hasilnya.\
**ID audit:** `AUD-20261021`. **Baseline:** `041ed083a1be330d97d4f335cda8555670045cdd`.\
**Status:** pemeriksaan dan sintesis selesai dalam batas yang disebutkan; temuan belum diperbaiki. **Bukan persetujuan produksi.**\
**Bahasa laporan:** Indonesia. Semua path sumber di bawah relatif terhadap root repositori `online-shop/`.

## 1. Kesimpulan eksekutif

**ZAFIRA sudah merupakan aplikasi perdagangan yang substansial, bukan sekadar landing page atau mockup. Namun belum aman dinyatakan siap menerima transaksi produksi end-to-end.** Fondasi framework, kontrol akses, transaksi lokal, dan pengujian cukup kuat; kelemahan utama berada pada hubungan antartahap lifecycle, konsistensi antarmuka, operasi pemulihan, dan ketepatan dokumentasi.

Temuan paling penting:

1. **Pembayaran terverifikasi dapat tidak mengubah order menjadi lunas.** Urutan `capture/challenge → capture/accept` untuk transaksi yang sama dibuang oleh deduplikasi terlalu kasar. Direproduksi dengan Status API palsu, bukan provider nyata.
2. **Pembatalan order lunas mengembalikan stok tetapi tidak membuat record kewajiban refund lokal.** Order tersebut juga tidak dapat memakai alur retur normal. Direproduksi melalui endpoint pelanggan.
3. **Kode pemulihan admin dapat terbawa ke admin berikutnya pada sesi browser dual-guard yang sama.** Enrolment, logout A, login B, dan pembacaan kode A berhasil direproduksi pada feature test.
4. **Origin URL reset password mengikuti Host request.** Builder aplikasi terbukti menghasilkan origin asing pada probe; eksploitasi produksi tetap bergantung pada penerimaan Host oleh ingress.
5. **Backup MySQL mengabaikan target `DB_URL` efektif.** Pembentukan command dump terbukti memakai field fallback; keberhasilan dan isi dump database nyata tidak diuji.
6. **Seluruh suite PHP dan Node yang dijalankan bisa hijau bersamaan dengan temuan tersebut.** Sembilan probe tambahan mengonfirmasi perilaku bermasalah yang belum dicakup pengujian standar.

**Verdict per tujuan:**

| Tujuan | Penilaian audit |
|---|---|
| Melanjutkan pengembangan dari fondasi sekarang | Layak; pertahankan struktur dan kualitas yang sudah baik. Tidak ada dasar untuk rewrite total. |
| Demonstrasi terbatas dengan data sintetis | Fondasi lokal tersedia, tetapi browser sesi audit ini BLOCKED; jangan memperluas bukti historis menjadi kelulusan baru. |
| Pembayaran, pembatalan, refund, dan pemenuhan komersial nyata | Belum direkomendasikan sebelum temuan finansial dan operasional ditutup. |
| Klaim WCAG 2.2 AA penuh | **TIDAK TERBUKTI.** Markup dan tes statis bukan pengujian screen reader/visual lengkap. |
| Klaim performa, kapasitas, SLA/SLO, dan recoverability produksi | **TIDAK TERBUKTI.** Tidak ada load test atau restore engine produksi dalam audit ini. |

## 2. Metode, lingkup, dan batas klaim

### 2.1 Apa yang dilakukan

- Baseline Git dan runtime diperiksa sebelum perubahan; working tree awal bersih menurut output tool awal dan catatan integrator di ledger. Snapshot output Git awal tidak diarsipkan dalam folder evidence.
- Enam lane read-only memeriksa **keamanan**, **transaksi**, **data**, **UI/HCI**, **operasi/QA**, dan **produk/dokumentasi**. Kepemilikan dan ID sesi ada di [ledger tim](../04-implementation/TEAM_SUPPORT_LEDGER.md).
- Integrator melakukan ricek sumber prioritas, deduplikasi, pengujian standar, sembilan probe sintetis, diagnosis kegagalan, dan sintesis.
- Probe transaksi serta keamanan diperiksa lagi oleh sesi reviewer lane yang berbeda dari penulis probe. Reviewer tidak mengklaim menjalankan ulang probe.
- Tidak ada perubahan pada kode aplikasi, konfigurasi bisnis, dependency, atau test yang sudah ada. Probe sementara dikeluarkan dari `tests/` setelah dijalankan dan disimpan sebagai [artefak teks](evidence-2026-10-21/AuditSnapshotProbeTest.php.txt).
- Agent tidak membaca langsung environment atau secret operasional, database operasional, maupun backup pelanggan. Namun suite otomatis membaca template `.env.example` melalui `tests/Feature/ScheduledTasksAndEnvParityTest.php:14`; eksekusinya tercatat pada `docs/01-audit/evidence-2026-10-21/php.log:1070-1071`. Karena itu, klaim bahwa tidak ada akses `.env*` sama sekali tidak tepat; pembacaan template oleh test bukan bukti kebocoran secret operasional. Tidak menjalankan migrasi destruktif pada database operasional, mengambil task server, sync NgodingPakeAI, commit, push, atau upgrade dependency.
- Audit registry Composer/npm mengirim metadata dependency, bukan sumber proyek. Tidak ada instalasi atau pembaruan paket.

**Audit menyeluruh secara lintas bidang tidak sama dengan pembuktian formal setiap baris atau setiap kombinasi keadaan.** Sumber inti ditelusuri berbasis risiko; tidak semua aset, arsip, script historis, dan test dibaca baris demi baris.

### 2.2 Label bukti

- **R — runtime lokal:** direproduksi integrator dengan data sintetis atau diperoleh dari command yang benar-benar dijalankan.
- **M — manual sumber:** ditemukan pada kode/test/dokumen; belum tentu direproduksi.
- **I — inferensi:** konsekuensi yang masuk akal, tetapi interleaving, lingkungan, atau dampak sebenarnya belum diuji.
- **U — belum tersedia/TIDAK TERBUKTI:** tidak ada bukti memadai untuk menyatakan lulus atau gagal.

`P1` berarti prioritas sebelum penggunaan berisiko terkait; `P2` berarti perbaikan penting berikutnya; `P3` berarti perbaikan terarah dengan dampak lebih terbatas. **Prioritas bukan CVSS dan bukan klaim telah terjadi insiden.** Temuan bersyarat menyebut prasyaratnya.

ID seperti `TXN-02` merupakan singkatan unik `AUD-20261021-TXN-02`. Nomor lane dipertahankan agar handoff dapat ditelusuri; temuan yang sama dari beberapa lane digabung, bukan dihitung berulang.

## 3. Pemahaman produk dan arsitektur aktual

### 3.1 Identitas dan sasaran

ZAFIRA memosisikan diri sebagai butik digital atelier mewah: penemuan koleksi editorial, detail material/provenance/craft/care, konsultasi personal, serta pembelian standar. Bodoni Moda, Manrope, dan palet Sapphire/Linen/Gold/Ivory merupakan aset identitas yang perlu dipertahankan.

Persona yang memiliki dukungan implementasi nyata adalah guest, customer, serta admin/staff. Persona artisan, atelier manager, dan direktur Maison dalam rancangan MSI merupakan sasaran yang lebih luas; tidak membuktikan backend manufaktur telah tersedia.

Kebutuhan pasar, willingness-to-pay, keaslian bahan, kapasitas artisan, klaim pemasok, conversion uplift, dan profitabilitas **belum divalidasi oleh audit kode**. Kit `docs/04-implementation/STOREFRONT_COPY_USABILITY_TEST.md:3-11` sendiri menyebut belum ada sesi partisipan.

### 3.2 Stack dan ukuran sumber

| Elemen | Hasil pemeriksaan |
|---|---|
| Runtime lokal | PHP **8.5.11**, Node **24.21.0**, npm **11.19.0** |
| Framework terkunci | Laravel **13.36.0**, PHPUnit **12.5.39** |
| Dependency penting terkunci | Dompdf **3.1.6**, Socialite **5.31.0**, Google2FA **9.1.0**, Apple provider **6.1.0** |
| Frontend terkunci | Vite **8.3.5**, Tailwind **4.3.3**, Laravel Vite Plugin **3.2.0**, Axios **1.20.0** |
| Bentuk aplikasi | Monolit Laravel, Blade, vanilla JavaScript; prototype HTML/CSS/JS terpisah |
| Sumber aplikasi | **92** berkas PHP: **28 controller**, **26 model**, **12 service**, beserta middleware/jobs/mail/support |
| Database | **50 migrasi**, **36 nama tabel dideklarasikan**; tidak termasuk tabel pencatat migrasi/internal engine |
| UI dan test | **39 Blade view**, **131 berkas `*Test.php`**, **39 berkas `*.test.mjs` di `tests/`**, **5 locale** |

Angka tersebut merupakan inventaris sumber, bukan ukuran database terpasang atau persentase coverage. `node --test` juga menemukan tes di luar 39 berkas tersebut.

### 3.3 Pemisahan permukaan produk

| Permukaan | Implementasi aktual | Batas penting |
|---|---|---|
| `/` | Beranda editorial, kartu produk, pencarian lokal, quick view, drawer, concierge | Membaca seluruh katalog aktif; wishlist lokal berbeda dari API favorit. |
| `/koleksi` | Filter/pencarian server, pagination, detail dialog, SET jumlah cart, favorit akun | Memakai sebagian CSS prototype; race mutasi client belum tertutup. |
| `/produk/{slug}` | Detail kriya, JSON-LD, related products, personalisasi inquiry | Pencarian header memakai kumpulan kartu related, bukan katalog penuh. |
| `/belanja` | Cart, alamat, checkout akun/tamu, pesanan, tracking, favorit | Checkout membuat order lokal; UI jujur bahwa pembayaran online belum tersedia. |
| `/akun` | Profil, verifikasi email, OAuth, TOTP, ekspor/hapus akun, riwayat concierge | Riwayat concierge berbeda dari order transaksi pada `/belanja`. |
| `/admin` | Katalog, kategori, concierge, order, pengguna/subscriber/audit; permission dan TOTP | Sebagian kemampuan berupa endpoint JSON, bukan workflow UI lengkap. |
| Webhook Midtrans | Signature, konfirmasi Status API, nominal, ledger, perubahan paid, dead-letter | Tidak ditemukan inisiasi pembayaran dalam jalur checkout yang diperiksa; provider live tidak diuji. |
| Retur/refund | Eligibility, request, review lokal, pembatasan nominal | Pencairan refund provider sengaja fail-closed; UI/operasi retur belum lengkap. |
| Shipment | Manifest/event lokal dan status delivered saat order completed | Bukan bukti integrasi kurir atau barang benar-benar diterima. |
| `/prototype/` | Studio, cart simulasi, rendezvous, atelier/MSI berbasis state lokal | WIP, certificate, DSS dan manufaktur tidak boleh disamakan dengan backend operasional. |

Sumber: `routes/web.php:101-251`, `app/Http/Controllers/StorefrontController.php:23-204`, `app/Services/CheckoutService.php:121-346`, `app/Services/RefundService.php:70-92`, serta `docs/03-architecture/TPS_VOLUME_AND_QUALITY_ANALYSIS.md:25-44`.

### 3.4 Lifecycle dan invariant utama

- **Cart:** parent cart dan identitas akun dikunci; guest memakai token ber-hash dengan expiry; kuantitas dibatasi 99 per produk.
- **Checkout:** validasi harga server → conditional decrement stok → klaim voucher → order/item/history/reservation → pengosongan cart. Idempotency key dipasangkan dengan scope/owner dan payload hash.
- **Order:** `pending → paid → processing → shipped → completed`; `pending`, `paid`, dan `processing` masih dapat menuju `cancelled`.
- **Pembayaran:** notifikasi bertanda tangan → Status API → lock order → deduplikasi ledger → transisi paid. Kedua status/fraud decision harus konsisten, dan di sinilah TXN-02 ditemukan.
- **Reservasi:** reserved selama 60 menit; pembayaran mengubah menjadi committed; expiry membatalkan pending melalui scheduler. Dead-letter unresolved menahan expiry, tetapi pemeriksaannya memiliki race.
- **Notifikasi:** slot outbox ditulis bersama transisi, lalu dispatch setelah commit dengan lease. Jaminan praktisnya **at-least-once enqueue**, bukan exactly-once atau email sampai inbox.

Invariant yang perlu dipakai sebagai acceptance lintas modul: stok tidak negatif; satu intent checkout menghasilkan satu order; pembayaran diakui sekali tanpa kehilangan perubahan keputusan; pembatalan order lunas membentuk kewajiban finansial; total refund tidak melampaui dana bersih; dan penghapusan akun tidak melepas akses transaksi yang masih berlangsung.

### 3.5 Struktur data

36 tabel terbagi menjadi: identitas/profil/keamanan **8**, katalog/interaksi produk **4**, concierge/newsletter **2**, cart **2**, transaksi/fulfillment **13**, dan infrastruktur framework **7**. Migrasi memakai FK RESTRICT untuk referensi produk penting, SET NULL untuk mempertahankan catatan transaksi setelah akun dihapus, serta cascade pada sejumlah data pendamping.

Nominal belum seragam: harga produk `DECIMAL(10,2)`, order/item/payment umumnya unsigned BIGINT, tetapi retur/refund unsigned INTEGER. Status umumnya string dengan pembatasan aplikasi, bukan ENUM/CHECK database. Ada unique key penting untuk idempotensi, event pembayaran, cart, slug, favorit, dan notifikasi. **Engine serta konfigurasi produksi efektif tidak diperiksa.**

## 4. Kekuatan yang harus dipertahankan

1. **Pemisahan guard dan otorisasi nyata.** Route admin memakai `auth:admin`, TOTP, dan ability; role tak dikenal ditolak. Kepemilikan alamat/order/retur dibatasi server.
2. **TOTP admin bukan atribut dekoratif.** Secret terenkripsi, recovery di-hash, replay timestamp memakai CAS, marker terkait admin/sesi, dan enrolment mempunyai perlindungan CAS.
3. **OAuth tidak menautkan akun hanya karena email sama.** Identitas provider ditangani terpisah; state/nonce diperiksa. Apple `stateless()` tidak otomatis berarti tanpa perlindungan callback.
4. **Checkout tidak mempercayai harga client.** Conditional stock update, pengurutan lock, rollback voucher/stok, dan idempotensi menyediakan fondasi transaksi yang baik.
5. **Webhook memverifikasi lebih dari signature.** Status API, transaction/order ID, currency, dan nominal dicocokkan; status notification saja tidak cukup membuat order paid.
6. **Outbox sudah mempertimbangkan kegagalan sebagian.** Dispatch setelah commit dan retry slot tersedia; kemungkinan duplikasi saat lease kedaluwarsa diakui test, bukan disamarkan sebagai exactly-once.
7. **UI mempunyai banyak mekanisme pemulihan.** Cart canonical memiliki antrean mutasi, undo, pembaruan saat tab kembali, escaping, dan pemulihan fokus. Admin mendukung pagination/filter server, progressive enhancement, serta penolakan respons stale.
8. **Pengujian negatif cukup luas.** Ada ownership, rollback, malformed input, stale request, CSRF, locale, skema, secret sanitization, dan test guard database.
9. **Harness isolasi relatif matang.** Config/storage/database terpisah, allowlist command, penolakan symlink/hardlink, sanitasi evidence, dan lock per root tersedia.
10. **Dokumen terbaru mulai jujur terhadap batas.** Analisis TPS membedakan proyeksi dari trafik nyata; refund fail-closed, delivery email belum terbukti, dan prototype bukan backend dijelaskan di beberapa dokumen.

Kekuatan ini mendukung perbaikan terarah, bukan rewrite.

## 5. Verifikasi yang benar-benar dijalankan

Semua waktu berikut **UTC, 21 Oktober 2026**. Metadata command utama pada tabel menyimpan **argv, timestamp mulai/selesai, dan exit** dalam pasangan `.json` untuk log terkait pada [folder bukti](evidence-2026-10-21/); metadata tidak merekam environment override. `COMPOSER_DISABLE_NETWORK=1` untuk validate/platform diberikan melalui environment wrapper Python menurut catatan eksekusi integrator/transkrip, bukan dibuktikan oleh JSON tersebut. Command PHP memakai harness terisolasi yang tidak memuat `.env` operasional repositori; pembacaan template `.env.example` oleh test dijelaskan di §2.1.

| Pemeriksaan | Mulai UTC | Hasil aktual | Bukti |
|---|---|---|---|
| `composer --no-plugins --no-scripts validate --strict --no-check-publish` (override environment menurut catatan integrator di atas) | 10:40:41 | **EXIT=1**: manifest valid, warning pin versi tepat Dompdf 3.1.6 | [log](evidence-2026-10-21/composer-validate.log) |
| `composer --no-plugins --no-scripts check-platform-reqs --lock` (override environment menurut catatan integrator di atas) | 10:40:41 | **EXIT=0** | [log](evidence-2026-10-21/composer-platform.log) |
| `vendor/bin/pint --test` | 10:40:41 | **362 berkas lulus, EXIT=0** | [log](evidence-2026-10-21/pint.log) |
| `node --test` | 10:40:41 | **325 lulus, 0 gagal, EXIT=0** | [log](evidence-2026-10-21/node.log) |
| `./scripts/amadis/launch.sh test` | 10:41:35 | **979 lulus, 3 skipped, 27.359 assertions, EXIT=0**; durasi runner 228,65 detik | [log](evidence-2026-10-21/php.log) |
| `npm run build` | 10:46:15 | **EXIT=0**, Vite 8.3.5 | [log](evidence-2026-10-21/build.log) |
| Python boundary root | 10:47:18 | **1 lulus, EXIT=0** | [log](evidence-2026-10-21/python-boundary.log) |
| Python sanitization | 10:47:18 | **24 lulus, EXIT=0** | [log](evidence-2026-10-21/python-sanitization.log) |
| Python lock | 10:47:18 | **2 gagal, EXIT=1** | [log](evidence-2026-10-21/python-lock.log) |
| `./scripts/amadis/launch.sh test --filter=AuditSnapshotProbeTest` | 10:48:55 | **9 reproduksi terkonfirmasi, 51 assertions, EXIT=0**; bukan kelulusan produk | [log](evidence-2026-10-21/probes.log) |
| Browser lifecycle percobaan pertama | 10:57:30 | **BLOCKED pada S0, EXIT=1** | [result](evidence-2026-10-21/browser-first-result.json) |
| Browser lifecycle percobaan kedua | 10:58:25 | **BLOCKED pada S0, EXIT=1** | [result](evidence-2026-10-21/browser-rerun-result.json) |
| `composer --no-plugins --no-scripts audit --locked --format=json --no-interaction` | 11:01:34 | **0 advisory / 0 abandoned yang dilaporkan, EXIT=0** | [log](evidence-2026-10-21/composer-audit.log) |
| `npm audit --audit-level=high --json` | 11:01:34 | **0 vulnerability yang dilaporkan, EXIT=0** | [log](evidence-2026-10-21/npm-audit.log) |

Perintah Python masing-masing: `python3 -B -m unittest discover -s tests/python -p '<nama-berkas>' -v`, untuk `tests/python/test_amadis_boundary_root.py`, `tests/python/test_amdis_launch_sanitization.py`, dan `tests/python/test_amadis_lock.py`. Argumen `-p` menggunakan basename tanpa direktori karena `-s` sudah menunjuk `tests/python`. Nilai keluar command individual tercatat; wrapper pengumpulan log bukan pengganti exit tersebut.

### 5.1 Interpretasi hasil

- **Tiga skipped bukan lulus.** Ketiganya berasal dari `MysqlCheckoutRetryTest` yang memerlukan schema InnoDB terisolasi. Audit ini tidak menjalankannya pada engine tersebut.
- **Warning Composer bukan bukti dependency rusak atau rentan.** Pin Dompdf perlu dipahami dalam kebijakan dependency yang sudah ada; tidak ada rekomendasi upgrade massal untuk menghilangkan warning.
- Hasil audit registry berlaku pada dependency dan waktu pemeriksaan ini. **Nol advisory tidak membuktikan tidak ada bug aplikasi atau supply-chain risk lain.**
- Output build: CSS app **95,92 kB / gzip 19,33 kB**, JS app **34,67 kB / gzip 11,25 kB**, CSS admin **6,43 kB / gzip 1,78 kB**. Ini bukan total transfer halaman: CSS/JS publik, gambar, font, dan inline script perlu dihitung terpisah.
- Banyak test memeriksa markup/source string. Itu berguna sebagai guard tetapi tidak mengukur usability, kontras terkomputasi, rendering, concurrency engine lain, atau coverage persentase.

### 5.2 Kegagalan browser dan keamanan eksekusi

Fixture baru dibuat dengan `launch.sh init`, `migrate`, `seed`, dan `provision`; ketiga langkah beraksi `migrate/seed/provision` keluar **0** pada root baru dengan 76 produk sintetis. Bukan database pengguna.

Percobaan pertama gagal membuat lock OPcache di `/tmp` karena izin sandbox: [diagnostik PHP](evidence-2026-10-21/browser-php-stderr.log). Setelah izin temporary diperluas secara terbatas, percobaan kedua masih berhenti pada S0 sebelum page aktif. Diagnosis Chromium kosong menunjukkan `Target page, context or browser has been closed`, `SIGABRT`, dan `kill EPERM`: [log diagnosis](evidence-2026-10-21/browser-diagnostic.log).

**Akar penyebab Chromium yang lebih rinci belum terisolasi; jangan menyatakannya sebagai bug ZAFIRA.** Tidak dilakukan percobaan alur ketiga atau bypass sandbox luas. Cleanup process group server milik runner tercatat keluar 0 pada [command log percobaan pertama](evidence-2026-10-21/browser-first-commands.json) dan [command log percobaan kedua](evidence-2026-10-21/browser-rerun-commands.json). Tidak ada screenshot visual baru yang dinyatakan lulus.

Laporan browser terdahulu `docs/execution/amadis/browser-20261021/REPORT.md` tetap bukti historis yang dibaca, **bukan hasil pengujian baru audit ini**.

## 6. Temuan prioritas tinggi dan reproduksi konkret

### TXN-02 — P1 · R: keputusan capture berubah tetapi order tetap pending

**Sumber:** `app/Services/PaymentWebhookService.php:100-104,142-152`; unique event pada `database/migrations/2026_10_20_000003_create_payments_table.php:23-26`.

Deduplikasi memakai provider, transaction ID, dan `event_status`, sementara sukses capture juga ditentukan `fraud_status`. Probe mengirim dua respons Status API untuk transaksi sama: capture/challenge lalu capture/accept. Dua request provider palsu benar-benar dipanggil; kedua webhook merespons 200, tetapi order tetap pending dan satu ledger tetap `recorded`.

**Dampak:** perubahan keputusan pembayaran yang sah tidak diterapkan. Cancellation akibat expiry setelah itu merupakan risiko lanjutan, belum direproduksi di probe. Existing test menguji challenge tunggal dan duplicate settlement, bukan urutan ini.

**Perbaikan/AC yang disarankan:** deduplikasi harus idempoten terhadap observasi sama tanpa membuang perkembangan keputusan efektif; urutan challenge→accept menghasilkan tepat satu pengakuan paid. Validasi juga urutan aktual terhadap kontrak provider sebelum aktivasi live.

### TXN-03 — P1 · R: pembatalan order lunas tidak mencatat refund lokal

**Sumber:** `app/Models/Order.php:12-18,114-117`; `app/Http/Controllers/OrderController.php:85-95`; `app/Services/CheckoutService.php:514-521`; `app/Http/Controllers/OrderReturnController.php:99-105`.

Probe membuat order lewat checkout, melunaskannya lewat webhook sintetis, lalu memanggil cancel pelanggan. Order menjadi cancelled, stok kembali dari empat ke lima, tabel refunds kosong, dan eligibility retur false.

**Dampak:** alur aplikasi menyelesaikan pembatalan persediaan tanpa mencatat kewajiban finansial. Ini berbeda dari fitur provider refund yang sengaja ditunda. Pembayaran/manajemen manual di luar aplikasi tidak diperiksa.

**Perbaikan/AC:** batasi cancel mandiri ke pending sampai proses finansial siap, atau buat record kewajiban/refund/manual-review atomik untuk paid/processing. Pengguna harus diberi status penyelesaian dana yang jujur.

### SEC-03 — P1 untuk penutupan kebocoran secret lintas principal · R: recovery admin bertahan setelah pergantian akun

**Sumber:** `app/Http/Controllers/AdminTotpEnrolController.php:151,182-200`; `app/Http/Controllers/AdminAuthController.php:58-65`; `routes/web.php:222-229`.

Kode recovery disimpan terenkripsi dalam satu key sesi, tanpa owner. Pada dual-guard, logout admin memigrasikan ID tetapi mempertahankan atribut sesi. Probe melakukan enrolment nyata admin A; sebelum halaman recovery dikonsumsi, A logout dengan guard customer masih aktif, lalu B login. Halaman recovery B menampilkan kode A.

**Prasyarat:** sesi/browser bersama, guard web tetap aktif, kode A belum ditampilkan. B dalam fixture adalah admin legacy; variasi B pending TOTP dan round-trip browser nyata belum diuji. Ini bukan klaim guest dapat membaca kode.

**Perbaikan/AC:** ikat owner dan expiry pada state enrolment/recovery, periksa owner saat pull, serta bersihkan transient secret milik guard pada logout/pergantian identitas. Regression harus menguji A→logout→B dengan dual-guard. Pola serupa pada recovery pelanggan memerlukan pemeriksaan terpisah.

### SEC-01 — P1 bersyarat ingress · R/M: URL reset password menerima origin Host asing

**Sumber:** `app/Http/Controllers/PasswordResetController.php:25-35`; `bootstrap/app.php:31-48`; builder vendor `vendor/laravel/framework/src/Illuminate/Auth/Notifications/ResetPassword.php:90-99`.

Request feature test memakai domain sintetis asing dan tetap menghasilkan notifikasi; builder `toMail()` asli membentuk `actionUrl` dengan host tersebut. Tidak ada email nyata dikirim.

**Batas penting:** Notification dipalsukan, lalu builder asli dipanggil dalam konteks request tes. Ini mengonfirmasi konstruksi URL aplikasi, bukan kebocoran token produksi. DNS/TLS/reverse proxy tidak dilalui. Risiko pengambilalihan memerlukan ingress yang menerima/meneruskan Host arbitrer dan korban membuka tautan.

**Perbaikan/AC:** allowlist Host serta origin canonical tepercaya untuk tautan sensitif. Uji Host asing pada builder dan staging ingress; jangan hanya menguji `X-Forwarded-Host`.

### OPS-01 — P1 bersyarat konfigurasi · R/M: command backup tidak memakai target koneksi efektif

**Sumber:** `app/Services/DatabaseBackupService.php:125-148`; konfigurasi URL/socket/TLS di `config/database.php`; parser koneksi vendor `vendor/laravel/framework/src/Illuminate/Database/DatabaseManager.php:219-230`.

Probe mengatur URL koneksi menuju database A, tetapi field fallback menuju B. Dengan `Process::fake()`, command dump yang benar-benar dibentuk terbukti memakai B. Tidak ada koneksi MySQL atau dump data nyata.

**Dampak bersyarat:** deployment berbasis `DB_URL` dapat mencadangkan target salah atau gagal. Socket dan opsi TLS aplikasi juga belum diteruskan ke dump. Konfigurasi produksi aktual tidak diperiksa.

**Perbaikan/AC:** turunkan target backup dari konfigurasi efektif yang sama dengan koneksi aplikasi; uji URL, socket, TLS, dan target yang ditolak. Restore drill engine produksi menjadi gate terpisah.

### TXN-01 — P1 sebelum penggunaan InnoDB berbeban · M/I: retry deadlock bersarang menahan propagasi ke outer transaction

**Sumber:** `app/Services/CartStore.php:26-76`; `app/Services/CheckoutService.php:196-221`; vendor `vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php:88-101,148-174`.

**Outer retry sudah ada:** `CartStore` memakai `DB::transaction(..., 3)`. Temuannya bukan ketiadaan retry, melainkan `CheckoutService::attempt()` menangkap deadlock bersarang dan mengulang bagian dalam sebelum exception mencapai parent. Framework menjelaskan deadlock MySQL dapat membatalkan seluruh transaksi fisik.

**Risiko:** retry sebagian berjalan dengan state transaksi/lock yang tidak lagi sesuai. Partial commit atau bentuk kegagalan persisnya **belum terbukti**. Tes MySQL yang tersedia menyuntikkan exception sintetis, bukan deadlock fisik InnoDB.

**Perbaikan/AC:** propagasikan deadlock bersarang ke batas transaksi terluar agar cart, lock user, stok, voucher, order, dan clear-cart diulang bersama. Buktikan dengan dua koneksi InnoDB nyata pada database disposable, bukan hanya SQLite atau exception mock.

### DATA-01 — P1 untuk integritas penghapusan akun · M/I: active-transaction check berada di luar transaksi delete

**Sumber:** `app/Http/Controllers/ProfileController.php:116-134`; lock user checkout pada `app/Services/CheckoutService.php:277-288`.

Pemeriksaan order/retur/refund aktif terjadi sebelum transaksi penghapusan tanpa protokol lock/recheck bersama. Request lain dapat membuat transaksi setelah check, sebelum akun dihapus. FK SET NULL menjaga catatan transaksi tetapi dapat memutus akses kepemilikan saat proses masih berlangsung.

**Batas:** celah check–use terverifikasi; interleaving belum dieksekusi. **AC:** serialisasikan keputusan penghapusan dan pembuatan transaksi pada identity lock yang sama, recheck sebelum delete, dan uji dua koneksi. Jangan mengubahnya menjadi cascade delete transaksi.

### TXN-04 — P2 · R: full return berdiskon berakhir pada refund yang ditolak

**Sumber:** `app/Http/Controllers/OrderReturnController.php:62-79,121-128`; `app/Services/RefundService.php:35-42,101-109`.

Probe: barang Rp250.000, voucher Rp50.000, pembayaran Rp200.000. Setelah completed, retur seluruh item diterima dengan requested refund Rp250.000. Endpoint refund kemudian menolak 422 pada field `amount`.

Retur pending bukan penyebab penolakan: service saat ini hanya melarang return rejected. Penyebabnya nominal bruto item tidak dialokasikan menjadi nilai bersih setelah diskon.

**AC:** alokasi diskon/pajak per item/kuantitas deterministik, jumlah alokasi cocok dengan snapshot order, kebijakan ongkir eksplisit. Jangan melonggarkan batas total refundable untuk mengatasi salah perhitungan.

### TXN-05 — P2 · R/M: settlement lanjutan dicatat sebagai order non-payable

**Sumber:** `app/Services/PaymentWebhookService.php:146-168`.

Probe capture/accept→settlement untuk transaction ID sama menghasilkan outcomes `paid`, lalu `order_not_payable`. Order tetap paid. Alert “perlu refund manual” didukung source branch, tidak di-assert langsung di probe.

**Bukan** bukti double-charge, refund otomatis, atau `Order::hasPaymentAnomaly()` bernilai true. **AC:** bedakan progres transaksi yang sudah dibukukan dari pembayaran baru atas order cancelled; rekonsiliasi settlement tanpa pengakuan paid ganda dan tanpa alarm refund palsu.

### OPS-02 — P2 · R: retensi nol menghapus seluruh cadangan dalam scope koneksi

**Sumber:** `config/backup.php:7`; `app/Services/DatabaseBackupService.php:47-55`; `routes/console.php:64-73`. Alias temuan lane DATA-06.

Probe membuat dua artefak backup sintetis di direktori baru; `prune(0)` menghapus keduanya. Default 14 tidak memicu kondisi ini, tetapi nilai nol/kosong/teks yang di-cast menjadi nol bisa melakukannya. Tidak ada backup operasional diakses atau dihapus.

**AC:** konfigurasi harus integer positif; nilai invalid ditolak sebelum pekerjaan penghapusan. Pertahankan sedikitnya satu backup valid.

### SEC-07 — P3 · R/M: email array pada reset menghasilkan 500

**Sumber:** `routes/web.php:77`.

Probe POST JSON email array mengonfirmasi respons 500, bukan validasi 422. Cast string pada limiter sebelum validasi merupakan akar penyebab yang didukung pembacaan kode; probe tidak mengassert exception/stack trace spesifik.

**AC:** bucket akun hanya menerima string, input malformed tetap dibatasi IP dan diserahkan ke validator. Ini robustness/error-handling, bukan bukti bypass CSRF atau pengambilalihan akun.

## 7. Temuan tambahan keamanan, data, dan keandalan

Temuan berikut berbasis manual sumber/lane, bukan reproduksi tambahan. Risiko deployment/concurrency tetap bersyarat.

| ID / prioritas | Bukti, dampak, dan rekomendasi/acceptance |
|---|---|
| **SEC-02 / P2** — pencabutan sesi admin | `app/Http/Controllers/AdminDashboardController.php:229-252` mengganti password/remember token, tetapi tidak mencabut seluruh sesi lama atau menerapkan pemeriksaan hash sesi. Admin TOTP hanya di-logout pada request saat ini; `totp_last_used_at` direset null. **AC:** uji dua sesi termasuk pending challenge; revokasi versi autentikasi harus terpisah dari watermark anti-replay. Tidak mengklaim browser lintas perangkat telah direproduksi. |
| **SEC-04 / P2** — limiter TOTP admin | `app/Http/Controllers/AdminTotpController.php:49-61` membatasi kombinasi akun+IP, bukan akun lintas IP. Penyerang yang sudah lolos password mendapat budget baru dari IP lain. `tests/Feature/AdminTotpTest.php:527-531` memang mengharapkan budget baru. **AC:** batas akun global ditambah batas IP dengan backoff proporsional. |
| **SEC-05 / P2** — race enrolment TOTP pelanggan | `app/Http/Controllers/CustomerTotpController.php:135-171` mengecek `hasTotp()` sebelum transaksi, kemudian mengganti secret tanpa CAS. Dua enrolment paralel berpotensi saling menimpa. Admin sudah mempunyai CAS. **AC:** satu pemenang atomik, hanya pemenang memperoleh recovery codes; interleaving belum diuji. |
| **SEC-06 / P3** — newsletter dan email belum terverifikasi | `app/Http/Controllers/ProfileController.php:83,129-130` membaca/menghapus subscriber berdasarkan email; registrasi langsung login. Email yang belum dibuktikan kepemilikannya dapat memengaruhi subscription orang lain yang belum punya akun. **AC:** verifikasi email sebelum menghubungkan record subscription; hapus akun lokal tanpa menyentuh subscriber pihak lain. |
| **TXN-06 / P2** — batas nominal retur/refund | BIGINT order/item dibanding INTEGER unsigned pada migrasi returns/refunds. 99 × Rp50.000.000 = Rp4.950.000.000 melampaui UINT32. **AC:** satu kapasitas integer rupiah konsisten dan boundary test SQLite–MySQL. Kegagalan MySQL aktual belum dieksekusi. Alias DATA-02. |
| **TXN-07 / P2** — race hold dead-letter | `app/Jobs/ReleaseExpiredReservations.php:23-35` mengambil snapshot hold sebelum cancel; `app/Services/PaymentWebhookService.php:117-139` dapat menambah hold tanpa lock order yang sama. **AC:** keputusan expiry/hold dibaca ulang dan dikoordinasikan atomik. Test saat ini hanya membuat hold sebelum job, bukan di tengah interleaving. |
| **TXN-08 / P3** — label shipment terminal | `app/Models/Shipment.php:71-85`: adanya AWB memilih label in-transit meski status delivered. **AC:** label mengikuti status, AWB menjadi informasi terpisah; tambahkan assertion label terminal, bukan hanya timestamp/status. |
| **DATA-03 / P2** — kontrak input produk | `app/Http/Controllers/AdminProductController.php:19-21,63-65` menerima harga pecahan serta URL hingga 1.000 karakter; schema produk menetapkan DECIMAL(10,2) dan string 255. `CheckoutService::idr()` menolak pecahan nonnol. **AC:** satu aturan harga/batas presisi dan panjang URL yang konsisten; jangan menunggu error MySQL. |
| **DATA-04 / P2** — rollback nullable | `database/migrations/2026_10_20_000005_protect_returns_and_refunds_from_user_delete.php:24-36` dan `database/migrations/2026_09_07_125000_make_concierge_schedule_nullable.php:17-22` mengembalikan NOT NULL tanpa preflight data sah yang sudah null. **AC:** seluruh prasyarat diperiksa sebelum DDL pertama; fail-closed, jangan menghapus record atau mengarang pemilik/tanggal. |
| **DATA-05 / P2** — verifikasi backup dua waktu | `app/Services/DatabaseBackupService.php:81-87` membandingkan restored row counts dengan sumber hidup saat verify. Perubahan normal setelah snapshot membuat backup konsisten dianggap gagal; perubahan nilai dengan row count sama tidak terdeteksi. **AC:** validasi snapshot/integritas restore yang tepat, bukan kesamaan dua waktu; restore MySQL masih belum didukung. |
| **DATA-06 tambahan / P2** — memori backup | `app/Services/DatabaseBackupService.php:38,83` membaca seluruh file untuk kompresi/dekompresi. **AC:** streaming, penanganan gagal tulis, dan publikasi artefak atomik setelah verifikasi. OOM dan ukuran batas belum diukur. Retensi nol sudah dibahas sebagai OPS-02. |
| **DATA-07 / P2** — retensi dan ekspor PII | `app/Observers/UserObserver.php:14-41` terus menambah snapshot profil, tetapi snapshot tidak masuk ekspor `app/Http/Controllers/ProfileController.php:80-97`. PII transaksi dipertahankan setelah akun dihapus. `auth-events:prune` tersedia tetapi tidak dijadwalkan dalam `routes/console.php:88-96`. **AC:** matriks retensi, dasar/legal hold, ekspor, dan purge teruji. Ini bukan kesimpulan pelanggaran hukum. |
| **DATA-08 / P2** — operasi linear tak dibatasi | Beranda memuat seluruh produk aktif (`app/Http/Controllers/StorefrontController.php:28-31`), sitemap seluruh slug (`app/Http/Controllers/StorefrontController.php:120-124`), invalidasi cache memuat seluruh produk (`app/Http/Controllers/AdminCategoryController.php:55-66`), ekspor seluruh order/item (`app/Http/Controllers/ProfileController.php:86-96`). **AC:** featured collection terbatas, invalidasi terarah/versi namespace, dan streaming/chunking sesuai pengukuran. |
| **DATA-09 / P2** — indeks/query baru | Migrasi indeks mendahului tabel payments/reservations; FK tidak memberi indeks otomatis yang sama di SQLite. Query paid aggregate dan relasi order perlu EXPLAIN; contains search `LOWER(...) LIKE '%...%'` bukan kandidat yang otomatis selesai dengan B-tree biasa. **AC:** dataset representatif, query budget dan EXPLAIN per engine sebelum penambahan indeks. Tidak ada klaim latensi terukur. |

### Batas yang sengaja tidak dinyatakan sebagai bug pasti

- Admin legacy tanpa TOTP adalah kebijakan rollout yang terlihat disengaja; berbeda dari bypass akun yang sudah diwajibkan TOTP.
- CSP opsional/report-only adalah batas hardening, bukan bukti ada XSS yang dapat dieksploitasi.
- Refund provider sengaja ditolak sebelum ledger uang bergerak. Jangan menghapus guard demi membuat endpoint tampak selesai.
- Duplikasi outbox pada lease expiry sudah diakui sebagai at-least-once. `dispatched_at` membuktikan enqueue, bukan inbox delivery; retry/failed-job monitoring tetap diperlukan.
- Kewenangan staff membaca data profil/subscriber memerlukan keputusan least-privilege produk; keberadaan ability mutasi berbeda tidak otomatis membuktikan baca tersebut tidak sah.

## 8. Frontend, HCI, aksesibilitas, dan performa pengalaman

**Status semua temuan UI berikut: M/I.** Source dan beberapa kontrak dicrosscheck; tidak ada kelulusan browser baru. Identitas brand tidak perlu diganti untuk memperbaiki temuan ini.

| ID / prioritas | Masalah dan bukti | Perbaikan / acceptance yang disarankan |
|---|---|---|
| **UI-01 / P2** | Beranda/katalog mengimpor `public/prototype/app.css:70-91`, yang mengubah html overflow, scroller body, dan menyisihkan band dock 84px. Header canonical membaca `window.scrollY` (`resources/js/app.js:1437-1451`). | Scope layout prototype, pisahkan styling komponen yang dipakai ulang. Ukur viewport penuh dan header pada scroller aktual; jangan menyatakan besar gap visual sudah diukur. |
| **UI-02 / P2** | Save status admin memanggil `load()` sebelum `form.reset()` (`resources/js/admin-table.js:220-223`); `load()` memeriksa dirty dan dapat meminta discard pada perubahan yang sudah tersimpan (`resources/js/admin-table.js:279-295`). | Pisahkan state committed dari draft. Penolakan refresh tidak boleh meninggalkan badge lama sambil menyatakan seluruh tampilan telah diperbarui. Test select dirty nyata, bukan mock form kosong. |
| **UI-03 / P2** | Wishlist beranda/PDP memakai slug di localStorage (`resources/js/app.js:403-426`), katalog dan belanja memakai API `/favorites`. | Tentukan sumber kebenaran dan kebijakan guest→account; tambah/hapus konsisten antarsurface, reload, dan login. Jangan merge data lokal tanpa kebijakan jelas. |
| **UI-04 / P2** | Search header mengindeks `[data-product-card]`; PDP hanya menyediakan maksimal tiga related products (`app/Http/Controllers/StorefrontController.php:77-85`). | Gunakan search katalog canonical atau arahkan ke `/koleksi`; produk di luar related dan produk utama harus dapat ditemukan. |
| **UI-05 / P2** | Switch bahasa hanya memperbarui atribut `data-i18n*`; `appCopy` dibaca sekali. Cart/concierge server-rendered tidak seluruhnya ditandai (`public/js/zafira-core.js:1886-1905`, `resources/js/app.js:12-23`). | Setelah locale tersimpan, reload atau perbarui seluruh teks/accessible names secara atomik. Uji perubahan tanpa reload, reload, dan navigasi lintas halaman. |
| **UI-06 / P2** | Modal rendezvous/shortcuts prototype tidak memakai siklus fokus drawer (`public/prototype/app.js:1234-1237,1954-1971`). | Fokus masuk, containment, background inert, Escape dan restore opener; uji transisi drawer→modal. Bukan klaim modal canonical semuanya rusak. |
| **UI-07 / P2** | Form SET jumlah katalog mengirim setiap mutasi tanpa serialization/pending guard (`resources/views/catalog/index.blade.php:819-846`). | Simulasi respons tertunda/terbalik; final state mengikuti intent terakhir yang disepakati. Abort fetch tidak menjamin mutasi server dibatalkan. |
| **UI-08 / P2** | `shopRequest` tidak memiliki timeout (`resources/views/shop/request.blade.php:3-15`), sementara `action()` mengunci semua kontrol main sampai promise selesai (`resources/views/shop/index.blade.php:76-92`). | Batas waktu respons/body, loading per region, retry terarah; timeout checkout harus mempertahankan idempotency key karena hasil server belum tentu gagal. |
| **UI-09 / P2** | Pesan validasi stok tidak ada dalam whitelist catch cart sehingga berubah menjadi pesan jaringan (`resources/js/app.js:607-633`). | Bedakan 422 stok, 429, 419, 5xx, dan transport sambil tetap menyaring pesan sensitif. Retry jaringan bukan pemulihan stok habis. |
| **UI-10 / P3** | Kode fokus mencari `.auth-errors` di dalam form (`resources/js/app.js:1505-1511`), tetapi ringkasan login/profil berada di luar form. | Setelah validation error, fokus menuju ringkasan terkait dan kesalahan field dapat dikenali melalui label/deskripsi. |

### 8.1 Evaluasi tujuh lensa HCI dan aksesibilitas

Nama tokoh dipakai sebagai lensa kerja dalam aturan proyek, bukan kutipan primer atau klaim bahwa mereka menyetujui produk.

| Lensa | Yang sudah baik | Gap terpenting |
|---|---|---|
| Norman | Batas monogram dan inquiry terpisah membantu model mental | Favorit terpisah dan pencarian PDP tidak sesuai ekspektasi pengguna |
| Nielsen | Status loading, error, empty state, dan konfirmasi sudah banyak | Status admin stale, error stok salah label, serta async tanpa batas |
| Shneiderman | Undo cart dan closure nomor order | Kontinuitas fokus prototype dan closure pembatalan berbayar belum memadai |
| Cooper | Persistensi preferensi dan toleransi storage tersedia | Pengguna perlu mengulang pekerjaan akibat state antarsurface terpisah |
| Engelbart | Filter/sort/density/selection membantu kerja operator | Data status stale dan batas simulasi MSI perlu diperjelas |
| Brown | Prototype interaktif berguna untuk eksplorasi | Nilai bisnis/usability pengguna nyata belum dibuktikan |
| Krug | Label SET jumlah katalog lebih jelas | Cakupan search serta pembagian order/concierge belum konsisten |
| Accessibility/Watson | Label, skip link, live region, dialog/inert dan reduced motion tersedia | Screen reader, reflow/zoom, focus containment, contrast aktual, dan bahasa accessible names belum tuntas |

### 8.2 Maintainability dan beban frontend

`resources/js/app.js` **1.526 baris**, `resources/css/app.css` **986**, `resources/views/catalog/index.blade.php` **870**, `resources/views/welcome.blade.php` **861**, dan `resources/js/admin-table.js` **554**. Ukuran adalah sinyal inspeksi, bukan bug sendiri. Coupling yang sudah menimbulkan dampak adalah CSS prototype pada halaman canonical, state favorit ganda, copy server vs runtime, serta strategi request yang berbeda antarhalaman.

Refactoring yang bernilai: pisahkan lifecycle overlay, request/error contract, state favorit, dan adapter locale yang benar-benar dipakai bersama; jangan membuat banyak wrapper pass-through atau memindahkan kompleksitas tanpa menguranginya.

Responsive strategy terlihat pada source: grid bertahap, filter disclosure mobile, PDP satu kolom, scroll-region tabel berlabel, dan ringkasan checkout sticky. **320px, zoom 400%, keyboard virtual, layout teks panjang lima bahasa, target sentuh terkomputasi, dan WCAG penuh tetap U.** Standar internal target 44×44 tidak identik dengan minimum WCAG 2.2 yang memiliki ketentuan dan pengecualian tersendiri.

## 9. Operasi, CI, dan keandalan gerbang kualitas

| ID / prioritas | Temuan | Acceptance yang disarankan |
|---|---|---|
| **OPS-03 / P2 · M** | `tests/TestCase.php:45-55` mensyaratkan tepat **30** migrasi untuk schema MySQL, sementara sumber sekarang **50**. CI hanya SQLite. | Perbandingan manifest/schema yang aman, bukan angka magic; jangan sekadar melonggarkan guard. Pisahkan tes DDL dari transaksi biasa dan jalankan pada engine target. |
| **OPS-04 / P2 · R/M** | Dua test lock Python gagal sebelum menguji lock: fixture `amadis-locktest-*` ditolak regex `amadis-[a-f0-9]{24}` (`tests/python/test_amadis_lock.py:67-73`, `scripts/amadis/launch.py:694-700`). Python tidak ada pada gate CI. | Selaraskan fixture tanpa melemahkan guard, jalankan suite Python di CI, dan tampilkan skip/error dengan jujur. Gagal fixture bukan bukti algoritma flock rusak. |
| **OPS-05 / P2 · M/I** | `scripts/amadis/acceptance_isolated.sh:39,57-61` tidak memasukkan exit test escaping ke keputusan akhir. Anti-drift CI `.github/workflows/ci.yml:64-68` hanya mencari teks Pending di pipeline conditional. | Negative test command stub gagal harus membuat seluruh gate gagal. Kegagalan `migrate:status` tidak boleh diperlakukan sebagai tidak ada migrasi tertunda. |
| **OPS-06 / P2 · M/I** | `scripts/amadis/acceptance_isolated.sh:41-44` melakukan cleanup `pkill -f` berdasarkan port tanpa membuktikan listener miliknya. | Gunakan process group milik runner, port preflight dan identity readiness; tes port occupied tidak boleh menghentikan proses lain. Pola lebih baik sudah ada di `scripts/amadis/browser-lifecycle.cjs:41-79`. |
| **OPS-07 / P2 bersyarat · M/I** | `config/mail.php:82-88` failover SMTP→log dapat dianggap sukses tanpa pengiriman nyata; isi pesan masuk log. | Fallback pengiriman nyata atau failure retryable, pengujian SMTP gagal, redaksi dan retensi sink. Konfigurasi mail produksi belum diperiksa. |
| **OPS-08 / P3 · M** | Manifest Node `>=22` menerima 22.0–22.11, sedangkan dependency Vite/plugin menghendaki minimal 22.12 pada lini 22. | Selaraskan minimum runtime dengan lockfile dan uji versi minimum. Runtime lokal Node 24 lulus tidak membuktikan semua rentang deklarasi didukung. |
| **OPS-09 / P3 · M** | `scripts/ci/protect-main.sh:10-12` menetapkan nol approval dan admin tidak wajib mengikuti protection. | Selaraskan dengan protokol review independen; verifikasi konfigurasi GitHub aktif secara terpisah. Script bukan bukti protection remote benar-benar sudah dipasang. |

**Observabilitas:** correlation ID, auth events, status history, health dan dead-letter merupakan fondasi yang baik. Tetapi queue size tidak membuktikan worker hidup; schedule registration tidak membuktikan cron berjalan; `Storage::delete()` pada probe health belum diperiksa hasil false-nya. SLA/SLO, queue age, alert delivery, stale lock recovery, backup offsite, RPO/RTO, dan restore drill belum dibuktikan.

**Portabilitas harness:** beberapa path temporary, browser cache, CLI, dan schema test terikat mesin pengguna. Ini membantu isolasi pada host tertentu tetapi belum menjadi setup lintas mesin/CI yang reproduktif. Jangan mengartikan lintas-platform framework sebagai paritas seluruh tooling proyek.

## 10. Dokumentasi, requirement, dan klaim produk

Dokumen terbaru dan historis harus dibaca terpisah. Banyak materi berguna, tetapi tidak ada satu matriks current-state yang sepenuhnya konsisten dengan kode terbaru.

| ID / prioritas | Konflik yang dapat ditelusuri | Koreksi yang disarankan |
|---|---|---|
| **DOC-01 / P1 sebagai kontrak keamanan** | `docs/02-product/PRD.md:65-66` masih mengizinkan riwayat berdasarkan user ID atau email-match; controller memakai `user_id` dan traceability mengakui konflik. | Supersede klausul email-match. Jangan mengembalikan perilaku tidak aman demi mengikuti spesifikasi lama; klaim guest memerlukan pembuktian kepemilikan tersendiri. |
| **DOC-02 / P2** | FR-004/005 mencampur monogram cart, inquiry concierge, dan checkout. AC-005 (`docs/04-implementation/ACCEPTANCE_CRITERIA.md:33-36`) menyebut form concierge menyimpan orders, padahal `bookConcierge()` tetap membuat `ConciergeRequest`. | Tulis tiga kontrak terpisah: cart pembelian, checkout order, dan inquiry personalisasi. Acceptance harus menyebut endpoint dan hasil yang benar. |
| **DOC-03 / P2** | `docs/03-architecture/DATABASE_DESIGN.md:156-187` masih menempatkan order sebagai masa depan; ADR-010/keterangan integrasi lama tidak merefleksikan webhook/history terbaru. | Pertahankan histori dengan label superseded dan rujukan current-state. Perbarui ERD/kamus dari migrasi, termasuk kapasitas nominal dan FK. Alias DATA-10. |
| **DOC-04 / P2** | Readiness 10 Oktober menampilkan skor 100% dan 49 test (`docs/05-quality/PROJECT_READINESS_REPORT.md:14-20`); tracker memberi label selesai untuk WCAG sementara screen reader/CLS/coverage belum dibuktikan. | Ganti persentase global dengan gate spesifik, timestamp/SHA, bukti, dan residual risk. “Siap implementasi” bukan “siap produksi”. |
| **DOC-05 / P2** | Matriks FR/NFR/US dan R-01–R-60 berasal dari snapshot berbeda; `docs/04-implementation/REQUIREMENT_TRACEABILITY.md:3-11,78-82` mengakui relasi goal inferred. Traceability lama masih menyebut verifikasi email tidak ada. | Satu crosswalk requirement→AC→kode→test→hasil→reviewer; mapping konseptual tidak boleh menggantikan bukti acceptance. Audit tidak mengubah status plan server. |
| **DOC-06 / P3** | Sebagian dokumen masih Laravel 12/PHP 8.2, menyebut cache PDP/LRU yang tidak sesuai kode, tautan `file://` lokal, serta lokasi FOUNDATION_CHANGELOG yang berbeda. | Perbaiki navigasi/supersession dan onboarding. Jangan menyalin ulang seluruh arsip; tandai mana catatan historis. |

### 10.1 Keterkaitan audit dengan requirement

Pemetaan berikut adalah analisis audit, bukan redefinisi PRD atau status task:

| Requirement | Area yang diperiksa / gap utama |
|---|---|
| FR-001–003, FR-202 | Render, filter, PDP, search scope, cache, provenance. UI-01/UI-04, DATA-08/09. |
| FR-004/201, FR-005/203 | Persistensi cart vs personalisasi/inquiry; DOC-02, UI-07/08/09. |
| FR-007–009, FR-014/204, NFR-002 | Ownership, guard, reset, TOTP, sesi, dan PII. SEC-01–07, DATA-01/07. |
| FR-010–018, NFR-006 | Dashboard, mutasi, filter/sort, draft/focus. UI-02 dan UI-10; koreksi status/kontrak acceptance. |
| NFR-003 | Stok, voucher, ledger, idempotensi, nominal, FK, pembatalan/retur. TXN-01–07, DATA-02–05. |
| NFR-001/004/005 | Performa terukur, brand, runtime, engine parity, dan browser. DATA-08/09, UI-01, OPS-03/08. |

Sembilan probe tambahan ditautkan terutama ke NFR-002/NFR-003. Probe adalah **characterization of defects**; saat remediation disetujui, assertion harus diubah menjadi invariant aman dan disimpan sebagai regression test normal, bukan menjadikan perilaku cacat sebagai acceptance.

### 10.2 Klaim eksternal dan scope produk

Target AOV, margin, retensi, lead time, sertifikat autentikasi, kapasitas atelier, atau kepatuhan ISO/UU PDP bukan fakta hanya karena tertulis pada dashboard/dokumen. Sebagian dokumen MSI menyebut metrik “aktual” tanpa sumber operasional yang diperiksa. Setiap klaim perlu sumber, periode, denominator, dan pemilik data.

After-sales API, manifest shipment, serta layar prototype tidak sama dengan layanan end-to-end. Masih diperlukan keputusan scope untuk UI retur/refund, administrasi resi, rekonsiliasi pembayaran, aturan pajak/ongkir, dan bantuan pelanggan. Fitur deferred bukan otomatis bug, tetapi tidak boleh dijanjikan sebagai kemampuan yang selesai.

## 11. Rencana tindak lanjut yang disarankan

Ini urutan rekomendasi, **bukan pelaksanaan backlog atau estimasi waktu yang sudah disepakati**.

### Gelombang 1 — tutup risiko keamanan dan uang

- SEC-03 dan SEC-01: owner-binding recovery, cleanup dual-guard, Host/canonical URL.
- TXN-02/03/04/05: perkembangan status pembayaran, kewajiban refund cancellation, alokasi diskon retur, klasifikasi settlement.
- SEC-02/04/05: pencabutan sesi, limiter TOTP lintas IP, CAS enrolment pelanggan.
- OPS-01/02: target backup efektif dan retensi aman.
- DATA-01 serta TXN-01/07: penghapusan akun dan lifecycle konkurensi diuji pada engine yang benar.

**Gate keluar:** regression spesifik gagal sebelum fix dan lulus sesudahnya, tidak melemahkan test, peer review independen, suite penuh tetap lulus, serta batas provider/ingress yang relevan teruji.

### Gelombang 2 — satukan kontrak dan pulihkan kepercayaan UI

- Harga/URL/skema nominal konsisten; rollback punya preflight.
- Favorit, search, locale, error contract, dan async lifecycle konsisten lintas halaman.
- Scope CSS prototype dan siklus fokus; uji draft admin setelah save.
- Perbaiki fixture Python dan agregasi exit CI; hilangkan cleanup berbasis nama proses.

**Gate keluar:** tidak ada split source-of-truth yang tidak disengaja; browser desktop/mobile nyata, keyboard dan screen reader pada alur inti; semua gate wajib gagal bila substep gagal.

### Gelombang 3 — buktikan operasi pada staging

- Inisiasi pembayaran dan webhook/refund provider sandbox end-to-end, termasuk callback terlambat/duplikat dan rekonsiliasi.
- Worker/scheduler hidup, antrean stuck terdeteksi, failure mail diuji, dead-letter memiliki owner/prosedur.
- Restore database engine target, backup offsite/enkripsi, RPO/RTO, rollback rilis, dan monitoring.
- EXPLAIN/query budget dan load test representatif; jangan menggunakan proyeksi TPS sebagai benchmark kapasitas.

### Gelombang 4 — rekonsiliasi dokumentasi dan validasi produk

- Satu current-state manifest bertanggal/SHA dan crosswalk requirement lengkap.
- Tandai laporan historis dan klaim simulasi; hapus penilaian 100% tanpa gate.
- Uji pengguna nyata, review bahasa oleh penutur, verifikasi kebijakan komersial dan hukum.
- Perluas atelier/WIP/DSS hanya setelah kebutuhan serta model operasional terbukti.

## 12. Tidak diperiksa dan risiko tersisa

- Infrastruktur/ingress produksi, TLS edge, konfigurasi efektif, secret rotation, data pengguna, serta log produksi.
- Midtrans live/sandbox nyata, charge/refund sungguhan, SMTP delivery, kurir, dan pemenuhan barang.
- Deadlock InnoDB nyata, concurrency semua endpoint, penerapan/rollback migrasi pada engine produksi, dan restore MySQL/MariaDB.
- Visual browser terbaru, screen reader, cross-browser, perangkat nyata, contrast/reflow/zoom, dan Core Web Vitals lapangan.
- Coverage persentase, mutation score, seluruh suite Python, semua script historis, dan seluruh kombinasi bahasa/state.
- Remote CI/GitHub protection aktif, offsite backup, observability end-to-end, SLA/SLO dan RPO/RTO.
- Validasi pasar/pemasok, keaslian produk, kapasitas artisan, data bisnis aktual, serta audit hukum/kepatuhan.

**Risiko terbesar bila laporan ini diabaikan:** mengartikan “tes standar hijau”, “endpoint sudah ada”, “prototype interaktif”, atau “dokumen bertanda selesai” sebagai bukti transaksi komersial dan operasi produksi sudah aman.

## 13. Jejak bukti, review, dan perubahan audit

- [Folder bukti eksekusi](evidence-2026-10-21/): log dan metadata exit/timestamp; tidak berisi database, file konfigurasi rahasia, atau kredensial browser.
- [Probe characterization](evidence-2026-10-21/AuditSnapshotProbeTest.php.txt): kode probe yang dipakai, disimpan sebagai teks agar tidak masuk discovery suite normal.
- [Ledger kepemilikan](../04-implementation/TEAM_SUPPORT_LEDGER.md): enam sesi lane dan tindak lanjut.
- Reviewer SEC `c9dd9784-c81f-4aa3-a037-9843c63c0cab` memeriksa kausalitas tiga probe keamanan; reviewer TXN `db374cb9-9ba6-452f-98f5-fc4fe0f22129` memeriksa empat probe finansial. Keduanya menerima bukti dengan batas eksplisit di §6; tidak mengulang eksekusi.
- Dua probe backup dicrosscheck terhadap implementasi sumber oleh integrator, dengan temuan awal yang saling mendukung dari lane OPS/DATA; tidak diklaim sebagai rerun independen.
- Reviewer OPS `164fe2f2-6c4a-41e9-ad15-627e66751214` memberikan **APPROVE untuk akurasi laporan** pada recheck 21 Oktober 2026 setelah koreksi akses template environment, provenance metadata, dan path sumber. Tidak ada blocker akurasi yang tersisa dalam scope review; reviewer tidak mengulang test/runtime/network. Jam review tidak tersedia. Approval laporan **bukan approval rilis aplikasi**; penerimaan hasil dan pemeriksaan akhir dicatat pada ledger.

### Continuous Evaluation Record

- **Task:** AUD-20261021; requirement/AC audit pada ledger dan mapping §10.1.
- **Baseline:** commit `041ed08`; working tree bersih menurut output tool awal dan catatan integrator/ledger, bukan snapshot Git yang disimpan dalam evidence. Laporan historis tidak dijadikan bukti runtime sekarang.
- **Controlled change:** hanya dokumentasi dan evidence audit; probe sementara telah dikeluarkan dari `tests/`; kode aplikasi dan test lama tidak diubah.
- **Verification:** argv, timestamp, exit, hasil, dan batas pada §5 serta metadata evidence; environment override Composer berasal dari catatan eksekusi integrator/transkrip, tidak direkam metadata.
- **Comparison:** baseline suite hijau tetapi probe lintas-lifecycle mengungkap celah; Python lock gagal; browser BLOCKED; registry tidak melaporkan advisory.
- **Decision:** audit diserahkan dengan temuan terbuka. **Tidak ada pernyataan bahwa perbaikan selesai atau produksi diterima.**
