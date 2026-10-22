# Publikasi React 19 dan Inertia 3

**Tanggal:** 23 Oktober 2026 WIB; timestamp bukti memakai 22 Oktober UTC.

**Task:** FE-PUB-20261023.

**Keputusan:** publikasi source dan CI sampai kandidat rilis **ACCEPTED**. Migrasi seluruh frontend tetap **PARTIAL**; deployment dan penerimaan produksi **BELUM DISETUJUI**.

## 1. Commit dan run yang diperiksa

Pengguna memberi izin eksplisit commit dan push normal ke `main`. Tidak dilakukan force-push, deployment, perubahan branch protection, atau mutasi database operasional.

| Commit | Perubahan |
|---|---|
| `953340aea168e4efd134ad02df00eec0c24b5ad0` | Supervisor: bounded observation untuk racing child exit Darwin; identitas unknown tetap fail-closed dan regression test disertakan |
| `ee5c3e957044c884b702100904cbcdbdaa718b5b` | Sebelas komponen React/Inertia, DTO/fallback/privacy/rollback, dependency terkunci, mandatory CI dan acceptance native, dokumentasi serta bukti lokal |

Keduanya dipush normal ke `hanifp62/online-shop`, branch `main`, dari baseline `14bdfab95cbb866ca8b6702741425a73fab32329`. Run [39821579334](https://github.com/hanifp62/online-shop/actions/runs/39821579334) adalah event **push** untuk SHA penuh `ee5c3e957044c884b702100904cbcdbdaa718b5b`, bukan run baseline atau hasil working tree.

Laporan ini merekam run source tersebut. Commit dokumentasi penutupan sesudahnya tidak mengubah source aplikasi; CI SHA dokumentasi tetap harus diperiksa terpisah. URL run source di sini tidak dipakai sebagai klaim bahwa SHA dokumentasi otomatis hijau.

## 2. Kelima job wajib sukses

| Job | Mulai UTC | Selesai UTC | Hasil |
|---|---|---|---|
| `quality` | 17:19:19 | 17:27:38 | success |
| `mysql` | 17:27:40 | 17:28:52 | success |
| `browser` | 17:27:40 | 17:29:34 | success |
| `release` | 17:29:36 | 17:31:15 | success |
| `php` (aggregate) | 17:31:17 | 17:31:21 | success |

Tidak ada job wajib failed, cancelled, atau skipped. Snapshot API lengkap dengan SHA, waktu dan steps ada pada [remote-ee5c3e9.json](evidence-react-publication-2026-10-23/remote-ee5c3e9.json). Pengamatan terakhir 17:31:34 UTC. Durasi job adalah durasi CI, bukan pengukuran TPS, latency pengguna, uptime, atau metrik DORA produksi.

### Bukti dari runner Linux

| Gate | Hasil aktual | Bukti aman |
|---|---|---|
| PHP / SQLite | 1.250 total: **1.247 passed, 3 exact engine skips**, 0 failure/error | [JUnit summary](evidence-react-publication-2026-10-23/remote-php.json) |
| MySQL 8.4.13 | **3/3 passed, 0 skipped**, producer EXIT=0 dan JUnit gate PASS | [MySQL summary](evidence-react-publication-2026-10-23/remote-mysql.json) |
| Restore MySQL | PASS; 331 kolom, 52 migrasi, 35 FK, 0 pelanggaran FK; cleanup PASS/0 failure | [MySQL summary](evidence-react-publication-2026-10-23/remote-mysql.json) |
| Node | **495 passed**, 0 failed/cancelled/skipped/todo | [Angka log allowlist](evidence-react-publication-2026-10-23/remote-js-counts.json) |
| React / Vitest | **110/110 passed**, 7/7 file | [Angka log allowlist](evidence-react-publication-2026-10-23/remote-js-counts.json) |
| Python 3.12 | **373 tests**, 0 failure/error/import-error/skip | [Python summary](evidence-react-publication-2026-10-23/remote-python.json) |
| Browser legacy | PASS; 76 produk, 10 halaman, 3 checkout, 2 created/1 rejected, 0 page-content leak | [Legacy summary](evidence-react-publication-2026-10-23/remote-browser-legacy.json) |
| Browser native | **17 checks PASS**, consoleErrors=0 pada konteks normal yang dipantau, supervisorExit=0, cleanup PASS | [Native summary](evidence-react-publication-2026-10-23/remote-browser-native.json) |
| Lint/build/audit/scanner | Steps wajib success; tidak ada gate dilemahkan atau dilewati | [Job dan steps](evidence-react-publication-2026-10-23/remote-ee5c3e9.json) |

Tiga skip SQLite bukan dianggap lulus secara semu: ketiga test exact yang sama benar-benar dijalankan pada engine MySQL. Restore merupakan fixture disposable, **bukan** pemulihan backup pelanggan atau bukti RPO/RTO produksi.

## 3. Kandidat rilis terverifikasi

- Source bersih: `ee5c3e957044c884b702100904cbcdbdaa718b5b`.
- Kandidat: **8.005 file**, **35.332.157 byte** archive `tar.gz`, 30 path alias dimaterialisasi dengan provenance.
- SHA-256 archive: `ebecc0589765fec6000c382093089db42b66e2bc652fe302cc0078d63dc4d2c7`.
- [Manifest ringkas](evidence-react-publication-2026-10-23/release-candidate-summary.json) mengikat source, archive dan kedua lockfile.
- Verifier offline sesudah unduh, `python3 scripts/ci/release_artifact.py verify --archive … --manifest …`, **EXIT=0 pada 17:37:24 UTC**. Verifier membaca stream tanpa mengekstrak/menjalankan aplikasi: [rekaman command](evidence-react-publication-2026-10-23/release-offline-verification.json).
- [Metadata artefak GitHub](evidence-react-publication-2026-10-23/artifacts-ee5c3e9.json): empat artefak tidak expired saat pemeriksaan. Kandidat dijadwalkan kedaluwarsa **5 November 2026 17:31:08 UTC**. Digest artefak GitHub membungkus ZIP; berbeda dari digest `release.tar.gz` di atas.

Kandidat tidak dideploy dan tidak disebut smoke-tested di staging. Hash manifest bukan signature atau attestation; kanal GitHub dan proses build tepercaya tetap asumsi rantai pasok.

## 4. Ricek lokal sebelum publikasi

- Verifier independen `8b314d13-92cb-4642-b918-02c1e5b14c9d`: 17:09:34–17:09:59 UTC, Node495/React110/Python373, seluruh EXIT=0. [Evidence](evidence-react-publication-2026-10-23/independent-tests.json).
- Integrator: 17:11:05–17:11:10 UTC, Pint397, Composer validate/audit, npm audit, actionlint, diff check, secret/PII scanner dan build EXIT=0. [Commands](evidence-react-publication-2026-10-23/static-gates.json).
- PHP terisolasi: 17:11:52–17:16:15 UTC, **1.247 pass/3 exact engine skips/159.143 assertions**, producer dan JUnit checker EXIT=0. [Command](evidence-react-publication-2026-10-23/php-command.json), [summary](evidence-react-publication-2026-10-23/php-summary.json).
- Browser legacy/native serial: 17:16:49–17:17:17 UTC, producer/checker/supervisor sukses; hasil sesuai tabel remote. [Commands](evidence-react-publication-2026-10-23/browser-commands.json), [legacy](evidence-react-publication-2026-10-23/browser-legacy.json), [native](evidence-react-publication-2026-10-23/browser-native.json).
- Reviewer independen `9804fc1c-37d5-440a-aa0c-dffde7de600d`: 75/75 hash source cocok dengan [manifest baseline gabungan](evidence-react-inertia-2026-10-22/source-manifest.json), tidak ada changed source di luar manifest, CI mandatory dan wrapper exact17+cleanup sesuai source; Node kontrak CI3pass EXIT=0. Persetujuan scoped, bukan endorsement produksi.
- Staging terkurasi diperiksa ulang: 2 file supervisor lalu 95 file migrasi/evidence; tidak memasukkan scratch, vendor, node_modules, build, dump DB, atau log privat.
- Crosscheck penutupan reviewer yang sama, **17:43:00–17:43:16 UTC**: metadata GitHub aktual cocok, 85 tautan lokal valid, 75 hash tetap cocok, manifest-summary sesuai, verifier offline independen EXIT=0. **APPROVE scoped**, penugasan selesai: [rekaman review](evidence-react-publication-2026-10-23/independent-publication-review.json).

## 5. Kendala dan warning yang tidak disembunyikan

1. `composer validate --no-check-publish` EXIT=0 dengan warning **exact pin Dompdf/Inertia**. Eksekusi `--strict` terdahulu memang EXIT=1 hanya karena warning tersebut. Pin kompatibilitas tidak dilonggarkan untuk menghapus warning. Tidak mengklaim semua warning hilang.
2. `git config --get core.hooksPath` awal EXIT=1 karena key tidak disetel; pengecekan berikut mengonfirmasi tidak ada custom path atau hook aktif. Ini bukan kegagalan test.
3. `gh run download` semua artefak mencapai **timeout 180 detik**. Kandidat, quality dan browser telah terunduh; MySQL belum. Tidak memperpanjang timeout otomatis. Kandidat yang sudah ada kemudian lolos verifier offline, dan artefak MySQL diambil secara terarah melalui API resmi, EXIT=0 17:37:24–17:37:26 UTC. Timeout tetap dicatat, bukan diubah menjadi keberhasilan command awal.
4. Parser ringkasan log pertama menolak format dekorasi Vitest, EXIT=1; belum mengeklaim angka dari hasil itu. Diagnosis allowlist memastikan nama step dan angka aktual, lalu parser membatasi exact step serta counter numerik; hasil Node495/React110 tervalidasi 17:38:17 UTC. Tidak mengubah test atau aplikasi. Raw log tidak diterbitkan.
5. Gate pra-commit `git diff --cached --check` menolak dua Markdown hard-break berupa trailing whitespace pada header laporan ini. Diganti pemisah paragraf sebelum commit; gate tetap dipertahankan.

## 6. Scope, tidak diperiksa, dan risiko tersisa

- Stack: Laravel **13.36.0**, React/ReactDOM **19.3.1**, Inertia React **3.9.1**, adapter Laravel **3.5.2**. Ada **11 komponen halaman allowlisted**, bukan seluruh frontend React. [Cakupan dan batas detail](REACT_INERTIA_INTEGRATION_2026-10-22.md), [kontrak](../04-implementation/REACT_INERTIA_MIGRATION.md).
- Beranda, PDP, checkout, dashboard admin tujuh panel, enrol/recovery TOTP dan unsubscribe masih Blade. Operations-summary tetap feature-flag OFF secara bawaan; live browser panel itu belum diuji.
- Identitas brand, native auth POST, DTO allowlist, fallback Blade, private history dan rollback tetap dipertahankan. Tidak mengklaim React meningkatkan TPS atau mengurangi payload; entry React/Inertia sekitar 110,26 KB gzip, ditambah chunk dan fallback HTML.
- **Main belum protected** pada GET sesudah push, dikonfirmasi kembali **17:41:59 UTC**: [publication checks](evidence-react-publication-2026-10-23/publication-checks.json). Tidak mengubah proteksi tanpa mandat/proses approval yang berlaku. CI hijau tidak sama dengan enforcement branch aktif.
- Belum ada deployment, staging promotion, provider OAuth/payment nyata, observabilitas worker/cron/alert host, beban/TPS/p95/p99/SLO, recovery operasional/RPO/RTO, atau sertifikasi aksesibilitas penuh. Safari/Firefox, screen reader dan matriks locale/viewport lengkap belum diuji.
- Browser membuktikan skenario yang disebut, bukan semua kemungkinan interaksi atau BFCache `persisted` pada seluruh browser. Tidak ada klaim bebas bug universal.

## Continuous Evaluation Record

- **Requirement/AC:** NFR-FE-002/003/004; NFR-CICD-002/004/005/006. Source yang direview sama dengan yang dipush, seluruh job wajib pada SHA sama sukses, MySQL menutup pengecualian engine, kandidat terikat source/lock dan diverifikasi offline.
- **Baseline:** HEAD/remote `14bdfab…`; migrasi lokal belum dipublikasikan dan CI lama bukan bukti migrasi.
- **Controlled change:** dua commit terpisah, normal push; tidak ada source baru dibutuhkan untuk memperbaiki CI karena run source pertama berhasil.
- **Ricek:** suite lokal oleh integrator/verifier; **crosscheck:** reviewer independen manifest/CI dan artefak remote yang terikat SHA.
- **Skills/lensa:** git-workflow-and-versioning, ci-cd-and-automation, laravel-verification, documentation-and-adrs, debugging-and-error-recovery, code-review-and-quality pada lane reviewer. Antislop dimuat namun workflow UI tidak relevan untuk publikasi; tidak melakukan redesign atau memasang skill lain. Lensa operasi dipakai untuk keterulangan, failure/cleanup, source identity dan batas klaim, bukan endorsement tokoh.
- **Decision:** **ACCEPTED untuk publikasi source, CI dan kandidat**; **PARTIAL untuk migrasi semua halaman**; **BELUM DISETUJUI untuk produksi**. Commit dokumentasi penutupan wajib memiliki CI-nya sendiri.
