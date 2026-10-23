# Verifikasi paritas localhost dan persiapan publikasi

**Task:** FE-COMP-PUB-20261023. **Tanggal:** 23 Oktober 2026 UTC. **Requirement:** NFR-FE-002/003/004, NFR-CICD-002/004/005.

**Keputusan lokal:** jalur native `php artisan serve` pada checkout pengguna telah diuji, termasuk auto-fallback ke8001, port eksplisit8001, dan profil canonical-origin8001. Error500 lokal ditemukan dan diperbaiki. Ini bukan klaim localhost identik produksi atau semua interaksi terhadap DB operasional telah diuji.

[Petunjuk startup yang dapat diulang](../04-implementation/LOCALHOST_TESTING.md) · [Hasil integrasi sebelumnya](REACT_INERTIA_COMPLETION_2026-10-23.md) · [Bukti lokal](evidence-localhost-publication-2026-10-23/)

## 1. Baseline, diagnosis, dan perubahan terkontrol

- Source dasar `016fb944c4d1d0dc380a014b9f99570167b106f0`, ditambah84file source/test dalam [manifest increment](evidence-react-completion-2026-10-23/source-manifest.json). Semua 84 hash cocok saat ricek runtime. Gate staged kemudian menemukan satu blank line berlebih di EOF `tests/react/admin-dashboard.spec.jsx`, yang sebelumnya belum tercakup unstaged diff-check karena file masih untracked. Hanya blank line tersebut dihapus, full React232 lulus ulang, lalu source dicommit sebagai `acae7f4310e9125b16611ac258b02df8acf2a817`. [Manifest publikasi](evidence-localhost-publication-2026-10-23/source-manifest.json) mencatat 84 hash final dan delta whitespace; manifest awal tetap historis. Tidak ada perubahan logika aplikasi untuk memperbaiki runtime lokal.
- Existing PHP PID64887 mendengarkan8000, cwd `public` proyek, parentPID1. Tidak dihentikan. Awalnya8001kosong.
- Dua smoke awal gagal500 pada home: [R1/8000](evidence-localhost-publication-2026-10-23/browser-r1-failure.json), [R2/native8001](evidence-localhost-publication-2026-10-23/browser-r2-failure.json). MySQL SELECT1 dan keberadaan tabel migrations/sessions/cache/products/categories/orders lulus. Tidak menjalankan seed/migrasi.
- Diagnostik tersanitasi menunjuk `Inertia\Ssr\Gateway`. Package/services manifest checkout biasa tidak memuat Inertia. Provider package terpasang mendefinisikan binding, dan middleware me-resolve Gateway walauSSRmati. Ketiadaan provider menjelaskan kegagalan; asal historis stale cache dari instalasi terisolasi adalah inferensi mekanisme, bukan riwayat yang direka.
- `composer dump-autoload --optimize --no-interaction` EXIT0 memperbarui autoload/discovery melalui [hook existing](../../app/Support/ComposerHooks.php). Kedua manifest kemudian memuat Inertia. Home8000juga kembali200 tanpa menghentikan proses; shared generated files memang berubah.
- Konfigurasi kanonis lokal semula loopback port80. Tidak mengedit dotenv: profil proses `APP_URL=http://127.0.0.1:8001` bersama `--no-reload` diuji terpisah. Profil biasa tetap memiliki origin kanonis lama, walau halaman HTTP sudah bekerja.

## 2. Bukti native browser

| Invocation | Waktu UTC | Hasil |
|---|---|---|
| `php artisan serve --host=127.0.0.1 --port=8001` | 04:10:00–04:10:09 | ProducerEXIT0, browserEXIT0,8halamanReact/16asetidentik |
| `APP_URL=http://127.0.0.1:8001 php artisan serve --host=127.0.0.1 --port=8001 --no-reload` | 04:11:03–04:11:18 | ProducerEXIT0, browserEXIT0,8halamanReact/16asetidentik; CLIcanonical/signedorigin8001true |
| `php artisan serve` | 04:15:14–04:15:24 | Otomatis mencoba8001karena8000terpakai; producerEXIT0, browserEXIT0, smoke yang sama |

Bukti runtime/config/browser: [plain](evidence-localhost-publication-2026-10-23/plain-runtime.json), [canonical](evidence-localhost-publication-2026-10-23/canonical-runtime.json), [default](evidence-localhost-publication-2026-10-23/default-runtime.json). Fakta konfigurasi adalah **CLI bootstrap**, bukan endpoint diagnostik HTTP. HTTP diperiksa melalui renderer dan aset nyata.

Pada ketiga invocation:

- Home, katalog, PDP dari tautan publik, shop, login, register, forgot-password, dan login admin mencapai `data-react-boot=ready`.
- Tujuh route pertama juga memeriksa komponen pada payload dokumen dan pembuangan script payload inert. PDP memeriksa root ready; jangan mengklaim oracle payload identik pada semua8route.
- Enam belas aset yang dimuat browser cocok SHA-256 dengan disk. Manifest build setelah rebuild tetap `e13a60d6bd92a30a881a6b40322f38c54cb09dde0184ae8a6e45778cf2f33f80`.
- Home390px tidak overflow, fallback Blade tampil, kembali home menjadi React, dan katalog no-JS tetap mempunyai tautan produk.
- Consoleerror0, consolewarning0, pageerror0, failedrequest0, HTTPerror0, non-GETblocked0, assetmismatch0 pada context JS yang dimonitor. Context no-JS hanya memeriksa status dan tautan.
- Profile browser terisolasi; tidak memakai sesi login pengguna. Tidak mengirim checkout/registrasi/login/aksi admin. GET masih dapat menulis cache/sesi.
- Listener8001terbukti berada dalam process group invocation; server milik penguji dihentikan dan8001dibebaskan. Proses8000tetap hidup. Tidak ada server uji yang ditinggalkan background.

