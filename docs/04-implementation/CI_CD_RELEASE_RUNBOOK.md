# Runbook CI/CD dan Gerbang Rilis ZAFIRA

**Tanggal:** 2026-10-22\
**Status dokumen:** runbook source/lokal; hasil dan batas operasional mengikuti [laporan remediasi](../05-quality/CI_CD_REMEDIATION_REPORT_2026-10-22.md). **Bukan persetujuan rilis atau izin mutasi**.\
**Traceability:** NFR-CICD-001…008 dalam [audit CI/CD §8](../05-quality/CI_CD_END_TO_END_AUDIT_2026-10-22.md).\
**Aturan kerja:** [model operasi agent](AGENT_OPERATING_MODEL.md), [protokol keunggulan](ENGINEERING_EXCELLENCE_PROTOCOL.md), dan [AGENTS.md](../../AGENTS.md).

> Workflow menyediakan CI dan **kandidat artefak**, bukan implementasi CD/deployment. Target staging/produksi, secret manager, kontrol promosi, dan prosedur host belum ditetapkan dalam runbook ini. Jangan mengisi kekosongan itu dengan server, akun, provider, secret, atau perintah deployment tebakan.

## 1. Status bukti dan cara memakai runbook

| Jenis bukti | Yang diketahui | Batas kesimpulan |
|---|---|---|
| Pembacaan source 2026-10-22 | Workflow dan helper yang ditautkan di bawah tersedia; dependency antar-job, guard, CLI, dan kontrak readiness dapat diperiksa | Pembacaan source bukan eksekusi clean runner, aktivasi proteksi, atau deploy |
| Bukti integrator lokal | Source readiness APPROVE reviewer427; targeted PHP78 passed/1.392 assertions, dilanjutkan full PHP1.200 passed/3 skipped | Bukti dan command pada [laporan remediasi](../05-quality/CI_CD_REMEDIATION_REPORT_2026-10-22.md); tidak diulang penulis awal runbook. Bukan bukti daemon, Linux CI atau approval produksi |
| Browser R1 → R2 | **R1 tetap FAIL**: EPERM cleanup dan shared cart. Sesudah perbaikan serta review source independen, **R2 lokal lulus producer dan checker**; HTTP201/422/201, order aktual20+17, aktor berbeda, invariant lulus dan port8042 tanpa listener | [Summary](../05-quality/evidence-ci-remediation-2026-10-22/browser-summary.json) dan [verification](../05-quality/evidence-ci-remediation-2026-10-22/browser-verification.json) integrator; macOS/SQLite/fixture sintetis. Bukan Linux CI, MySQL, staging atau produksi |
| Audit historis | Audit memotret commit `041ed083a1be330d97d4f335cda8555670045cdd`, run CI lama yang sukses, dan proteksi yang belum aktif saat itu | Jangan memakai run lama sebagai bukti source/workflow baru. Status remote terkini tidak diperiksa dalam penulisan dokumen ini |
| Gate operasional | Staging, reload worker, approval promosi, penerimaan alert, recovery layanan, RPO/RTO, dan benchmark | **TIDAK TERBUKTI** sampai ada eksekusi berizin dengan bukti yang terikat rilis |

Seluruh blok perintah adalah **instruksi untuk operator/integrator setelah prasyarat dan izin dipenuhi**, bukan rekaman perintah yang telah dijalankan penulis. Penulisan runbook tidak menjalankan tes, instalasi, browser, server, Docker, akses remote, atau membaca `.env*`.

**Aturan penghentian:** kegagalan, timeout, hasil tidak diketahui, artefak tidak ada, cleanup gagal, atau skip di luar kebijakan menahan gate. Jangan menurunkan assertion, guard database, threshold, atau required check agar terlihat hijau. Dua kegagalan beruntun pada submasalah yang sama memerlukan diagnosis dan eskalasi sesuai protokol.

## 2. Peta gate dan requirement

Alur rilis yang harus dibuktikan:

```text
Source terkurasi + izin commit/push → PR dan SHA yang diuji
  → quality
    → mysql dan browser pada runner terpisah
      → release: build + verify kandidat
        → php: aggregate check semua dependency wajib success
          → proteksi main + approval PR terverifikasi
            → staging memakai kandidat yang diverifikasi
              → approval promosi independen
                → promosi berizin, observasi, dan recovery bila perlu
```

Bagian sampai aggregate `php` ada dalam [workflow CI](../../.github/workflows/ci.yml). Bagian staging/promosi/recovery adalah **gerbang operasional yang masih harus disiapkan dan dibuktikan**, bukan job deploy yang sudah ada. Proteksi adalah prasyarat merge; letaknya pada alur bukan izin melakukan merge tanpa proteksi saat menunggu gate lain.

| Requirement audit | Gate runbook | Bukti penerimaan yang diperlukan; sisa gap |
|---|---|---|
| **NFR-CICD-001** — enforcement | §7 | Required check `php` dari App yang benar, strict update, approval independen dan penolakan force-push/deletion terbukti melalui GET serta drill PR berizin; keberadaan script saja tidak cukup |
| **NFR-CICD-002** — source terbaru | §3–6 | Izin commit/push, PR, SHA yang benar-benar diuji, URL/run attempt, dan semua hasil clean runner terbaru; bukan hasil working tree atau run baseline |
| **NFR-CICD-003** — gate keamanan | §4.1, §6 | Scanner fail-closed, pengecualian sempit, Actions dipin SHA penuh, audit dependency dan review perubahan pin; tidak menjamin seluruh secret/PII terdeteksi |
| **NFR-CICD-004** — cakupan tes | §4–5 | Producer dan report gate sama-sama sukses; PDF/font Linux, guard, PHP/Node/Python, MySQL fixture, browser/keyboard dan cleanup; konkurensi DB nyata serta cakupan aksesibilitas penuh tetap terpisah |
| **NFR-CICD-005** — artefak sampai promosi | §6, §8, §10 | Commit, digest, lockfile, runtime, hasil gate, target, deployment lock, smoke dan approval mengikat kandidat yang sama; CD belum diimplementasikan oleh workflow ini |
| **NFR-CICD-006** — pemulihan | §5.1, §11 | Restore fixture MySQL adalah satu lapis bukti; upgrade/N−1, rollback aplikasi, restore backup off-site, RPO/RTO dan rekonsiliasi masih memerlukan drill terpisah |
| **NFR-CICD-007** — processing/alert | §9 | Probe async selesai, scheduler host dan worker aktual terverifikasi, kegagalan menghentikan promosi, alert benar-benar diterima operator; source/test readiness bukan bukti daemon |
| **NFR-CICD-008** — pengukuran dan operasi | §10–12 | Workload/SLI/SLO, benchmark dan event deploy/incident/recovery nyata; runbook dapat diikuti operator berbeda. Jumlah assertion dan durasi CI bukan kapasitas produksi atau metrik DORA |

