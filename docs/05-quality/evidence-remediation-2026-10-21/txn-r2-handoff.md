# TXN R2 — handoff REM-20261021

Tanggal: 2026-10-21. Requirement: **FR-REM-002 / NFR-REM-003**. Status terkini: **service replay diterima reviewer menurut pengguna; fixture targeted diperbaiki, menunggu rerun integrator; collision refund_number perlu tindak lanjut source terpisah**. Bukti lint di bawah berasal dari putaran sebelumnya, bukan validasi PHP atas edit fixture terakhir.

## Baseline dan sumber

- Mandat langsung pengguna: dua blocker R2 yang diterima. Pada discovery, artefak review yang ditemukan adalah `review-round1.md`; tidak mengarang artefak/session approval R2.
- Source diperiksa: `ReturnAllocation`, controller/model return, migrasi return/DLQ/monetary 000050, `PaymentWebhookService`, replay command, dan fixture `RefundStateMachineTest`.
- Baseline allocator lama: net 400002 / qty4 -> `[100000,100001,100000,100001]`. Reject indeks 0 dan 2 tanpa obligation menyisakan qty2/nominal200000. Formula prefix lama menghasilkan reclaim1=99999; reclaim2=200000 ditolak floor200001. Aritmetika direcek dengan Python, bukan runtime aplikasi.
- Source replay lama: batch50 berdasarkan eligible attempts<10, tetapi response 4xx tidak memajukan attempts. Akibatnya backlog lama dapat terus memenuhi batch.

## Perubahan dan kontrak uang

1. Migrasi additive `2026_10_21_000060_add_return_allocation_provenance.php` menambah tiga kolom nullable pada `return_items`: `allocation_version`, `allocation_quantity_before`, `allocation_amount_before`. **Tidak ada backfill histori.** Nilai snapshot nominal memakai unsigned BIGINT, mengikuti kapasitas lane DATA.
2. Klaim baru memakai `net-balance-v2`: `floor(remaining_amount * requested_quantity / remaining_quantity)`. Klaim seluruh sisa menerima seluruh saldo nominal. Snapshot saldo sebelum klaim disimpan server-side; field ini tidak diambil dari payload pelanggan.
3. Validasi v2 mengecek versi, rentang snapshot, formula nominal, kuantitas aktif, nominal aktif, header vs item, dan obligation refund. Nominal tidak bergantung lagi pada floor/ceil harga rata-rata original setelah rejection.
4. Baris tanpa provenance tetap memakai guard legacy net-unit floor/ceil; legacy bruto yang tidak kompatibel tetap `manual_reconciliation`. Versi tak dikenal/metadata parsial juga gagal tertutup. Tidak mengubah subtotal historis, header uang, refund, atau payment yang sudah ada.
5. Lock user -> order -> claim/obligation tetap berlaku. Jumlah kuantitas/nominal aktif tidak boleh melampaui order-item net budget. Batas total refund terhadap ledger paid tetap ada; ongkir tidak dipakai menutupi over-allocation barang.
6. `down()` menolak sebelum DDL bila provenance sudah terisi. Rollback produksi membutuhkan writer quiescence; jangan membuang provenance atau memaksa downgrade aplikasi lama yang tidak mengenal v2. Migration up/down **belum dieksekusi**.

## Kontrak replay untuk OPS/main

