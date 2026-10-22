# Ricek CI/CD end-to-end ZAFIRA — 22 Oktober 2026

**Task:** AUD-CICD-20261022 · **Jenis:** audit read-only terhadap aplikasi, workflow, dan remote; penulisan hanya laporan/evidence/indeks/ledger.\
**Baseline lokal dan remote `main`:** `041ed083a1be330d97d4f335cda8555670045cdd`. Working tree remediasi belum commit/push.\
**Waktu bukti utama:** 21 Oktober 2026, sekitar 17:03–17:07 UTC, yaitu 22 Oktober WIB. Timestamp UTC pada artefak dipertahankan.\
**Keputusan:** **CI baseline terbukti berjalan; enforcement merge belum aktif; CD end-to-end belum terbukti. Bukan persetujuan produksi.**

## 1. Ringkasan keputusan

1. **CI benar-benar ada dan pernah sukses di GitHub**, bukan hanya file YAML atau tes lokal. Run [39578450137](https://github.com/hanifp62/online-shop/actions/runs/39578450137) berhasil untuk SHA `041ed08`, event `push` ke `main`.
2. **`main` belum dilindungi:** API mengembalikan `protected: false`, endpoint protection mengembalikan `404 Branch not protected`, dan daftar ruleset termasuk parent kosong. Check `php` tersedia, tetapi belum diwajibkan oleh proteksi/ruleset GitHub.
3. **Run hijau itu tidak menguji perubahan working tree terbaru.** Workflow pada commit tersebut belum menjalankan Python dan masih memakai anti-drift lama. Penambahan Python serta perbaikan anti-drift ada lokal, belum terbukti di GitHub.
4. **GitHub belum mencatat jalur delivery/deployment:** satu workflow CI, nol environment, daftar deployment kosong, dan nol artefak pada run yang diperiksa. Penelusuran repo juga belum menemukan mekanisme deployment eksternal konkret. Ini tidak membuktikan bahwa server/platform/repo lain tidak memiliki CD.
5. **Ada defect gate yang direproduksi:** scanner PII dapat berakhir sukses ketika `grep` mengalami error. Selain itu, ada pemeriksaan yang dilewati pada Linux, gap engine database, dan belum ada browser E2E dalam workflow.
6. **Rollback yang tersedia bukan rollback aplikasi lengkap:** R-39 mematikan fitur laporan admin. Restore engine target, pemulihan artefak aplikasi, reload worker, dan smoke setelah rollback belum memiliki bukti staging end-to-end.

Alur aktual yang terverifikasi:

```text
PR (trigger tersedia; run PR belum terbukti) / push main
  → GitHub CI, job php
  → install → audit/scans → build/Pint → migrasi SQLite → PHP/Node
  → check success pada SHA 041ed08
  → proteksi merge TIDAK AKTIF
  → artefak rilis / deploy staging / approval / deploy produksi: BELUM TERBUKTI
  → smoke + observasi rilis + rollback aplikasi: BELUM TERBUKTI
```

Nama job `php` tidak berarti hanya PHP: job yang sama menjalankan Node dan, pada working tree baru, Python.

## 2. Metode dan batas klaim

- **Fakta:** source yang dibaca, metadata GitHub yang diperoleh lewat GET, hasil probe sintetis, serta evidence lokal yang sudah tersedia.
- **Inferensi:** risiko yang mengikuti perilaku source, misalnya queue tetap dapat diakses ketika worker mati. Tidak disajikan sebagai insiden produksi.
- **Unknown / TIDAK TERBUKTI:** runtime staging/produksi, platform eksternal, nilai konfigurasi efektif, keberhasilan restore, dan approval di luar GitHub yang diperiksa.
- **Rekomendasi:** rencana dan acceptance criteria di §8 belum diimplementasikan/disetujui otomatis oleh audit ini.

Dua reviewer independen membantu penelusuran, kemudian integrator mencocokkan temuan inti dengan source dan API:

| Reviewer | Sesi | Cakupan |
|---|---|---|
| CI / platform-security | `9bd61a94-aa82-4095-9750-4259fd7f7e6d` | Runner bersih, Composer/Artisan, skip, scanner, keamanan Actions, protection script |
| CD / release-reliability | `1051e569-f163-4f4a-8d1d-b99271d7c9b5` | Deployment/provenance, staging, health, worker/scheduler, migrasi, rollback/restore, observabilitas |

Reviewer tidak menjalankan suite aplikasi atau mengakses remote; bukti API/probe diperoleh integrator. Identitas sesi integrator tidak diekspos tool dan tidak dikarang. Model mengikuti konfigurasi subagent; tidak ada klaim benchmark bahwa model tertentu “terbaik”.

## 3. Bukti GitHub aktual

Sumber mesin: [remote-snapshot.json](evidence-ci-cd-2026-10-22/remote-snapshot.json). Setiap query mencatat argv, waktu, exit, dan hasil berfield terbatas; tidak menyimpan token/secret.

| Lapisan | Hasil terverifikasi | Arti / batas |
|---|---|---|
| Repo/default branch | `hanifp62/online-shop`, `main` | Identitas diperoleh dari `gh repo view`, bukan menebak URL |
| SHA remote | `041ed083a1be330d97d4f335cda8555670045cdd` | Sama dengan HEAD lokal, bukan working tree lokal |
| Workflow | 1, `CI`, `.github/workflows/ci.yml`, aktif | Tidak ada workflow CD lain dalam daftar yang dikembalikan |
| Riwayat runs yang tersedia | `total_count: 1`, event `push`, attempt 1 | Tidak ada bukti run PR dalam riwayat yang tersedia; bukan klaim tidak pernah ada run yang telah dihapus |
| Run terakhir | `39578450137`, `completed / success` | 21 Oktober, 09:37:52–09:40:15 UTC |
| Job | `php`, `success`, 09:37:55–09:40:14 UTC | **139 detik / 2 menit 19 detik**; bukan rata-rata performa CI |
| Commit check | `php`, `success`, App `github-actions`, ID `15368` | Nama/sumber check aktual tersedia untuk rancangan required check |
| Branch protection | `protected: false`; GET protection → HTTP 404 | CLI exit 1 di sini adalah bukti konfigurasi tidak ada, bukan gangguan autentikasi |
| Rulesets | `[]`, dengan `includes_parents=true` | Tidak ditemukan ruleset pengganti proteksi branch |
| Environments | `total_count: 0` | Tidak ada environment approval GitHub pada snapshot ini |
| Deployments | `[]` | Tidak ada deployment tercatat pada endpoint yang diperiksa; CD di luar GitHub tetap unknown |
| Artefak run | `total_count: 0` | Build sukses tidak menghasilkan release artifact yang dipublikasikan lewat Actions pada run ini |

Seluruh step yang tercatat pada job lama berstatus sukses: checkout/setup, Composer/npm install dan audit, scan, build, Pint, migrasi/anti-drift, subset a11y/schema/brand/tone, PHP, Node, serta cleanup. **Kesuksesan step test tidak berarti semua test dieksekusi tanpa skip.**

### Source lokal bukan source run remote

Hasil pemeriksaan `git diff -- .github/workflows/ci.yml` dan hash:

| Snapshot | SHA-256 workflow | Perbedaan penting |
|---|---|---|
| Commit `041ed08` | `7d025c8c6464a278517e93171ed56f5ab31c6061ad89933bd400329974f2a5fc` | Belum ada step Python; anti-drift lama memakai pipeline dalam kondisi `if` |
| Working tree | `9b77b816d8e093e3d28d1bb3927f1b5b91e05a39c01bf2d80c8e640436918fc6` | Python 3.12 + fixture root + unittest; kegagalan `migrate:status` dipropagasikan |

Hash **132 source/test** pada [manifest remediasi](evidence-remediation-2026-10-21/source-manifest-export-final.json) diperiksa ulang dan cocok tanpa drift melalui [ci-probes.json](evidence-ci-cd-2026-10-22/ci-probes.json). Ini mengikat bukti lokal sebelumnya; manifest tersebut secara eksplisit tidak mencakup environment/runtime/build sehingga bukan provenance artefak deploy.

Bukti lokal sebelumnya: PHP **1.154 passed / 3 skipped**, Node **465 passed**, Python **159 dieksekusi / 6 skipped**, Pint **377 file**, build sukses. Lihat [laporan remediasi](REMEDIATION_REPORT_2026-10-21.md) dan [metadata PHP final](evidence-remediation-2026-10-21/php-full-export-final.json). **Suite tidak diulang dalam audit CI/CD ini, dan angka lokal tidak dipindahkan menjadi angka CI remote.**

## 4. Kekuatan CI yang sudah tersedia

Sumber: [workflow](../../.github/workflows/ci.yml), [Composer](../../composer.json), [Node package](../../package.json), [hooks](../../app/Support/ComposerHooks.php).

- Trigger `push` ke `main` dan `pull_request`; izin `contents: read`; concurrency per ref dengan pembatalan run CI lama; timeout job 20 menit.
- `composer install` dan `npm ci` menggunakan lockfile, bukan upgrade dependency saat CI.
- Audit dependency Composer dan npm tersedia. `npm audit --audit-level=high` membatasi status gagal pada severity tersebut; bukan klaim semua advisory selalu nol.
- Scanner secret memakai nama file saja dan membedakan match, bersih, serta error. Tidak ada `continue-on-error` pada gate.
- Build aset, Pint, migrasi SQLite disposable, anti-drift, regresi PHP/Node, a11y markup/schema/brand/tone tersedia; Python ada pada working tree baru.
- Workflow PHP `8.4` selaras dengan requirement `^8.4.1` bila resolver memilih patch 8.4 yang memenuhi minimum; Node `22` selaras dengan engine minimum `22.12`. Run lama membuktikan baseline berhasil, bukan seluruh versi patch atau source baru.
- **Artisan langsung tidak otomatis melanggar guard.** `ComposerHooks.php:12–18,51–68` membedakan jalur normal dan isolasi. Tanpa penanda isolasi, `package:discover` berjalan normal dan kegagalannya dipropagasikan melalui `mustRun()`.
- Konfigurasi PHPUnit yang diperiksa secara terbatas memakai SQLite `:memory:`, queue `sync`, cache/session/mail `array`. Baik untuk isolasi unit/feature; bukan simulasi worker, SMTP, atau engine produksi.

Durasi job baseline 139 detik tidak menunjukkan kebutuhan sharding agresif. Pisahkan lane untuk cakupan/diagnostik bila diperlukan, bukan mengejar banyak agent atau job tanpa bottleneck terukur.

## 5. Temuan prioritas

Severity menunjukkan risiko bila keadaan ini dipakai untuk menyetujui rilis, bukan bukti eksploitasi atau insiden produksi.

### CICD-01 — HIGH: merge belum dipaksa melewati CI dan review

**Bukti:** §3, serta [scripts/ci/protect-main.sh:7–25](../../scripts/ci/protect-main.sh#L7).

Script menyediakan required check `php`, strict update, satu approval, dismissal approval lama, approval push terakhir, enforcement admin, serta penolakan force-push/deletion. **Script tersedia ≠ konfigurasi telah diterapkan.** Saat ini tidak ada branch protection/ruleset yang mewajibkan itu.

**Dampak:** aktor dengan hak tulis tidak diwajibkan oleh aturan tersebut untuk menunggu check/review sebelum perubahan masuk `main`.

**Rekomendasi:** maintainer menerapkan aturan dengan otorisasi eksplisit, memastikan reviewer independen tersedia agar repo tidak terkunci, lalu memverifikasi GET dan skenario penolakan PR gagal/tanpa approval. Pertimbangkan mengikat check ke App `github-actions`, bukan sekadar nama. Jika job dipecah, perbarui required check atau gunakan aggregate gate yang fail-closed.

**Batas:** script tidak dijalankan. `DRY_RUN=1` tanpa `REPO` masih melakukan discovery `gh repo view`; preview bukan sepenuhnya offline.

### CICD-02 — HIGH: source terbaru belum memiliki bukti CI remote

**Bukti:** SHA, diff, hash pada §3. Run sukses menguji baseline sebelum remediasi; Python belum ada dalam run tersebut.

**Dampak:** lulus lokal tidak menjamin kompatibilitas checkout Linux bersih, Python 3.12, dependency resolver, atau suite terbaru di GitHub. Ini gap delivery/provenance, bukan klaim bahwa source baru pasti rusak.

**Rekomendasi:** setelah persetujuan commit/push, kirim perubahan terkurasi melalui PR, rekam head/merge SHA yang benar-benar diuji, dan wajibkan CI baru. Jangan menamai run `041ed08` sebagai bukti remediasi terbaru.

### CICD-03 — MEDIUM: scanner PII gagal terbuka saat error

**Source:** [ci.yml:50–54](../../.github/workflows/ci.yml#L50). `grep` berada dalam kondisi `if`, tanpa cabang penanganan error. `set -e` tidak membuat error di kondisi itu menggagalkan step.

**Reproduksi aman:** blok asli dieksekusi dengan fungsi pengganti `grep` yang hanya mengembalikan exit code; tidak membaca aplikasi/PII atau menjalankan DB/server.

| Kondisi stub | Exit `grep` | Exit shell step aktual | Penilaian |
|---|---:|---:|---|
| Tidak ada match | 1 | 0 | Sesuai |
| Ada match | 0 | 1 | Sesuai |
| Error pembacaan | 2 | **0** | **Defect terkonfirmasi** |

[Probe](evidence-ci-cd-2026-10-22/ci-probes.json) selesai **EXIT=0 karena berhasil membuktikan defect**, bukan karena scanner sudah diperbaiki. Opsi `-RInE` juga mencetak seluruh baris temuan; bila baris memuat nilai sensitif, nilai itu dapat masuk log. Tidak ada kebocoran aktual yang dibuktikan audit ini.

**AC perbaikan:** hanya exit 1 dianggap bersih; match/error menggagalkan gate; keluaran path-only atau identitas temuan yang aman. Pertahankan regression untuk ketiga kondisi. Scanner berbasis regex tetap heuristik, bukan jaminan semua aliran PII tercakup.

### CICD-04 — MEDIUM: cakupan green CI belum setara kebutuhan rilis

| Gap | Bukti | Kesimpulan berbatas |
|---|---|---|
| Ekstraksi isi PDF di Linux | [AdminOrderReportTest.php:50–60](../../tests/Feature/AdminOrderReportTest.php#L50) | Helper skip di non-Darwin; assertion isi yang bergantung helper tidak selesai di Linux |
| Jalur guard launcher yang valid | [ComposerHooksTest.php:47–54](../../tests/Unit/ComposerHooksTest.php#L47) | Test skip bila `AMADIS_ROOT` tidak ada; workflow Artisan normal tidak menyediakan root launcher |
| Enam pemeriksaan struktur/glyph font | [test_amadis_pdf_font.py:12–15,52–94](../../tests/python/test_amadis_pdf_font.py#L52) | Skip bila FontTools tidak tersedia; workflow baru tidak memasangnya. Status aktual interpreter CI baru belum diuji |
| MySQL/MariaDB retry | [MysqlCheckoutRetryTest.php:22–25](../../tests/Feature/MysqlCheckoutRetryTest.php#L22) | SQLite tidak menjalankan cabang ini. Bahkan injection engine target bukan bukti deadlock konkurensi nyata |
| Browser/E2E, keyboard/pembaca layar, load | [ci.yml](../../.github/workflows/ci.yml) | Tidak ada job/step terkait; markup/fake-DOM tidak membuktikan alur browser atau WCAG penuh |
| Coverage | `coverage: none`, tanpa ambang coverage | Banyak assertion bukan persentase coverage |

**AC:** definisikan test rilis wajib; pasang dependency font/extractor Linux dengan versi terkontrol, pisahkan validasi guard aman, serta uji disposable engine target. Publikasikan laporan pass/fail/skip yang terstruktur. Skip wajib yang tidak disetujui harus terlihat dan menahan rilis, bukan disembunyikan atau guard DB dilonggarkan. Gate browser/staging melengkapi, bukan menggantikan regresi unit.

### CICD-05 — HIGH: rantai artefak → staging → approval → deploy belum terbukti

**Bukti:** nol artifact/environment/deployment di §3; workflow berhenti pada test; [README:15–27](../../README.md#L15) adalah setup lokal; [runbook lingkungan:26–33](../04-implementation/SCHEDULED_TASKS_AND_ENV_PARITY.md#L26) masih menyatakan staging/produksi belum diverifikasi.

**Dampak:** belum bisa menjawab “byte mana yang diuji, byte mana yang dipasang, siapa menyetujui, dan versi mana untuk kembali”. `public/build` diabaikan Git, dan membangun aset tidak sama dengan memublikasikan release artifact.

**Crosscheck eksternal:** reviewer menelusuri nama file dan isi source/docs/scripts untuk Forge/Vapor/cloud/SSH/rsync/orchestration. Penyebutan platform dalam komentar trust proxy, `docker_stage.py` untuk Composer terisolasi, serta rsync uji lokal bukan bukti deployment aplikasi aktif. Server-side hook atau repo infrastruktur lain tetap mungkin ada.

**AC:** definisikan target deployment dan owner; satu release ID mengikat commit, lockfile, build/runtime, digest, gate, target, approval, dan previous version. Promosikan artefak yang sama setelah staging lulus. Jangan menyimpan environment, secret, atau config-cache berisi secret dalam artefak.

### CICD-06 — HIGH: pemulihan aplikasi dan data belum teruji end-to-end

- [SAFE_RELEASE_R39.md:52–61,75–88](../04-implementation/SAFE_RELEASE_R39.md#L52) menguji kill switch fitur secara lokal. Itu bukan redeploy versi aplikasi sebelumnya, pemulihan checkout, atau restore DB. Angka sekitar 0,2 detik bukan RTO layanan.
- [Migrasi uang `000050`:31–53](../../database/migrations/2026_10_21_000050_widen_return_refund_money_columns.php#L31) melakukan preflight, tetapi memerlukan writer dihentikan karena DDL MySQL dapat auto-commit. Pemeriksaan source tidak menghentikan HTTP/worker/scheduler secara operasional.
- [Migrasi provenance `000060`:20–29](../../database/migrations/2026_10_21_000060_add_return_allocation_provenance.php#L20) sengaja menolak rollback bila data provenance terisi. Jangan menghapus guard demi hasil rollback hijau.
- [DatabaseBackupService.php:140–169](../../app/Services/DatabaseBackupService.php#L140) memverifikasi integritas snapshot SQLite dan menolak verifikasi MySQL/MariaDB. Checksum/dump tidak membuktikan import, keberlangsungan bisnis, atau pemulihan di engine target.

**AC:** pisahkan drill kill switch, rollback artefak aplikasi, dan disaster recovery. Uji kompatibilitas aplikasi N−1 dengan skema baru; gunakan forward-fix jika skema tidak aman diturunkan. Restore ke engine disposable, bukan menimpa data operasional. Tetapkan RPO/RTO, off-site/failure domain, kontrol writer, dan rekonsiliasi transaksi/provider sebelum menerima hasil.

### CICD-07 — HIGH: health dependency belum membuktikan pemrosesan transaksi hidup

**Bukti:** [HealthController.php:21–60](../../app/Http/Controllers/HealthController.php#L21) memeriksa database/cache/storage, tetapi queue hanya `Queue::size()`. Ukuran tidak dinilai sebagai backlog, dan tidak ada heartbeat scheduler atau bukti konsumsi job pada endpoint itu.

**Inferensi kausal:** saat worker mati tetapi backend queue masih bisa dibaca, `/health` dapat tetap 200. Ini keterbatasan readiness yang penting **bila** endpoint dipakai sebagai satu-satunya gate rilis; bukan bukti bahwa worker produksi sedang mati.

[Scheduler source](../../routes/console.php#L96) dan [instruksi cron/worker](../04-implementation/SCHEDULED_TASKS_AND_ENV_PARITY.md#L14) tersedia, tetapi proses host, reload setelah deploy, dan penyelesaian job belum diverifikasi. [OperationalMetrics.php:99–139](../../app/Services/OperationalMetrics.php#L99) menyediakan informasi antrean dengan cakupan best-effort/partial; bukan otomatis alerting.

**AC:** dependency health dipisahkan dari processing liveness. Smoke job sintetis harus selesai; heartbeat scheduler, umur antrean, keterlambatan notifikasi/reservasi, serta release version worker diperiksa. Jika gagal, promosi dihentikan. Alert harus benar-benar diterima operator dalam drill terkendali.

### CICD-08 — MEDIUM: hardening supply chain dan bukti operasi perlu dilengkapi

- Actions masih menggunakan tag major mutable (`checkout@v4`, `setup-php@v2`, `setup-node@v4`, `setup-python@v5` pada working tree). Gunakan dependency lockfile Actions bila sesuai dukungan platform, atau SHA penuh terverifikasi dengan pembaruan terkontrol; bukan upgrade major tanpa kajian. Pertimbangkan `persist-credentials: false` untuk checkout CI yang tidak perlu push.
- Scanner secret mengecualikan empat file seluruhnya (`ci.yml:42`), sehingga pengecualian lebih luas daripada sentinel yang dimaksud. Batasi pengecualian dan uji bahwa pola baru di luar fixture tetap gagal, tanpa mencetak nilai.
- [SLO TPS §8](../03-architecture/TPS_REAL_WORLD_TESTING_2026-10-21.md#L377) masih usulan, bukan pengukuran. DORA belum diukur. Satu job CI 139 detik bukan deployment frequency, lead time produksi, change failure rate, atau waktu pemulihan.
- PR template hanya memuat sebagian checks; browser/build/Python/skip, bukti deployment, dan rollback belum menjadi checklist rilis lengkap. Ini prioritas di bawah enforcement dan pemulihan, bukan alasan mengabaikan checks yang sudah ada.

## 6. Dampak pada enam sasaran sistem

| Sasaran | Yang sudah ada | Gap CI/CD yang relevan |
|---|---|---|
| Performance | Build dan regresi; job baseline 139 detik | Belum ada benchmark staging p95/p99/TPS, browser budget, atau profil engine target; durasi CI bukan latensi ecommerce |
| Continuous availability | Health, scheduler, backup, retry, flag | Worker/scheduler/restore/rollback/SLO belum dibuktikan runtime |
| Data integrity | Regresi SQLite dan guard migrasi | Engine parity, race nyata, upgrade N−1, restore serta rekonsiliasi pembayaran masih gate |
| Ease of use / HCI | Test markup a11y, brand/tone, unit interaksi | Belum ada browser E2E rilis, keyboard/pembaca layar atau uji pengguna; tidak ada redesign dalam audit ini |
| Modular growth | App, hooks dan test dapat dibedakan; lockfile | Kontrak artefak/deploy/monitor belum tegas; tidak perlu rewrite atau Kubernetes tanpa kebutuhan terukur |
| Controlled processing | Check CI, script proteksi, idempotensi/regresi | Proteksi belum aktif; scanner fail-open; approval/promotion/audit trail rilis belum tertutup |

## 7. Alur end-to-end yang direkomendasikan

Berikut rancangan, **bukan status implementasi**:

1. **Perubahan kecil → PR:** source dan lockfile terkurasi, reviewer independen, tujuan/risiko/AC jelas. PR tidak mendapat kredensial produksi.
2. **CI fail-closed pada SHA yang benar:** lint/scanner, audit dependency, PHP/Node/Python, migrasi disposable serta engine-target integration; laporan test/skip. Tambahkan E2E alur kritis dalam lingkungan terisolasi yang bisa dibersihkan dengan aman.
3. **Merge protection:** required check dari sumber yang ditentukan, approval terbaru, tanpa bypass diam-diam. Jika merge queue dipilih kemudian, trigger dan required checks juga harus mendukungnya; saat ini tidak diklaim ada merge queue.
4. **Build release immutable:** kaitkan commit/dependency/build dengan digest dan bukti gate. Pisahkan artefak aplikasi dari konfigurasi/secret lingkungan. Sediakan previous release dan retensi yang cukup untuk rollback.
5. **Deploy staging terisolasi:** lock deployment per environment; jangan membatalkan migrasi di tengah hanya karena ada push baru. CI lama boleh dibatalkan, tetapi deploy/DDL perlu kebijakan berbeda. Injeksi secret melalui secret manager, pertahankan kunci aplikasi stabil; jangan membawa langkah `key:generate` CI menjadi langkah rilis rutin.
6. **Migrasi dan lifecycle Laravel:** preflight/expand-contract sesuai engine, writer/worker/scheduler dikendalikan bila diperlukan; cache dibangun setelah konfigurasi lingkungan tersedia; reload proses berumur panjang dan pastikan memuat versi yang benar. Jangan otomatis menurunkan skema yang menolak rollback.
7. **Smoke staging + observasi:** ingress/TLS, aset, health, login/TOTP/RBAC, cart/checkout stok/idempotensi, webhook provider sandbox, job/email sandbox, scheduler, dan alur admin. Periksa keyboard/fokus, bahasa dan viewport kritis. Tidak ada transaksi provider nyata tanpa otorisasi.
8. **Approval produksi:** sesudah staging lulus, reviewer yang bukan pemicu menyetujui promosi artefak yang sama. Ketersediaan fitur environment protection bergantung plan/visibility GitHub; jika tidak tersedia, gunakan kontrol setara yang dapat diaudit.
9. **Pascadeploy:** amati error, latency, invarians uang/stok, backlog/age, heartbeat dan provider outcome dengan ambang/jendela yang disetujui. Catat release ID dan keputusan lanjut/rollback, bukan mengandalkan HTTP 200 saja.
10. **Recovery terukur:** redeploy artefak N−1 bila kompatibel, reload proses, dan ulang smoke. Jika masalah data, jalankan runbook recovery/forward-fix yang disetujui; restore bukan pembatalan otomatis transaksi eksternal. Rekam waktu deteksi, pemulihan, data terdampak dan rekonsiliasi.

Tidak menambahkan workflow deploy dengan server, secret, URL, atau perintah tebakan. Target host/platform, engine/version, owner, approval dan kebijakan pemulihan perlu dipastikan sebelum implementasi CD.

## 8. Rencana perbaikan dengan acceptance criteria

ID berikut **usulan terpisah**, bukan klaim fitur sudah dibuat atau tugas remote sudah dibuka. Turunan dari FR-REM-006/AC-REM-F06, NFR-REM-001…006, FR-REM-003/004 dan NFR-R39-01/03.

| ID usulan | Prioritas / owner | Acceptance terukur |
|---|---|---|
| **NFR-CICD-001** | P0 / maintainer | Proteksi/ruleset aktif dibuktikan GET; check `php` atau pengganti eksplisit dari sumber benar wajib; PR gagal/tanpa approval ditolak dalam uji berizin; stale approval/last-push policy sesuai keputusan |
| **NFR-CICD-002** | P0 / CI owner | Source remediasi masuk PR setelah izin commit/push; run clean runner baru mengikat SHA/diff tepat; semua gate wajib berjalan, bukan memakai run baseline sebagai bukti source baru |
| **NFR-CICD-003** | P1 / CI + AppSec | Scanner error/match/bersih diuji; error tidak sukses; keluaran tidak memuat nilai sensitif; pengecualian sempit; Actions immutable dan update direview |
| **NFR-CICD-004** | P1 / QA + Data | Laporan test/skip terstruktur; font/PDF Linux dan guard wajib benar-benar dieksekusi pada lane yang tepat; engine disposable mencakup migrasi, retry serta uji konkurensi terpisah; browser alur kritis lulus tanpa melonggarkan guard |
| **NFR-CICD-005** | P1 / release engineer | Release ID/digest mengikat source-build-gate; staging menerima artefak tepat; deployment lock aktif; smoke gagal menghentikan promosi; approval independen dan target tercatat |
| **NFR-CICD-006** | P1 / Data + Ops | Drill upgrade/N−1 pada engine target; guard rollback tetap berlaku; rollback aplikasi dan restore off-site disposable lulus terpisah, dengan RPO/RTO serta rekonsiliasi disetujui |
| **NFR-CICD-007** | P1 / Ops + QA | Worker/scheduler job sintetis selesai setelah deploy; gangguan terkendali menghasilkan alert yang diterima operator; ambang backlog/latensi/error dan owner jelas |
| **NFR-CICD-008** | P2 / product + Ops | Baseline workload/SLI/SLO dan event deployment/incident/recovery tercatat; metrik delivery dihitung dari data nyata, bukan jumlah test atau durasi CI; runbook dapat diikuti operator lain |

**Urutan praktis:** enforcement + bukti source baru → koreksi gate/coverage wajib → artefak dan staging → drill recovery/observabilitas → promosi produksi. Persiapan dokumentasi/infrastruktur dapat berjalan paralel; tidak boleh melewati gate dependennya.

## 9. Lensa audit, skill, dan referensi

Lensa operasi diwujudkan sebagai pertanyaan yang bisa diuji, bukan endorsement atau kutipan tokoh:

| Lensa | Pertanyaan | Hasil audit |
|---|---|---|
| Hightower — operasional berulang | Bisakah operator lain memasang versi yang sama? | Belum ada kontrak deployment/artefak lengkap |
| Forsgren — delivery terukur | Adakah event deploy/kegagalan/pemulihan nyata? | Baru satu run CI tersedia; DORA unknown |
| Treynor Sloss — reliabilitas | Adakah SLI/SLO dan error budget terukur? | Dokumen masih usulan; tidak memberi izin rilis |
| Majors — observabilitas | Dapatkah regresi worker/checkout dideteksi setelah rilis? | Metrik source ada; alert/synthetic liveness belum terbukti |
| Vogels — kegagalan komponen | Apa yang terjadi bila worker/host/DB gagal? | Health saja tidak cukup; drill recovery belum tertutup |

Fowler/Beck mendasari pemeriksaan integrasi kecil dan test yang benar-benar mengikat perubahan; Kleppmann/Lamport dipakai sebagai pertanyaan invarians/kompatibilitas data. Dasar teknis temuan tetap source/probe/dokumentasi resmi, bukan otoritas nama. Tujuh lensa HCI serta Watson tetap relevan untuk acceptance browser rilis, tetapi **tidak diaudit ulang secara UI penuh** karena task saat ini CI/CD. Hudson/Purbo/Berners-Lee diterapkan pada kejelasan runbook dan dokumentasi yang dapat diikuti, tanpa mengarang verifikasi perangkat/jaringan.

**Skill dipakai:** `ci-cd-and-automation`, `code-review-and-quality`, `five-axis-code-review` (lane CD), `documentation-and-adrs`. Mutation test terhadap source tidak dilakukan karena read-only; probe return-code menggantikan kebutuhan reproduksi terbatas pada shell tanpa mengubah file produk. Skill redesign/animasi/native, implementasi infra, dan banyak skill lain tidak relevan pada scope ini; tidak dipanggil sekadar memenuhi jumlah.

**Sumber primer tambahan, diakses dalam audit ini (22 Oktober WIB / 21 Oktober UTC):**

- [GitHub — About protected branches](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches): required check/review, sumber App, strict update, enforcement/bypass.
- [GitHub — Secure use reference](https://docs.github.com/en/actions/reference/security/secure-use): least privilege, log secret, dependency lockfile/SHA, untrusted PR, OIDC. Tidak ada konfigurasi OIDC yang diasumsikan tersedia di proyek.
- [GitHub — Deployments and environments](https://docs.github.com/en/actions/reference/workflows-and-actions/deployments-and-environments): approval, prevent self-review, branch restriction, environment secrets, serta batas plan/visibility.
- Source/test/runbook lokal yang ditautkan pada tiap temuan, serta metadata GitHub pada §3. Dokumen lama tetap histori; tidak ditulis ulang menjadi bukti rilis terbaru.

## 10. Validation, kegagalan pemeriksaan, dan residual risk

### Pemeriksaan yang dijalankan

| Pemeriksaan | Waktu / hasil | Batas |
|---|---|---|
| Metadata repo/branch/run/check/ruleset/environment/deployment/artifact | Snapshot selesai `2026-10-21T17:05:53Z`; collector **EXIT=0**; sembilan query metadata sukses, protection exit 1/HTTP 404 sesuai hasil yang direkam | GET/read-only; hasil point-in-time |
| Probe shell scanner PII + 132 hash source/test | `2026-10-21T17:05:50Z`, **EXIT=0**, defect terkonfirmasi; source mismatches `[]` | Bukan test aplikasi, bukan scanner yang telah diperbaiki |
| Pembandingan workflow HEAD/working tree + konfigurasi test nonsecret | Transkrip terminal **EXIT=0** | Tidak membaca `.env*`; nilai key test tidak ditampilkan |
| Review dua lane | Handoff sesi di §2, manual read-only | Tidak disamakan dengan eksekusi suite atau izin produksi |

### Pemeriksaan yang gagal / tidak lengkap

- Pemanggilan pertama `gh run view --json ... startedTime ...` gagal **EXIT=1** karena nama field CLI salah. Diganti dengan field yang didukung `startedAt`; pengambilan metadata berikutnya sukses. Ini error audit, bukan kegagalan CI.
- Dua upaya ekstraksi ringkasan numerik log memiliki `gh --log EXIT=0`, tetapi validator collector **EXIT=1** karena tidak mendapatkan ringkasan PHP terikat yang diharapkan. [Percobaan pertama](evidence-ci-cd-2026-10-22/remote-test-summary.json) kosong; [percobaan kedua](evidence-ci-cd-2026-10-22/remote-test-summary-r2.json) menangkap angka Node 325 pass, tetapi label step belum terpetakan. API waktu step berpresisi detik, sedangkan timestamp log pecahan detik berada setelah batas detik; parser PHP juga belum cocok. Diagnosis dibatasi pada ekstraksi; format PHP sebenarnya belum ditentukan. Sesuai batas dua kegagalan, tidak ada retry ketiga, pencetakan raw log, atau penurunan validator agar hijau.
- Karena itu **angka pass/skip PHP remote tidak diklaim**. Status run/step sukses tetap dibuktikan secara independen lewat metadata API. Raw log tidak disimpan dalam artefak audit.

### Tidak diperiksa

- Tidak ada commit/push, PR baru, aktivasi protection/ruleset, perubahan secrets/variables, webhook/hook setting, dispatch workflow, deployment atau transaksi provider nyata.
- Tidak menjalankan install/build/suite aplikasi ulang, migration pada database, browser, load test, server, atau membaca `.env*`. Evidence lokal lama dipakai dengan label historis dan hash binding, bukan diklaim rerun.
- Host/platform eksternal, konfigurasi efektif PHP-FPM/queue/cron/TLS/SMTP/provider, plan/izin admin efektif, secret manager, sistem alert, RPO/RTO, dan skenario bypass runtime belum diverifikasi.
- Dokumentasi GitHub tidak membuktikan fitur plan tertentu dapat dipakai repo ini. Kehadiran/ketiadaan environment/deployment GitHub tidak memetakan seluruh infrastruktur di luar GitHub.

**Risiko tersisa:** changes dapat masuk `main` tanpa required gates; source terbaru belum punya CI remote; scanner PII false-success; pengujian lintas engine/platform/browser belum lengkap; deployment/recovery/monitoring belum tertutup. Tidak ada klaim “nol masalah”, continuous availability, WCAG penuh, atau siap produksi.

### Continuous Evaluation Record

- **Task / AC:** AUD-CICD-20261022; kriteria §2/§3/§5/§8 dan requirement terkait di §8.
- **Baseline:** HEAD/remote `041ed08`, working tree remediasi tidak diubah; run baseline sukses namun protections kosong.
- **Controlled change:** laporan/evidence ini, tautan indeks, dan bagian ledger audit saja; tidak ada fix source atau konfigurasi remote.
- **Ricek:** metadata API diambil ulang untuk snapshot, temuan reviewer dicocokkan dengan source, scanner direproduksi secara sintetis, 132 hash source cocok.
- **Crosscheck:** kedua sesi independen di §2 memberikan **APPROVE untuk akurasi laporan dalam scope masing-masing**, tanpa koreksi wajib. Ini bukan approval source/deploy/produksi. Penugasan dilepas sesudah handoff; catatan ada di [ledger](../04-implementation/TEAM_SUPPORT_LEDGER.md). Hasil pemeriksaan penutupan tersedia sebagai `evidence-ci-cd-2026-10-22/closure-check.json`.
- **Comparison:** pemahaman berubah dari konfigurasi lokal menjadi bukti remote: CI baseline sukses, protection tidak aktif, CD tidak tercatat; defect scanner tetap terbuka karena scope audit.
- **Decision:** **audit selesai secara substantif; CI/CD end-to-end REWORK_REQUIRED / belum layak disetujui untuk produksi.** Keputusan rilis tetap pada maintainer setelah gate terbuka dibuktikan.