## 3. Gate A — source, izin, dan checkout aman

### 3.1 Sebelum membuka atau memperbarui PR

1. Integrator meninjau diff terkurasi, requirement, hasil tes yang dilaporkan, dan review independen. Jangan menyertakan perubahan lane lain tanpa persetujuan owner.
2. Dapatkan **izin commit dan push secara eksplisit**. Runbook ini tidak memberikan izin tersebut dan tidak menyediakan perintah commit/push otomatis.
3. Catat SHA head PR serta SHA checkout yang benar-benar dijalankan CI. Pada event PR, checkout dapat berupa merge commit pengujian; jangan menyamakan head PR, tested SHA, dan hasil merge `main` tanpa pemeriksaan.
4. Untuk SHA final yang hendak dirilis, wajibkan hasil CI yang relevan dan baru. Perubahan source setelah gate/review membatalkan keterikatan bukti lama sampai diuji dan direview ulang.

Pemeriksaan metadata dari root checkout, tanpa membuka isi `.env*`:

```sh
git --no-pager rev-parse --verify 'HEAD^{commit}'
git --no-pager --no-optional-locks status --porcelain=v1 --untracked-files=all
```

**Checkout kotor bukan kandidat rilis.** Jangan menggunakan `git clean`, reset, penghapusan file orang lain, atau commit otomatis untuk melewati guard. Setelah izin dan integrasi source, siapkan checkout/runner baru yang dipercaya.

### 3.2 Lingkungan reproduksi

- Acuan canonical adalah **Ubuntu/Linux runner bersih** pada workflow, dengan Bash, Git, Composer, PHP **8.4 yang memenuhi minimum 8.4.1**, Node **22 yang memenuhi minimum 22.12** dari [`.nvmrc`](../../.nvmrc)/[package](../../package.json), dan Python **3.12**. [Composer](../../composer.json) mengunci kontrak PHP/dependency.
- Extension workflow: `mbstring`, `gd`, `bcmath`, serta `pdo_sqlite` untuk quality/browser/release dan `pdo_mysql` untuk MySQL. Ikuti dependency platform Composer juga; daftar ini bukan seluruh kebutuhan runtime produksi.
- MySQL helper khusus **Linux**, Python minimal 3.11, Docker daemon lokal `unix:///var/run/docker.sock`, image `mysql:8.4` sudah tersedia, dan vendor Composer **dev**. Docker Desktop macOS/daemon remote bukan pengganti kontrak helper.
- Gunakan checkout terpisah dan shell bersih per lane seperti CI. Jangan berbagi fixture, proses browser, database, atau directory output dengan sesi lain. Reproduksi lokal yang berbagi resource wajib serial; paralel CI hanya pada runner/job terisolasi.
- Tidak membawa dotenv operasional, credential produksi, atau config cache lama. Jangan menjalankan `composer setup`, menyalin template dotenv, atau membuat ulang kunci aplikasi sebagai langkah rilis. Jangan mencetak environment, `config:show`, config cache, atau nilai secret ke log.
- Tidak mewariskan `AMADIS_*` atau override cache/storage dari sesi lain ke Composer. [ComposerHooks](../../app/Support/ComposerHooks.php) menganggap penanda tersebut sebagai niat isolasi dan wajib melewati guard; jangan mematikan hook untuk menghindarinya.
- Instalasi/pull di bawah memerlukan jaringan dan izin operator pada runner disposable. Tidak ada instalasi yang dilakukan saat penulisan runbook.

Untuk reproduksi lokal berizin, mulai **Bash baru dari root checkout lane**. Inisialisasi ini diulang untuk setiap lane; pada GitHub Actions, `RUNNER_TEMP` sudah disediakan platform dan tidak perlu diganti:

```bash
set -euo pipefail
umask 077
RUNNER_TEMP=$(mktemp -d)
export RUNNER_TEMP
```

Output tetap di luar checkout. Jangan memasukkan evidence baru ke source checkout ketika helper mensyaratkan clean checkout. Raw evidence bersifat privat; berikan hanya ringkasan allowlist sesuai §10.

## 4. Gate B — quality

Sumber: [workflow job quality](../../.github/workflows/ci.yml), [security scanner](../../scripts/ci/security_scan.py), [JUnit checker](../../scripts/ci/check_test_report.py), [Python runner](../../scripts/ci/run_python_tests.py).

### 4.1 Dependency, keamanan, style, build

Dengan toolchain §3 sudah tersedia pada runner disposable:

```bash
sudo apt-get update
sudo apt-get install --no-install-recommends -y poppler-utils
python3 -m pip install --disable-pip-version-check -r scripts/ci/requirements.txt
composer install --no-interaction --prefer-dist --no-progress
npm ci
composer audit --locked --no-interaction
npm audit --audit-level=high
python3 scripts/ci/security_scan.py --mode secrets
python3 scripts/ci/security_scan.py --mode pii
vendor/bin/pint --test
npm run build
```

- [Requirements Python](../../scripts/ci/requirements.txt) memin FontTools `4.66.1`; `poppler-utils` menyediakan ekstraksi PDF di Linux. Dependency yang hilang bukan alasan mengizinkan skip tambahan.
- Scanner: **exit 0 bersih, 1 temuan, 2 pemindaian tidak dapat dipercaya**. Git menentukan tracked dan untracked non-ignored; mode PII dibatasi `app/`. `.env*` tidak dibaca scanner. Keluaran berupa path aman/identitas hash dan kode error statis, bukan isi temuan. Pengecualian sentinel berbasis konteks/offset, bukan pengecualian satu file penuh.
- Jangan mengganti scanner dengan pipeline `grep` yang menganggap error sebagai tidak ada match. Scanner regex/binary heuristic bukan audit seluruh aliran PII atau bukti tidak ada secret dalam ignored/generated files.
- `npm audit --audit-level=high` bukan pernyataan nol advisory pada semua severity. Tidak menjalankan upgrade dependency spontan untuk mengejar hijau; perubahan dependency kembali melalui review dan gate.

### 4.2 Migrasi dan anti-drift SQLite disposable

