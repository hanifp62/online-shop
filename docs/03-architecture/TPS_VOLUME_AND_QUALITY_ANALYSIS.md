# Analisis TPS, Volume Data, dan Kualitas Pemrosesan ZAFIRA

**Tanggal pemeriksaan:** 21 Oktober 2026  
**Metode:** pembacaan kode dan migrasi lokal; kalkulasi deterministik tanpa koneksi database.  
**Batas:** analisis implementasi pada working tree, bukan sertifikasi kesiapan produksi.

## 1. Kesimpulan dan status bukti

ZAFIRA memiliki **Transaction Processing System (TPS)** untuk checkout, reservasi stok, pencatatan pembayaran, perubahan status pesanan, pengiriman, serta permintaan retur/refund. TPS di sini adalah sistem pemrosesan transaksi bisnis, **bukan** angka *transactions per second*. Untuk menghindari ambiguitas, dokumen memakai satuan `order/detik`, `event/detik`, dan `baris INSERT/order` secara terpisah.

**Fakta terverifikasi dari kode:** checkout baru dengan `L` baris produk berbeda menghasilkan `2L + 2` baris INSERT inti, ditambah `L` perubahan stok dan kemungkinan satu perubahan kuota voucher. Dalam contoh lifecycle lengkap dengan dua baris produk, satu event pembayaran, dan tiga notifikasi, total menjadi **17 baris INSERT/order**, bukan enam baris checkout saja.

**Asumsi model:** 100, 1.000, dan 10.000 order/hari adalah skenario perencanaan. Angka tersebut **bukan trafik produksi aktual** dan tidak membuktikan kapasitas sistem.

**TIDAK TERBUKTI:** throughput maksimum, latency produksi, SLA/SLO, uptime, kapasitas konkurensi, konfigurasi infrastruktur aktif, serta keberhasilan pemulihan MySQL/MariaDB. Tidak ada benchmark beban yang dijalankan dalam pekerjaan ini.

Label bukti dalam dokumen:

- **Fakta:** ditemukan pada kode/migrasi yang disebutkan; belum otomatis membuktikan konfigurasi deployment.
- **Inferensi:** konsekuensi teknis yang masuk akal dari kode, belum diukur.
- **Asumsi:** input eksplisit untuk kalkulasi, bukan observasi pengguna nyata.
- **Informasi belum tersedia:** data operasional atau pengujian yang tidak diperoleh.
- **Rekomendasi:** langkah terbuka, bukan fitur yang sudah selesai.

## 2. Peta transaksi dan batas implementasi

| Proses | Bukti implementasi | Batas klaim |
|---|---|---|
| Checkout pelanggan dan tamu | `app/Services/CheckoutService.php:121`, `:153`, `:277`, `:291`; endpoint `routes/web.php:127` | Order lokal, stok, dan snapshot; bukan bukti uang berhasil dipindahkan. |
| Verifikasi pembayaran Midtrans | `app/Services/PaymentWebhookService.php:30`, `:103`, `:142`; `routes/web.php:124` | Signature, Status API, nominal, ledger, dan transisi dibaca dari kode; koneksi provider nyata tidak diuji. |
| Siklus status pesanan | `app/Models/Order.php:12`; `app/Services/CheckoutService.php:425`, `:474` | `pending → paid → processing → shipped → completed`; pembatalan hanya mengikuti state machine. |
| Pengiriman dan tracking lokal | `app/Services/CheckoutService.php:492-507` | Membuat shipment dan event lokal tidak membuktikan integrasi kurir atau pengiriman fisik. |
| Retur | `app/Http/Controllers/OrderReturnController.php:33-87`, `:95-135`; `routes/web.php:204-205` | Validasi eligibility dan pencatatan permintaan; bukan bukti barang telah diterima gudang. |
| Refund | `app/Services/RefundService.php:24-48`, `:50-99`; `routes/web.php:203`, `:251` | Permintaan dan review tersedia. Status yang memerlukan ledger provider ditolak pada `RefundService.php:72`. Pencairan refund otomatis **TIDAK TERBUKTI**. |
| Notifikasi | `app/Services/CheckoutService.php:458-470`; `app/Services/OrderNotificationDispatcher.php:17-66` | Klaim slot dan dispatch tersedia; dispatch bukan bukti email tiba di inbox. |
| Pelepasan reservasi | `app/Jobs/ReleaseExpiredReservations.php`; `routes/console.php:93` | Jadwal setiap menit ada; eksekusi scheduler produksi belum diverifikasi. |

