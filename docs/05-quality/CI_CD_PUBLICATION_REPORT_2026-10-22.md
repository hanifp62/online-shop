# Publikasi `main` dan Verifikasi CI sampai Kandidat Rilis

**Tanggal:** 22 Oktober 2026 (timestamp bukti memakai UTC).\
**Task:** CICD-PUB-20261022; NFR-CICD-001…008.\
**Keputusan:** **CI dan kandidat artefak LULUS; deployment/produksi BELUM DISETUJUI.**

## 1. Hasil yang sudah terbukti

Pengguna memberi izin commit dan push langsung ke `main`. Publikasi dilakukan tanpa force-push. Source terakhir yang diverifikasi dalam laporan ini:

- **Commit:** `baee7994fca44b054dc8d63ca826f0cbdccd6967`.
- **Run:** [GitHub Actions 39766255281](https://github.com/hanifp62/online-shop/actions/runs/39766255281).
- **Waktu job:** 10:51:46–11:02:33 UTC; kelima job selesai dengan `success`.
- **Evidence durable:** [remote-baee799-success.json](evidence-ci-remediation-2026-10-22/remote-baee799-success.json), [angka Node dan snapshot protection](evidence-ci-remediation-2026-10-22/remote-publication-checks.json).

| Gate | Hasil nyata | Batas bukti |
|---|---|---|
| `quality` | PASS: PHP **1.200 passed**, tiga skip engine exact; Node **485 passed/0 skip**; Python **371 passed/0 skip**; Pint, build, audit dependency, scanner secret/PII, migrasi SQLite dan anti-drift PASS | Matriks workflow Linux/PHP8.4/Node22/Python3.12; bukan semua kombinasi OS/runtime |
| `mysql` | PASS: **tiga tes yang ditentukan, semuanya passed/0 skip**, MySQL8.4.13, migrasi/producer/JUnit/restore/cleanup sukses | Fault injection pada InnoDB nyata, bukan pembuktian physical concurrent deadlock atau seluruh suite di MySQL |
| `browser` | PASS: 76 produk/10 halaman, keyboard/fokus, tiga request checkout dengan dua order dibuat dan satu ditolak, cleanup dan report gate sukses | Fixture terisolasi; bukan semua alur admin/HCI, screen reader, perangkat atau pengguna nyata |
| `release` | PASS: dependency produksi dari lockfile, build Vite, build/verify artefak dan upload | Paket kandidat, bukan deploy atau smoke byte paket di staging |
| `php` aggregate | PASS: seluruh empat dependensi tepat `success` | Tidak menggantikan branch protection atau approval promosi |

Tiga skip PHP pada job SQLite **tidak dihitung sebagai lulus**. Ketiga ID yang sama benar-benar dieksekusi tanpa skip pada job MySQL. Jumlah assertion bukan ukuran coverage atau throughput transaksi.

## 2. Perbaikan yang diperlukan untuk mencapai hasil tersebut

### 2.1 Ownership export dan diagnostik Python

- `scripts/amadis/export_evidence.py` mempertahankan maksimal empat descriptor output sampai commit/rollback. Inode yang sudah di-unlink tidak dapat didaur ulang dan salah dianggap sebagai file milik exporter.
- `scripts/ci/run_python_tests.py` menambahkan `cases[].test_index` berupa ordinal discovery. Nama tes, exception, parameter subtest, stdout/stderr, dan locals tidak dipublikasikan.
- Review menemukan lifetime finalizer yang dapat mencetak sesudah capture ditutup. Helper frame dan pengumpulan siklus traceback dipindahkan ke dalam capture; regresi child process mengunci perbaikan.
- Root failure Python historis pada run pertama **tidak dapat dipastikan secara retroaktif**: laporan lama hanya memuat ordinal di antara kegagalan. Defect inode dibuktikan tersendiri; tidak diklaim pasti satu-satunya penyebab run lama.

### 2.2 Tes retry MySQL pada batas transaksi yang benar

`MysqlCheckoutRetryTest` sebelumnya memakai `RefreshDatabase`, sehingga request HTTP memiliki transaksi luar buatan tes. Ini membuat pemilik retry menjadi nested. Fixture sekarang unik, cleanup hanya berdasarkan ownership fixture, dan transaksi harus level nol sebelum/sesudah request.

Ekspektasi exhaustion mengikuti **tiga attempt total milik HTTP CartStore**, konsisten dengan `TransactionLockRetry` dan regresi transaksi yang sudah ada. Kebijakan aplikasi tidak diubah. Stock/voucher rollback, cart persisten, idempotency/replay, stale cancel, serta penolakan retry timeout1205 tetap diperiksa; injection memakai SQLSTATE dan wrapper Laravel realistis.

### 2.3 Restore yang membedakan semantik dari penulisan charset

Run Linux membuktikan perbedaan DDL: restore menambahkan `CHARACTER SET utf8mb4` di depan `COLLATE utf8mb4_unicode_ci`. Perubahan bukan diselesaikan dengan mengabaikan schema hash.

- Canonicalization hanya menghapus penulisan charset redundan tersebut pada kepala definisi kolom. Quoted identifier/literal, comment, expression tail, pasangan charset lain, tipe, urutan kolom, default, constraint, index, table options dan counter tetap exact.
- Hash dan jumlah metadata kolom efektif dari `information_schema.columns` dibandingkan secara terpisah: nama/ordinal/type/nullability/default/charset/collation/extra/generation expression/comment. Record JSON dipisah hanya dengan LF; separator Unicode dalam nilai tetap valid dan payload asli yang di-hash.
- Snapshot yang cocok mencakup **331 kolom, 52 migrasi, 35 foreign key, nol pelanggaran FK**, row counts dan dua marker sintetis. Fixture bisnis sudah dibersihkan oleh tes; mayoritas tabel kosong. Ini **bukan** uji pemulihan seluruh nilai bisnis atau backup produksi.
- Diagnostik mismatch dibatasi ukuran dan menggunakan kosakata tetap, ordinal, boolean dan angka; SQL/dump/PII tidak dipublikasikan.

Permintaan dokumentasi resmi MySQL `charset-column` menghasilkan HTTP403; tidak diklaim sebagai sumber yang berhasil dibaca. Bukti keputusan berasal dari source, regresi, dan eksekusi engine yang dicatat.

### 2.4 Aset storefront dalam kandidat rilis

Tiga alias Git yang memang dipakai storefront (`public/app.css`, `public/app.js`, `public/prototype`) dimaterialisasi sebagai **file reguler** dari blob target `prototype/` pada commit yang sama. URL dan source aset tidak diubah.

Payload alias diikat ke blob Git dan diperiksa dengan parent descriptor `O_NOFOLLOW`/`readlink`; pembacaan byte tidak mengikuti symlink. Alias lain, target bertingkat berupa link/gitlink, hardlink dan special file tetap ditolak. Pemetaan diperiksa ulang selama build. Manifest schema1 mewajibkan `git_path` tepat hanya untuk destinasi alias; verifier menolak mapping palsu atau hilang. Inventaris 28 target tracked menghasilkan 30 destinasi publik.

## 3. Kandidat yang dihasilkan dan verifikasi setelah unduh

- Artefak GitHub: `release-candidate-baee7994fca44b054dc8d63ca826f0cbdccd6967`, ID `12421280445`.
- Arsip `release.tar.gz`: **35.122.795 byte**, **7.880 file reguler**, termasuk 30 file dengan pemetaan sumber alias.
- SHA-256 arsip: `9c7daebf491e866c339c8e8b5016f477e9065c63e55f5c27598e5cbe2dbc1271`.
- Retensi artefak yang teramati: sampai **5 November 2026, 11:02:22 UTC**. Artefak CI bukan penyimpanan rilis jangka panjang.
- Setelah diunduh, verifier canonical dijalankan lagi secara offline **tanpa ekstraksi/eksekusi paket** pada 11:05:43–11:05:44 UTC: **EXIT=0**. [Metadata command dan digest](evidence-ci-remediation-2026-10-22/release-candidate-offline-verify.json).

Manifest/hash bukan signature atau attestation. Kanal distribusi harus dipercaya. Paket tidak membawa dotenv, database/dump/log, storage/upload atau konfigurasi host. Runtime harus menyediakan writable directories dan secret injection setelah target serta izin deployment ditetapkan.

## 4. Riwayat kegagalan dipertahankan

| Commit / run | Hasil pada saat itu |
|---|---|
| `ac28d1e` / `39722123468` | Publikasi remediasi awal; Python1failure/338, downstream tertahan |
| `8ce0b84` / `39727304603` | Quality/browser lulus; tes MySQL gagal |
| `26aa2c8` / `39732497987` | Tiga tes MySQL lulus; restore comparison gagal |
| `b628a98` / `39734130255` | Diagnostik membatasi mismatch ke DDL; field lain cocok |
| `86803a2` / `39735798693` | Hipotesis urutan klausa saja ditolak; comparator tidak dilonggarkan |
| `2d8946c` / `39738767585` | Kosakata diagnostik mengungkap charset redundan |
| `2bfcfe0` / `39761262887` | MySQL/restore lulus; release menolak symlink source |
| `baee799` / `39766255281` | **Kelima job lulus**, kandidat tersedia dan verifikasi offline lulus |

Evidence tiap run tersimpan sebagai JSON `remote-*.json` dalam [folder bukti](evidence-ci-remediation-2026-10-22/). Raw log/JUnit/dump tidak diarsipkan ke Git. Kegagalan dan hipotesis yang ditolak tidak ditulis ulang menjadi PASS.

Percobaan lokal Python3.12/macOS gagal pada 13 error/10 tes supervisor karena `os.waitid` tidak tersedia; guard tidak dibypass. Matriks yang terverifikasi adalah Python3.14/macOS dan Python3.12/Linux, bukan Python3.12/macOS. Editor diagnostic repo-wide juga tidak diklaim bersih. Warning Actions Node20/deprecation dan migrasi label `ubuntu-latest` masih non-blocking; tidak dilakukan upgrade major spontan.

## 5. Traceability dan gate yang belum tertutup

| Requirement | Status sesudah publikasi |
|---|---|
| NFR-CICD-001 — proteksi | **BELUM:** GET protection 11:07 UTC menunjukkan `main` belum protected. Tidak diaktifkan tanpa izin; reviewer manusia independen dan drill PR masih diperlukan |
| NFR-CICD-002 — source yang diuji | **TERBUKTI untuk SHA di §1:** commit remote, run dan seluruh job cocok; laporan lama hanya histori |
| NFR-CICD-003 — supply chain/scanner | **PASS dalam run tersebut:** install lockfile, audit dan scanner; bukan jaminan tidak ada advisory/secret di masa depan |
| NFR-CICD-004 — cakupan CI | **PASS scoped:** PHP/Node/Python, PDF/font, InnoDB fixture, browser/keyboard/cleanup. Full a11y/admin/perangkat dan race engine nyata belum lengkap |
| NFR-CICD-005 — artefak/promosi | **PARTIAL:** kandidat nyata dan verify selesai; staging atas byte yang sama, approval, promotion lock dan deployment belum dilakukan |
| NFR-CICD-006 — pemulihan | **PARTIAL:** restore fixture disposable selesai; off-site backup, upgrade/N−1, rollback aplikasi, rekonsiliasi dan RPO/RTO belum diuji |
| NFR-CICD-007 — processing | **PARTIAL:** implementasi/test readiness tersedia; daemon scheduler/worker dan alert diterima operator belum terbukti |
| NFR-CICD-008 — operasi/performa | **PARTIAL:** runbook dan bukti terikat tersedia; TPS/p95/p99/soak, SLO/error budget dan metrik DORA operasional belum diukur |

**Tidak diperiksa:** deployment host/TLS/FPM, provider pembayaran/refund/pengiriman, SMTP, secret manager, failover, beban produksi, screen reader, seluruh alur admin dan pengguna nyata. Tidak ada deployment, aktivasi protection, transaksi provider, migrasi database operasional, atau sync NgodingPakeAI dalam sesi ini.

[Analisis TPS/volume/MSI](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md) tetap membedakan proyeksi dari pengukuran. Angka tes, durasi CI, dan row marker tidak boleh dipakai sebagai TPS bisnis.

## 6. Review independen dan Continuous Evaluation Record

- **Baseline:** `ac28d1e` dipush dengan otorisasi; CI pertama gagal dan output terlalu terbatas untuk diagnosis.
- **Acceptance:** semua job wajib sukses pada SHA yang benar; output aman; tidak ada skip tambahan, bypass guard atau penerimaan hasil parsial; artefak terikat sumber dan diverifikasi.
- **Controlled changes:** ownership exporter, observabilitas hasil, fixture retry, fingerprint restore dan materialisasi alias. Tidak merombak storefront atau kebijakan bisnis.
- **Ricek integrator:** targeted/full Python, PHP transaksi32/497, boundary skip SQLite, Node485, Pint scoped, scanner/diff, run CI Linux, download dan offline verify. Command/timestamp/exit pada evidence; hasil reviewer tidak disamakan dengan eksekusi integrator.
- **Crosscheck:** reporter `9092e8f9-4a5b-4f51-9275-17540868732b`; exporter/MySQL helper `5e073663-8519-4d5e-a988-551b5d0e589a`; fixture PHP `56dbeb98-c4f6-4d61-8edc-a85e4a04af4e`; release `49c46571-6e2f-411e-ab2a-b1d3690a81ea`. Semuanya berbeda dari author terkait; detail handoff/temuan/rework di [ledger](../04-implementation/TEAM_SUPPORT_LEDGER.md#publikasi-dan-verifikasi-linux--cicd-pub-20261022).
- **Skill:** debugging/error recovery, CI/CD automation, Git workflow, incremental implementation, code review/quality, Laravel TDD/patterns/verification, dan documentation. Skill UI/animasi/native tidak dipaksakan pada koreksi harness ini.
- **Lensa audit:** keterulangan dan kendali perubahan (Hightower/Fowler), observabilitas kegagalan (Majors), failure/recovery (Vogels), pemisahan CI dari SLO/DORA operasional (Treynor Sloss/Forsgren), invariants/retry (Kleppmann/Lamport), serta bukti RED→GREEN (Beck). Nama adalah lensa metode, bukan keterlibatan atau endorsement tokoh. Audit HCI menyeluruh tidak diulang pada slice CI ini.
- **Decision:** **ACCEPTED untuk CI sampai kandidat pada SHA §1; BLOCKED untuk promosi/produksi sampai gate eksternal dipenuhi.** Ini bukan janji nol defect.

### Masukan yang diperlukan untuk langkah operasional berikutnya

Target staging/platform dan engine database, owner operasi, cara injeksi secret, reviewer manusia independen, izin aktivasi protection, serta batas/izin deployment dan drill recovery. Setelah itu, gunakan [runbook rilis](../04-implementation/CI_CD_RELEASE_RUNBOOK.md) untuk smoke kandidat yang sama, worker/scheduler, provider sandbox, load/soak, a11y dan rollback—bukan menebak target dari repository.