Hanya pada runner bersih tanpa konfigurasi operasional; target di bawah adalah file baru milik lane, bukan database aplikasi:

```bash
touch "$RUNNER_TEMP/ci.sqlite"
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE="$RUNNER_TEMP/ci.sqlite" php artisan migrate --force --no-interaction
status=$(APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE="$RUNNER_TEMP/ci.sqlite" php artisan migrate:status) || {
    echo 'migrate:status gagal; status skema tidak diketahui'; exit 1;
}
case "$status" in
    *Pending*) echo 'Ada migrasi belum diterapkan'; exit 1 ;;
esac
```

Exit `migrate:status` dan tidak adanya migrasi pending merupakan dua syarat berbeda. Tidak ada instruksi `migrate:fresh` atau `db:wipe` untuk menyiapkan target operasional.

### 4.3 PHP dengan guard fixture, JS, dan Python

Root fixture berikut **konstanta guard repo**, bukan path staging/produksi atau lokasi bebas yang boleh diganti. Workflow sengaja membuat path itu pada Linux agar guard tetap berlaku. Sumber: [launcher](../../scripts/amadis/launch.py) dan [guard](../../scripts/amadis/guard.php).

```bash
sudo install -d -m 700 /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
sudo chown --reference=. /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
AMADIS_ROOT=$(scripts/amadis/launch.sh init)
export AMADIS_ROOT
scripts/amadis/launch.sh test "--log-junit=$AMADIS_ROOT/evidence/phpunit.xml"
python3 scripts/ci/check_test_report.py "$AMADIS_ROOT/evidence/phpunit.xml" \
    --allow-skipped-test 'Tests.Feature.MysqlCheckoutRetryTest::test_deadlock_retry_succeeds_replays_and_cancel_restores_stock_only_once' \
    --allow-skipped-test 'Tests.Feature.MysqlCheckoutRetryTest::test_deadlock_exhaustion_returns_503_without_partial_writes_and_key_is_reusable' \
    --allow-skipped-test 'Tests.Feature.MysqlCheckoutRetryTest::test_lock_wait_timeout_is_not_classified_as_deadlock' \
    --summary-json "$RUNNER_TEMP/phpunit-summary.json"
node --test
python3 scripts/ci/run_python_tests.py | tee "$RUNNER_TEMP/python-summary.json"
```

- Jalankan inisialisasi tanpa root Amadis lama. Launcher membuat child `amadis-…` baru dengan identitas/lock; test PHP memakai SQLite `:memory:` serta cache/session/mail array dan queue sync. Ini **bukan pengujian daemon async**.
- **Producer PHP harus exit 0 DAN checker harus exit 0.** Checker tidak boleh menutupi kegagalan runner. Ia menolak laporan hilang/rusak, aggregate tidak cocok, failure/error, tidak ada test dieksekusi, atau skip di luar exact allowlist. Identitas memakai `classname::name` XML PHPUnit bertitik, termasuk suffix data set jika ada; bukan wildcard atau alias nama kelas.
- Tiga pengecualian di atas hanya untuk lane SQLite. Lane MySQL wajib mengeksekusi ketiganya tanpa skip. Jangan menambahkan allowance global atau menganggap seluruh suite boleh skip.
- Python runner menolak setiap skip, expected failure, unexpected success, import error, suite kosong/tidak selesai. `pipefail` wajib agar `tee` tidak menyembunyikan kegagalan producer.
- JUnit mentah/log fixture tidak dipublikasikan. Upload hanya ringkasan aman dari run yang sama; jangan memakai summary lama jika penulisan summary gagal.

## 5. Gate C/D — MySQL dan browser

Kedua job bergantung pada `quality`, tetapi berjalan pada runner terpisah. Perintah lokal di bawah tidak boleh dijalankan pada checkout aktif bersama atau diarahkan ke service operasional.

### 5.1 MySQL 8.4: retry dan restore antar-container milik job

Sumber: [mysql_integration.py](../../scripts/ci/mysql_integration.py), [Tests\TestCase](../../tests/TestCase.php), [MysqlCheckoutRetryTest](../../tests/Feature/MysqlCheckoutRetryTest.php).

Dalam checkout bersih khusus MySQL dan shell/output baru (§3):

```bash
composer install --no-interaction --prefer-dist --no-progress
docker pull mysql:8.4
install -d -m 700 "$RUNNER_TEMP/mysql-evidence"
python3 scripts/ci/mysql_integration.py --output "$RUNNER_TEMP/mysql-evidence"
```

**Kontrak yang tidak boleh dilonggarkan:**

- Schema fixture fixed: **`zafira_upgrade13_phpunit_20261015_0420`**. Nama yang sama dengan sebuah schema host tidak memberikan izin mengaksesnya. Helper tidak menerima opsi host/port/schema/password/target; ia membuat dua container sendiri, source dan restore.
- Checkout dipercaya, eksklusif, bersih termasuk untracked, dan HEAD tidak berubah selama run. Helper menolak dotenv operasional dan `bootstrap/cache/config.php`; `.env.example` hanya dikecualikan sebagai nama, tidak dibaca helper. Jangan memasang config cache atau mengubah source di tengah lane.
- `--output` adalah **directory yang sudah ada**, canonical, owned oleh UID pelaksana, mode **0700**, di luar checkout. Helper membuat child unik `mysql-integration-…` sendiri. Bukan path database atau arsip backup.
- Daemon lokal dipercaya; publikasi port hanya loopback dengan port acak; data MySQL memakai tmpfs, tanpa bind mount/volume host. Setiap akses/cleanup memeriksa ID, label owner/role, image, mount dan port. Image ID serta versi aktual direkam; tag `mysql:8.4` sendiri bukan pin digest lintas run.
- Migrasi biasa diterapkan pada schema kosong milik container. Guard PHPUnit memeriksa nama schema, engine InnoDB, dan set migrasi lengkap; perintah refresh/reset/wipe ditolak. Jangan mengatur flag test aman secara manual untuk menjalankan suite pada DB host.
- Harus ada **tepat tiga test yang ditentukan, passed, nol skip**, producer sukses, serta JUnit valid. Fault injection menguji klasifikasi/retry/idempotensi; **bukan deadlock transaksi paralel nyata**, bukan suite lengkap MySQL, dan bukan pengujian MariaDB.
- Restore dump ke container kedua membandingkan schema/migrasi, row counts, marker sintetis dan foreign-key integrity. Ini bukan restore backup produksi/off-site, uji seluruh nilai bisnis, atau pengukuran RPO/RTO.
- Sukses juga mensyaratkan cleanup dan publikasi summary sukses. SIGINT/SIGTERM mencoba cleanup terbatas; SIGKILL/daemon hilang perlu penanganan runner owner. Jangan memakai prune/kill global atau menghapus container dengan identitas yang tidak lagi terverifikasi.