### Atelier, sertifikat, dan WIP

Nilai layanan `atelier` ada dalam validasi concierge (`app/Http/Controllers/StorefrontController.php:147`). Hal ini **tidak setara** dengan modul produksi atelier, routing pekerjaan, kapasitas artisan, sertifikat autentikasi, atau pencatatan WIP (*work in progress*).

Route `/prototype` mengarah ke aset statis (`routes/web.php:109`). Dalam penelusuran lokal `app`, `database`, dan `routes`, tidak ditemukan bukti memadai untuk mengesahkan backend certificate/WIP. Rancangan atau layar atelier/certificate/WIP harus diperlakukan sebagai **prototype/desain, bukan fitur backend terverifikasi**, sampai ditemukan model, migrasi, service, endpoint, serta uji alur lengkapnya. Ketiadaan hasil pencarian istilah bukan pembuktian absolut bahwa konsep tersebut tidak pernah ada dengan nama lain.

Dokumen lama `docs/03-architecture/DATABASE_DESIGN.md:156-187` masih menempatkan `orders` sebagai rencana masa depan. Untuk analisis ini, kode dan migrasi aktual mengalahkan status historis dokumen tersebut. Dokumen lama tidak diubah karena di luar cakupan.

## 3. Model beban tulis checkout

### 3.1 Definisi

`L` adalah jumlah baris produk berbeda pada cart yang lolos validasi, **bukan jumlah unit barang**. Membeli lima unit dari satu produk tetap satu line. Batas kuantitas per line adalah 99 (`CheckoutService.php:22`, `:40-49`).

Model berlaku untuk **checkout baru yang berhasil**, bukan replay idempotensi, transaksi rollback, atau percobaan gagal. Harga, biaya pengiriman, pajak, alamat, dan voucher disimpan sebagai bagian snapshot order/item; bukan masing-masing tabel baru per checkout.

| Operasi inti | Baris terdampak | Jenis | Sumber |
|---|---:|---|---|
| Pengurangan stok produk, dengan syarat stok cukup | `L` | UPDATE | `app/Services/CheckoutService.php:291-306` |
| Klaim voucher bila ada dan memenuhi syarat | `0 atau 1` | UPDATE | `app/Services/CheckoutService.php:408-416` |
| Header pesanan | `1` | INSERT | `app/Services/CheckoutService.php:317-333` |
| Snapshot item pesanan | `L` | INSERT | `app/Services/CheckoutService.php:334-336` |
| Riwayat awal `pending` | `1` | INSERT | `app/Services/CheckoutService.php:337` |
| Reservasi stok dengan expiry 60 menit | `L` | INSERT | `app/Services/CheckoutService.php:338-343` |

Dengan `V ∈ {0,1}` untuk voucher:

```text
INSERT inti/order = 1 + L + 1 + L = 2L + 2
UPDATE stok/voucher saat checkout = L + V
Contoh L=1: 4 INSERT; L=2: 6 INSERT; L=3: 8 INSERT.
```

Ini hitungan **baris yang ditambahkan**, bukan jumlah query keseluruhan, disk I/O, maupun transaksi database per detik. SELECT harga, pencarian idempotensi, validasi voucher, lock, commit, index maintenance, dan overhead ORM tetap memakai sumber daya.

### 3.2 Biaya di luar rumus inti

Checkout login mengambil lock user dan mencari order dengan idempotency key (`CheckoutService.php:277-287`). Cart memiliki transaksi/lock sendiri melalui `app/Services/CartStore.php`; sinkronisasi dapat memakai `insertOrIgnore` cart dan `updateOrInsert` item. Pembersihan item setelah checkout melibatkan DELETE serta pembaruan cart. Operasi ini tidak boleh dihitung sebagai order_items baru.

