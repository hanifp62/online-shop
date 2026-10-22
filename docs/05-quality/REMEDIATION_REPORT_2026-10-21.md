# Laporan remediasi, verifikasi, TPS, dan kesiapan uji ZAFIRA

**ID:** REM-20261021 · **Tanggal:** 21 Oktober 2026\
**Baseline:** `041ed083a1be330d97d4f335cda8555670045cdd` · **Implementasi:** working tree, belum commit/push.\
**Keputusan:** **remediasi lokal terverifikasi; real-world end-to-end dan produksi BELUM DISETUJUI.**\
**Review laporan:** APPROVE bounded factual review oleh sesi independen `baba615a-5f4d-4965-82f6-70194d773b5d`; bukan persetujuan produksi.

Laporan ini menindaklanjuti [audit baseline AUD-20261021](../01-audit/COMPREHENSIVE_AUDIT_2026-10-21.md). Audit lama tetap bukti kondisi sebelum perbaikan, bukan daftar bug yang masih semuanya aktif. Requirement/AC ada pada [kontrak REM](../04-implementation/REMEDIATION_REAL_WORLD_2026-10-21.md), [acceptance](../04-implementation/ACCEPTANCE_CRITERIA.md), dan [crosswalk](../04-implementation/REQUIREMENT_TRACEABILITY.md).

## 1. Kesimpulan untuk pemilik produk

1. Perubahan aplikasi, regression test, konfigurasi contoh, dan dokumentasi telah ditulis. Tidak dilakukan rewrite brand, upgrade dependency, perubahan database operasional, pembayaran/refund nyata, commit, push, atau sync plan.
2. Cacat keamanan dan uang yang direproduksi telah mendapat perbaikan terarah: canonical URL, revokasi sesi, isolasi recovery/TOTP, progres pembayaran, larangan cancel berbayar yang belum memiliki workflow refund aman, alokasi retur bersih, replay dead-letter berversi, serta collision nomor refund.
3. Suite lokal PHP/Node/Python, format dan build lulus dalam batas berikut. **Skipped bukan passed**, mock bukan engine produksi, dan browser lama bukan verifikasi perubahan baru.
4. Ringkasan operasional admin tersedia sebagai fitur **opt-in, mati secara default**, berbasis data nyata yang tersedia dan label `unknown/partial` untuk hal yang belum diperiksa. Ini bukan sistem manufaktur/DSS lengkap atau bukti semua indikator kesehatan normal.
5. Pintu menuju uji lapangan telah dipetakan, tetapi belum seluruhnya dapat dieksekusi di sesi ini. Sandbox menolak inventaris/sinyal process group; browser end-to-end terbaru, InnoDB, provider, email, restore MySQL/MariaDB, dan beban produksi masih membutuhkan lingkungan yang tepat.

**Bukan klaim:** nol defect, WCAG 2.2 AA penuh, throughput maksimum, uptime tertentu, tidak mungkin terjadi double charge pada provider nyata, atau seluruh nama tokoh/skill telah memberikan persetujuan. Nama tokoh adalah lensa metodologis; reviewer adalah sesi agent independen yang dicatat di bawah.

## 2. Bukti pengujian yang benar-benar dijalankan

Semua command dijalankan integrator secara serial untuk resource PHP/DB/browser. Test Laravel memakai launcher terisolasi dan SQLite `:memory:`, bukan database operasional. Log dan metadata command/waktu/exit berada di [evidence](evidence-remediation-2026-10-21/).

| Gate | Hasil terverifikasi | Artefak |
|---|---|---|
| PHP penuh terbaru (termasuk inquiry dan streaming export) | **1.154 passed, 3 skipped, 156.806 assertions; EXIT=0** | `php-full-export-final.json` / `.log`; terikat `source-manifest-export-final.json` |
| Urutan regresi trusted-host | RED 3 gagal/45 lulus → **GREEN 48 lulus/358 assertions; EXIT=0** | `php-host-order-red.*`, `php-host-order-green.*` |
| Node penuh | **465 passed, 0 failed, 0 skipped; EXIT=0** | `node-full-export-final.*` |
| Python penuh | **165 test dilaporkan, 6 skipped; 159 dieksekusi; EXIT=0** | `python-full-r7.*` |
| Pint penuh | **377 file; EXIT=0** | `pint-full-export.*` |
| Production build | **EXIT=0**; bundle app 37,92 kB / gzip 12,13 kB pada run ini | `build-r3.*`; source frontend tidak berubah setelah build ini |
| MSI terarah | **25 passed / 497 assertions; EXIT=0** | `php-msi-r3.*` |
| Remediasi terarah | **147 passed / 1.648 assertions; EXIT=0** | `php-targeted-r3.*` |
| Inquiry bukan checkout — tambahan setelah R7 | **12 passed / 51 assertions; Pint satu file EXIT=0** | `php-inquiry-final.*`, `pint-inquiry-final.*` |
| Export/privasi/locale/keamanan terarah | **54 passed / 127.336 assertions; EXIT=0** | `php-export-green-r2.*`; termasuk 4 test streaming dengan fixture riwayat besar |
| Observer child native Darwin | **NATIVE_EXIT_OBSERVER_OK**; child exit 0 | `native-observer-green.json`; hanya observer, bukan cleanup/browser |

