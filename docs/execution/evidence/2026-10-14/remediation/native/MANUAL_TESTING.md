# Pengujian Manual Terisolasi ZAFIRA (Artisan + SQLite)

Panduan operasional untuk menjalankan storefront secara manual dengan
`php artisan serve` dari checkout terisolasi. Data 100% sintetis;
tidak menyentuh database atau file produk yang sudah ada.

## Batasan Wewenang

- Milik agen ini: `scripts/manual/*` (baru), `docs/MANUAL_TESTING.md` (file ini),
  dan checkout terisolasi di bawah `/private/var/.../T/opencode/zafira-manual-*`.
- Dilarang mengedit file produk yang sudah ada, `.env`, atau `.env.manual`.
- Dilarang memakai port operasional 8015/8016. Port manual: **8017**.
- Dilarang commit.

## Prasyarat

Terminal zsh baru. Pastikan tidak ada variabel berbahaya yang terwariskan:

```zsh
env | grep -E '^(APP_|DB_|LARAVEL_|VIEW_COMPILED|SESSION_|CACHE_|MAIL_)' | cut -d= -f1
```

Jika ada output, unset satu per satu secara eksplisit di terminal tersebut.
Preflight menolak variabel warisan tanpa pernah menghapusnya diam-diam.

## 1. Setup Checkout Terisolasi

```zsh
cd ~/Documents/online-shop
export MANUAL_ROOT=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-YYYYMMDD
scripts/manual/setup.sh
```

Setup menyalin source (tanpa secret/runtime/git/deps/prototype aset besar),
menyalin `vendor/` agar autoload menunjuk checkout sendiri, membuat `.env`
pribadi + `identity.json`, lalu menjalankan `php artisan migrate --force`
pada SQLite baru yang kosong. Menolak root yang sudah ada (data lama aman).

Fingerprint: `private-runtime/source-manifest.sha256` berisi checksum tiap file
source yang disalin, diverifikasi byte-identik terhadap repo; drift (akibat
agen lain mengedit bersamaan) dicatat di `source-drift.txt`, bukan disembunyikan.

## 2. Fixture Sintetis (Idempoten)

```zsh
cd "$MANUAL_ROOT"
php artisan tinker --execute='require base_path("scripts/manual/fixture.php");'
```

Membuat 1 kategori + 1 produk `manual-synthetic-sapphire` dan akun
customer/admin sintetis. Aman dijalankan ulang: record yang ada dilewati,
tidak pernah diisi ulang atau dihapus. Kredensial hanya di
`private-runtime/credentials.json` (0600), jangan ditampilkan.

## 3. Jalankan Server Native

```zsh
cd "$MANUAL_ROOT"
zsh -lc 'cd "$MANUAL_ROOT" && python3 scripts/manual/preflight.py serve && php artisan serve --host=127.0.0.1 --port=8017'
```

Perintah yang berjalan harus persis `php artisan serve` dengan flag di atas —
tanpa launcher pengganti. Preflight `serve` menolak symlink `.env`,
config cache, dan sink yang diubah.

## 4. Uji Penerimaan (Opsional, Terotomasi)

```zsh
cd ~/Documents/online-shop
MANUAL_ROOT="$MANUAL_ROOT" python3 scripts/manual/acceptance.py
```

Mencakup: root, katalog, PDP sintetis, login customer, cart, checkout,
cancel order, login + dashboard admin, daftar order admin, dan PDF
`admin/reports/orders.pdf?period=30d` (assert `%PDF-` + content-type).

## 5. Hentikan

`Ctrl+C` di terminal server, atau `kill <PID artisan>`.

## Bukti Terbaru

Lihat `docs/execution/evidence/2026-10-14/remediation/native/`.
