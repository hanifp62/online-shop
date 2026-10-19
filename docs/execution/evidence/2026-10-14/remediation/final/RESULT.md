# Bukti native final ZAFIRA — 2026-10-14

**Hasil: PASS untuk jalur checkout native dan browser utama; catatan UX katalog: tidak menyediakan toast atau badge cart.** Semua waktu UTC. Root baru: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-final-8018`; port 8018. Uji memakai SQLite, fixture sintetis, tanpa mail/pembayaran nyata. Tidak ada source atau `.env` yang diubah. Tidak ada commit.

## Bukti eksekusi

| Pemeriksaan | Status | Bukti |
|---|---|---|
| Root belum ada sebelum setup | PASS | `test ! -e "$MANUAL_ROOT"` exit 0. |
| Environment bersih | PASS | Daftar nama variabel warisan `APP_`, `DB_`, `LARAVEL_`, `VIEW_COMPILED`, `SESSION_`, `CACHE_`, `MAIL_`: kosong; preflight setup, migrate, fixture, serve: PASS. |
| Setup pada root baru | PASS | `MANUAL_ROOT=.../zafira-manual-final-8018 MANUAL_PORT=8018 scripts/manual/setup.sh` exit 0; 24 migrasi; `setup.log`. Semua proses Artisan hanya dari checkout setelah preflight; tidak menjalankan Artisan di repo. |
| Fixture pertama | PASS | `fixture-1.log` exit 0; `counts-fixture-1.txt`: categories=1 products=3 users=1 admins=1 addresses=1 vouchers=1 orders=0 order_items=0. |
| Fixture kedua / idempotensi | PASS | `fixture-2.log` exit 0; `counts-fixture-2.txt`: seluruh hitungan identik. |
| Server native 8018 | PASS | `server.log`: preflight PASS, server `127.0.0.1:8018`; HTTP `/` 200. |
| `acceptance.py` satu kali | PASS | `acceptance.log` exit 0: root/koleksi/PDP 200; PDP nonaktif 404; stok habis `OutOfStock`; login customer 200; cart 200; checkout 201, order #1; replay 200, konflik 409; pembatalan ulang 200; admin PDF 200 `application/pdf`, 7801 byte. |
| Browser Chromium nyata: login customer | PASS | `browser-results.json`, 2026-10-14T13:24:35Z; `/akun` 200. Chromium lokal Playwright; tidak menginstal dependency baru. |
| Beranda add-to-cart + toast + badge | PASS | `home-diagnostic.json`, `home-add-toast.png`: produk synthetic Sapphire, toast visible (`toast show`, opacity 1), teks `MANUAL — Synthetic Sapphire ditambahkan ke tas Anda.`, badge `cart-count has-items` berisi `1`, cart server berisi quantity 1. Percobaan awal browser script memberi FAIL palsu karena membaca `innerText` sebelum render selesai; pemeriksaan susulan menunggu toast dan menunggu 300 ms, lalu mengonfirmasi DOM + server. |
| Drawer: 3 klik plus cepat | PASS | `browser-results.json`, 2026-10-14T13:24:36Z; quantity sebelum 1, sesudah 4 pada DOM dan `/cart`; `drawer-quantity.png`. |
| Hapus lalu Urungkan | PASS | `browser-results.json`, 2026-10-14T13:24:36Z; cart kosong setelah hapus, quantity 4 setelah undo; `drawer-undo.png`. |
| Katalog: tambah ke tas | PASS | `browser-results.json`, 2026-10-14T13:24:37Z; feedback `Tas belanja diperbarui. Jumlah 1 menggantikan jumlah sebelumnya`; `/cart` quantity 1; `catalog-add.png`. Katalog memakai pesan inline `#catalog-feedback`, bukan toast. |
| Katalog: toast + badge cart | **FAIL (tidak tersedia pada halaman katalog)** | `browser-followup.json`: DOM `.toast`=0, `.cart-count`=0, `#catalog-feedback`=1. Kriteria toast+badge dipenuhi di beranda, tetapi tidak di katalog. Tidak disamarkan sebagai PASS. |
| `/belanja`: baris ringkasan sama dengan cart | PASS | `browser-results.json`, 2026-10-14T13:24:37Z: `Rp125.000 × 1 = Rp125.000`, subtotal Rp125.000 cocok `/cart`; `checkout-summary.png`. |
| Pesan pesanan + referensi | PASS | `browser-results.json`, 2026-10-14T13:24:38Z: `Pesanan #3 dibuat: pending / belum dibayar. Total Rp125.000. Tidak ada pembayaran online.`; cart kosong; `order-confirmation.png`. |
| Katalog 390px tanpa overflow; filter buka | PASS | `browser-results.json`, 2026-10-14T13:24:38Z: viewport=scrollWidth=390 saat tertutup/terbuka; disclosure semula tertutup lalu terbuka; `catalog-390.png`. |
| Katalog 320px tanpa overflow; filter buka | PASS | `browser-results.json`, 2026-10-14T13:24:39Z: viewport=scrollWidth=320 saat tertutup/terbuka; disclosure semula tertutup lalu terbuka; `catalog-320.png`. |
| Login admin + unduh PDF browser | PASS | `browser-followup.json`, 2026-10-14T13:26:07Z; klik tombol `Unduh PDF` di `/admin`, HTTP 200, `Content-Type: application/pdf`, 7822 byte, header `%PDF-`; `admin-report.pdf`, `admin-report-download.png`. Percobaan awal script salah mencari link di `/admin/orders`, lalu dikoreksi dengan form laporan di `/admin`; tidak mengubah source. |
| Error JS browser | PASS | `browser-results.json`: `pageErrors: []`. |
| Server 8018 dihentikan | PASS | PID listener sendiri 43373 dihentikan 2026-10-14T13:28:24Z; `lsof -nP -iTCP:8018 -sTCP:LISTEN` tanpa output. Tidak menyentuh server 8015/8016/8017. |

