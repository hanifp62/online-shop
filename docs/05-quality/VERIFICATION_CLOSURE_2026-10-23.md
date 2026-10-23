# Penutupan celah verifikasi integrasi

**Task:** VERIFY-CLOSE-20261023. **Tanggal:** 23 Oktober 2026 UTC. **Requirement:** NFR-CICD-002/004/005, NFR-FE-002/003/004.

**Keputusan lokal:** kendala unduhan kandidat baseline selesai; dua alur browser yang sebelumnya optional kini wajib dan benar-benar lulus. Regresi lokal lengkap dan smoke native Artisan8001 lulus. Dokumen ini menyimpan bukti sebelum push increment; CI commit publikasi terbaru harus diperiksa tersendiri, bukan disimpulkan dari CI baseline.

[Indeks bukti](evidence-verification-closure-2026-10-23/) · [Startup localhost](../04-implementation/LOCALHOST_TESTING.md) · [Runbook CI](../04-implementation/CI_CD_RELEASE_RUNBOOK.md) · [Ledger](../04-implementation/TEAM_SUPPORT_LEDGER.md)

## 1. Baseline dan penerimaan

Baseline adalah `a650b12f03e59396ce12e37a9e84b08e00adfc84`. [Run39900693030](https://github.com/hanifp62/online-shop/actions/runs/39900693030), attempt1, eventpush, sudah menuntaskan lima job CI. Tiga artifact ringkasan terverifikasi tetapi unduhan kandidat35.717.864byte berulang kali kehabisan budget90detik. Browser baseline meluluskan29tahap wajib dan menyatakan dua alur tidak tersedia, bukan mengujinya.

Acceptance criteria ditetapkan sebelum perubahan:

1. Unduhan asli harus lolos pemeriksaan identitas run/attempt/SHA, ukuran/digest ZIP, expiry, isi ZIP dan verifier kandidat offline.
2. Budget lebih panjang harus eksplisit dan terbatas; tidak memperbesar default atau melonggarkan kontrol keamanan.
3. Ulasan completed order dan signed unsubscribe harus deterministik dalam fixture terisolasi, melalui interaksi UI nyata dan oracle persistensi.
4. Gate harus menolak laporan lama, palsu, hilang, salah urutan atau cleanup gagal. Tidak boleh mengklaim CI finalSHA sebelum menjalankannya.

## 2. Perbaikan terkontrol

### Kolektor dan batas waktu

`collect_publication.py` kini menyediakan `--download-seconds` integer1..900, default90. Nilai invalid ditolak sebelum akses token/jaringan. Receive timeout15detik, retry dengan deadline yang sama, redirect HTTPS, pelepasan credential lintas origin, byte/hash/ZIP/provenance guard dan offline verifier dipertahankan.

Percobaan baseline memakai transport `urllib` asli dan API `download_wait=900`, bukan transport diagnostik pengganti. Hasil05:55:51–05:56:37UTC, EXIT0, actual45,675detik. Ini membuktikan percobaan berhasil, **bukan** bahwa900detik selalu diperlukan atau penyebab jaringan sebelumnya telah terbukti.

Kandidat baseline:8.054file, archive35.418.622byte, archive SHA-256 `fc73e06bc6b603e022f3397dd6d22b0c83dad9b6d7e3742c8034d4ad2b315b75`. Ukuran ZIP GitHub berbeda dari tar.gz. [Bukti unduhan dan metadata](evidence-verification-closure-2026-10-23/baseline-release-verified.json). Verifier offline juga dijalankan reviewer independen. Bukti historis kontrak29tahap tidak diubah menjadi bukti31tahap.

Kolektor terbaru meneruskan bytes ringkasan React ke `check_react_browser_report.parse_report()`. Ia menolak duplicate keys sebelum normalisasi, schema/fault counts invalid, urutan salah, unavailable tidak kosong, supervisor nonzero dan cleanup gagal. Output final tetap hanya kandidat terverifikasi dan ringkasan allowlist.

### Dua alur browser wajib

Mode guarded `provision-react PORT` memakai root SQLite baru. Ia menambah satu order historis sintetis completed untuk customer fixture dan produk stock37, tanpa mengurangi stok, serta menyimpan signed URL melalui channel privat. Mode legacy tidak mendapat order tambahan. Tidak ada perubahan UI, controller, route, migration, dependency atau data bisnis operasional.

- **Ulasan:** pilih form milik order fixture, kirim rating/body melalui UI, wajib HTTP201 dan tepat satu POST. Oracle SQLite read-only memeriksa user/order/product/rating/body yang benar-benar tersimpan.
- **Unsubscribe:** buka signed URL fixture melalui GET native, periksa opt-in tidak berubah, lakukan POST, lalu ulangi untuk membuktikan idempotensi. Respons harus no-store, bukan service worker. Oracle memeriksa opt-in menjadi false. Signed URL tidak ditaruh pada laporan publik atau ditambahkan ke UI produksi untuk keperluan tes.
- **Channel:** validasi root/repo/UID/permission, symlink/hardlink/sidecar, schema dan origin; data privat hanya lewat captured pipe. Error tetap tersanitasi.
- **Gate:** parent runner, wrapper, verifier offline dan collector menuntut31tahap berurutan, `unavailable=[]`; failure injection yang disengaja tetap dihitung secara sempit. Monitor final dan cleanup tidak dilonggarkan.

## 3. Bukti runtime dan regresi

Semua command pada tabel benar-benar dijalankan integrator, tidak hanya laporan author. Timestamp/exit lengkap ada di evidence.

| Gate | Waktu UTC | Hasil |
|---|---|---|
| Native browser + fixture + supervisor | 06:38:01–06:38:47 |31mandatory PASS, unavailable0, unexpected console0, supervisor0, cleanupPASS |
| Node `node --test` | 06:40:13–06:40:18 |592pass, fail/skip/cancel/todo0 |
| React `npm run test:react` | 06:40:18–06:40:21 |232pass,15file |
| Python runner kanonis | 06:40:21–06:40:44 |434pass, failure/error/skip0 |
| Build | 06:40:44–06:40:45 |EXIT0, manifest/aset tidak berubah |
| PHP suite isolated | 06:40:52–06:49:13 |1.286pass,3exactMySQLskip,160.073assertion, producer/checkerEXIT0 |
| Lint/audit/scanner | 06:40–06:49 |Pint, Composer validate/audit, npm audit, actionlint, secret/PII scanner, diffcheck EXIT0 |
| Browser legacy | Sesudah PHP, sebelum smoke8001 |76produk,10halaman,3checkout,2created,1rejected,0contentleak, producer/checkerEXIT0 |
| Native Artisan canonical8001 | 06:50:19–06:50:30 |8halamanReact,16asetidentik, console/warning/pageerror/requesterror0 |

Bukti: [gate lokal](evidence-verification-closure-2026-10-23/local-gates.json), [PHP](evidence-verification-closure-2026-10-23/php-summary.json), [31tahap browser](evidence-verification-closure-2026-10-23/react-browser-summary.json), [timestamp browser](evidence-verification-closure-2026-10-23/react-browser-commands.tsv), [legacy](evidence-verification-closure-2026-10-23/legacy-summary.json), [localhost](evidence-verification-closure-2026-10-23/localhost-runtime.json).

Tiga skip hanya tes engine yang telah di-allowlist exact, bukan skip umum. Final CI MySQL wajib mengeksekusi ketiganya tanpa skip. Warning Composer untuk intentional exact pins tetap ada; EXIT0 bukan klaim semua warning editor legacy hilang. Diagnostik targeted collector dan artisan helper0error/0warning pada pemeriksaan ini.

## 4. Paritas localhost

Command yang diuji ulang:

```sh
APP_URL=http://127.0.0.1:8001 php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

Browser memakai source/build checkout yang sama. Home390px tidak overflow; fallback Blade, kembali React dan katalog no-JS lulus. Buildmanifest SHA-256 `e13a60d6bd92a30a881a6b40322f38c54cb09dde0184ae8a6e45778cf2f33f80`,16aset HTTP cocok byte disk. Provider Inertia dan Gateway binding tersedia.

Server owned8001 dihentikan setelah tes, port dibebaskan, server existing8000 dipertahankan. Tidak membaca/mengubah dotenv atau menjalankan transaksi sintetis terhadap MySQL lokal. GET dapat menulis sesi/cache. [Bukti browser](evidence-verification-closure-2026-10-23/localhost-browser.json) dan [snapshot konfigurasi CLI](evidence-verification-closure-2026-10-23/localhost-config.json) dibedakan dari introspeksi konfigurasi HTTP.

`php artisan serve` tanpa opsi sebelumnya juga teruji memilih8001saat8000sibuk. Itu tidak menjamin selalu8001. Override canonicalorigin di atas berlaku untuk proses server, bukan mengubah worker/scheduler/CLI lain. Lihat [petunjuk lengkap](../04-implementation/LOCALHOST_TESTING.md).

## 5. Ricek, crosscheck dan evaluasi

- **Author RELEASE:** `b8dd3a2c-9130-4548-b8e6-71191d50445b`; reviewer berbeda `08c5ef2e-f784-482b-91dc-185d83bb581c`, APPROVE source/targeted tests/offline archive.37collector+8report tests PASS dengan network guard. Dua mutasi in-memory terdeteksi.
- **Author QA:** `4b1f8967-3737-4457-9ac6-9ebd48f6caba`; reviewer berbeda `3d0d4cf4-0569-4ce3-9af2-95d81d5c42d2`, APPROVE source/offline tests.57Node+64Python scoped PASS dan tiga mutasi in-memory terdeteksi. Review tidak menyamar sebagai eksekusi browser reviewer.
- **Integrator:** menerima source sesudah review, lalu menjalankan sendiri regresi dan runtime di atas. Semua author berhenti sebelum gate final; tidak ada penulis background.
- **Crosscheck penutupan:** reviewer `6b48bb68-719d-422d-a7d6-eaf8b913b60a` APPROVE scoped07:02–07:04UTC;16sourcehash,31asethash,114tautan dan14gate cocok. [Rekam review independen](evidence-verification-closure-2026-10-23/independent-closure-review.json) membedakan hasil runtime integrator dari pemeriksaan reviewer.
- **Perbandingan:** dua unavailable menjadi dua tahap wajib yang lulus; report lama29ditolak; candidate baseline yang sebelumnya incomplete kini verified. No production source diff; legacy browser dan build tetap sama.
- **Failure dipertahankan:** percobaan unduhan90detik terdahulu serta RED regression sebelum implementasi tidak ditimpa dengan PASS. Dua false-negative probe pembanding teks reviewer didiagnosis sebagai normalisasi signature, lalu pemeriksaan AST15definisi membuktikan kontrol transport tidak berubah.
- **Decision:** ACCEPTED untuk source, gate lokal dan penutupan unduhan baseline. Publikasi remote increment wajib exactSHA baru.

Lensa dewan dipakai sebagai pertanyaan, bukan endorsement tokoh: keterulangan startup/deadline (operasi), invarian ownership/idempotensi (data), pembatasan capability/fixture (keamanan), bukti umpan balik form dan reversibilitas (HCI), serta pemisahan fakta/risiko (dokumentasi). Tidak ada keputusan arsitektur besar yang memerlukan ADR baru.

Skills yang dipakai: CI automation, git workflow, debugging, incremental implementation, Laravel TDD/verification, browser testing, contract propagation, code review, documentation dan antislop. DevTools MCP tidak tersedia; Playwright existing dipakai dengan context terisolasi. Skill redesign, animasi, native mobile dan video tidak relevan pada increment ini.

**Delivery gate antislop scoped:** R-35PASS untuk interaksi/tahap yang direkam; R-36/38PASS untuk klaim berbasis bukti. Tidak membuat aset, desain, statistik pengguna, atau testimonial. Pengujian baru tidak dianggap audit kontras/screenreader/semua kontrol universal.

## 6. Publikasi dan batas real-world testing

Izin pengguna mencakup commit/push normal ke `main`. Ia tidak dipakai untuk force-push, mengubah branch protection, mutasi DB operasional atau deploy. Remote terakhir diperiksa tetap SHA baseline, `protected=false`; workflow adalah CI sampai kandidat rilis, bukan deployment otomatis.

Sesudah push, penerimaan mensyaratkan lima job (`quality`, `mysql`, `browser`, `release`, `php`) success pada SHA publikasi, empat artifact valid, native31tanpa unavailable dan candidate offline verifier lulus. Hasil ini harus berasal dari run baru. [Workflow aktual](../../.github/workflows/ci.yml) dan [daftar run](https://github.com/hanifp62/online-shop/actions/workflows/ci.yml) adalah jalur pemeriksaannya.

**Tidak diperiksa / risiko tersisa:** deployment/promotion staging-produksi, proteksi branch, email nyata, provider pembayaran/OAuth, daemon queue/scheduler, disaster recovery operasional, SLO/TPS beban nyata, screenreader dan seluruh perangkat. Ini memerlukan lingkungan/operasi nyata, bukan alasan untuk mengklaim zero-defect. Aplikasi siap untuk pengujian lokal dengan cakupan yang telah dibuktikan; tidak ada klaim semua lingkungan bebas masalah.
