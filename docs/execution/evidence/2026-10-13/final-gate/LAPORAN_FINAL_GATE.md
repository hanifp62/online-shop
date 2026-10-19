# Laporan Final Gate ZAFIRA / AMADIS — 2026-10-13

## Ringkasan

- **PASS terverifikasi:** persiapan root terisolasi, guard sentinel menolak konfigurasi uji, PHP suite normal dan acak, Node, Python, build, pemeriksaan diff, serta browser lifecycle S0–S5.
- **BLOCKED:** Pint gagal pada 8 file. Perbaikan format memerlukan edit kode, dilarang dalam lingkup gate ini.
- **NOT RUN:** sentinel export end-to-end dan bundle allowlist export audit. Tidak ditemukan jalur/tool aman yang sesuai; guard-test dan allowlist Composer bukan bukti export.
- **AM-01–AM-17:** seluruhnya **NOT PROVEN** sebagai acceptance matrix. Test suite lulus bukan bukti semua baris matriks; S0–S5 membuktikan alur browser khususnya, bukan kelengkapan AM rubric.
- **F1–F9:** tidak dijalankan ulang pada gate ini. Gunakan triage terdahulu hanya sebagai evidence historis, bukan hasil gate 2026-10-13.

## Root, port, dan cleanup

- Root baru: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-4ce30337e17a9a7fa8cfcb4e`
- Root PDF lama dipertahankan: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-d183833c94dbbd1638368827` (lihat `pdf-root-preserved.txt`).
- Port 8016 sebelum dan sesudah lifecycle: `lsof` rc **1**, tidak ada listener.
- Harness mencatat proses server miliknya dihentikan dengan rc 0; tidak ada proses pihak lain dihentikan.
- Root sementara **tidak dihapus**. Perlu persetujuan operator sebelum cleanup.
- S0–S5 menghasilkan `result.json` berstatus PASS, semua tahap S0–S5 PASS. `sha256.json` memvalidasi 21 file evidence (semua cocok).

## Perintah dan hasil

Semua file mentah berada di direktori evidence ini. Setiap gate menyimpan command (`*.cmd` jika tersedia), stdout, stderr, dan rc. Setup `init`, `guard-test`, `migrate`, `seed`, `provision` masing-masing rc **0**.

| Gate | Perintah | Hasil |
|---|---|---:|
| PHP standar | `scripts/amadis/launch.sh test` | rc 0 — 158 passed, 1.253 assertions |
| PHP acak | `scripts/amadis/launch.sh test --order-by=random --random-order-seed=20261013` | rc 0 — 158 passed, 1.252 assertions; seed 20261013 |
| Node | `node --test tests/amadis-layout.test.mjs tests/hit-hero-run12.test.mjs tests/phase4-a11y.test.mjs tests/prototype-currency.test.mjs tests/prototype-dock.test.mjs tests/prototype-drawer.test.mjs tests/prototype-header-1025.test.mjs tests/prototype-mobile-lang.test.mjs tests/prototype-security.test.mjs tests/sw.test.mjs tests/sweep10-repairs.test.mjs tests/task3.test.mjs tests/task4-focus.test.mjs tests/task4.test.mjs tests/zafira-i18n.test.mjs tests/zafira-theme.test.mjs scripts/amadis/browser-lifecycle.test.cjs` | rc 0 — 86 passed, 0 failed |
| Python | `python3 -m unittest discover -s tests/python` | rc 0 — 102 tests; 6 skipped |
| Pint | `vendor/bin/pint --test` | **rc 1 — FAIL**, 8 file perlu format |
| Build | `npm run build` | rc 0 — JS 19.50 kB |
| Diff | `git diff --check` | rc 0 |
| Browser lifecycle | `AMADIS_ROOT=<root> node scripts/amadis/browser-lifecycle.cjs` | rc 0 — S0–S5 PASS; CLI 0.1.22, browser 149.0.7827.55 |
| Port bebas sebelum/sesudah | `lsof -nP -iTCP:8016 -sTCP:LISTEN` | rc 1 / rc 1 — tidak ada listener |
| Hash sumber | perintah tercatat di `source-hashes.cmd` | rc 0 — hash launcher, lifecycle, guard, server tersimpan di `source-hashes.stdout` |

Catatan Python: output mencatat `docker_unavailable` dengan rc 125 pada pemeriksaan init internal; suite tetap selesai rc 0 dan melaporkan 6 skipped.

## S0–S5: bukti terukur

