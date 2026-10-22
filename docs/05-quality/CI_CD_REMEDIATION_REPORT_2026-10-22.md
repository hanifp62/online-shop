# Laporan Perbaikan dan Verifikasi CI/CD ZAFIRA

**Tanggal:** 22 Oktober 2026 WIB; timestamp eksekusi menggunakan UTC.\
**Task:** CICD-REM-20261022 — mandat pengguna «selesaikan semua perbaikan».\
**Baseline Git:** `041ed083a1be330d97d4f335cda8555670045cdd`; perubahan tetap lokal, belum commit/push.\
**Status:** perbaikan source dan gate lokal terverifikasi; **CI remote, CD, dan penerimaan produksi masih BLOCKED**.\
**Dokumen terkait:** [audit sebelum perbaikan](CI_CD_END_TO_END_AUDIT_2026-10-22.md), [runbook rilis](../04-implementation/CI_CD_RELEASE_RUNBOOK.md), [ledger tim](../04-implementation/TEAM_SUPPORT_LEDGER.md), [evidence](evidence-ci-remediation-2026-10-22/).

## 1. Ringkasan keputusan

Perbaikan tidak berhenti pada YAML. Scanner, report gate, skenario browser, proteksi branch, provenance artefak, helper MySQL/restore, PDF/font, dan readiness pemrosesan diperbaiki beserta regresinya. Review independen menemukan beberapa false-PASS yang kemudian ditutup. Acceptance browser nyata lokal berhasil setelah koreksi cleanup dan isolasi cart.

**Bukti integrator terbaru:**

| Pemeriksaan | Hasil aktual | Batas kesimpulan |
|---|---|---|
| PHP penuh melalui launcher terisolasi | **1.200 passed, 3 skipped, 157.683 assertions; EXIT=0** | SQLite fixture; tiga test khusus MySQL tidak dieksekusi di lane ini |
| Gate JUnit final | **PASS, EXIT=0**, tepat tiga skip diizinkan berdasarkan nama lengkap | Tidak mengubah skip menjadi pass; MySQL harus menjalankannya tanpa skip |
| Node penuh | **485 passed, 0 skipped; EXIT=0** | Mencakup regression mock producer browser, bukan semuanya browser nyata |
| Python penuh dengan FontTools terpasang | **338 passed, 0 skipped; EXIT=0** | Termasuk mock MySQL/artefak, bukan Docker/restore nyata |
| Pint | **383 berkas; EXIT=0** | Style, bukan pembuktian correctness |
| Vite production build | **EXIT=0** | Build frontend, bukan production release tar atau deploy |
| Actionlint 1.7.12 | **EXIT=0** | Validasi statis workflow baru, bukan run GitHub |
| Scanner secrets dan PII | **EXIT=0** | Batas regex, allowlist dan scope scanner tetap berlaku |
| Composer/npm audit | **EXIT=0**, tidak melaporkan advisory/vulnerability saat pemeriksaan | Snapshot advisory, bukan jaminan seluruh supply chain aman |
| Browser Chromium terisolasi + report gate | **Keduanya EXIT=0** | macOS/SQLite/fixture sintetis, bukan semua browser atau staging |

**Yang belum selesai secara operasional:** required protection belum diaktifkan, workflow baru belum dijalankan di GitHub, helper MySQL/restore dan production candidate belum dijalankan pada clean Linux runner, target deploy belum tersedia, dan drill alert/rollback/restore operasional belum ada. Tidak ada dasar untuk menyatakan «nol kendala», continuous availability terbukti, atau siap produksi.

## 2. Baseline, scope, dan keselamatan

Audit historis memotret remote `main` pada SHA baseline di atas: run `39578450137` sukses, branch belum protected, rulesets/environment/deployment/artefak kosong dalam cakupan GET saat itu. Run tersebut **tidak menguji perubahan ini**. Status remote tidak diubah pada remediasi.

