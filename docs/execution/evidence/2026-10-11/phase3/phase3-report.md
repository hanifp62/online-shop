# Laporan Eksekusi Keamanan Prototype Phase 3 & Verifikasi Browser

## Status tambahan: Logout customer browser

Browser Playwright Chromium menguji aplikasi Laravel pada `127.0.0.1:8013` dengan SQLite sementara di `/private/var/folders/.../T/opencode/zafira-logout2/db.sqlite` (bukan MariaDB). User sintetis `qa.logout@example.test` dibuat hanya di SQLite sementara. Skrip `logout-e2e.mjs`: 14/14 assertion PASS (desktop 1440×900 dan mobile 320×568), exit 0 di `logout-final.log`; hasil terinci `logout-results.json` dan screenshot logout sudah tersamarkan. Form POST+CSRF tersedia; login lalu klik logout meninggalkan `/akun`; akses ulang diarahkan ke `/login`; token CSRF berubah. Dua run pertama gagal karena asumsi nama cookie dan selector yang ambigu pada skrip, bukan kegagalan aplikasi. Proses server 8013 dimatikan setelah verifikasi, berkas password dan SQLite sementara dihapus. Port 8000/8001 tidak dihentikan. Review independen dilakukan dua kali: sebelum Phase 3 (temuan HIGH `openProductStudio` dan MEDIUM `emptyMsg`) dan setelah perbaikan (`code-reviewer`: tidak ada bug tersisa pada `prototype/app.js` dan `tests/prototype-security.test.mjs`; cakupan review terbatas pada dua berkas itu, tanpa interaksi browser).

---

Tanggal: 11 Oktober 2026.
Status: **PASS untuk perbaikan sink prototype tersisa, allowlist URL skema aman, dan harness DOM browser terisolasi.**

## 1. Temuan & Tindakan Kode

Berdasarkan review independen (HIGH pada `openProductStudio` & diptych katalog; MEDIUM pada terjemahan `cart_empty_title` di `renderCartDrawer`), tindakan mitigasi diterapkan secara terarah:

1. **Sink Studio & Katalog Terbuka (`prototype/app.js`):**
   - Diptych editorial (`filtered[0]` & `filtered[1]`): `title`, `badge`, `origin`, `hero_quote`, dan `desc` di-encode dengan `escapeHtml(...)`.
   - Dedicated Studio (`openProductStudio`): `cat`, `p.title`, `p.desc`, `p.hero_quote`, `p.material`, `p.origin`, `p.craft`, `p.care`, dan monogram di-encode dengan `escapeHtml(...)`.
   - Indeks dan ID pada atribut event handler inline (`onclick`) dinormalisasi dengan `Number(...)` atau `JSON.stringify(...)` yang di-encode context-safe.
   - Pilihan ukuran kriya (`selectStudioSize`): nilai string `sizeKey` di-encode dengan `escapeHtml(JSON.stringify(sizeKey))` sehingga tidak memungkinkan injeksi pemutus string JS literal.
   - Pesan keranjang kosong (`emptyMsg`): di-encode dengan `escapeHtml(emptyMsg)` untuk menangkal manipulasi kamus terjemahan kustom.

2. **Allowlist Skema URL Gambar (`safeImageSrc`):**
   - Helper baru `safeImageSrc(url)` memfilter URL skema aktif berbahaya (seperti `javascript:`, `vbscript:`, atau `data:` tak terotorisasi), memblokir karakter kontrol/whitespace newline (`\r`, `\n`, `\t`), dan hanya meloloskan path relatif aman (`assets/...`, `/storage/...`) atau URL `http://` / `https://`.
   - Diterapkan pada seluruh render gambar kartu produk, diptych katalog, galeri studio, dan drawer tas belanja.

## 2. Metodologi Uji TDD (RED-GREEN)

- **RED Baseline:**
  - Uji sandbox Node `tests/prototype-security.test.mjs` dijalankan sebelum perbaikan (`phase3-sinks-red.log`, exit 1): 3 tes gagal karena injeksi payload HTML tembus ke DOM string.
  - Harness browser Chromium Playwright `browser-sinks.mjs` dijalankan terhadap sumber lama `HEAD:prototype/app.js` (`browser-red-head.log`, exit 1): assertion gagal karena tag `<img src=x onerror=...>` tidak ter-encode.
- **GREEN Verification:**
  - Suite Node `tests/*.test.mjs`: 46 tests, 46 passed, 0 failed (`phase3/final-5.log`, exit 0).
  - Harness Playwright headless Chromium (`phase3/browser-sinks.mjs`): 14 assertions passed pada viewport 320px dan 1440px tanpa error event handler (`injected = 0`), memverifikasi literal textContent dan ketiadaan skema URL aktif (`phase3/final-9.log`, exit 0).

## 3. Hasil Verifikasi Akhir (Command Execution Table)

Semua perintah dijalankan di direktori kerja tanpa modifikasi database aktif:

| Perintah | Hasil | Log Berkas | Exit Code |
|---|---|---|---:|
| `php artisan test` | 76 passed (394 assertions) | `final-1.log` | 0 |
| `php artisan test --order-by=random --random-order-seed=1791702000` | 76 passed (394 assertions) | `final-2.log` | 0 |
| `vendor/bin/pint --test` | PASS (gaya kode rapi) | `final-3.log` | 0 |
| `npm run build` | PASS (Vite production build) | `final-4.log` | 0 |
| `node --test tests/*.test.mjs` | 46 tests passed (0 fail) | `final-5.log` | 0 |
| `node --check prototype/app.js` | PASS (syntax valid) | `final-6.log` | 0 |
| `node --check tests/prototype-security.test.mjs` | PASS (syntax valid) | `final-7.log` | 0 |
| `node --check docs/execution/evidence/2026-10-11/phase3/browser-sinks.mjs` | PASS (syntax valid) | `final-8.log` | 0 |
| `node docs/execution/evidence/2026-10-11/phase3/browser-sinks.mjs` | 14 assertions passed (DOM browser nyata) | `final-9.log` | 0 |
| `git diff --check` | PASS (tidak ada trailing whitespace/conflict) | `final-10.log` | 0 |