Tabel auxiliary juga dapat menerima aktivitas: antrean job/failed job, cache dan cache lock, session, notifikasi, dead letter, serta log. Default file konfigurasi adalah database untuk queue (`config/queue.php:16`) dan cache (`config/cache.php:21`), tetapi driver aktif **TIDAK TERBUKTI** tanpa verifikasi runtime. Default DB SQLite (`config/database.php:8`) juga bukan bukti engine produksi.

Retry lock dibatasi tiga retry setelah percobaan awal, dengan backoff, lalu respons 503 (`CheckoutService.php:196-226`). Jadi maksimum empat percobaan; retry menambah pekerjaan tanpa menambah order bisnis yang sah. Rumus pertumbuhan baris tidak menangkap biaya percobaan yang rollback.

## 4. Total lifecycle: jangan berhenti pada checkout

### 4.1 Rumus umum baris baru

Definisikan:

- `T`: transisi status sesudah `pending`.
- `P`: event pembayaran unik yang tercatat; bukan jumlah order atau jumlah webhook diterima.
- `N`: slot notifikasi baru, bergantung status dan keberadaan email.
- `S`: shipment baru; `E`: event tracking baru.
- `A`: permintaan retur baru; `R`: total line retur baru.
- `F`: permintaan refund baru.

```text
INSERT lifecycle/order = (2L + 2) + T + P + N + S + E + A + R + F
```

Rumus mengasumsikan event tersebut terkait order yang dianalisis. Ledger untuk unknown order, dead letter, queue, cart, audit/log, dan kejadian tambahan harus dihitung terpisah. Tidak semua lifecycle akan selesai, dan tidak semua selesai dalam periode checkout yang sama.

Setiap transisi yang berhasil menambah satu history dan memperbarui order melalui pembandingan status lama (`CheckoutService.php:474-485`). Notifikasi hanya untuk `paid`, `shipped`, `completed`, `cancelled` (`app/Mail/OrderStatusChanged.php:19-24`). Status `processing` **tidak** menambah notifikasi.

Untuk jalur lengkap berikut:

```text
pending → paid → processing → shipped → completed
T=4; P=1; N=3; S=1; E=2; A=R=F=0
INSERT/order = 2L + 2 + 4 + 1 + 3 + 1 + 2 = 2L + 13
L=2 → 17 baris/order
```

Komposisi untuk `L=2`: orders 1, order_items 2, history 5, reservations 2, payments 1, notifications 3, shipments 1, tracking events 2. Total **17**. Menghitung hanya tiga transisi akan menghasilkan 16 dan salah untuk jalur ini.

### 4.2 Update dan variasi lifecycle

- Pembayaran memeriksa duplikat dan mencatat event unik (`PaymentWebhookService.php:144-185`). Constraint unik memakai provider, transaction ID, dan event status (`database/migrations/2026_10_20_000003_create_payments_table.php:23-26`). Capture dan settlement berbeda dapat menghasilkan lebih dari satu baris; karena itu `P=1` hanya asumsi skenario.
- `paid` mengubah `L` reservasi `reserved → committed`. Pemanggilan ulang kondisi ini saat `processing/completed` dapat menjalankan UPDATE tanpa baris cocok (`CheckoutService.php:510-511`). Jangan menghitungnya sebagai INSERT atau selalu `L` perubahan baru.
- `shipped` membuat shipment jika belum ada dan satu event; `completed` memperbarui shipment dan menambah event (`CheckoutService.php:492-507`).
- Dispatcher memperbarui lease dan `dispatched_at`; operasi itu tidak menambah slot notifikasi baru (`OrderNotificationDispatcher.php:24-48`). Retry/queue delivery memiliki overhead terpisah.
- Pembatalan memperbarui reservasi yang masih reserved, mengembalikan stok per item, dan kemungkinan kuota voucher (`CheckoutService.php:514-520`). Jumlah reservasi yang berubah bergantung status sebelumnya.
- Retur menambah `1 + R` baris; validasi membatasi pengajuan sampai 50 item (`OrderReturnController.php:33-87`). Refund request menambah satu baris (`RefundService.php:24-48`). Tidak boleh memasukkan ledger pencairan refund sebagai fitur aktif karena jalur itu fail-closed.

## 5. Skenario volume dan pertumbuhan data

### 5.1 Asumsi eksplisit