Pekerjaan aplikasi dari [remediasi sebelumnya](REMEDIATION_REPORT_2026-10-21.md) dipertahankan. Angka historisnya PHP1.154/skip3, Node465, dan Python159 dieksekusi/skip6 bukan hasil source CI terbaru. Laporan lama tidak ditulis ulang menjadi bukti rilis baru.

Batas tindakan:

- Tidak commit, membuat branch, push, dispatch workflow, sync NgodingPakeAI, atau deploy.
- Tidak membaca/menampilkan nilai dotenv atau credential operasional. Scanner mengecualikan `.env*`. Suite otomatis yang sudah ada memeriksa template `.env.example`; ini bukan pembacaan secret operasional oleh agent.
- PHP/DB/browser hanya dijalankan integrator secara serial, memakai launcher dengan root baru dan fixture terisolasi. Tidak ada migrasi/charge/refund pada sistem operasional.
- Data browser berupa registrasi, alamat, cart dan order sintetis di SQLite milik test. Request halaman ke origin luar diblokir.
- Tidak melemahkan guard DB, skip gate, review independen, atau cleanup demi hijau. Tidak membunuh proses berdasarkan port; proses lama yang bukan milik invocation tidak disentuh.
- Source snapshot final memuat **137 path berubah/baru nonsecret** dalam [manifest final](evidence-ci-remediation-2026-10-22/source-manifest-closure.json). Ini inventaris gabungan pekerjaan lokal dalam scope source/test/config, bukan jumlah bug atau jumlah file yang hanya diubah sesi CI. Dotenv/data operasional/docs tidak dihash oleh manifest ini.

## 3. Perubahan yang diterapkan

### 3.1 CI wajib bertingkat, bukan satu check yang terlalu luas

[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) sekarang memisahkan:

```text
quality: install lockfile, audit, scanner, lint, build,
         migrasi/anti-drift SQLite, PHP + JUnit, Node, Python
  ├─ mysql: migrasi + retry fixture + restore disposable
  └─ browser: katalog + keyboard + checkout terisolasi + cleanup
       ↓ keduanya wajib sukses bersama quality
release: production dependency + build/verify kandidat artefak
       ↓
php: aggregate semua empat job harus tepat success
```

- Nama required check **`php` dipertahankan**, tetapi artinya sekarang aggregate. `always()` memastikan dependency skipped/cancelled/failure tidak membuat aggregate sukses.
- PHP8.4, Node22 dari `.nvmrc`, Python3.12, dependency berdasarkan lockfile, FontTools4.66.1 dan Poppler Linux dipasang pada lane yang memerlukannya.
- Lima Actions dipin SHA penuh yang sebelumnya dicocokkan ke referensi resmi; checkout `persist-credentials: false`, permission `contents: read`, Bash fail/pipefail, dan timeout per job.
- Summary terstruktur yang aman dipublikasikan, bukan raw JUnit, dump, cookie, atau seluruh direktori browser. Retensi14hari bukan kebijakan backup produksi.
- [Dependabot](../../.github/dependabot.yml) menjadwalkan review update Actions/npm/Composer; major update tidak digabung sembarangan. [Template PR](../../.github/pull_request_template.md) menyelaraskan requirement, review, gates dan evidence.

**Batas:** workflow ini CI + kandidat artefak, **belum CD**. Job release membangun ulang dependency produksi/aset; byte kandidat yang akan dipromosikan tetap harus diuji di staging, bukan dianggap identik dengan runtime job quality/browser.

### 3.2 Scanner dan hasil test fail-closed