Riwayat: R7 mencatat 1.150 passed/3 skipped/29.856 assertions; run `php-full-final` sesudah inquiry mencatat 29.864 assertions. Setelah slice streaming export ditambahkan, suite **penuh** dijalankan lagi pada 16:42:53–16:47:14 UTC; angka terbaru pada tabel bukan penjumlahan targeted. Banyak assertion tambahan memeriksa setiap field fixture besar, **bukan** 126 ribu requirement baru atau coverage persen. Manifest terbaru mengikat **132** file source/test berubah atau baru; manifest 131 file sebelumnya tetap artefak historis. Environment, data runtime, dokumen dan build output dikecualikan dari hash source.

PHP tiga skipped membutuhkan schema InnoDB guarded untuk fault injection deadlock/timeout. Python enam skipped membutuhkan FontTools pada interpreter/venv yang sesuai; tidak menginstal paket baru untuk mengubah hasil. Test yang menyebut fault database masih dibedakan: SQLite nyata atau PDO/mock interleaving **bukan** bukti dua koneksi InnoDB bersaing.

Baseline audit sebelum perubahan: PHP 979 passed/3 skipped/27.359 assertions; Node 325 passed; Pint 362 file; build lulus. Python baseline hanya subset—termasuk dua kegagalan lock fixture—sehingga tidak dibuat perbandingan persentase coverage dari hitungan tersebut. Bertambahnya test bukan ukuran coverage persen; coverage belum diukur.

### Kegagalan selama pengerjaan tidak disembunyikan

- RED awal Node: 59 gagal/20 lulus. RED PHP yang diterima guard: 60 gagal/19 lulus. Sebagian merupakan fixture baru yang keliru, bukan sebanyak itu bug aplikasi.
- Integrasi pertama: 17 gagal/94 lulus; fixture Process/DB dan kompilasi Blade dikoreksi.
- Full PHP R1 terhenti pada sanitizer `JSONDecodeError`; **tidak menghasilkan verdict suite**. Malformed key, prefix nilai, dan interval redaksi overlap masing-masing mendapat reproducer sebelum fix.
- Full PHP R4 `EXIT=1`: fixture sesi/enrolment, markup, inventaris/konfigurasi, dan state trusted-host statik. Redaksi fail-closed memotong sebagian diagnostik; kegagalan diisolasi per kelas, bukan membuka log mentah.
- Nomor refund 16-bit benar-benar bertabrakan pada fixture transaksi. Ini cacat source yang diperbaiki, bukan disembunyikan dengan retry test.
- Dua pola yang ditandai secret scanner pada `node-red.log` disensor **tanpa mencetak nilainya**, bukan menambah allowlist. Hash sebelum/sesudah dan jumlah redaksi ada di `node-red-redaction.json`; metadata exit RED tidak diubah. Provenance pola tidak disimpulkan sebagai kredensial produksi.
- Slice export: RED 3 gagal/1 lulus; percobaan GREEN awal kehabisan memori pada **pembentukan diff PHPUnit**, bukan bukti server export kehabisan memori. Test kecil mengisolasi fixture `actingAs` yang belum menghidrasi default DB (`null` vs `false`). Fixture di-refresh dan pembandingan dataset besar tetap strict per section tanpa diff raksasa. Rerun terarah dan full kemudian lulus; tidak menaikkan memory limit atau melonggarkan equality.
- Suite otomatis membaca template `.env.example`; agent tidak membaca environment operasional. Dua default fitur non-secret ditambahkan secara append-only berdasarkan laporan parity, tanpa membaca/menampilkan isi template.

## 3. Perubahan per bidang

### 3.1 Keamanan identitas — FR-REM-001/004