## 3. Ricek kualitas sebelum commit

[Precommit gates](evidence-localhost-publication-2026-10-23/precommit-gates.json),04:13:59–04:14:18UTC:

- Node556pass, React232pass, Python402pass tanpa skip/error.
- PintEXIT0, buildEXIT0, Composer validation/audit, npm audit, actionlint, scannersecret/PII, whitespace seluruhnyaEXIT0.
- Warning exact pin Composer tetap, bukan disembunyikan. Pint lokal mencakup408file karena dua probePHP disposable ikut dipindai; source aplikasi increment tetap406file seperti laporan sebelumnya.
- Pint sebelumnya gagal pada kedua probe baru, bukan source produk. [Kegagalan tersimpan](evidence-localhost-publication-2026-10-23/precommit-r1-style-failure.json); hanya probe diformat, tidak menambah exclusion/suppression atau melemahkan gate.
- Full PHP 1.286 pass/3 exact MySQL skips/160.073 assertion dan browser transaksi native 29 mandatory tetap bukti increment sebelumnya; delta source setelahnya hanya satu blank line EOF pada tes. Tidak diklaim sebagai rerun penuh pada runtime operasional.

## 4. Crosscheck dan gate publikasi

Reviewer independen `6b48bb68-719d-422d-a7d6-eaf8b913b60a` memeriksa startup Laravel, stale discovery, portfallback, filtering environment, Vite/SW dan batas evidence. Ia tidak menjalankan server/browser/DB sendiri. Source-level remedy dan bukti pra-push **APPROVE scoped** pada04:27:38–04:28:16UTC. Reviewer menjalankan tiga validator read-only EXIT0:84hash/gitblob cocok,31aset cocok,14JSON dan117tautan/2anchor valid. [Rekam review](evidence-localhost-publication-2026-10-23/independent-review.json). Ini tidak menggantikan CI SHA final.

Pengguna mengizinkan commit/push normal ke `main`, bukan deploy, force-push atau perubahan proteksi. Snapshot remote sebelum publikasi masih `016fb94`, `protected=false`; tidak ada custom/active Git hook. CI harus diperiksa untuk SHA terbaru, bukan meminjam run baseline. Lihat [workflow runs](https://github.com/hanifp62/online-shop/actions/workflows/ci.yml) dan [runbook collector](../04-implementation/CI_CD_RELEASE_RUNBOOK.md#koleksi-bukti-satu-run-attempt-tambahan-23-oktober-2026).

**Dokumen ini merekam bukti lokal sebelum push, bukan klaim bahwa CI commit yang memuatnya sudah lulus.** Status publikasi akhir harus menyertakan SHA/runID/attempt, tepat5job success, empatartifact valid dan offline candidate verification. Hasil verifikasi remote dilaporkan terpisah sesudah push. Kandidat CI bukan deployment.

## 5. Continuous Evaluation Record

- **Acceptance criteria:** native Artisan benar-benar dipakai; source/build terikat;500terdiagnosis; perbedaan config/fixture jujur; tidak ada mutasi bisnis sintetis ke DB lokal; process ownership/cleanup terbukti; gate lokal tidak diturunkan.
- **Baseline:** duaHTTP500, discovery belum memuatInertia, canonicalport80.
- **Controlled change:** generated autoload/discovery melalui hook normal; profil canonicalorigin eksplisit; README/runbook/evidence. Tidak mengubah logika source increment; satu blank line EOF pada tes dihapus untuk gate staged.
- **Verification:** tiga invocations native di atasEXIT0;12gatelokalEXIT0. Timestamp/exits berada dalamJSON.
- **Comparison:** home500→200,8routeReactready,16assetmatching, console0; plaincanonicalport80tetapdibedakan dari profil8001.
- **Review:** source remedy ditinjau6b; review akhir bukti ditautkan pada ledger. Nama/lensa tokoh bukan endorsement profesional nyata.
- **Decision:** ACCEPTED scoped untuk smoke localhost; publikasi/CI/produksi merupakan gate terpisah.

## 6. Tidak diperiksa dan risiko tersisa

Tidak dilakukan login dengan akun pengguna, operasi admin/checkout pada DB lokal, email nyata, queue/schedulerdaemon, OAuth/Midtranssandbox, screenreader/semuaperangkat, staging/promotion/rollback/TPS/SLO. Dua flow optional browser pada increment sebelumnya tetap tidak dijalankan. Diagnostik Python legacy/editor belum dinyatakan bersih repo-wide.

Native lokal memakai MySQL/session-cache-queue database, mail log, APP_DEBUGtrue khususlocal; harness memakai konfigurasi sintetis berbeda. Override server tidak memperbaiki origin worker/CLI terpisah. Jangan mengklaim jumlahworker, cache/statebrowser, isiDB atau kinerja produksi setara karena source yang sama.

**Delivery gate antislop:** R-35PASSscoped untuk route/states yang dicatat, R-36/38PASSuntuk klaim berbasis bukti; tidak ada aset/redesign baru. Audit kontras, keyboard/semuakontrol dan desain menyeluruh tidak diulang atau dinyatakanPASSuniversal. Skill relevan: antislopDURING, Laravelverification, debugging, CIautomation, gitworkflow, browser-testing dan documentation. DevToolsMCPtidaktersedia; Playwrightisolated dipakai. Skill redesign/animasi/nativeplatform tidak relevan.