- [`security_scan.py`](../../scripts/ci/security_scan.py): exit0 bersih, exit1 temuan, exit2 scanner error. Pengecualian sentinel sempit menggantikan pengecualian seluruh file; tidak mengikuti target symlink, tidak membuka dotenv, dan tidak mencetak nilai temuan. Test PHP memakai adapter scanner yang sama.
- [`check_test_report.py`](../../scripts/ci/check_test_report.py): menolak XML hilang/rusak, aggregate tidak cocok, failure/error, hasil kosong dan skip tak diizinkan. Identitas testcase publik menjadi opaque, bukan string yang dapat membawa token dari XML. Summary ditulis atomic.
- [`run_python_tests.py`](../../scripts/ci/run_python_tests.py): menolak skip, expected failure, unexpected success, error/import error, suite kosong/tidak lengkap. Font test yang sebelumnya skip kini benar-benar berjalan lokal memakai venv dengan dependency pinned.
- [`check_browser_report.py`](../../scripts/ci/check_browser_report.py): report/commands/invariant/tipe/schema wajib cocok; ID order unik, semua interval HTTP beririsan, dan bukti prepared cart serta order aktual wajib ada. Report format lama yang hanya memuat label quantity **sengaja ditolak**.

**Batas:** scanner bukan audit aliran data menyeluruh; checker membuktikan konsistensi output producer, bukan signature/attestation atau pengulangan transaksi sendiri.

### 3.3 PDF portable tanpa menyamarkan kegagalan sebagai skip

[`PdfTextExtractor`](../../tests/Support/PdfTextExtractor.php) menggunakan `pdftotext` pada Linux, dengan PDFKit/Swift fallback macOS. Dependency/runtime failure pada platform CI yang didukung adalah failure, bukan skip. Assertion Unicode, isi, dan paginasi laporan tetap dipertahankan di [AdminOrderReportTest](../../tests/Feature/AdminOrderReportTest.php).

Kompilasi Swift dicache per instance pada directory acak0700, executable/hash diverifikasi sebelum reuse, dan cleanup eksplisit/fallback tersedia. Ini menghindari kompilasi berulang per ekstraksi. Kegagalan compiler, binary berubah, output tidak valid, serta cleanup diuji pada [unit test](../../tests/Unit/PdfTextExtractorTest.php).

Targeted rerun **78 passed/1.392 assertions** selesai36detik dengan batas180detik yang sama; full suite sesudahnya juga lulus. PDF nyata yang dijalankan lokal adalah backend macOS, **bukan bukti Poppler Linux remote**.

### 3.4 Browser: perbaiki kualitas bukti, bukan sekadar status hijau

Dua akar masalah ditutup:

1. **Cleanup exited-singleton di Darwin.** Probe child Python milik invocation membuktikan `WNOWAIT` exited dan inventory singleton, tetapi signal lama menghasilkan EPERM. [`stop_owned()`](../../scripts/amadis/acceptance_server.py) sekarang final-wait tanpa signal hanya setelah kedua bukti positif. Worker tersisa memakai jalur TERM/KILL + whole-group drain; unknown identity/inventory dan EPERM pada jalur yang masih perlu signal tetap gagal. Tidak ada signal setelah reap. Author dan reviewer berbeda menjalankan probe innocuous serta regresi mock.
2. **Tiga context tidak berarti tiga cart.** `CartStore` keyed by user, sehingga fixture lama dengan pelanggan sama berbagi cart dan menimpa quantity. [Producer baru](../../scripts/amadis/acceptance_isolated.cjs) mendaftarkan tiga akun lewat alur publik, memilih slug stock37 exact, lalu membaca ulang seluruh cart **setelah semua write, sebelum checkout**. Setiap order sukses diverifikasi owner/product/quantity aktual. Aktor laporan menggunakan HMAC dengan kunci acak per-run, bukan user ID/credential mentah.

Regression producer nyata dengan transport/browser mock terdapat pada [`tests/ci-browser-fixtures.test.mjs`](../../tests/ci-browser-fixtures.test.mjs); gate Python dan cleanup memiliki regresi terpisah. Tidak ada bypass registrasi, CSRF, auth atau limiter.

**Acceptance nyata runr2:**