- Tautan reset dan verifikasi menggunakan origin canonical tepercaya, termasuk port lokal; clone generator URL tidak mewarisi cache route-origin yang salah.
- `AuthenticateGuardSession` mengikat fingerprint password per guard. Sesi persisted lama tanpa marker diminta login kembali; pergantian password mencabut sesi/pending challenge lama tanpa menghapus watermark anti-replay TOTP.
- Rotasi sesi hanya mempertahankan second factor guard lain apabila marker itu **sudah valid sebelum rotasi**.
- Recovery/enrolment owner-bound, expiry 300 detik, dan kode pemulihan hanya ditampilkan sekali. Budget TOTP direservasi atomik sebelum verifier, termasuk budget akun lintas IP; sukses tidak menghapus reservasi request lain.
- Enrolment TOTP customer memakai CAS. Input malformed diberi validasi, bukan exception internal.
- Penghapusan akun mengunci identitas dan memeriksa ulang transaksi aktif. Subscription berdasarkan email hanya dikaitkan/dihapus setelah kepemilikan email terbukti. Ekspor mencakup snapshot profil milik pengguna sendiri.

Bukti utama: `SecurityRemediationTest`, `AdminTotpTest`, `AuthEventFlowsTest`, `EmailVerificationTest`, `CartIdempotencyTest`. Login HTTP nyata dipakai pada fixture yang sebelumnya memasang guard persisted tanpa fingerprint; middleware tidak dinonaktifkan agar tes lolos.

### 3.2 Integritas transaksi dan uang — FR-REM-002

- `capture/challenge → capture/accept` tidak lagi dibuang sebagai duplikat; ledger tetap append-only. Settlement lanjutan transaksi yang sama direkonsiliasi tanpa mengakui paid dua kali atau mengeluarkan alarm refund yang salah.
- **Perubahan perilaku penting:** `paid`/`processing → cancelled` ditolak. Pending cancellation tetap mengembalikan stok/voucher sekali. Pengembalian uang sesungguhnya belum diimplementasikan dengan verifikasi provider; tidak membuat ilusi refund berhasil.
- `ReturnAllocation` memakai nilai bersih setelah diskon dan saldo klaim aktif. Provenance `net-balance-v2` serta snapshot saldo mencegah data algoritma baru dianggap legacy bruto. Dua klaim ditolak lalu diajukan ulang, secara terpisah maupun sekaligus, diuji. Ongkir tidak diam-diam masuk nilai item yang dapat direfund.
- Baris legacy tanpa provenance tidak di-backfill atau diubah nominal historisnya. Data yang tidak dapat dibuktikan kompatibel gagal tertutup dan memerlukan rekonsiliasi.
- Replay dead-letter memakai tuple terpilih `(id, order_id, attempts)`. Semua failure write replay, termasuk 503, melalui CAS di bawah lock order; tidak menimpa body/reason worker yang lebih baru. Success acknowledgement berada dalam transaksi record yang sama.
- Kegagalan 4xx menghabiskan budget. Pada attempts ≥10, replay otomatis dikarantina tetapi baris tetap **unresolved dan menahan expiry**. Lima puluh entri invalid tidak selamanya menghalangi entri valid ke-51. Jangan reset/decrement `attempts` untuk recovery karena juga berfungsi sebagai versi CAS.
- Retry checkout dilakukan pada owner transaksi penuh: maksimum tiga percobaan HTTP, bukan retry sebagian setelah deadlock. MySQL 1205 tidak diperlakukan sebagai deadlock 1213/40001. Service non-nested mempunyai kontrak retry tersendiri; keduanya tidak dikalikan.
- Nomor refund membawa seluruh UUID, muat `varchar(40)` hingga order ID 64-bit, dan memeriksa maksimal tiga kandidat di bawah lock order. Exhaustion menjadi validation error terlokalisasi; ledger/state tetap tidak berubah. Nomor historis dipertahankan.
- Status shipment delivered tidak dikalahkan oleh keberadaan AWB.

Bukti utama: `TransactionRemediationTest`, `RefundStateMachineTest`, `AmadisOrderTest`, `PaymentWebhookTest`, dan suite penuh. Retry HTTP SQLite memakai koneksi in-memory terpisah agar benar-benar menguji transaksi terluar, bukan memalsukan transaction level wrapper test.

### 3.3 Data dan pemulihan — FR-REM-003/004