Artefak yang boleh dipublikasikan: **`mysql-evidence/mysql-integration-*/summary.json` dari run yang sedang dinilai**. Directory `private`, JUnit mentah, kredensial sementara, dan dump bukan artefak publik. Dump berada dalam temporary file, bukan output evidence.

### 5.2 Browser/keyboard/checkout dan cleanup

Sumber: [acceptance_isolated.sh](../../scripts/amadis/acceptance_isolated.sh), [acceptance_isolated.cjs](../../scripts/amadis/acceptance_isolated.cjs), [acceptance_server.py](../../scripts/amadis/acceptance_server.py), [check_browser_report.py](../../scripts/ci/check_browser_report.py).

Pada checkout dan shell baru khusus browser, dengan toolchain §3:

```bash
composer install --no-interaction --prefer-dist --no-progress
npm ci
npm run build
sudo apt-get update
sudo apt-get install --no-install-recommends -y sqlite3 lsof
node node_modules/playwright/cli.js install --with-deps chromium
sudo install -d -m 700 /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
sudo chown --reference=. /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode
export AM_PLAYWRIGHT="$PWD/node_modules/playwright"
AM_CHROMIUM=$(node -p 'require("playwright").chromium.executablePath()')
export AM_CHROMIUM
bash scripts/amadis/acceptance_isolated.sh "$RUNNER_TEMP/browser-evidence"
python3 scripts/ci/check_browser_report.py "$RUNNER_TEMP/browser-evidence" | tee "$RUNNER_TEMP/browser-summary.json"
```

- `AMADIS_ROOT` lama tidak boleh diwariskan. Runner menginisialisasi fixture baru; evidence directory harus **belum ada**. Port default helper adalah **8042**, bukan target layanan staging. Jika listener sudah ada, berhenti; jangan membunuh proses lain untuk mendapatkan port.
- Supervisor hanya membersihkan process group milik invocation. Fastpath **tanpa signal** mensyaratkan exit child terkonfirmasi positif melalui `WNOWAIT` dan inventory group tepat `{leader PID}`; child di-reap terakhir, tanpa signal sesudahnya. Worker tersisa tidak memenuhi fastpath: jalur cleanup biasa tetap wajib membuktikan group drained. Identitas/inventory tidak diketahui, timeout, atau `EPERM` yang masih terjadi tetap fail-closed, bukan error yang diabaikan.
- Producer membuat **tiga customer melalui registrasi publik `/register`**, masing-masing pada request context terpisah. `actor` berupa HMAC-SHA256 dari identitas user dengan kunci acak per-run dan wajib berbeda untuk ketiganya; raw user ID, credential, dan kunci HMAC tidak ditulis ke report. Ini memperbaiki penggunaan shared customer/cart; context browser terpisah saja bukan bukti cart independen.
- Produk checkout diikat ke tepat satu slug **`amadis-demo-stock-37`**, ID valid, dan stok awal 37. Setelah **seluruh penulisan** cart untuk kuantitas `[20, 20, 17]` selesai, producer membaca ulang **ketiga cart sebelum checkout mana pun**. Setiap cart harus berisi tepat satu item produk/kuantitas yang dimaksud dan available; echo respons penulisan saja tidak cukup.
- Setiap checkout sukses HTTP 201 wajib diverifikasi producer sebagai order milik user aktornya, dengan ID order valid serta tepat satu item yang produk dan kuantitasnya cocok. Respons 422 tidak boleh membawa order. Report merekam `preparedItems` hasil pembacaan ulang cart dan `orderItems` dari respons order sukses, bukan sekadar label kuantitas request.
- Checker membaca `commands.tsv`, `invariants.json`, dan `browser-results.json`; step/invariant producer, 76 produk pada 10 halaman, keyboard/fokus, dan tiga interval checkout beririsan tetap wajib. Kontrak AM-10 kini strict: `product` terikat slug/stok di atas, tiga `actor` berbeda, serta field/item `preparedItems` dan `orderItems` dengan tipe/nilai tepat. Untuk 422, `orderId` dan `orderItems` harus null; order sukses minimal satu, ID unik, dan total **kuantitas order aktual** ≤37. **Report format lama ditolak**; jangan menambal report lama dengan field sintetis untuk melewati gate.
- Checker memvalidasi report, bukan mengulang verifikasi owner terhadap DB atau mengautentikasi HMAC secara independen. Producer, checker, invarians DB fixture, dan cleanup semuanya harus sukses pada run yang sama. **R1 tetap overall FAIL. R2 lokal sesudah remediasi lulus producer dan checker**, dengan bukti pada [laporan remediasi](../05-quality/CI_CD_REMEDIATION_REPORT_2026-10-22.md). Nilai parsial `PASS_HTTP` saja bukan hasil keseluruhan PASS.
- Jika rerun dan seluruh gate lulus, cakupan AM-10 tetap terbatas pada irisan interval HTTP sisi klien dan invarians fixture yang dilaporkan, **bukan** bukti konkurensi transaksi InnoDB, integrasi pembayaran eksternal, seluruh browser, seluruh alur admin, atau sertifikasi WCAG. Pengujian pembaca layar dan alur staging tetap gate tersendiri.
- Publish hanya `browser-summary.json`; jangan upload keseluruhan directory browser yang memuat log, identitas fixture, dan data diagnostik.

## 6. Gate E/F — kandidat artefak dan aggregate `php`

### 6.1 Build/verify kandidat, bukan deploy

Sumber: [release_artifact.py](../../scripts/ci/release_artifact.py). Job `release` membutuhkan **quality, mysql, browser semuanya sukses**. Mulai dari checkout bersih dan shell baru khusus release, bukan vendor dev sisa lane quality.

```bash
composer install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader
npm ci
npm run build
python3 scripts/ci/security_scan.py --mode secrets
python3 scripts/ci/release_artifact.py build --output "$RUNNER_TEMP/release-candidate"
python3 scripts/ci/release_artifact.py verify \
    --archive "$RUNNER_TEMP/release-candidate/release.tar.gz" \
    --manifest "$RUNNER_TEMP/release-candidate/release-manifest.json"
```

