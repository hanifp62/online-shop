# Audit Recheck Current — ZAFIRA

**Tanggal:** 2026-10-12  
**Mode:** read-only; tidak membaca `.env`, tidak menyentuh database operasional, port 8000/8001, `mysql_upgrade`, atau git mutation.  
**Kesimpulan:** **Produksi belum siap.** Repo layak untuk lokal/demo bersyarat, bukan sign-off produksi.

## Scope dan bukti

Diperiksa:

- Route publik, auth user, auth admin, akun, CRUD admin, throttle: `routes/web.php:14-69`.
- Storefront catalog/PDP, sitemap, concierge, newsletter, validasi dan mail queue: `app/Http/Controllers/StorefrontController.php:21-212`.
- Theme/language bootstrap dan runtime aktual: `resources/views/welcome.blade.php:40-45`, `prototype/index.html:12-19`, `prototype/zafira-core.js:1349-1605`, `public/js/zafira-core.js`.
- Migrations: 17 file di `database/migrations/`; tidak ada migration Order.
- Deployment/CI: tidak ditemukan `.github/workflows/*`; `package.json:5-16` hanya menyediakan `build` dan `dev`.
- Ledger, handoff, traceability: `docs/execution/EXECUTION_LEDGER.md`, `docs/execution/FINAL_HANDOFF_2026-10-11.md`, `docs/02-product/REQUIREMENTS_TRACEABILITY.md`.
- Bukti `final2`, `triage-final3`, dan visual inspection.

Klaim visual tidak diperluas. Handoff menyebut cakupan visual aktual terbaru hanya **23/56**; `visual-inspection-final2.md:7-11` juga membatasi klaim pada file yang benar-benar terlihat.

## Command results

| Command | Result | Status |
|---|---|---|
| `node --test tests/*.test.mjs` | 73 passed, 0 failed, 0 skipped; 132.838 ms | Fresh pass |
| `git status --short --branch` | branch `fix/audit-benchmark-remediation`; 75 modified dan 73 untracked | Working tree tidak bersih; audit tidak mengubahnya |
| `npm run build` | Tidak dijalankan ulang pada recheck ini; handoff/final2 mencatat exit 0 | Historical evidence only |
| PHP tests | Tidak dijalankan ulang pada recheck ini; final2/handoff mencatat pass sebelum/di batch terkait | Historical evidence only |

`node --test tests/` sebelumnya adalah invocation invalid, bukan app failure. Invocation glob di atas valid dan lulus.

## Findings

### BLOCKER / HIGH

1. **[BLOCKER][NFR-003] Tidak ada Order model/tabel/FK.**
   - Bukti: 17 migrations tidak memuat tabel `orders`; `StorefrontController::bookConcierge()` hanya menyimpan ringkasan checkout ke `concierge_requests` (`app/Http/Controllers/StorefrontController.php:157-180`).
   - Dampak: checkout bukan order yang durable/queryable; fulfillment, status, retry, dan rekonsiliasi tidak punya domain persistence.
   - Tindakan: putuskan dan dokumentasikan apakah checkout memang hanya concierge lead. Jika order wajib, tambah model/migration/relasi/idempotency dan test; jangan restore database sebelum otoritas data diputuskan.

2. **[HIGH][Ops] Tidak ada CI workflow atau deployment contract yang terdeteksi.**
   - Bukti: `.github/workflows/*` tidak ditemukan; `package.json` hanya build/dev. Handoff `FINAL_HANDOFF_2026-10-11.md:46` menandai deployment NOT RUN.
   - Dampak: tidak ada bukti clean checkout, environment validation, migration gate, health check, rollback, atau production smoke test.
   - Tindakan: buat pipeline eksplisit untuk PHP tests, Node tests, Pint, build, secret-safe checks, migration dry-run, dan deploy/rollback owner.

3. **[HIGH][Security] CSP belum diterapkan.**
   - Bukti: handoff `FINAL_HANDOFF_2026-10-11.md:25` menyebut tidak ada header CSP/report-only; hanya simulasi harness dengan banyak violation.
   - Dampak: XSS defense-in-depth dan observability browser belum tersedia di runtime deploy.
   - Tindakan: mulai report-only pada environment uji, inventaris inline script/style yang sah, lalu enforce policy bertahap dengan regression check.

4. **[HIGH][Data] Database authority dan drift belum selesai.**
   - Bukti: handoff `FINAL_HANDOFF_2026-10-11.md:20-26` mencatat dump tidak identik dengan aktif, `getcat` ERROR 1558, `db:show` blocked, dan otoritas data belum diputuskan.
   - Dampak: deploy migration/restore dapat menimpa data yang salah atau gagal pada runtime target.
   - Tindakan: pilih source of truth tertulis, snapshot/read-only diff non-sensitif, validasi schema target, backup dan rollback plan sebelum perubahan.

### MEDIUM

5. **[MEDIUM][Theme/i18n] Cart membaca key underscore yang benar hanya bila API tersedia, tetapi fallback memakai key hyphen yang salah.**
   - Bukti: `prototype/app.js:1112` memanggil `window.Zafira.getLang` yang tidak diekspor; runtime mengekspor `getLanguage` di `prototype/zafira-core.js:1588`. Fallback membaca `localStorage.getItem('zafira-lang')`, sedangkan bootstrap/runtime memakai `zafira_lang` (`prototype/index.html:12-19`, `prototype/zafira-core.js:1350-1405`).
   - Dampak: variabel lokal `lang` dapat selalu jatuh ke `id`; cart re-render atau fallback dapat salah bahasa, terutama ketika core belum tersedia.
   - Tindakan: gunakan satu API yang benar (`getLanguage`) dan satu key underscore. Tambahkan test perilaku cart lintas bahasa, bukan hanya regex theme bootstrap.