1. `D` order baru berhasil per hari; rata-rata dibagi 86.400 detik.
2. Setiap order memiliki `L=2` line berbeda.
3. Skenario lifecycle mengikuti jalur lengkap bagian 4, satu event pembayaran dan tiga notifikasi.
4. Tidak ada retur/refund, pembatalan, penghapusan, arsip, atau purge dalam proyeksi dasar.
5. Peak hipotetis adalah `10 × rata-rata`, bukan pola trafik yang teramati.
6. Kolom lifecycle menunjukkan **hasil akhir cohort order**, bukan janji semua event terjadi dalam 30/365 hari yang sama.

| Order/hari | Rata-rata order/detik | Peak asumsi 10×, order/detik | Order/30 hari | Order/365 hari |
|---:|---:|---:|---:|---:|
| 100 | 0,001157 | 0,011574 | 3.000 | 36.500 |
| 1.000 | 0,011574 | 0,115741 | 30.000 | 365.000 |
| 10.000 | 0,115741 | 1,157407 | 300.000 | 3.650.000 |

| Order/hari | INSERT inti/30 hari (6/order) | INSERT inti/365 hari | INSERT lifecycle/30 hari (17/order) | INSERT lifecycle/365 hari |
|---:|---:|---:|---:|---:|
| 100 | 18.000 | 219.000 | 51.000 | 620.500 |
| 1.000 | 180.000 | 2.190.000 | 510.000 | 6.205.000 |
| 10.000 | 1.800.000 | 21.900.000 | 5.100.000 | 62.050.000 |

Rumus reproduksi:

```text
order/detik = D / 86400
orders periode = D × H, H ∈ {30,365}
INSERT checkout periode = (2L + 2) × D × H
INSERT lifecycle periode = (2L + 13) × D × H
```

Untuk 10.000 order/hari selama 365 hari, komposisi cohort lifecycle adalah: 3.650.000 orders; 7.300.000 items; 18.250.000 history; 7.300.000 reservations; 3.650.000 payments; 10.950.000 notifications; 3.650.000 shipments; 7.300.000 tracking events. Jumlahnya **62.050.000**.

**Verifikasi kalkulasi:** PHP CLI dijalankan lokal tanpa database, exit 0:

```php
foreach ([100, 1000, 10000] as $d) {
    printf("%d | %.6f | %.6f | %d | %d | %d | %d | %d | %d\n",
        $d, $d / 86400, 10 * $d / 86400, $d * 30, $d * 365,
        6 * $d * 30, 6 * $d * 365, 17 * $d * 30, 17 * $d * 365);
}
if (2 * 2 + 2 + 1 + 4 + 3 + 1 + 2 !== 17) {
    exit(1);
}
```

```text
100 | 0.001157 | 0.011574 | 3000 | 36500 | 18000 | 219000 | 51000 | 620500
1000 | 0.011574 | 0.115741 | 30000 | 365000 | 180000 | 2190000 | 510000 | 6205000
10000 | 0.115741 | 1.157407 | 300000 | 3650000 | 1800000 | 21900000 | 5100000 | 62050000
```

### 5.2 Baris bukan ukuran penyimpanan

Ukuran GB **TIDAK TERBUKTI**. JSON snapshot alamat/payload, panjang teks, indeks, overhead engine, WAL/binlog, dead tuples, replica, backup, dan retensi memengaruhi ukuran fisik.

Rekomendasi model lanjutan:

```text
data logis ≈ Σ(baris tabel i × rata-rata byte aktual tabel i)
kapasitas disk = data + indeks + log + ruang operasi + cadangan sesuai kebijakan
```

Ukur byte/baris pada dataset representatif dan engine target. Jangan mengalikan jumlah order dengan satu ukuran asumsi lalu menyebutnya kapasitas produksi. Pertumbuhan queue/dead letter dapat melonjak saat provider bermasalah meski order baru tidak bertambah.

## 6. Enam dimensi performance

Enam dimensi berikut adalah kerangka evaluasi teknis dokumen ini, bukan enam metrik yang sudah diukur.