- Harga input produk berupa rupiah integral dalam batas schema saat ini (maksimum Rp99.999.999); panjang image URL/path maksimum 255.
- Migrasi additive `000050` memperlebar empat nominal retur/refund; narrowing rollback melakukan preflight sebelum DDL. Migrasi `000060` menambah provenance nullable tanpa backfill dan menolak rollback bila provenance akan hilang. Rollback nullable lama juga memeriksa prasyarat sebelum DDL.
- Beranda memuat paling banyak 24 produk unggulan, bukan seluruh katalog. Link kategori/pencarian menuju katalog penuh. Sitemap di-stream dalam batch 500 dan mengabaikan slug kosong/whitespace.
- **DATA-08 export ditutup pada kontrak lokal:** `ProfileController::export()` kini memakai `StreamedJsonResponse` dan iterator `lazyById(1000)`, termasuk nested order items. Test dengan 1.003 record per riwayat owner/foreign dan satu order berisi 1.003 items membuktikan tidak ada history read saat respons dibentuk, emisi sebelum halaman berikutnya, query keyset dibatasi 1.000 baris, 12 key/tipe/proyeksi JSON tetap, serta tidak ada mutasi/kebocoran data foreign. Iterator dipakai agar `LazyCollection::JsonSerializable` tidak mematerialisasi seluruh data.
- **Trade-off export:** minimal satu query items per order, jadi query count bertambah; perlu profil latensi staging. Batas baris bukan batas byte/RSS, dan buffering proxy/browser belum diuji. Pembacaan best-effort, bukan snapshot atomik; perubahan konkuren dapat terlihat antarpages. Error setelah output dimulai dapat menghasilkan unduhan parsial. Header attachment/private/no-store dan allowlist privasi dipertahankan.
- Cache invalidation memakai namespace; reclaim terbatas 50 saat invalidasi dan command prune batch 100 per jam. Reclaimer ini khusus **direct DatabaseStore**; batas baris dihapus bukan jaminan biaya scan atau dukungan semua store.
- Backup memakai konfigurasi URL efektif, transport socket/TCP eksplisit, dan menolak opsi PDO/TLS yang belum didukung. Retensi invalid gagal sebelum penghapusan.
- Kompresi/checksum di-stream; SQLite diverifikasi terhadap snapshot sendiri melalui integrity/FK check, bukan terhadap DB hidup pada waktu berbeda. Kandidat backup SQLite invalid tidak menggusur backup baik terakhir.
- **Batas tetap:** hash/gzip MySQL bukan bukti SQL bisa direstore. Restore MySQL/MariaDB belum tersedia pada verifier ini. Publikasi dua file bukan transaksi filesystem durable terhadap power loss; checksum bukan autentikasi.

### 3.4 HCI/UI/UX — FR-REM-005

Identitas Bodoni Moda, Manrope, Sapphire/Linen/Gold/Ivory dipertahankan.

- Viewport canonical tidak lagi mewarisi ruang dock/scroller prototype.
- Favorit akun memakai API; favorit guest lokal dinyatakan terpisah dan tidak diam-diam di-merge. Search header/beranda memakai katalog lengkap.
- Perubahan bahasa menunggu persistensi lalu reload agar copy/accessible names tidak bercampur.
- Mutasi SET cart diserialisasi. Timeout mencakup header dan body. Intent checkout yang hasilnya belum pasti mempertahankan payload dan idempotency key; retry bukan membuat order baru.
- Pesan stok 422, rate limit, session expiry, dan kegagalan jaringan dibedakan tanpa membocorkan pesan internal. Error summary terkait form mendapat fokus dan hubungan ARIA yang sesuai.
- Admin memisahkan state tersimpan dari draft. Refresh gagal setelah save menampilkan peringatan data lama, bukan mengklaim seluruh tabel sudah baru.
- Tujuh overlay prototype memakai lifecycle fokus/inert/Escape/restore yang sama. Undo toast ditempatkan di overlay aktif dan mendapat pengujian keamanan/aksi.

Penerapan lensa: Norman—signifier/status; Nielsen—feedback dan recovery; Shneiderman—konsistensi, undo, closure; Cooper—mengurangi langkah berulang; Engelbart—ringkasan membantu keputusan operator; Brown—fitur minimum layak diuji; Krug—navigasi dan label jelas; Watson—keyboard, fokus, ARIA. **Markup/DOM test tidak membuktikan pengalaman screen reader, visual, atau WCAG penuh.**

### 3.5 MSI dan controlled processing — FR-REM-007

Route `GET /admin/reports/operations-summary` menggunakan guard admin, TOTP, `view-reports`, feature flag, dan no-store. `OperationalMetrics` memisahkan agregasi dari controller/view.