Raw evidence: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/amadis-4ce30337e17a9a7fa8cfcb4e/evidence/run-s0s5-20261012T211948480Z/`.

- S0: produk demo muncul pada katalog dengan stok 37; snapshot awal tanpa order.
- S1: customer login, tambah kuantitas 3, simpan/pilih alamat; total ter-render 3.750.000.
- S2: checkout membuat satu order pending; stok turun menjadi 34.
- S3: pembatalan mengembalikan stok menjadi 37; order berstatus cancelled.
- S4: replay evidence disimpan; hasil akhir keseluruhan PASS.
- S5: admin dan katalog/history ditangkap; tahap PASS.

File `result.json`, `commands.json`, screenshot, snapshot, UI JSON, dan `sha256.json` adalah sumber bukti. S0–S5 tidak menyatakan semua persyaratan AM telah diuji.

## Pemetaan AM-01–AM-17

Matriks sumber: `docs/demo/ZAFIRA_ACCEPTANCE_MATRIX.csv`. Statusnya tetap NOT RUN/TBD; barisnya sendiri mencatat bahwa rubric AMADIS resmi tidak ditemukan. Karena itu, hasil suite tidak dipromosikan menjadi PASS untuk seluruh acceptance criterion.

| ID | Status gate ini | Bukti / batas |
|---|---|---|
| AM-01 | NOT PROVEN | Snapshot S0 membuktikan produk stok-37, bukan total 76 produk dan distribusi 4×19. |
| AM-02 | PARTIAL | Tes katalog memeriksa 8 per halaman dan pagination pada fixture 20 produk; bukan fixture 76 produk / halaman terakhir empat. |
| AM-03 | PASS (tes) | `AmadisCatalogTest`: filter pencarian/atribut bertahan pada tautan pagination. |
| AM-04 | PASS (tes) | `BrandHeritageTest` (PDP valid 200, `product detail shows provenance`) dan `StorefrontTest` (`product detail page 404 for unknown slug`) lulus. |
| AM-05 | PASS (tes) | `AmadisCatalogTest::test_locale_is_validated_and_labels_localized`: zh/en benar, locale `xx` jatuh ke `<html lang="id">`. |
| AM-06 | PARTIAL | Tes memeriksa escaping dan markup dialog; operasi keyboard tidak dibuktikan oleh tes tersebut. |
| AM-07 | PASS (tes) | `AmadisOrderTest`: cart validation termasuk kasus kuantitas/sesi invalid. |
| AM-08 | PASS (tes) | Checkout repricing dan snapshot diuji; S1/S2 membuktikan alur checkout browser. |
| AM-09 | PASS | Tes replay tanpa pengurangan stok ganda dan payload berubah ditolak; S4 merekam replay. |
| AM-10 | NOT PROVEN | Tidak ada eksperimen checkout paralel terhadap stok awal 37; bukti S1–S3 bukan uji konkurensi. |
| AM-11 | PASS (tes) | Tes stock failure memeriksa rollback order dan voucher. |
| AM-12 | PASS (tes) | Tes quota voucher global/per-user lulus. |
| AM-13 | PASS (tes) | Tes batas kepemilikan dan akses order/alamat lulus sesuai cakupan test terkait. |
| AM-14 | PASS | Tes cancellation memeriksa pemulihan stok sekali; S3 mengonfirmasi stok 37 setelah cancel. |
| AM-15 | PASS (tes) | Tes hanya-admin transition serta transisi valid lulus. |
| AM-16 | PASS (tes) | `AmadisOrderTest::test_only_admin_can_transition_and_completed_owner_can_review_once`: review ditolak sebelum completed, satu review oleh pemilik, duplikat dan non-pemilik ditolak. |
| AM-17 | PASS (tes) | Tes favorites owner-scoped dan idempotent lulus. |

“PASS (tes)” berarti test bernama tersebut lulus dalam suite, bukan bukti produksi atau audit menyeluruh. Matriks masih perlu diperbarui oleh pemilik acceptance setelah bukti lengkap tersedia.

## Pemetaan F1–F9

ID F1–F9 berasal dari triage visual terdahulu: `docs/execution/evidence/2026-10-12/final-acceptance/triage-final3/triage-final3.md`. Tidak ada triage visual baru pada gate ini; status di bawah adalah **historis**, bukan hasil pengujian 2026-10-13.

| ID | Bukti historis | Status pada gate ini |
|---|---|---|
| F1 | Header: screenshot artefact untuk clipping headline/CTA; crowding navigasi pada 1024 dikonfirmasi. | NOT RETESTED |
| F2 | Floating dock overlap konten/controls dikonfirmasi. | NOT RETESTED |
| F3 | String Indonesia tersisa pada prototype EN dikonfirmasi. | NOT RETESTED |
| F4 | Frasa “Deux Rives” pada storefront ID dikonfirmasi. | NOT RETESTED |
| F5 | Dua label service ID belum diterjemahkan dikonfirmasi. | NOT RETESTED |
| F6 | Casing “Scheda artigianale” tidak konsisten dikonfirmasi. | NOT RETESTED |
| F7 | Defect kontras adalah artefak screenshot; sampel historis memenuhi AA, dengan batas metodologis terdokumentasi. | NOT RETESTED |
| F8 | Announcement terpotong secara visual dikonfirmasi; ukuran DOM tepat belum diukur. | NOT RETESTED |
| F9 | Heading IT membungkus baris tanpa overflow; bukan defect dengan sendirinya. | NOT RETESTED |

## Blocker dan batas

1. **Pint gagal:** 8 file tercatat di `pint.stdout`. Tidak dijalankan auto-format karena scope melarang perubahan kode. Ini satu-satunya gate kualitas yang gagal.
2. **Export audit belum berjalan:** tidak ada kemampuan export sentinel end-to-end atau bundle allowlist audit yang aman dan telah teridentifikasi. Guard rejection PASS tidak setara dengan export sentinel; pemeriksaan allowlist Composer launcher juga bukan bundle export audit.
3. **Pemetaan acceptance parsial:** AM-01 dan AM-10 belum terbukti; sejumlah kriteria lain hanya dibuktikan oleh test, dengan batas di tabel.
4. **F1–F9 tidak diulang:** gunakan hasil triage lama hanya untuk konteks historis.
5. Tidak menjalankan Composer langsung, reinstall, operational DB, `.env`, raw `php -r`, atau raw server. Tidak ada source edit, commit, push, atau deploy.

## Indeks evidence

`docs/execution/evidence/2026-10-13/final-gate/` berisi stdout/stderr/rc/command untuk gate terkait, `root.txt`, `source-hashes.*`, serta laporan ini. `s0s5.*` mencatat harness lifecycle. Evidence browser lengkap tetap berada pada root terisolasi yang dicantumkan di atas; jangan memindahkannya ke tempat publik sebelum redaksi.