| Dimensi | Fakta/inferensi berbasis kode | Pengukuran yang diperlukan |
|---|---|---|
| 1. Latency/response time | Webhook melakukan HTTP Status API sinkron sebelum transaksi ledger (`PaymentWebhookService.php:76`, `:103`, `:189-205`). Timeout 5 detik; maksimal dua percobaan untuk connection exception, dengan jeda 200 ms. Ini dapat menahan worker, tetapi bukan angka latency hasil ukur. | p50/p95/p99 checkout, webhook, detail order; pisahkan waktu DB, lock, provider, dan antrean. |
| 2. Throughput | Satu order memicu beberapa fase transaksi. Rate limit bukan kapasitas server. | Successful order/detik, event pembayaran/detik, commit/detik; laporkan error dan duplikasi, bukan HTTP 200 saja. |
| 3. Konkurensi dan contention | Lock user (`CheckoutService.php:280`), UPDATE stok bersyarat (`:306`), CAS status (`:476`), serta session blocking (`routes/web.php:127`) mengontrol konflik. Inferensi: produk populer dan user yang sama dapat menjadi titik serialisasi. | Banyak user/produk versus satu SKU panas; lock-wait, deadlock, retry, 409/503, invariant stok. |
| 4. Efisiensi sumber daya | INSERT/index, SELECT, retry, dan HTTP menambah biaya di luar `2L+2`. Queue/cache default database dapat berbagi sumber daya DB. | CPU, RSS worker, koneksi, IOPS, query/order, durasi transaksi, konsumsi provider call. |
| 5. Skalabilitas dan kapasitas | Indeks status/tanggal/FK disiapkan (`database/migrations/2026_10_19_000004_add_performance_and_foreign_indexes.php:16-40`). Adanya indeks tidak membuktikan query plan optimal. | EXPLAIN pada volume 30/365 hari, kurva beban terhadap latency, ukuran indeks dan dataset, batas connection pool. |
| 6. Stabilitas dan freshness pekerjaan latar | Release reservasi tiap menit; replay pembayaran/notifikasi tiap lima menit (`routes/console.php:91-93`). Frekuensi jadwal bukan batas keterlambatan aktual. | Soak test, queue age, scheduler lag, backlog, retry storm, waktu sampai status pelanggan konsisten. |

Hasil integrator **977 passed, 4 skipped, 27.351 assertions, 42,65 detik** merupakan informasi yang diterima, bukan eksekusi ulang oleh penulis. Angka tersebut bukan throughput produksi, benchmark, ataupun bukti coverage performance. Membagi jumlah tes dengan durasi suite untuk memperoleh TPS adalah tidak sah.

## 7. Lima karakteristik kualitas TPS

### 7.1 Continuous availability

**Fakta:** tersedia retry lock, circuit breaker pembayaran, dead letter, retry notifikasi, release reservasi, dan jadwal backup. Circuit membuka setelah lima kegagalan dengan jendela satu menit (`PaymentWebhookService.php:23-25`, `:69-81`). HTTP provider dilakukan sebelum transaksi ledger, bukan saat lock order di `record()` dipegang.

**Inferensi:** kontrol tersebut membantu pemulihan gangguan sementara, tetapi tidak menghilangkan ketergantungan pada DB, cache, scheduler, queue worker, dan provider. Circuit terbuka menghasilkan 503; itu degradasi terkendali, bukan continuous availability penuh.

**Gap:** topology HA, failover, supervision worker, shared cache, health alerting, RPO/RTO, offsite backup, dan restore drill produksi belum dibuktikan. `routes/console.php:94-96` secara eksplisit membatasi verifikasi backup otomatis pada SQLite. Backup MySQL/MariaDB tidak boleh disebut sudah teruji pulih.

**Rekomendasi:** sepakati SLO/RPO/RTO, monitor proses latar, uji provider outage, dan lakukan restore drill engine target pada lingkungan terisolasi.

### 7.2 Data integrity

**Fakta:** checkout memakai transaksi/retry, pengurangan stok bersyarat, idempotensi, dan state transition CAS. Payments mempunyai unique key event. Reservasi mempunyai FK dan indeks status/expiry (`database/migrations/2026_10_20_000002_create_inventory_reservations_table.php:11-21`). Verifikasi nominal dan status provider dilakukan sebelum status paid diterima (`PaymentWebhookService.php:86-104`, `:158-168`).