- 76 produk unik, 10 halaman; jumlah kartu `[8,8,8,8,8,8,8,8,8,4]`.
- Keyboard Enter/Space/Escape/close/focus-return lulus;12Tab, nol kebocoran fokus ke konten halaman. Native focus ke browser chrome dibedakan dari leak konten.
- Tiga aktor/cart independen dengan quantity20/20/17. HTTP **201/422/201**, order aktual **20 dan17 unit**; invariant stok dan total net sold lulus.
- Producer **EXIT=0**, report gate **EXIT=0**; probe pasca-run `lsof` menemukan **tidak ada listener8042** (exit1 yang berarti no match pada probe ini).
- Bukti: [browser summary](evidence-ci-remediation-2026-10-22/browser-summary.json), [verification](evidence-ci-remediation-2026-10-22/browser-verification.json), [run metadata](evidence-ci-remediation-2026-10-22/browser-acceptance-r2.json).

Ini membuktikan alur fixture dan irisan interval HTTP klien; **bukan konkurensi transaksi InnoDB, TPS produksi, real payment provider, seluruh admin flow, screen reader, atau kepatuhan WCAG penuh**.

### 3.5 Proteksi branch yang aman untuk diaktifkan

[`protect-main.sh`](../../scripts/ci/protect-main.sh): default preview offline; mutasi perlu apply eksplisit dan reviewer independen. Membandingkan **ID akun kanonis**, bukan kapitalisasi login; mewajibkan role yang cukup dan check sukses dari GitHub Actions App15368. Tidak menimpa branch yang sudah protected tanpa review; readback memeriksa hasil.

Policy: strict required `php`, minimal satu approval independen, stale review dismissal, last-push approval, admins enforced, tanpa force-push/deletion. Mock mencakup akun sama dengan casing berbeda, ID invalid, API error, role salah, dan reviewer sah.

**Belum diaktifkan.** Audit sebelumnya menemukan hanya satu collaborator `hanifp62`. Memaksakan approval tanpa reviewer manusia lain dapat mengunci alur merge. Meminta reviewer independen bukan alasan untuk menghapus approval. Test penolakan PR/protection remote masih wajib.

### 3.6 Artefak rilis terikat source dan lockfile

[`release_artifact.py`](../../scripts/ci/release_artifact.py) memiliki `build`/`verify`: clean checkout wajib, source dibandingkan dengan Git blob, vendor production diverifikasi terhadap lockfile, aset Vite wajib lengkap. Manifest memuat commit, digest archive, lock hashes, serta origin/hash setiap entry; input/path/link/duplicate berbahaya ditolak. Verifikasi tanpa extraction/execution. Dotenv, data operasional, storage, upload, config caches dan dev tooling tidak ikut.

Regression mock dan review independen lulus. **Build kandidat production nyata belum dijalankan**: working tree ini sengaja dipertahankan kotor dan vendor lokal dev. Guard tidak dilewati dengan commit otomatis, reset, atau menghapus pekerjaan pengguna. Digest+manifest bukan signature; provenance channel dan smoke kandidat tepat di staging tetap wajib.

### 3.7 MySQL dan restore disposable, bukan database host

[`mysql_integration.py`](../../scripts/ci/mysql_integration.py) menyiapkan dua container Linux milik job, loopback/random port, imageID aktual, schema test fixed yang diizinkan guard, dan generated credential melalui environment. Memeriksa ownership sebelum akses/cleanup, migrate biasa, tepat tiga test retry tanpa skip, dump/restore ke container kedua, schema/migrations/counts/marker/FK, lalu cleanup. Summary safe/atomic; raw JUnit/dump/private runtime tidak dipublikasikan.

**Belum ada eksekusi nyata helper ini.** Probe Docker sebelumnya gagal karena socket daemon tidak tersedia; helper juga memang Linux-only dan menolak dirty checkout. Tidak memulai daemon, mengarahkan helper ke database host, atau melonggarkan guard. Mock tidak membuktikan deadlock paralel nyata, seluruh data-value equivalence, upgrade N−1, restore off-site, maupun RPO/RTO.

### 3.8 Readiness scheduler/worker terpisah dari HTTP health