- Window UTC half-open, pilihan hari ini/7/30 hari/custom, maksimal 366 hari.
- Metrik kejadian dibedakan dari backlog seluruh umur; unit, cutoff `as_of`, cakupan parsial, dan status unknown ditampilkan.
- Agregat order/status, anomali pembayaran, reservasi kedaluwarsa/hold, dead-letter, outbox dan antrean memakai data/config yang tersedia. Metrik integritas yang belum dihitung **tidak dipalsukan nol**.
- Kontrak test membatasi service hingga **≤20 query** dan hanya statement baca SELECT/PRAGMA/SHOW/DESCRIBE. Pembacaan statis jalur normal slice mengidentifikasi sembilan SELECT; bukan jumlah query seluruh request autentikasi/layout atau batas latensi produksi.
- Pembacaan best-effort, bukan snapshot atomik antartabel. Tidak ada polling atau penyimpanan snapshot di localStorage.
- Refresh manual progresif mempertahankan laporan/`as_of` lama pada timeout/error dan menandainya **Data lama**. Respons terlambat tidak menimpa retry; banner validasi SSR dibersihkan hanya setelah refresh sukses tervalidasi. Form GET tetap bekerja tanpa JavaScript.
- JSON agregat tidak memuat PII pelanggan atau detail exception. HTML admin tetap dapat memuat identitas operator dan token CSRF yang sah; HTML tidak diklaim bebas seluruh identitas/token.

Default `ZAFIRA_FEATURE_ADMIN_OPS_SUMMARY_ENABLED=false`; rollout default 100 baru relevan bila enabled. Jangan mengaktifkannya di produksi sebelum query/engine, izin, dan usability staging dibuktikan. Panel baru memakai Bahasa Indonesia eksplisit, bukan klaim sudah dilokalisasi lima bahasa.

### 3.6 Operasi dan kualitas gate — FR-REM-006

- Guard test tidak lagi bergantung pada jumlah migrasi magic; tetap memeriksa manifest tepat, schema, driver, dan InnoDB untuk jalur MySQL yang diizinkan.
- CI memasukkan suite Python, anti-drift/failure propagation fail-closed, dan requirement review branch yang lebih aman pada script. **Remote branch protection tidak diterapkan oleh sesi ini.**
- Fallback SMTP→log yang dapat mengaku sukses palsu dihapus. Test tidak membuktikan email sampai inbox.
- Node minimum diselaraskan ke `^22.12.0 || >=24.0.0`; tidak meng-upgrade versi paket terkunci.
- Auth prune harian dan cache prune per jam dijadwalkan; konfigurasi jadwal bukan bukti scheduler produksi hidup.
- Sanitizer tidak lagi crash pada quoted key malformed, tidak membocorkan suffix valid-prefix, dan menggabungkan interval overlap. Fail-closed dapat memangkas diagnostik; tidak ada raw-output fallback.
- Supervisor mempertahankan identitas child tanpa reap sebelum cleanup. Observer Darwin diperbaiki setelah bukti nyata `waitid` masih mengenali child sementara `getpgid` menghasilkan ESRCH. PID mismatch/stale/reaped dan EPERM tetap ditolak.
- Symfony trusted-host statik di-reset **sesudah test** dalam `finally`; proteksi Host selama request dan aplikasi produksi tidak dilonggarkan. Ordered RED→GREEN membuktikan penyebab kegagalan full-only.

## 4. Fitur transaksi dan bagaimana data terbentuk

| Proses | Data yang dihasilkan/diubah | Batas produk nyata |
|---|---|---|
| Cart dan quote | Cart, item, kuantitas; quote harga server; lock identitas/cart | Request baca juga dapat mengunci/menulis metadata; bukan murni read-only |
| Checkout akun/tamu | Order, item snapshot, history awal, reservasi; stok/voucher; item cart dibersihkan | Satu intent memakai idempotency key; order lokal bukan bukti pembayaran provider |
| Pembayaran/webhook | Observasi payment ledger, transisi paid, history, reservasi committed, slot notifikasi | Signature dan status diverifikasi dalam desain; uji sesi ini memakai provider fake |
| Pending cancel/expiry | History, pelepasan reservasi, pengembalian stok/voucher, notifikasi | Paid cancel ditolak; unresolved dead-letter menahan expiry |
| Pemenuhan | Processing/shipped/completed, shipment dan tracking events | Manifest lokal bukan bukti kurir/serah terima nyata |
| Retur | Header retur, return items, nilai bersih/provenance | Legacy tak valid harus direkonsiliasi, bukan dinormalisasi diam-diam |
| Refund lokal | Permintaan/persetujuan/penolakan refund | Pencairan/settlement tetap ditolak tanpa verifikasi provider |
| Outbox/queue | Slot notifikasi, lease, job/reserve/delete bila database queue | At-least-once enqueue; bukan exactly-once inbox delivery |
| Concierge | `concierge_requests`, catatan personalisasi, mail job | **Bukan checkout**: test membuktikan cart/stok tetap dan tidak membuat order/reservasi/payment |
| Identitas, favorit, newsletter | Account/profile snapshot, auth events, favorites/subscriber | Kepemilikan dan verifikasi terpisah dari email sekadar cocok |