## Provenance source

`source-fingerprint.txt`: `61ae491c87be9c6ccb5120753d898061379bdd18ae97c8e7332e3523401647d7`. `source-file-count.txt`: **275**. `source-drift.txt`: **0 baris saat setup**. Salinan manifest mentah ada di `source-manifest.sha256`; perhitungan dan hash ulang repo/checkout ada di `provenance.json` pada 2026-10-14T13:28:08Z. Hash empat file berikut identik antara repo dan checkout pada verifikasi tersebut:

| File | SHA-256 |
|---|---|
| `app/Services/CheckoutService.php` | `b1b88e542d59e4d55009007cf99f4120b8cce0fb3bf7a2a6fc56a063b58df90f` |
| `resources/js/app.js` | `4a10ffe7146f6cbbab17debb13862b8f139062adea21e432e11c63f2c800e90e` |
| `resources/views/catalog/index.blade.php` | `855f104dd791eae703e93a3fce52e4dc9bccef6f55e40340f215d09516d5cd80` |
| `app/Services/PdfDocumentService.php` | `a93e022cf4057b76c8c0c76b2534c68873645d7d352e43135d484ea7f69ba90b` |

Counts final setelah acceptance dan browser (`provenance.json`): categories=1, products=3, users=1, admins=1, addresses=3, vouchers=1, orders=3, order_items=3; order #1 cancelled, #2 pending, #3 pending. Ini **bukan** hitungan idempotensi fixture: acceptance menambah dua order dan dua alamat; browser menambah satu order.

Batas: screenshot hanya membuktikan keadaan visual pada viewport yang diuji. PDF berasal dari akun admin sintetis checkout terisolasi. Tidak ada uji perangkat fisik atau pembayaran nyata. `browser.cjs` dan `browser-followup.cjs` ialah skrip bukti dalam folder ini, bukan source produk.