- Jalur otomatis command sekarang memanggil `PaymentWebhookService::replay($selectedLetter)`, bukan `handle()` langsung.
- Return `null` berarti snapshot terpilih sudah stale/resolved/exhausted: skip, tanpa fetch atau penulisan terhadap versi baru.
- Public `handle()` adalah jalur live ingress. `replay()` memanggil processor dengan snapshot tetap `(id, order_id, attempts)` dari seleksi awal, bukan mengamati ulang hold terbaru. Jalur sukses hanya dapat mengakui snapshot terpilih itu dalam lock order; tidak boleh mengadopsi hold v2 yang muncul setelah precheck. HTTP 200 sendiri bukan instruksi menutup seluruh hold.
- Semua failure write replay, termasuk circuit-open503, fetch503, 4xx, dan 200 yang masih unresolved, melalui **satu** conditional failure-accounting CAS di bawah lock order: `(id, order_id, attempts yang dipilih, resolved_at IS NULL)`. Body tidak ditulis ulang. Replay tidak pernah memanggil writer live `deadLetter()`.
- Tanpa interleaving, replay503 memajukan budget tepat sekali melalui CAS tersebut. Bila live writer menyimpan B v2 sewaktu A v1 diputar ulang, CAS v1 tidak cocok: reason/body/version/timestamp B tetap utuh. Tidak ada live-unconditional write A sebelum CAS. Writer live tetap khusus notifikasi live.
- Eligible: provider midtrans, unresolved, attempts<10. Batch maksimum50, urutan last_attempted_at lalu id. Attempts>=10 adalah karantina otomatis, **bukan resolved**; hold expiry tetap aktif. CAS failure terakhir memberi reason `quarantined:replay_http_<status>`; upstream writer dapat tetap ber-reason upstream, dengan attempts>=10 sebagai sumber status karantina.
- Untuk fixture 50 invalid422 attempts1 + valid51, jam dibekukan agar tie ordering paling buruk: maksimal9 batch gagal lalu batch10 memproses valid, total451 service calls dan hanya1 Status API fetch. Ini batas untuk backlog tetap tanpa arrival baru; bukan janji fairness terhadap arrival tak terbatas, circuit-open, exception infrastruktur, atau worker yang tidak berjalan.
- **Jangan reset/decrement attempts untuk mencoba ulang:** kolom tersebut juga versi CAS. Recovery karantina membutuhkan verifikasi provider/service atau prosedur operator version-aware; jangan memakai unconditional `markResolved()`.
- Edit `routes/console.php` hanya blok `payments:replay-dead-letters`. Sanitizer/command/schedule lain tetap milik OPS dan tidak diedit pada putaran ini.

## Tes dan fixture

- `TransactionRemediationTest`: 29 test total setelah focused replay rework (jumlah metode diverifikasi statis, bukan jumlah test lulus). Tambahan R2: histori qty4 reviewer, reclaim `[1,1]` dan `[2]`, semua nominal historis tetap, refund total400002; rollback tidak menghapus provenance; 50 invalid +1 valid mencapai proses dalam batas; kegagalan replay422 tidak menimpa body/reason/versi baru; stale selected version dilewati; 503 dihitung sekali dan hold karantina masih menahan expiry.
- Regresi legacy bruto, rounding/multibarang/pajak/ongkir/net-zero dari R1 tetap ada.
- `RefundStateMachineTest`: fixture kini memiliki return_items nyata sesuai header. Test cap menggunakan klaim valid500000 dan ledger sintetis underpaid250000, bukan header rusak. Test terpisah memastikan header rusak tetap ditolak `manual_reconciliation`. Assertion guard/provider verification tidak dilonggarkan.
- Exact filters integrator serial:
  - `--filter=TransactionRemediationTest`
  - `--filter=RefundStateMachineTest`
  - `--filter=PaymentWebhookTest`
- Fokus R2: `--filter=TransactionRemediationTest::test_txn04_r2_two_rejections_reclaim_released_balance_split_or_whole` dan `--filter=TransactionRemediationTest::test_txn07_r2_fifty_invalid_letters_cannot_starve_valid_letter_fifty_one`.
- Jangan menjalankan migrasi/suite bersamaan dengan lane lain. Launcher/environment disposable tetap ditentukan integrator.

## File yang ditulis putaran R2

- `app/Services/ReturnAllocation.php`
- `app/Models/ReturnItem.php`
- `app/Http/Controllers/OrderReturnController.php`
- `database/migrations/2026_10_21_000060_add_return_allocation_provenance.php`
- `app/Models/PaymentDeadLetter.php`
- `app/Services/PaymentWebhookService.php`
- `routes/console.php` (blok replay saja)
- `tests/Feature/TransactionRemediationTest.php`
- `tests/Feature/RefundStateMachineTest.php`
- Evidence ini; tidak mengedit ledger/docs bersama.

## Continuous Evaluation Record