6. **[MEDIUM][Accessibility] Target sentuh dan WCAG AA belum terbukti penuh.**
   - Bukti: handoff `FINAL_HANDOFF_2026-10-11.md:16-18,42-43` mencatat kontrol 24–42px/ikon 37px dan hanya satu pasangan kontras 19.05:1. `RESULT-final2.md:5` secara eksplisit mengatakan contrast exit 0 bukan acceptance penuh.
   - Dampak: keyboard/screen-reader checks dapat lulus sementara touch usability dan pasangan warna lain gagal.
   - Tindakan: audit semua interactive controls pada semua surfaces, ukur rendered compositing, dan tetapkan owner untuk residual <44px.

7. **[MEDIUM][UI runtime] Visual defects tetap tercatat pada prototype.**
   - Bukti: `visual-inspection-final2.md:17-67` melihat header melintas hero, dock menutup konten, dan campuran copy EN/ID pada seluruh prototype-en widths yang dilihat. Hanya 23/56 visual actual coverage; jangan generalisasi ke file NOT SEEN.
   - Dampak: prototype tidak merepresentasikan navigasi/locale yang stabil pada desktop dan mobile.
   - Tindakan: perbaiki positioning/header lifecycle, dock clearance desktop, dan audit semua literal non-translation; ulangi screenshot visual dengan coverage lengkap.

8. **[MEDIUM][Localization] F4 belum terbukti sebagai intentionality defect.**
   - Bukti: `triage-final3/triage-final3.md:10` menyebut `Deux Rives` sebagai intentional di FR. Itu membuktikan perbedaan dictionary, bukan owner evidence bahwa bentuk tersebut salah atau benar untuk ID.
   - Status: **localization inconsistency, intent unresolved**, bukan confirmed product defect.
   - Tindakan: minta keputusan owner bahasa untuk ID/EN/FR/IT, lalu tambah acceptance test per locale.

9. **[MEDIUM][Contrast] F7 tidak membuktikan full AA.**
   - Bukti: `triage-final3/triage-final3.md:13,22` menjelaskan sampling bounding rectangles dengan glyph disembunyikan; ini approximation background contrast, bukan per-glyph rendered-pixel compositing.
   - Status: screenshot artefact/measurement gap; bukan klaim bahwa hero pasti gagal atau pasti lulus AA.
   - Tindakan: gunakan computed/rendered contrast audit pada teks aktual dan pasangan state overlay, termasuk mobile.

10. **[MEDIUM][Observability/email] Mail queue dipakai tanpa bukti worker/delivery/health path.**
    - Bukti: `StorefrontController.php:180` dan `202` memanggil `Mail::...->queue(...)`; tidak ditemukan CI/deployment/worker contract pada repo surface yang diperiksa.
    - Dampak: request concierge/newsletter dapat terlihat sukses sementara job tidak diproses atau gagal tanpa operator signal.
    - Tindakan: dokumentasikan worker supervisor, retry/dead-letter, mail provider health, dan test delivery sandbox.

### LOW / EVIDENCE GAPS

11. **[LOW][Traceability] Evidence state tersebar dan sebagian historis.**
    - `FINAL_HANDOFF_2026-10-11.md:9-12,39-46` membedakan final-regression-3 sebelum dock CSS, final-regression-4 terbatas, dan final2. Jangan menyebut PHP/full regression sebagai bukti sesudah semua source changes.
    - `RESULT-final2.md:30` menyatakan 56 PNG final belum inspeksi visual langsung; hanya visual coverage aktual 23/56 yang boleh diklaim.
    - Tindakan: setiap gate release harus punya commit/source hash, command, exit, scope, dan timestamp yang sama.

12. **[LOW][Requirement conflicts] Konflik acceptance belum memiliki keputusan owner.**
    - Bukti: handoff `FINAL_HANDOFF_2026-10-11.md:20` mencatat `<100ms` vs `<150ms`, FR-008 vs FR-204, `Cache::flush()` vs `forget`, dan bcrypt round 12 belum terverifikasi.
    - Tindakan: resolve dalam traceability ledger sebelum sign-off; jangan memilih angka/aturan secara implisit.

## Strengths verified

- Route separation memakai `guest`, `auth`, `guest:admin`, `auth:admin`, dan throttle (`routes/web.php:30-68`).
- Concierge melakukan validation, allowlist service, date/time constraints, product existence, quantity/monogram constraints (`StorefrontController.php:141-155`).
- JSON-LD memakai `JSON_HEX_TAG` (`StorefrontController.php:52-60,92-108`).
- Fresh Node suite lulus 73/73, termasuk theme key contract, overlay lifecycle, escaping, navigation, dan i18n tests.
- Current source theme keys konsisten underscore pada bootstrap dan core (`zafira_theme`, `zafira_lang`); issue tersisa berada pada caller cart/fallback, bukan bootstrap source.

## Release decision

**DO NOT SHIP production.** Minimum exit criteria: resolve data authority and Order-vs-concierge contract; add CI/deploy/rollback/worker evidence; apply CSP; fix cart language API/key mismatch; run post-change PHP/build/browser checks; complete visual coverage and WCAG/touch audit. Existing final2 and triage-final3 are useful evidence, but they do not erase these current gaps.

**Tidak diperiksa:** `.env`, live DB contents, ports 8000/8001, deployed URL, real mail delivery, authenticated browser flows, full 56-image visual inspection, dan production infrastructure. Risiko terbesar: data authority, missing order persistence, CSP/deployment absence, serta cart localization mismatch.