- Build dijalankan dari root Git checkout **bersih**, termasuk untracked. Output harus **baru**, di luar checkout dan inputnya, dengan parent yang sudah ada. Berbeda dari MySQL: jangan membuat `release-candidate` lebih dahulu.
- Source yang diikutkan dibandingkan dengan blob HEAD. Vendor harus merupakan instalasi production Composer 2 yang cocok dengan `composer.lock`; metadata versi/reference dan entry vendor diperiksa. Vite manifest/chunk/asset wajib lengkap. Symlink, hardlink dan entry tidak aman ditolak sesuai kontrak helper.
- Output hanya `release.tar.gz` dan `release-manifest.json` schema 1. Manifest mengikat commit, digest archive, hash kedua lockfile, serta hash/origin setiap file; `release_id` dibentuk dari commit dan digest archive.
- Allowlist mencakup source aplikasi, migrasi, vendor produksi dan build. Tidak membawa dotenv, secret-file, database/dump/log, storage/upload/backup, `bootstrap/cache`, test/docs, atau `node_modules`. Runtime harus menyiapkan writable directory dan konfigurasi sendiri setelah target/izin jelas.
- Verifier membaca stream archive **tanpa ekstraksi atau eksekusi**. Hash/manifest **bukan signature/attestation**; archive dan manifest wajib berasal dari kanal CI terpercaya. Scanner tetap wajib; allowlist tidak mendeteksi semua secret yang di-hardcode.
- Normalisasi arsip membuat hasil deterministik untuk input byte identik, **bukan jaminan seluruh toolchain/build reproducible**.
- Quality/browser dan release membangun di job berbeda; release menginstal vendor production dan membangun aset lagi. Sukses CI tidak otomatis membuktikan bahwa byte tar sudah dijalankan dalam browser. **Smoke staging harus memakai kandidat yang benar-benar akan dipromosikan**, bukan rebuild baru yang hanya memiliki label SHA sama.

### 6.2 Aggregate gate dan source workflow

- Job **`php`** adalah aggregate required-check name yang dipertahankan. Ia memakai `if: always()` dan menuntut hasil `quality`, `mysql`, `browser`, **dan** `release` masing-masing tepat `success`. Failure, skipped, cancelled, atau hasil lain menahan gate.
- Jangan menilai kelulusan dari upload artifact atau satu job saja. Summary quality/MySQL dapat diunggah pada kondisi gagal untuk diagnosis; itu bukan approval.
- Trigger source: PR dan push `main`; izin `contents: read`; Actions dipin SHA penuh dengan `persist-credentials: false`. Perubahan SHA dependency Actions tetap perlu review/provenance, bukan diasumsikan aman hanya karena bentuknya SHA.
- Concurrency membatalkan run CI lama per ref. **Jangan menyalin cancel-in-progress ini ke operasi deploy/migrasi**: operasi yang sudah mengubah data membutuhkan kebijakan lock/recovery tersendiri.
- Source workflow tidak memiliki job deploy atau environment approval. Keberhasilan aggregate tidak memberikan izin deploy.

## 7. Gate G — proteksi `main` dan approval PR

Sumber: [protect-main.sh](../../scripts/ci/protect-main.sh). Identitas repo `hanifp62/online-shop`, branch `main`, check `php`, dan App ID `15368` bersumber dari helper/audit, bukan target baru.

Preview canonical **offline**, tanpa perubahan remote:

```bash
REPO=hanifp62/online-shop BRANCH=main DRY_RUN=1 bash scripts/ci/protect-main.sh
```

Helper terbaru default ke dry-run dan tidak membutuhkan discovery remote untuk preview. Catatan audit mengenai perilaku dry-run lama adalah riwayat, bukan kontrak helper baru.

**Sebelum apply:** maintainer memiliki otorisasi remote yang eksplisit, `gh` sudah diautentikasi melalui mekanisme aman, reviewer manusia kedua sudah ditetapkan dan bersedia, serta dampak aturan sudah disetujui. Jangan menyalin token ke command/log. `REVIEWER_LOGIN` berikut wajib diisi akun nyata yang telah disetujui; runbook tidak menetapkan nilainya.

```bash
: "${REVIEWER_LOGIN:?Tetapkan reviewer manusia independen yang disetujui}"
REPO=hanifp62/online-shop BRANCH=main DRY_RUN=0 APPLY=1 \
    REVIEWER_LOGIN="$REVIEWER_LOGIN" bash scripts/ci/protect-main.sh
```

Apply adalah **mutasi GitHub berizin**, bukan bagian penulisan/run default dokumen. Helper:

1. Memastikan ID reviewer berbeda dari aktor dan haknya `write`, `maintain`, atau `admin`.
2. Menolak menimpa branch yang sudah dilindungi; aturan existing memerlukan review maintainer. Penolakan bukan alasan menonaktifkan proteksi.
3. Memerlukan check `php` sukses dari App `15368` pada head `main` sebelum PUT. Check bootstrap itu bisa berasal dari CI lama: ia **tidak menggantikan** bukti CI source remediasi terbaru.
4. Mengatur strict required check, enforcement admin, minimal satu approval, dismissal stale approval, last-push approval, serta penolakan force-push/deletion; lalu melakukan GET untuk memverifikasi.

Readback operator setelah apply, bukan perintah yang telah dijalankan penulis:

```bash
gh api repos/hanifp62/online-shop/branches/main --jq '.protected'
gh api repos/hanifp62/online-shop/branches/main/protection
gh api 'repos/hanifp62/online-shop/rulesets?includes_parents=true'
```

Review ruleset/inheritance dan kebijakan efektif, bukan JSON protection saja. Uji PR gagal/tanpa approval/stale approval ditolak secara terkontrol dan berizin; jangan melakukan force-push/deletion nyata pada `main` untuk demonstrasi. Jika PUT berhasil tetapi verifikasi gagal, keadaan remote bisa sudah berubah: hentikan dan minta maintainer membaca kembali, jangan retry buta.

**Persetujuan source reviewer427 bukan identitas `REVIEWER_LOGIN`, bukan izin commit/push, dan bukan approval promosi produksi.**

## 8. Gate H — kesiapan staging dan lifecycle rilis

**Gate ini belum diimplementasikan sebagai deployment workflow.** Tanpa isian §12, berhenti sebelum transfer, migrasi, reload, atau perubahan host. Tidak ada command SSH, nama unit service, URL staging, atau lokasi release yang ditebak di sini.