[`ProcessingReadiness`](../../app/Services/ProcessingReadiness.php), [`RecordProcessingHeartbeat`](../../app/Jobs/RecordProcessingHeartbeat.php), [`config/operations.php`](../../config/operations.php), dan [schedule](../../routes/console.php) menambahkan `ops:heartbeat`/`ops:readiness`:

- Hanya shared cache dan async transport database/Redis yang diverifikasi; sync/file/array/in-memory tidak dijadikan bukti worker sehat.
- Scheduler heartbeat ditambah penyelesaian probe worker; duplicate/stale probe tidak menyegarkan status secara palsu.
- Queue/backlog/pending-age, freshness dan latency diuji; enqueue dibatasi lock/lease. Default threshold freshness180s, latency60s, pending-age300s, backlog100 dan retry300s.
- JSON aman dan exit0/1; readiness tidak mendispatch job sendiri. `/health` tidak diubah menjadi klaim semua proses sehat.

[Tests](../../tests/Feature/ProcessingReadinessTest.php) lulus dalam full PHP, tetapi transport/cache diuji dengan double. Scope hanya **default queue**, bukan seluruh queue atau multi-host. Threshold adalah konfigurasi awal, bukan SLO disepakati. Real daemon, restart/reload, clock/lock topology, alert delivery dan operator response **belum terbukti**.

## 4. Traceability acceptance NFR-CICD