Analisis lengkap, formula, skenario, retensi, sumber dan 30 lensa berada di [TPS real-world](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md).

### Contoh volume yang dapat dihitung ulang

**Asumsi eksplisit:** model lifecycle lengkap historis dengan dua line produk, akun, tanpa voucher, satu event paid, tiga slot mail, tanpa retry, hingga completed. Bukan rata-rata trafik yang diobservasi.

- Checkout: `2L+2 = 6 INSERT` ke order/item/history/reservation; ditambah perubahan stok/cart dan penghapusan item cart.
- Lifecycle tersebut: **17 INSERT + 16 UPDATE logis + 2 DELETE = 35 perubahan baris bisnis**.
- Jika queue database: tiga notifikasi × (insert + reserve update + delete job) = **9 perubahan jobs**, sehingga total model **44**.
- Pada **1.000 order/hari selama 30 hari**: 510.000 INSERT bisnis, 480.000 UPDATE, 60.000 DELETE, ditambah 270.000 perubahan jobs = **1.320.000 perubahan baris**.
- Rata-rata aritmetisnya 0,01157 order/detik atau sekitar 0,5093 perubahan baris/detik menurut model 44, **bukan kapasitas maksimum yang diukur**. Peak, kegagalan, payload, indeks, cache/session, IO dan jumlah koneksi belum masuk.
- Overhead mutex jadwal dapat menjadi **8.264 perubahan baris/hari** pada asumsi satu scheduler, seluruh 2.066 run selesai, database lock tanpa kontensi, acquire/release pada pemeriksaan dan eksekusi. Bukan pertumbuhan bersih; `onOneServer`, cleanup tambahan dan retry dikecualikan.

**Peringatan interpretasi:** INSERT bukan total baris tersimpan; UPDATE/DELETE bukan data baru; perubahan logis bukan byte/IOPS/commit. Cohort order satu bulan dapat selesai pada bulan berikutnya. SC-B 70/25/5 lama mengandung paid-cancel yang sekarang ditolak dan **tidak executable**; tidak dibuat bobot bisnis pengganti. Model commit webhook lama `3W` ditarik, karena ack CAS kini satu transaksi dengan record. Model lifecycle harus diinstrumentasi ulang pada engine/workload staging sebelum sizing produksi.

## 5. Penilaian enam dimensi yang diminta

| Dimensi | Hasil perubahan | Bukti yang belum tersedia |
|---|---|---|
| **Performance** | Featured 24, sitemap streaming, cache namespace/reclaim, query budget MSI; build berhasil | Export sudah keyset-streaming, tetapi byte/RSS dan biaya query per order belum dibenchmark; p50/p95/p99, throughput saturasi, EXPLAIN engine target, peak/soak test, Core Web Vitals, byte storage |
| **Continuous availability** | Failure tidak disamarkan, retry penuh/bounded, quarantine, backup snapshot, scheduler dan supervisor lebih defensif | Restore MySQL/MariaDB, RPO/RTO, worker crash drill, cleanup browser native, deploy/rollback, SLO/error budget aktual |
| **Data integrity** | Ownership/lock/CAS, ledger append-only, cap uang, provenance, migration preflight, regression negatif | Dua koneksi InnoDB, isolation/deadlock fisik, restore produksi, provider idempotency/reconciliation |
| **Ease of use** | Status/pesan/fokus, navigasi katalog, bahasa konsisten, stale dashboard, SSR fallback/Undo | Browser aktual terbaru, screen reader, zoom/mobile/kontras komputasi, uji pengguna dan terjemahan oleh penutur |
| **Modular growth** | Service alokasi/retry/metrik terpisah, flag fitur, kontrak domain dan dokumentasi, tanpa rewrite | Bukti scaling bukan konsekuensi otomatis modularitas; workload produksi dan organisasi operasi belum diuji |
| **Controlled processing** | Guard/TOTP/ability, event/version/cap, bounded replay, audit evidence dan gate fail-closed | Rekonsiliasi operator, monitoring/alert nyata, remote protection, delivery mail/provider dan kebijakan bisnis |