**Batas:** transaksi DB lokal tidak menjamin exactly-once side effect eksternal. Pembayaran sukses pada order non-pending dicatat sebagai `order_not_payable` dan memerlukan penanganan manual (`PaymentWebhookService.php:162-166`). Kode ledger refund setelah exception bukan bukti refund bekerja.

**Rekomendasi:** uji race expiry versus settlement, replay event, duplicate checkout, SKU terakhir, cancellation, dan crash setelah commit/sebelum dispatch. Rekonsiliasi provider dan ledger harus memiliki owner operasional.

### 7.3 Ease of use

**Fakta:** tersedia route order history/detail, tracking tamu, cancellation, eligibility retur, dan refund (`routes/web.php:128-129`, `:198-205`). Pesan lifecycle memakai locale penerima (`CheckoutService.php:489-505`).

**TIDAK TERBUKTI:** keterpahaman pengguna, WCAG aktual, kecepatan menyelesaikan checkout, responsivitas mobile, dan keberhasilan browser flow pada sesi ini.

**Lensa HCI:** Norman/Nielsen menuntut status pembayaran dan pengiriman tidak menyesatkan; Shneiderman menuntut penutupan transaksi dan pemulihan; Cooper/Krug menuntut label singkat serta minim input ulang. Engelbart/Brown mendukung informasi operasional yang membantu keputusan, bukan dashboard simulasi yang dianggap data nyata.

**Rekomendasi:** evaluasi browser terpisah untuk pending/paid, error, duplicate-submit, keyboard, fokus, pembaca layar, dan mobile. Jangan menampilkan refund selesai sebelum bukti provider tersedia. Tidak ada agent browser yang dijalankan atau dihentikan dalam analisis ini.

### 7.4 Modular growth

**Fakta:** checkout, webhook, refund, cart, dan notification dispatch dipisah sebagai service. Orders, items, reservations, payments, shipments, returns, refunds, dan history dipisah sebagai entitas penyimpanan.

**Inferensi:** pemisahan tersebut memberi titik perluasan tanpa rewrite storefront, tetapi bukan bukti modul dapat diskalakan independen. Checkout masih mengorkestrasi stok, voucher, status, shipment, dan notifikasi dalam domain terkait.

**Rekomendasi:** pertahankan batas modul yang sudah ada. Tambahkan integrasi refund provider atau atelier hanya setelah kontrak, requirement ID, acceptance criteria, dan uji invariant tersedia. Hindari microservice/partitioning sebelum bukti bottleneck menuntutnya.

### 7.5 Controlled processing

**Fakta:** validasi cart, batas jumlah item, rate limiting, lock/session blocking, state machine, signature webhook, dan gate admin terdapat pada jalur transaksi. Endpoint admin order/refund memakai permission (`routes/web.php:244-251`). Pembayaran manual diblokir (`app/Models/Order.php:25`; `CheckoutService.php:431-432`).

`routes/web.php:124` membatasi webhook `60,1`; cakupan key limiter dan proxy harus diverifikasi sebelum menganggapnya aman untuk banyak callback dari provider. Batas ini adalah admission control, bukan hasil benchmark.

**Gap:** pemantauan pelanggaran, audit tamper resistance, hak akses operasional produksi, retensi PII, dan efektivitas shared rate-limit store belum dibuktikan. Dead letter menyimpan raw body (`PaymentWebhookService.php:131-138`); akses, logging, dan purge harus diperiksa sebagai data sensitif.

**Rekomendasi:** dashboard rekonsiliasi, alert backlog, audit perubahan status, serta prosedur pemulihan manual yang memisahkan approval dari bukti pencairan.

## 8. Rencana verifikasi yang direkomendasikan

Ini daftar usulan NFR khusus analisis ini; **bukan klaim requirement sudah disetujui atau dikerjakan**.