Operator/release owner harus menyediakan dan membuktikan:

1. Target staging, owner, akses, engine/version, namespace cache/queue, storage, ingress/TLS, serta pemisahan dari data dan credential produksi.
2. Kandidat dari run/SHA yang benar. Verifikasi archive dan manifest dengan helper canonical setelah transfer ke lokasi yang disetujui. Jangan membangun ulang kandidat setelah staging untuk promosi.
3. Secret manager dan izin least-privilege; injeksi konfigurasi hanya pada target. Pertahankan kunci aplikasi yang memang berlaku; **jangan menjadikan `key:generate` langkah deploy rutin**. Tidak ada nilai secret atau dump konfigurasi pada evidence.
4. Directory writable dan permission runtime; tidak menganggap archive membawa storage/config cache. Config cache runtime tidak dimasukkan kembali ke artefak yang dibagikan.
5. Deployment lock, rencana migrasi/kompatibilitas N−1, kondisi penghentian writer, backup/restore yang relevan, dan prosedur abort. `migrate:status` saja bukan bukti upgrade aman.
6. Mekanisme reload proses berumur panjang sesuai host aktual, termasuk worker/scheduler/web runtime, dan verifikasi bahwa mereka memakai release yang dimaksud. Tidak ada klaim command readiness mengecek versi release worker.
7. Smoke kandidat: aset/ingress, `/health`, login/TOTP/RBAC, cart/checkout dengan stok dan idempotensi, alur admin, webhook dan notifikasi **sandbox yang benar-benar dikonfigurasi**, keyboard/fokus/bahasa/viewport, serta §9. Tidak mengirim transaksi/provider/email nyata tanpa otorisasi.

Perintah mutasi target hanya boleh ditetapkan setelah lingkungan efektif dan owner disetujui. Daftar requirement ini bukan bukti bahwa konfigurasi tersebut telah tersedia.

## 9. Gate processing — `/health`, heartbeat, dan readiness

Sumber: [HealthController](../../app/Http/Controllers/HealthController.php), [ProcessingReadiness](../../app/Services/ProcessingReadiness.php), [probe job](../../app/Jobs/RecordProcessingHeartbeat.php), [operations config](../../config/operations.php), [console routes](../../routes/console.php).

### 9.1 Dua kontrak berbeda

| Pemeriksaan | Kontrak | Bukan bukti |
|---|---|---|
| HTTP `/health` | Dependency health database/cache/queue/storage; HTTP 200 atau 503 dengan kontrak publik yang tetap | Konsumsi queue, backlog layak, scheduler/worker hidup, atau semua transaksi sehat. Endpoint melakukan roundtrip dependency; bukan pemeriksaan tanpa efek sama sekali |
| `ops:heartbeat` | Menulis tick scheduler dan mencoba enqueue probe dengan lock/lease; JSON `status`, exit 0/1 | Exit 0 dapat berarti dispatch sengaja ditunda; bukan worker-ready atau bukti cron host bila dipanggil manual |
| `ops:readiness` | Snapshot baca-saja **`processing-readiness.v1`**, scope **`default_queue`**, consistency `best_effort`; JSON allowlist, exit 0 hanya bila semua check valid, selain itu 1 | Semua queue/worker, versi release worker, SMTP/provider, keterlambatan notifikasi/reservasi bisnis, SLO, atau alert aktif |

`default_queue` berarti `queue.default` **dan nama queue yang dikonfigurasi pada connection itu**; tidak harus bernama literal `default`. Cache dan lock scheduler, worker, serta monitor harus menunjuk backend/namespace yang sama dan jam host tersinkronisasi. Dukungan source dibatasi queue **database/Redis** dan cache **database/Redis** dengan tipe backend yang cocok. Sync/deferred/background/failover/null bukan bukti async; array/file/nonshared/failover cache serta database cache in-memory ditolak. Driver shared-capable tetap memerlukan verifikasi topologi nyata; konfigurasi saja tidak membuktikan seluruh host berbagi backend.

Jangan mencoba membuktikan runtime readiness lewat launcher Amadis: launcher memakai konfigurasi fixture sync/file atau array, dan allowlist `launch.sh artisan` tidak memuat command ops. Test readiness memakai transport/cache sintetis, bukan daemon produksi.

### 9.2 Threshold awal, bukan SLO terukur

| Parameter source | Nilai | Kondisi |
|---|---:|---|
| `heartbeat_max_age_seconds` | 180 detik | Scheduler stale bila umur >180; worker dinilai dari **umur request**, bukan hanya waktu selesai |
| `probe_max_latency_seconds` | 60 detik | Pending probe >60 gagal; job terlambat >60 tidak boleh menulis bukti worker fresh |
| `probe_interval_seconds` | 60 detik | Cadence minimal pengiriman probe; jadwal `ops:heartbeat` setiap menit dengan `withoutOverlapping(1)` |
| `probe_retry_after_seconds` | 300 detik | Lease habis **dan antrean kosong** diperlukan untuk mengganti pending probe yang hilang/tidak pasti |
| `queue_max_age_seconds` | 300 detik | Umur oldest pending >300 gagal; bukan pengukuran umur seluruh reserved/delayed job |
| `max_backlog` | 100 job | Ukuran antrean ≥100 gagal; size mencakup reserved/delayed menurut backend |

Batas usia/latensi bersifat inklusif pada nilai threshold. Umur pending memakai API backend: database menggunakan `available_at` untuk job siap; Redis menggunakan timestamp pembuatan head pending. Ini bukan satu pengukuran presisi yang seragam untuk semua jenis antrean.

Probe tidak membawa model/PII/transaksi; satu attempt, timeout job 10 detik. Duplikat/ID lama tidak memperbarui bukti. Timestamp future, malformed, urutan waktu salah, sumber gagal, atau evidence hilang membuat readiness gagal. State pending dipertahankan, bukan dihapus hanya karena timeout; selama antrean nonempty tidak ada redispatch pending. Cap backlog juga membatasi pertumbuhan setelah eviction. Jangan flush cache/mengubah clock/menulis heartbeat manual untuk memaksa gate hijau.

### 9.3 Drill berizin pada target disposable/staging

Setelah target/configuration/owner disetujui, dari root aplikasi target yang benar:

```sh
php artisan schedule:list --no-ansi
php artisan ops:readiness --no-ansi
```

`readiness` exit 1 pada cold start tanpa evidence adalah keadaan belum siap, bukan alasan bypass. Untuk diagnosis terbatas, operator dapat menjalankan command mutasi berikut **hanya dengan izin**:

```sh
php artisan ops:heartbeat --no-ansi
```

Drill penerimaan:

1. Buktikan tick dari **scheduler host nyata**, bukan hanya daftar jadwal atau invocation manual. Verifikasi process manager dan konfigurasi cron/scheduler yang aktual; pemanggilan `schedule:run` dapat menjalankan pekerjaan bisnis lain, sehingga tidak diberikan sebagai smoke tanpa batas.
2. Buktikan worker async mengonsumsi probe pada connection/queue yang sama, lalu baca readiness. Catat release yang dimuat worker secara terpisah tanpa secret.
3. Dalam jendela gangguan yang disetujui, hentikan worker lewat kontrol host yang memang tersedia. Pastikan pending/worker age menyebabkan kegagalan dan probe tidak menumpuk. Pulihkan worker; job lama yang stale tidak cukup untuk hijau—harus ada probe baru yang valid.
4. Uji scheduler berhenti, dependency gagal, dan backlog berlebih pada lingkungan disposable. Jangan menyuntik fault atau mengubah data cache/queue produksi tanpa rencana dan izin khusus.
5. Host monitor menjalankan `ops:readiness --no-ansi`, menangani nonzero exit, timeout proses, JSON rusak/hilang, serta hasil yang terlalu lama. Interval monitor dan deadline harus ditetapkan owner; threshold source bukan jaminan waktu deteksi host.
6. Kirim kegagalan melalui kanal alert yang **benar-benar tersedia**, buktikan operator menerima/acknowledge, dan rekam pemulihan. Tanpa bukti ini, alert masih **TIDAK TERBUKTI**. Command tidak memasang monitor atau kanal alert secara otomatis.

## 10. Gate I — approval, bukti rilis, dan observasi

Sebelum promosi, reviewer/approver yang berbeda dari penulis dan pemicu menilai:

- SHA source/tested/merge yang relevan, PR, URL run/attempt, serta hasil `quality/mysql/browser/release/php` yang tepat.
- `release_id`, digest archive, hash lockfile, versi toolchain/engine dan identitas image; manifest tidak otomatis memuat seluruh konteks run/approval/target ini, sehingga catatan rilis harus melengkapinya.
- Identitas target, deployment lock, bukti staging terhadap **archive yang sama**, hasil smoke/readiness, versi worker, pengecualian risiko yang disetujui, kandidat N−1 dan rencana recovery.
- Nama/identitas approver, waktu keputusan, hak operator, serta batas/jendela observasi. Source workflow tidak menyediakan environment approval; kontrol yang dipilih harus benar-benar tersedia dan dapat diaudit sebelum dipakai.

Artefak workflow yang boleh dibagikan:

| Nama artefak | Isi | Retensi source |
|---|---|---:|
| `quality-summary-<SHA run>` | `phpunit-summary.json`, `python-summary.json` | 14 hari |
| `mysql-summary-<SHA run>` | Summary allowlist invocation MySQL | 14 hari |
| `browser-summary-<SHA run>` | `browser-summary.json` | 14 hari |
| `release-candidate-<SHA run>` | `release.tar.gz`, `release-manifest.json` | 14 hari |

`<SHA run>` di tabel menjelaskan pola nama workflow, bukan ID run/kandidat nyata. Ambil identitas aktual dari run yang diperiksa. `if-no-files-found: error` tidak membolehkan mengganti output hilang dengan evidence lama. Retensi Actions 14 hari bukan kebijakan backup atau jaminan kandidat N−1 tersedia selama jendela recovery; owner harus menetapkan penyimpanan/akses/retensi rilis secara terpisah.

Jangan upload raw JUnit, dump, private runtime, cookie/session, log mentah, config cache, atau `.env*`. Catatan evidence memuat command aman, waktu UTC, exit aktual, source/digest, ringkasan hasil, reviewer, dan batas klaim. Jumlah test/assertion bukan coverage persentase.

Pascadeploy berizin, amati error, latency, invarians uang/stok, backlog/age, scheduler/worker, dan outcome provider yang telah diotorisasi. **SLI/SLO, workload, target p95/p99/TPS, durasi observasi, error budget, dan ambang rollback bisnis masih terbuka.** Tetapkan lewat data/keputusan owner, bukan angka rekaan. DORA memerlukan event deployment/incident/recovery nyata; durasi job CI dan jumlah assertion tidak menggantikannya.

## 11. Gate J — recovery aplikasi dan data

Pisahkan tiga operasi berikut; tidak ada perintah recovery produksi generik yang aman tanpa target/owner:

| Operasi | Persiapan dan bukti | Batas |
|---|---|---|
| Kill switch fitur | Ikuti [R-39](SAFE_RELEASE_R39.md) pada fitur yang memang terpasang; verifikasi konfigurasi dan reload proses yang relevan | Mematikan fitur laporan bukan rollback seluruh aplikasi atau restore data; angka drill lokal R-39 bukan RTO layanan |
| Rollback artefak aplikasi | N−1 yang dipercaya tersedia, diverifikasi, kompatibel dengan skema saat ini; operator menjalankan prosedur target berizin, reload dan ulang smoke/readiness | Jangan otomatis menjalankan `migrate:rollback` bersamaan dengan rollback code; bila N−1 tidak kompatibel, tahan promosi/pilih forward-fix yang direview |
| Restore/recovery data | Backup owner, snapshot konsisten, lokasi off-site/failure domain, akses/enkripsi, RPO/RTO disetujui; restore dahulu ke engine disposable yang sesuai lalu rekonsiliasi | Restore fixture CI tidak membuktikan backup operasional pulih; restore DB tidak membatalkan transaksi eksternal/provider |

Guard konkret yang wajib dipertahankan:

- [Migrasi uang `000050`](../../database/migrations/2026_10_21_000050_widen_return_refund_money_columns.php) menolak narrowing bila nominal melampaui kapasitas. MySQL DDL dapat auto-commit; writer harus dikendalikan agar tidak terjadi race setelah preflight. Transaksi pembungkus bukan jaminan keselamatan lintas ALTER.
- [Migrasi provenance `000060`](../../database/migrations/2026_10_21_000060_add_return_allocation_provenance.php) menolak rollback saat provenance terisi. Jangan menghapus data/guard untuk meloloskan rollback; lakukan rekonsiliasi/forward-fix dengan persetujuan Data owner.
- [DatabaseBackupService](../../app/Services/DatabaseBackupService.php) mendukung backup sesuai driver yang diizinkan, tetapi `verify()` pemulihan hanya SQLite. `db:backup --verify`/`db:backup-verify` **tidak boleh diklaim** sebagai verifikasi restore MySQL/MariaDB. `db:backup` juga menerapkan retensi; bukan operasi baca-saja.
- Jadwal backup di [console routes](../../routes/console.php) memakai `--verify` hanya untuk SQLite. Helper MySQL CI adalah mekanisme fixture terpisah, bukan pengganti implementasi verify backup operasional.
- Dilarang wipe/refresh/drop operasional tanpa persetujuan eksplisit. Tidak ada langkah mengimpor dump ke DB operasional, menghapus provenance, atau mengubah rollback guard dalam runbook ini.