## 6. Review independen dan lensa profesional

| Scope | Reviewer session berbeda dari author | Keputusan terbatas |
|---|---|---|
| SEC dan fixture autentikasi/host isolation | `4a87e550-206a-4389-8929-ba10cafaffc7` | APPROVE source; ditambah runtime integrator |
| TXN, provenance, replay, nomor refund | `88fc481b-b7cd-4d36-a1e2-d41c567d49ca` | APPROVE source; InnoDB/provider tetap gate |
| DATA/BACKUP, kontrak DOC, test inquiry main | `a3568e6b-70f7-48d9-8e67-99595380745b` | APPROVE source/docs; backup MySQL tidak dianggap restore-verified |
| UI/ADMIN-UI, fixture keamanan/Undo | `0ad08d07-3e0b-4d70-a6fd-35f1bd4b8225` | APPROVE source; browser/WCAG belum terbukti |
| OPS, sanitizer, observer Darwin | `c7bc6b8f-e74e-494d-a99c-4bd79edfad05` | APPROVE bounded source review; cleanup platform belum lulus |
| MSI dan delta analisis TPS | `baba615a-5f4d-4965-82f6-70194d773b5d` | APPROVE source/delta; bukan pengukuran kapasitas |

Seluruh reviewer tidak dianggap menjalankan test yang hanya dijalankan integrator. Nama dan ruang tulis author serta proses rework tercatat dalam [ledger](../04-implementation/TEAM_SUPPORT_LEDGER.md). Format otomatis sesudah review tidak dimaksudkan mengubah perilaku.

Tiga puluh lensa profesional dipetakan dalam dokumen TPS: HCI/aksesibilitas/produk, Fowler/Evans/Beck, konsistensi Kleppmann/Lamport, data Stonebraker, performa Knuth/Gregg, reliability/operasi, threat modeling dan keamanan, web terbuka, serta konteks Indonesia. **Status riset sumber luar: 10 dilaporkan memadai, 14 parsial, 6 belum terverifikasi**. Ini bukan 30 verifikasi primer baru atau endorsement pribadi. Keputusan keamanan/data terutama bertumpu pada source/framework dan reproducer, bukan kutipan tokoh tanpa bukti.

Skill proses yang digunakan: `incremental-implementation`, `laravel-tdd`, `laravel-verification`, `git-workflow-and-versioning`, `debugging-and-error-recovery`, `doubt-driven-development`, `code-review-and-quality`, `documentation-and-adrs`; lane menambahkan skill Laravel, API, frontend, accessibility, CI, dan cross-platform contract audit. Skill browser dibaca tetapi Chrome DevTools MCP tidak tersedia. Skill video, media, mobile dan framework yang tidak dipakai proyek tidak diterapkan karena tidak relevan; tidak mengarang penggunaan semua skill.

## 7. Gate sebelum real-world testing dan produksi

| Gate terbuka | Tindakan dan pemilik yang diperlukan | Kriteria penerimaan |
|---|---|---|
| **Platform/browser** | Operator menyediakan runner dengan izin inspeksi dan signal child miliknya serta pasangan Playwright/Chromium yang kompatibel | Cleanup seluruh proses milik runner terbukti, lalu browser storefront/admin/MSI, keyboard, viewport, timeout/retry lulus; tanpa memakai profil pribadi |
| **Migrasi/engine** | DBA menjalankan migrasi normal pada schema disposable engine/version target; quiesce writer saat rollback drill | BIGINT/nullable/provenance, upgrade/down preflight, dua koneksi lock/race, deadlock/timeout, tanpa kehilangan histori |
| **Backup** | DBA/operasi melakukan restore MySQL/MariaDB disposable dengan opsi transport efektif | Skema/data/FK/count tervalidasi, backup terakhir tetap baik, RPO/RTO tercatat |
| **Pembayaran/refund/kurir/email** | Owner bisnis dan integrasi memilih kebijakan/provider, memakai sandbox resmi sebelum transaksi nyata | Pembayaran dibuat dan direkonsiliasi end-to-end; refund terverifikasi; delivery SMTP/webhook; tidak membuka fail-closed hanya untuk demo |
| **Performa** | QA/performance menetapkan workload berbasis data/target bisnis dan engine, network, hardware | p50/p95/p99, error rate, saturation, backlog, recovery/soak, query plan dan storage growth terukur; rumus proyeksi tidak dijadikan hasil benchmark |
| **Usability/aksesibilitas** | QA dan pengguna representatif, termasuk keyboard/screen reader | Alur nyata selesai, fokus/kontras/zoom diuji, hambatan persona dan terjemahan dicatat |
| **Operasi rilis** | Pemilik rilis menerapkan/mengecek worker, scheduler, alert, backup, branch protection dan rollback | Bukti staging bertimestamp, runbook dapat diikuti operator lain, tidak hanya script tersedia |