- Acceptance: dua pola reclaim reviewer dapat dipersist/refund tanpa over-allocation; legacy gross tetap ditolak; replay tidak starvation tanpa batas akibat 4xx; CAS mempertahankan versi baru; fixture refund valid tanpa melemahkan guard.
- Controlled change: daftar file di atas; source/artefak lane lain dipertahankan. Skill diterapkan: `laravel-tdd`, `laravel-patterns`, `api-and-interface-design` (instruksi sudah dimuat pada sesi ini). Skill UI/media tidak dipakai karena di luar cakupan.
- Verifikasi benar-benar dijalankan: `php -l <file>` melalui Python subprocess untuk sembilan file PHP di atas -> **EXIT=0**, semuanya `No syntax errors detected`. Tidak meng-autoload atau mengeksekusi aplikasi.
- `git --no-pager diff --check -- <delapan path source/test>` -> tidak ada temuan; grup command EXIT=0. Pemeriksaan trailing whitespace Python untuk seluruh sembilan path -> **EXIT=0**, findings `[]`.
- Tidak menjalankan Pint, migration, PHPUnit/Artisan test, DB, server, atau provider nyata. Hasil runtime test/migration dan InnoDB dua koneksi **TIDAK TERBUKTI**.
- Ricek penulis: source/aritmetika + sintaks; ini bukan crosscheck independen atau approval. Reviewer/approver selanjutnya ditentukan integrator.
- UI/main: pesan `manual_reconciliation` masih memerlukan katalog terjemahan lintas locale (bukan boundary ini). Status karantina harus dibedakan dari resolved; jangan memberi kesan dana telah selesai direkonsiliasi.
- Decision awal: **MENUNGGU SERIAL RUNTIME + REVIEW R2**, bukan klaim GREEN/siap produksi. Status terbaru dan batas verifikasi ada pada follow-up di bawah.

## Follow-up fixture — php-targeted-r2.log

Sumber: `php-targeted-r2.log` milik integrator. Lima blok FAILED yang secara eksplisit berkelas `Tests\\Feature\\TransactionRemediationTest` diperiksa; hasil lane lain tidak ditafsirkan sebagai hasil TXN. Log ini bukan pengujian yang dijalankan author.

1. `txn07 replay pins selected version...`: `CacheManager::setEventDispatcher()` memanggil `bound()` pada app null. Partial mock tidak menjalankan constructor; sekarang `setApplication($this->app)` dipanggil sebelum expectation `get`. Kedua iterasi `[200,503]`, writer callback, strict snapshot/version/body checks, request counts, assertion paid/pending, dan pemulihan original cache di `finally` tetap dipertahankan.
2. `txn02 legacy recorded capture...` dan `txn04 legacy gross partial...`: strict array snapshot membandingkan urutan atribut model baru dengan urutan kolom hasil hydration. Snapshot awal sekarang juga diambil melalui `fresh()->getRawOriginal()`, sama dengan snapshot akhir. **Tetap `assertSame` untuk seluruh raw attributes**; tidak membuang key, mengabaikan tipe, atau mengganti dengan loose equality.
3. `txn01 real framework boundary...`: fixture `MySqlConnection` tanpa konfigurasi mengembalikan driver null. Constructor kini menerima `['driver' => 'mysql']`. Real Laravel transaction lifecycle di atas fake PDO, ekspektasi rollback/commit, exception identity, dan jumlah percobaan 1205/1213 tidak diubah. Ini tetap bukan bukti engine InnoDB nyata.
4. `txn04 multiple lines...`: log menunjukkan UNIQUE constraint `refunds.refund_number`. Source `Refund::generateRefundNumber()` memakai suffix 2 random bytes (16 bit) per order/tanggal; `RefundService::request()` membuat record tanpa collision recovery. **Bug source dilaporkan ke pengguna sebelum perubahan; tidak diperbaiki dalam scope fixture ini.** Tidak menghapus assertion, memalsukan generator, atau retry test agar collision tersembunyi. Generator/atomic collision handling memerlukan penugasan source terpisah.

Scope follow-up ini hanya `tests/Feature/TransactionRemediationTest.php` dan evidence ini. Source service yang sudah diterima reviewer tidak diedit lagi. Tidak menjalankan PHP sama sekali pada follow-up ini, termasuk syntax lint; tidak menjalankan DB/server/test suite. Pemeriksaan statis: jumlah metode test=29 dan scoped `git diff --check` -> **EXIT=0**. Runtime sesudah fixture correction **BELUM TERBUKTI**.

Exact filter rerun integrator: `--filter=TransactionRemediationTest`; fokus cache: `--filter=TransactionRemediationTest::test_txn07_replay_pins_selected_version_before_success_observation_or_circuit_failure`.

Decision terbaru: **FIXTURE PATCH READY FOR SERIAL RERUN; REFUND-NUMBER SOURCE FINDING OPEN**. Service acceptance adalah laporan pengguna/reviewer, bukan approval mandiri author.