AC lengkap tetap pada [audit §8](CI_CD_END_TO_END_AUDIT_2026-10-22.md#8-rencana-perbaikan-dengan-acceptance-criteria). Status di bawah tidak menutup requirement yang memiliki komponen eksternal.

| ID | Yang sudah diperbaiki/diverifikasi | Yang masih menahan acceptance penuh |
|---|---|---|
| NFR-CICD-001 | Script protection, ID reviewer kanonis, mock fail-closed | Reviewer manusia kedua, izin apply, GET policy dan drill PR nyata |
| NFR-CICD-002 | Workflow/gates baru, local regression, manifest source | Izin commit/push, PR dan run clean GitHub pada SHA terbaru |
| NFR-CICD-003 | Scanner kanonis, regression, Actions SHA, audits lokal | Eksekusi ulang clean runner dan review dependency/secret berkelanjutan; bukan jaminan semua kebocoran terdeteksi |
| NFR-CICD-004 | PHP/Node/Python lokal, PDF macOS, browser nyata lokal dan skip/report controls | Poppler/PHP8.4/Node22/Python3.12 Linux, MySQL lane, race DB dan cakupan a11y/staging |
| NFR-CICD-005 | Build/verify helper + mock, workflow candidate | Candidate build nyata, channel provenance, target staging/lock, smoke artefak tepat, approval promosi |
| NFR-CICD-006 | Helper restore fixture MySQL + mock/guards | Linux drill nyata, upgrade/N−1, app rollback, backup off-site, rekonsiliasi, RPO/RTO |
| NFR-CICD-007 | Heartbeat/readiness + regression | Daemon/scheduler host, gangguan terkendali, alert diterima operator, owner/scope queue |
| NFR-CICD-008 | Runbook, evidence/timestamp, batas metrik dan owner checklist | Workload/SLI/SLO, TPS/p95/p99, event deployment/incident/recovery dan drill operator |

## 5. Catatan eksekusi dan kegagalan

### Bukti final

- Full PHP penutupan `2026-10-21T21:50:54Z–21:55:10Z`,256,074detik; [metadata](evidence-ci-remediation-2026-10-22/php-closure.json), [JUnit summary](evidence-ci-remediation-2026-10-22/phpunit-closure-summary.json), [checker](evidence-ci-remediation-2026-10-22/php-closure-report-gate.json). Argumen JUnit/root aktual ada pada metadata, bukan path operasional.
- Node485 dan Python338: [indeks hasil terbaru dan binding source](evidence-ci-remediation-2026-10-22/verification-index.json), [Node metadata](evidence-ci-remediation-2026-10-22/node-closure.json), [Python metadata](evidence-ci-remediation-2026-10-22/python-closure.json). Angka337 pada snapshot sebelum tambahan regresi loader tetap riwayat; tidak ditimpa.
- Browserr2 `21:22:43Z–21:22:51Z`,8,213detik; gate `21:23:27Z`, kemudian [checker ulang sesudah typeguard](evidence-ci-remediation-2026-10-22/browser-gate-closure.json) `21:48:37Z` tetap EXIT=0. Producer browser tidak berubah setelah R2. Seluruh angka bersumber dari execution, bukan perkiraan kinerja e-commerce.
- [Pint](evidence-ci-remediation-2026-10-22/pint-full.json), [build](evidence-ci-remediation-2026-10-22/build.json), [actionlint](evidence-ci-remediation-2026-10-22/actionlint.json), [Composer audit](evidence-ci-remediation-2026-10-22/composer-audit.json), [npm audit](evidence-ci-remediation-2026-10-22/npm-audit.json). Delta sesudah lint/build hanya harness browser/tes dan narrowing tipe helper Python, bukan aplikasi/frontend PHP.
- Runtime lokal: **macOS, PHP8.5.11, Node24.21.0, npm11.19.0, Python3.14.8**, Playwright1.64.1/Chromium156.0.8078.4. Tidak disamakan dengan matriks Linux CI.
- Log yang disimpan collector disanitasi dan diabaikan Git melalui pola `.log`; JSON metadata/summary dan laporan adalah catatan durable. Raw JUnit serta evidence browser tetap privat. Hash log hanya binding file lokal, bukan janji file log ikut PR.

### Kegagalan dan koreksi yang tidak disembunyikan

1. Targeted PHP awal timeout180detik; collector lama melempar exception sebelum metadata/log dibuat. Hasil suite awal **UNKNOWN**, tidak ada file bukti yang dikarang. Inspeksi menemukan biaya compile Swift per ekstraksi; cache diperbaiki. Rerun dengan batas180 yang sama lulus. Tidak cukup bukti untuk menyatakan biaya compile sebagai satu-satunya penyebab timeout awal.
2. Browserr1 **EXIT=1** pada cleanup EPERM; subhasil interaksi tidak digunakan sebagai PASS. [Diagnosis](evidence-ci-remediation-2026-10-22/browser-failure-diagnosis.json) dipertahankan. Probe positif dan regresi memperbaiki root path; browserr2 baru menjadi bukti lulus.
3. Fixture shared cart lama dapat melaporkan labelquantity yang tidak sama dengan quantity order. Status HTTP lama bukan bukti stock-contention yang dimaksud. Fixture/checker/regression diperketat, bukan report lama diedit agar lolos.
4. Review menemukan identifier leak JUnit, stale PASS summary MySQL saat write failure, self-review casing, duplicate-order dan serial-request false-PASS. Author memperbaiki dengan regresi; reviewer independen menutup delta source masing-masing. Tidak ada nilai rahasia yang perlu diulang untuk menjelaskan masalah.
5. Sesi author browser sempat gagal DNS; diteruskan pada sesi yang sama, tanpa penulis paralel pada file yang sama. Tidak dipresentasikan sebagai kegagalan aplikasi.
6. Pemeriksaan editor menemukan type error pada helper/test CI baru. Guard list/dict/optional, loader, dan result holder bertipe diperbaiki tanpa suppression atau schema weakening; regresi loader ditambah. Reviewer berbeda menyetujui delta; full PHP/Node/Python dan browser checker diulang sesudahnya. **Diagnostik editor seluruh repo tidak bersih:** masih ada warning style pada Python serta error pada berkas di luar slice ini, antara lain `scripts/amadis/launch.py` dan tes Amadis lama. Tidak ada klaim typecheck repo-wide lulus; warning broad-exception pada gate yang sengaja tidak mencetak data sensitif tidak dihapus dengan logging mentah.

## 6. Review independen, lensa audit, dan skill

| Scope | Author | Reviewer independen / keputusan source |
|---|---|---|
| Scanner/result gates/browser fixture | `da87cebf-9b1d-4bb3-9292-36c171b8f8fc` | `031cbd0c-d409-442c-b3f4-c8109ddac856` — APPROVE scoped source/mock setelah rework |
| PDF/JUnit/font | `6dc927f6-239d-470a-a0a8-ce4b3d2062f0` | `031cbd0c-d409-442c-b3f4-c8109ddac856` — APPROVE scoped |
| Readiness | `b8bffbea-facc-4678-a0d8-70de007c40dd` | `4277979b-1f7e-46b0-a160-3a13b3eb2deb` — APPROVE scoped |
| Release artifact | `0b5617a6-2c87-4991-8e74-98fceb31ac42` | `4277979b-1f7e-46b0-a160-3a13b3eb2deb` — APPROVE source/mock |
| MySQL/restore | `dfe38129-9390-40d4-9f8d-f441eb23f1c0` | `031cbd0c-d409-442c-b3f4-c8109ddac856` — APPROVE source/mock |
| Cleanup | `108ae1a3-c6e8-4d86-baae-a5b5c6b94c9b` | `4277979b-1f7e-46b0-a160-3a13b3eb2deb` — APPROVE; mock/native innocuous ricek |
| Workflow/protection/integrasi | Integrator Zed; ID utama tidak diekspos | `4277979b-1f7e-46b0-a160-3a13b3eb2deb` — APPROVE source termasuk canonical account ID |
| Runbook dan laporan penutupan | Runbook author readiness + rekonsiliasi integrator; laporan integrator | `4277979b-1f7e-46b0-a160-3a13b3eb2deb` — APPROVE scoped laporan/runbook/indeks/readiness setelah koreksi status R1/R2; bukan approval produksi |

Delta typeguard penutup juga direview: reviewer031 menyetujui scanner/browser/JUnit serta dua test loader integrator; reviewer427 menyetujui release helper/test. Bukti runtime dan manifest diperbarui setelah perubahan ini, bukan mewariskan hash lama.

Lensa dewan berupa pertanyaan/metode, **bukan keterlibatan atau endorsement tokoh**:

- Hightower: apakah operator lain dapat menjalankan gate yang sama? Jawaban: workflow/runbook/commands jelas; target CD tetap belum diketahui.
- Forsgren: apakah ada event delivery/recovery nyata? Belum; angka test/job tidak dipakai sebagai DORA.
- Treynor Sloss: apakah readiness/SLI membuktikan SLO? Tidak; probe default queue dan threshold tidak menggantikan SLO/error budget.
- Majors dan Vogels: apakah worker mati/cleanup gagal terdeteksi? Test fail-closed tersedia; alert/operasi host belum dibuktikan.
- Fowler/Beck/Kleppmann/Lamport: source terikat tes, RED→GREEN, identitas/invariant, retry dan laporan false-PASS diperiksa. Mock tidak diperlakukan sebagai konsistensi DB nyata.
- Norman/Nielsen/Shneiderman/Cooper/Engelbart/Brown/Krug serta Watson: acceptance fokus pada signifier/keyboard, status, error prevention, kendali dan pengembalian fokus. Tidak ada redesign atau audit ulang seluruh HCI; screen reader, pengguna nyata dan seluruh admin tetap gate tersendiri.
- Hudson/Purbo/Berners-Lee: runbook memakai bahasa Indonesia, boundary dan jalur reproduksi jelas; tidak mengarang hasil perangkat murah/jaringan lambat.

Dasar keputusan teknis adalah source/test/probe pada laporan ini dan dokumentasi primer GitHub yang tercatat dalam [audit §9](CI_CD_END_TO_END_AUDIT_2026-10-22.md#9-lensa-audit-skill-dan-referensi). Parafrase lensa bukan kutipan langsung tokoh.

Skill relevan digunakan: `ci-cd-and-automation`, `incremental-implementation`, `git-workflow-and-versioning`, `debugging-and-error-recovery`, `laravel-tdd`, `laravel-patterns`, `laravel-verification`, `code-review-and-quality`, `documentation-and-adrs`, serta discovery `instructree`/`browser-testing-with-devtools`. DevTools MCP tidak tersedia; browser memakai runner Playwright repo, bukan klaim MCP. Langkah skill yang membaca dotenv, commit, atau mutate operasi tidak dijalankan karena safety/otorisasi lebih tinggi. Skill desain/animasi/native lain tidak dipaksakan pada scope CI/CD; tidak relevan dan akan menambah perubahan tanpa tujuan.

## 7. Sisa pekerjaan yang memerlukan keputusan/lingkungan

**Urutan untuk menutup gate end-to-end, tanpa melewati dependensi:**

1. Maintainer memberi izin **commit/push/PR** atas perubahan terkurasi. Setelah source tersedia di remote, jalankan workflow baru pada SHA yang benar; review hasil Linux, bukan memakai green baseline.
2. Sediakan **reviewer manusia independen** dan izin aktivasi proteksi; verifikasi GET + drill PR gagal/tanpa approval. Jangan menurunkan approval karena repo baru memiliki satu collaborator.
3. Selesaikan MySQL8.4/restore dan release candidate di **clean Linux runner**. Jika engine produksi berbeda, tambah parity test yang sesuai, bukan menebak kompatibilitas dari MySQL8.4.
4. Tetapkan **target staging/produksi dan owner**: host/platform, engine, storage, secret injection, deployment lock, migrasi/reload, provider sandbox. Baru implementasikan CD yang sesuai target, tanpa host/secret placeholder.
5. Smoke kandidat tepat, scheduler/worker nyata, alert diterima operator, upgrade/N−1, rollback aplikasi, restore off-site/rekonsiliasi dan RPO/RTO. Ukur workload/TPS/SLI/SLO serta alur HCI/a11y yang belum diuji sebelum approval produksi.

**Tidak diperiksa:** pipeline Linux baru secara remote; policy GitHub aktif; runtime Poppler Linux; real MySQL/restore helper; signature/provenance/promotion layanan; hosting/TLS/FPM/cron/worker/shared-cache produksi; provider/SMTP eksternal; load/soak/failover; screen reader/lintas-browser/perangkat; operasi pengguna/admin lengkap; recovery uang/data eksternal. Tidak ada bukti bahwa hal-hal ini pasti rusak, tetapi juga tidak ada dasar menyebutnya lulus.

## 8. Continuous Evaluation Record

- **Task/AC:** CICD-REM-20261022, NFR-CICD-001…008; AC tidak diturunkan dan status parsial pada §4.
- **Baseline:** audit read-only, SHA041ed08 dan source remediasi yang belum commit; scanner fail-open, coverage/gate/artefak/operasi belum memadai.
- **Controlled change:** lane/write-set terpisah, tanpa overwrite pekerjaan pengguna; perbaikan source CI, portable test, readiness dan harness.
- **Ricek:** integrator menjalankan targeted→full PHP, Node/Python, build/lint/audit, JUnit gate serta browser producer+checker; command/time/exit aktual di metadata.
- **Crosscheck:** reviewer berbeda dari author pada tabel; temuan direproduksi/dicocokkan sebelum rework. Source approval tidak disamakan dengan runtime atau persetujuan rilis.
- **Comparison:** scanner/report fail-closed dan browser nyata lokal kini lulus; Python tidak skip; MySQL/candidate/protection/deploy tetap gate eksternal, bukan keberhasilan fiktif.
- **Binding evidence:** source manifest final dan pemeriksaan closure mengikat hash aktual, hasil dan tautan; source yang berubah sesudahnya memerlukan verifikasi ulang yang relevan.
- **Decision:** **ACCEPTED untuk remediasi source/lokal dalam scope bukti; BLOCKED untuk penerimaan CI/CD end-to-end/produksi.** Penutupan lokal bukan izin commit/push/deploy atau klaim tidak akan ada defect lagi.