Schema/secret/provider nyata tidak tersedia atau tidak diakses dalam sesi ini. Jangan mengirim credential ke chat. Gate tersebut **bukan diminta untuk diabaikan**, dan tidak dapat ditutup dengan menambah label “100%”.

### Dampak migrasi dan aktivasi

- Terapkan `database/migrations/2026_10_21_000050_widen_return_refund_money_columns.php` dan `2026_10_21_000060_add_return_allocation_provenance.php` sebelum writer retur versi baru dipakai. Uji pada clone/disposable terlebih dahulu; tidak ada migrasi operasional dijalankan di sesi ini.
- Setelah provenance dipakai, downgrade aplikasi lama tidak otomatis aman. Guard down sengaja menolak penghapusan provenance; jangan menghapus data untuk memaksa rollback.
- Sesi persisted tanpa fingerprint perlu login ulang. Kode recovery/enrolment kadaluwarsa memerlukan enrolment ulang sesuai flow.
- Pertahankan MSI off sampai gate query/engine dan operator selesai. Data unknown bukan indikator sehat; jangan memakai report partial untuk mengeksekusi pemulihan otomatis.
- Dead-letter quarantine memerlukan SOP rekonsiliasi; tidak boleh mengubah `attempts` ke nol atau unconditional resolve untuk melepas stok.

## 8. Continuous Evaluation Record dan batas akhir

- **Baseline:** audit AUD-20261021 dan sembilan probe, SHA di header; dokumen baseline dipertahankan.
- **Hipotesis:** invariant antar-tahap, ownership, pengelolaan error/versi dan drift test/dokumentasi lebih berisiko daripada rewrite visual.
- **Controlled change:** lane dengan satu penulis per file, RED → implementasi → review terpisah → integrasi serial → koreksi fixture/source berbukti. Tidak ada assertion penting atau gate yang dihapus untuk mengejar hijau.
- **Verification:** command/waktu/exit pada metadata evidence. R7, targeted inquiry, dan full final dibedakan; full final benar-benar dijalankan sesudah tambahan assertion. `php-full-export-final.json` menyertakan hash manifest source terbaru sesudah slice export; pemeriksaan penutupan memeriksa drift terhadap manifest tersebut.
- **Comparison:** bug baseline spesifik mendapat negative/regression tests; test lebih banyak dan hijau tidak menghapus batas engine/browser/provider.
- **Reviewer:** sesi berbeda dari author pada tabel §6; integrator menerima scoped source review. Reviewer laporan menutup tiga koreksi faktual tentang budget query, batas plaintext/ciphertext, dan supersession run. `closure-check.json` pada 16:20:45 UTC merupakan histori 131 file sebelum tambahan export. Pemeriksaan terbaru memakai `closure-check-export.json` terhadap manifest132; target tautan diperiksa keberadaannya, bukan validasi anchor/URL eksternal. Approval laporan akhir tidak sama dengan izin rilis.
- **Tidak diperiksa:** seluruh kombinasi state/input, database/secret operasional, environment deployment, provider nyata, multi-node failure, screen reader, coverage persen, beban/latensi produksi, remote branch settings, serta validasi bisnis/hukum/keaslian produk.
- **Decision:** hasil lokal dapat diserahkan untuk review dan persiapan uji terkontrol; **real-world end-to-end/produksi BLOCKED pada gate §7**, bukan “semua selesai tanpa kendala”. DATA-08 export kini lulus kontrak streaming/privasi lokal; pengukuran performa engine target tetap terbuka. DOC-06/arsip di luar rekonsiliasi dan workflow after-sales/provider belum dinyatakan selesai. Local verification adalah penerimaan perubahan yang diuji, bukan penutupan semua temuan audit.

Berkas log lokal mungkin diabaikan Git; metadata dan dokumen tidak otomatis membuat seluruh log ikut version control. Sebelum memindahkan bukti, gunakan sanitasi/export terkontrol, jangan bundel fixture credential, environment, database, atau backup pelanggan.