| ID usulan | Acceptance criteria terukur sebelum klaim kualitas |
|---|---|
| NFR-TPS-001 | Benchmark menyebut engine/version, hardware, workers, dataset, mix request, L, lama uji, throughput berhasil, p50/p95/p99, error, dan lock waits. Target lulus disetujui sebelum eksekusi. |
| NFR-TPS-002 | Uji paralel SKU terakhir dan replay checkout membuktikan stok tidak negatif serta tidak ada order ganda untuk identitas idempotensi yang sama. |
| NFR-TPS-003 | Race pembayaran/expiry dan replay webhook menghasilkan ledger konsisten; order non-payable masuk rekonsiliasi tanpa klaim paid palsu. |
| NFR-TPS-004 | Outage provider/worker lalu recovery tidak kehilangan transaksi lokal; queue age dan recovery time dicatat terhadap target yang disetujui. |
| NFR-TPS-005 | Restore terisolasi engine produksi membuktikan integritas data dan RPO/RTO aktual; jadwal backup saja tidak memenuhi kriteria. |
| NFR-TPS-006 | Model storage memakai ukuran tabel/indeks nyata, kebijakan retensi, dan faktor backup; membedakan volume cohort dari ukuran disk. |
| NFR-TPS-007 | Browser QA menyelesaikan checkout/status/retur dengan keyboard dan mobile; error/refund pending tidak memberi konfirmasi palsu. |

Mulai dari baseline read-only, lalu pengujian pada data sintetis terisolasi. Jangan mengirim transaksi nyata atau beban ke produksi tanpa izin. Jalankan ramp, burst, hot-SKU, dan soak; hentikan saat ambang error/latency yang disepakati terlampaui. Ulangi setelah satu perubahan terkontrol untuk membandingkan baseline.

## 9. Jejak pemeriksaan, tidak diperiksa, dan risiko tersisa

### Pemeriksaan yang dilakukan

- Membaca checkout, payment webhook, refund, controller retur, cart, notification dispatcher, model order, routes, dan migrasi terkait.
- Membandingkan alur state machine dengan perhitungan lifecycle; koreksi penting: empat transisi setelah pending menghasilkan lima history total.
- Mengeksekusi kalkulasi PHP lokal tanpa DB untuk seluruh skenario tabel.
- Memakai prinsip bukti dari `docs/04-implementation/AGENT_OPERATING_MODEL.md` dan `docs/04-implementation/ENGINEERING_EXCELLENCE_PROTOCOL.md`.
- Skill yang dipakai: `task-observer`, `ponytail`, `documentation-and-adrs`, `ponytail-audit`. Tidak membuat ADR atau log observasi terpisah karena cakupan hanya satu file. Skill browser/benchmark tidak dieksekusi karena pekerjaan ini analisis lokal, bukan pengujian runtime.
- Lensa evaluasi: Evans/Beck untuk domain dan pengujian; Kleppmann/Lamport untuk concurrency; Schneier untuk trust boundary; Stonebraker/Fowler/Vogels untuk skema dan pertumbuhan; Gregg/Knuth untuk pengukuran; Hudson/Purbo/Berners-Lee untuk dokumentasi yang dapat ditelusuri. Ini review penulis melalui lensa tersebut, **bukan audit independen oleh tokoh atau subagent lain**.

### Tidak diperiksa

Tidak membaca `.env*`, tidak memanggil remote service, tidak mengakses DB produksi, tidak menjalankan browser, benchmark, functional suite, migrasi, atau restore. Tidak membuat commit. Konfigurasi deployment aktif, traffic logs, ukuran tabel, coverage test, dan laporan lengkap tes integrator tidak diverifikasi ulang.

### Risiko tersisa

1. Kapasitas dan continuous availability tetap **TIDAK TERBUKTI** sampai pengujian operasional tersedia.
2. Model 17 baris/order mengabaikan auxiliary storage dan variasi event; gunakan sebagai baseline cohort, bukan anggaran disk final.
3. Refund provider fail-closed; jangan menjanjikan pencairan otomatis. Pembayaran terlambat memerlukan rekonsiliasi manual.
4. Konkurensi SKU panas, latensi provider, dan backlog worker dapat mendominasi meski rata-rata order/detik rendah.
5. Working tree dapat berubah oleh agen lain. Nomor baris merujuk pembacaan lokal sesi ini, bukan snapshot commit yang dibekukan.
6. Dokumen schema lama tertinggal dari kode; pembaca yang hanya memakai dokumen tersebut dapat salah menganggap backend order belum ada.