Catat waktu deteksi, keputusan, mulai recovery, pulihnya layanan, kehilangan/data terdampak, versi sebelum/sesudah, dan rekonsiliasi uang/stok/order/provider. **RPO dan RTO belum ditetapkan atau diukur**; tentukan sebelum drill penerimaan, bukan setelah hasil terlihat. Jika cleanup atau identitas resource tidak dapat dibuktikan, hentikan tindakan destruktif dan eskalasi ke owner.

## 12. Checklist informasi operator yang masih diperlukan

Kotak kosong berarti belum dipenuhi oleh bukti runbook, bukan berarti operator tersebut pasti tidak ada di luar repo.

### Otorisasi dan review

- [ ] Reviewer **manusia kedua** dengan login nyata, berbeda dari aktor/penulis, hak review yang cukup, dan kesediaan menjadi approver PR; aturan script meminta satu approval independen, bukan dua approval.
- [ ] Izin commit dan push source terkurasi, serta identitas PR/SHA yang akan diuji.
- [ ] Izin mutasi proteksi GitHub; owner memeriksa ruleset/aturan existing dan menyiapkan drill penolakan PR.
- [x] Versi runbook sebelum rekonsiliasi mendapat **APPROVE reviewer427 (source-only)** menurut handoff integrator; bukan approval runtime/rilis.
- [x] Delta source browser direview reviewer031 dan cleanup reviewer427; browser R2 lokal lulus. [Ledger](TEAM_SUPPORT_LEDGER.md) dan [laporan/evidence](../05-quality/CI_CD_REMEDIATION_REPORT_2026-10-22.md) mencatat lingkup approval; bukan pengganti review/rilis remote.

### Target dan operasi

- [ ] Target staging dan produksi aktual, owner, akses, engine/version, writable storage, ingress/TLS, pemisahan data/credential, dan prosedur transfer/promosi artefak.
- [ ] Secret manager yang dipilih, pemilik akses, injeksi konfigurasi, rotasi/kontinuitas kunci; hanya identitas/kebijakan, **tanpa nilai secret** pada dokumen.
- [ ] Pemilik deployment lock, prosedur migrasi/quiesce writer, reload proses, serta cara membuktikan versi release worker.
- [ ] Pemilik scheduler dan worker; topologi shared cache/lock, default connection/queue, sinkronisasi jam, serta inventaris queue lain yang tidak dicakup readiness.
- [ ] Pemilik **backup/restore**, retensi dan lokasi off-site, kebijakan recovery N−1, RPO/RTO, jadwal drill, dan owner rekonsiliasi transaksi.
- [ ] Pemilik **monitor/alert**, kanal dan penerima aktual, interval/deadline monitor, eskalasi/acknowledgement, serta bukti drill diterima.
- [ ] Lingkungan/provider sandbox yang benar-benar tersedia untuk smoke pembayaran/notifikasi, data fixture, dan izin interaksi eksternal.
- [ ] Baseline workload/benchmark, SLI/SLO, error budget, jendela observasi dan keputusan rollback; pencatatan event untuk metrik delivery.
- [ ] Penyimpanan kandidat/N−1 dan evidence aman di luar batas retensi artifact CI, termasuk kontrol akses dan integritas provenance.

## 13. Catatan evaluasi dokumen dan handoff

- **Task/requirement:** runbook rilis; NFR-CICD-001…008, bukan penutupan otomatis acceptance audit.
- **Baseline:** audit historis dan source workflow/helper terkini; hasil targeted PHP serta approval readiness berasal dari laporan pengguna/integrator.
- **Controlled change:** hanya file `docs/04-implementation/CI_CD_RELEASE_RUNBOOK.md`. Ledger, indeks, laporan, workflow, dan source aplikasi tidak diubah dalam tugas dokumentasi.
- **Skill:** `documentation-and-adrs`; keputusan/prasyarat/batas klaim didokumentasikan dengan tautan source. Eksekusi TDD/Laravel verification/browser testing tidak dilakukan karena scope dokumen dan larangan tes/server.
- **Ricek penulis:** pada 2026-10-22, kontrak command/flag, dependency job, guard, output aman, threshold dan traceability dicocokkan dengan source yang ditautkan; rekonsiliasi kecil §5.2 mencocokkan isolasi aktor/cart, strict report, dan fastpath cleanup terbaru. Ini bukan review independen atau eksekusi runtime. Lensa dokumentasi/operasi diterapkan melalui langkah reproduksi, pemisahan istilah/bukti, kriteria stop, dan owner yang eksplisit.
- **Command verifikasi dan exit:** tidak ada tes atau command operasional yang dijalankan saat penulisan; tidak ada `EXIT=0` baru yang diklaim untuk runbook/pipeline.
- **Reviewer/approver dokumen:** `4277979b-1f7e-46b0-a160-3a13b3eb2deb` memberi **APPROVE scoped dokumentasi** setelah koreksi R1/R2, pada `2026-10-21T21:38:11Z`; laporan, indeks dan preface readiness juga dicocokkan ke bukti. Integrator mencatat handoff pada ledger; tidak ada approval operasional yang diklaim.
- **Tidak diperiksa:** run CI remote source terbaru, proteksi efektif saat ini, eksekusi Linux/Docker/browser pada runner CI, staging/produksi, daemon, alert, backup off-site, benchmark, dan drill recovery. Browser lokal R2 adalah bukti integrator, bukan eksekusi ulang penulis awal runbook.
- **Risiko tersisa/decision:** **BLOCKED untuk penerimaan operasional**; R1 overall FAIL dipertahankan sebagai histori, R2 lokal lulus dengan evidence integrator. Gate Linux/remote/staging/recovery tetap diperlukan; source approval dan browser lokal tidak menyatakan CD implemented atau produksi siap.
