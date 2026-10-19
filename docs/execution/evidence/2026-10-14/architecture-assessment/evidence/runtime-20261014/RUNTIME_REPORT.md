# Verifikasi runtime terisolasi — 14 Oktober 2026

## Batas dan hasil

Asesmen penuh tetap **INCOMPLETE**. Run baru memakai `scripts/amadis/launch.py` melalui `launch.sh`, root disposable Amadis, SQLite dummy, storage/cache/views/logs dalam root, mail log dan queue sync. Tidak ada koneksi MySQL operasional, perubahan kode produk/dependency/env/DB manual, install, commit, push, atau `migrate:fresh`.

| Command/metode | RC | Hasil |
|---|---:|---|
| `./scripts/amadis/launch.sh guard-test` | 0 | PASS: sentinel rejected |
| `./scripts/amadis/launch.sh test` | 0 | PASS: 158 tests, 1253 assertions, 78.83s; `phpunit-isolated.txt` |
| `scripts/amadis/acceptance_isolated.sh <packet>/evidence/isolated-acceptance-20261014 8042` dengan installed Playwright/Chromium | 1 | AM-01 invariant PASS; AM-02 76 unique products PASS; AM-06 keyboard PASS; SQLite parallel checkout menghasilkan 500 |
| `AMADIS_ROOT=<lifecycle-root> AM_PORT=8043 node <temp>/browser-lifecycle.cjs` | 0 | S0–S5 PASS; `lifecycle-result.json` |
| PDF root: `launch.sh migrate`, `seed`, `provision`, `artisan tinker --execute='require base_path("scripts/amadis/pdf-ui-fixture.php");'`; `AM_PORT=8044 node <temp>/pdf-ui-download.cjs` | 0 | PDF download PASS; 10199 bytes; `pdf-result.json` |
| `AM_PORT=8045 AM_OUT=<temp> node <temp>/mobile-check.cjs` | 0 | 15 halaman/viewport HTTP 200; tanpa overflow horizontal |
| `AM_PORT=8046 AM_OUT=<temp> node <temp>/console-check.cjs` | 0 | 7 console resource errors; 0 page errors; font eksternal sengaja diblokir |
| Native `php artisan serve --env=manual` | — | NOT RUN: profil manual tidak menyediakan `LARAVEL_STORAGE_PATH`, compiled-view/cache sink overrides; storage/logs/views tetap repo-shared. Native command tidak memberi jaminan guard runner. Tidak dijalankan demi batas keselamatan. |
| MariaDB/MySQL concurrency target | — | NOT RUN: tidak ada DB disposable engine target yang disetujui; MySQL operasional dilarang |

Salinan dua skrip browser hanya mengganti port default 8016 dengan `AM_PORT` dan repo path absolut dalam temp. Skrip repo tidak diubah. Dua percobaan viewport pertama gagal karena executable Chrome salah; hasil final memakai Chrome terpasang. Tidak ada install otomatis.

## Before / action / result

| Alur | Before | Action | Result dan bukti |
|---|---|---|---|
| Katalog | Stock dummy 37; 76 produk aktif | Buka katalog dan navigasi pagination | 76 unik; stock UI 37 sesuai snapshot; `lifecycle-S0-ui.json`, AM-02 |
| Login | Sesi belum autentik | Login customer dummy | `authenticated=true`; tidak ada screenshot form login/kredensial; `lifecycle-S1-ui.json` |
| Cart | Belum ada order | Tambah qty 3, pilih alamat milik customer | Subtotal server Rp3.750.000; `lifecycle-S1-ui.json` |
| Checkout | Stock 37 | Konfirmasi qty 3 | Order #1 pending, total 3750000, stock 34; `lifecycle-S2-*` |
| Cancel/replay | Pending, stock 34 | Cancel lalu replay | Cancelled, stock kembali 37; replay HTTP 200 order sama; `lifecycle-S3-*`, `lifecycle-S4-*` |
| Restart/admin | Order cancelled | Restart owned server, refresh history dan admin | Persistensi dan admin_visible true; `lifecycle-S5-*`; bukan bukti settlement |
| PDF | Fixture laporan dummy 2026-10-13 | Unduh PDF lewat UI admin | PASS download, 10199 bytes, SHA256 `e3724de03966ed2727b2e6666fd3ef10524a53113afc6950a82cca2749c21fc7`; `pdf-download.json`. PDF mentah tidak dimasukkan paket; bukti download bukan review visual seluruh isi. |

## Concurrency

Acceptance SQLite: tiga request qty 20/20/17 mulai `2026-10-14T02:06:45.213Z`, berakhir .228/.236/.240Z; HTTP 201/422/500. Log root menunjukkan `SQLSTATE[HY000]: General error: 5 database is locked`. Invariant DB tetap 1, stock 17 dan satu pending order qty 20. Ini tidak membuktikan overlap transaksi independen engine target dan **bukan PASS concurrency**. Galat SQLite teramati; tidak diatribusikan sebagai hasil MariaDB.

## Browser/HCI/WCAG dan keamanan UI

Desktop 1365×900, mobile 390×844 reduced-motion, serta narrow reflow 320×650 DPR 2: `/`, `/koleksi`, `/belanja`, `/login`, `/admin/login` HTTP 200; scrollWidth sama dengan clientWidth. DPR 2 bukan browser zoom 200%; zoom native, kontras, target sentuh menyeluruh dan audit WCAG 2.2 AA penuh **NOT RUN**. Reduced-motion media query terdeteksi, bukan bukti semua animasi mematuhi preferensi. Keyboard acceptance: Enter, Space, Escape, close button, focus restored PASS; ada satu perpindahan fokus ke browser UI setelah 12 Tab, tanpa pageContentLeaks yang dicatat.

Console diagnosis menghasilkan 2+2+1+1+1 `Failed to load resource: net::ERR_FAILED`, origin yang sengaja diblokir hanya `https://fonts.bunny.net`; 0 page errors. Screenshot dengan external fonts diblokir tidak menjamin tipografi produksi identik.

Review statis tetap merujuk `SOURCE_EXCERPTS.md` dan `ARCHITECTURE_AS_IS.md`: Blade escaping dan invoice `e()`, auth/admin middleware dan ownership scope terlihat; CSP Report-Only tidak menegakkan pembatasan. Suite meliputi CSRF/escaping/ownership, tetapi tidak menggantikan pentest payload DOM, negative authorization seluruh route, atau sertifikasi keamanan. Jangan klaim bebas XSS/IDOR dari screenshot.

## Safety dan proses

Insiden awal GET koordinator ke 8016 sebelum sink proof tetap **FAIL**; run baru tidak menghapus insiden. Native manual profile tetap tidak aman untuk audit ini. Server lama 8015 PID 76656 dan 8016 PID 69317 tetap listening dan tidak dihentikan.

Server milik audit 8042–8046 sudah tidak listening pada pemeriksaan akhir. Lifecycle stop-owned-group RC 0 tercatat dalam `lifecycle-commands.json`. PHP child tersisa 79192, 79307, 79589 dihentikan; diagnosis 8046 PID 80771 dihentikan. Root disposable tetap tersedia; tidak menghapus bukti atau DB temp. Tidak ada server audit yang sengaja ditinggalkan.

Artefak disalin melalui allowlist: result/command/snapshot non-PII, screenshot setelah login, viewport tanpa form login, test/guard output. Tidak menyertakan credentials.json, env, key, DB, fixture-identity besar, raw Laravel stack log, atau raw PDF. Kata password/token dalam test output hanya nama test, bukan nilai rahasia.
