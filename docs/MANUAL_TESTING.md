# Pengujian Manual Terisolasi ZAFIRA (Artisan + SQLite)

Panduan operasional untuk menjalankan storefront secara manual dengan
`php artisan serve` dari checkout terisolasi. Data 100% sintetis;
tidak menyentuh database atau file produk yang sudah ada.

## Batasan Wewenang

- Milik agen ini: `scripts/manual/*` (baru), `docs/MANUAL_TESTING.md` (file ini),
  dan checkout terisolasi di bawah `/private/var/.../T/opencode/zafira-manual-*`.
- Dilarang mengedit file produk yang sudah ada, `.env`, atau `.env.manual`.
- Dilarang memakai port operasional 8015/8016. Port manual: **8017** atau **8018** untuk bukti native terpisah. Port operasional 8015/8016 dilarang.
- Dilarang commit.

## Prasyarat

Terminal zsh baru. Pastikan tidak ada variabel berbahaya yang terwariskan:

```zsh
env | grep -E '^(APP_|DB_|LARAVEL_|VIEW_COMPILED|SESSION_|CACHE_|MAIL_)' | cut -d= -f1
```

Jika ada output, unset satu per satu secara eksplisit di terminal tersebut.
Preflight menolak variabel warisan tanpa pernah menghapusnya diam-diam.
Untuk port alternatif terisolasi, set `export MANUAL_PORT=8018` sebelum setup/serve.

## 1. Setup Checkout Terisolasi

```zsh
cd ~/Documents/online-shop
export MANUAL_ROOT=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-YYYYMMDD
chmod +x scripts/manual/fixture.sh scripts/manual/start-server.sh
scripts/manual/setup.sh
```

Setup menyalin source working tree saat ini (termasuk perubahan belum commit; bukan HEAD),
tanpa secret/runtime/git/deps. `public/build` repo **tidak** disalin: setup menyalin
`node_modules` sementara, menjalankan `npm run build` (Vite) dari source salinan,
lalu menghapus `node_modules`; `public/hot` tidak boleh ada sehingga `@vite`
memakai manifest produksi checkout, bukan dev server.
Setup menyalin `vendor/` agar autoload menunjuk checkout sendiri, membuat `.env`
pribadi + `identity.json`, lalu menjalankan `php artisan migrate --force`
pada SQLite baru yang kosong. Menolak root yang sudah ada (data lama aman).

Fingerprint: `private-runtime/source-manifest.sha256` berisi checksum tiap file
source yang disalin, diverifikasi byte-identik terhadap repo; drift (akibat
agen lain mengedit bersamaan) dicatat di `source-drift.txt`, bukan disembunyikan.

## 2. Fixture Sintetis (Idempoten)

```zsh
cd "$MANUAL_ROOT"
bash scripts/manual/fixture.sh
```

Launcher menjalankan preflight **sebelum** setiap proses Artisan fixture.
Membuat 1 kategori, 3 produk (aktif, habis, pensiun), akun customer/admin,
alamat dan voucher sintetis. Akun tidak pernah direset password-nya.
Kredensial hanya di `private-runtime/credentials.json` (0600), jangan ditampilkan.
File kredensial ditulis atomik sebelum transaksi: rollback database boleh
menyisakan file, lalu run berikutnya memakai file yang sama untuk pemulihan.
Akun tanpa file atau password berbeda ditolak; periksa manual, jangan reset.
Tabrakan identitas ditolak, bukan ditimpa: kategori duplikat; produk (kategori,
brand, judul, harga, aktif, gambar, alt, deskripsi; stok sengaja tidak dicek
karena checkout mengubahnya); nama akun; seluruh kolom alamat; voucher
(diskon, kuota, batas per user, aktif). File kredensial juga divalidasi skemanya.
Tinker bisa mencetak exception namun tetap exit 0, jadi `fixture.sh` hanya
exit 0 jika baris penanda sukses muncul persis; selain itu exit 1 dan hanya
mencetak pesan `Manual fixture refused: ...` (tanpa nilai kredensial).

## 3. Jalankan Server Native

```zsh
cd "$MANUAL_ROOT"
python3 scripts/manual/preflight.py serve && php artisan serve --host=127.0.0.1 --port=8017
```

Alternatif: `bash scripts/manual/start-server.sh` menjalankan urutan identik.
Perintah native harus persis `php artisan serve --host=127.0.0.1 --port=8017` (atau port yang diset di `$MANUAL_PORT`).
Dalam harness gunakan background dengan **timeout: 0**, bukan timeout terbatas.
Preflight menolak variabel warisan, key `.env` ekstra/duplikat, sink berbeda,
semua symlink (termasuk nested), dan cache PHP yang berubah dari hash migrasi.
Hanya `services.php` dan `packages.php` hasil bootstrap awal yang diperbolehkan.
Aset front-end dimuat melalui manifest Vite produksi di `public/build/manifest.json`.
Jangan menjalankan perintah Artisan lain tanpa preflight tepat sebelumnya.

## 4. Uji Penerimaan (Opsional, Terotomasi)

```zsh
cd ~/Documents/online-shop
MANUAL_ROOT="$MANUAL_ROOT" python3 scripts/manual/acceptance.py
```

Sebelum membaca kredensial atau mengirim POST login, skrip memastikan
`/manual/identity.txt` cocok byte dengan nonce checkout lokal. Semua redirect
harus tetap satu origin; redirect endpoint identitas sendiri dilarang.
Mencakup: root, katalog, PDP sintetis, login customer, cart, checkout,
cancel order, login + dashboard admin, daftar order admin, dan PDF
`admin/reports/orders.pdf?period=30d` (assert `%PDF-` + content-type).
Pengujian acceptance membuat order dan alamat baru: hitung perubahan tersebut
terpisah dari pemeriksaan idempotensi fixture.

## 5. Hentikan

`Ctrl+C` di terminal server, atau `kill <PID artisan>`.

## Followup source terkini — persiapan 2026-10-14

Status **PREPARED, belum diuji runtime**. Agen browser menunggu konfirmasi source stabil
sebelum bootstrap checkout baru. Untuk followup ini hanya port **8018** boleh dipakai;
port operasional **8015/8016/8017**, `.env` operasional, dan MySQL tidak disentuh.

Rencana, gap, diagnosis historis, dan acceptance tercatat dalam
[`followup/browser/README.md`](execution/evidence/2026-10-14/followup/browser/README.md).
Suite baru: `followup/browser/browser.cjs` di direktori bukti yang sama.
Validasi sintaks Node lulus; ini bukan bukti bahwa journey browser lulus.

Fingerprint inspeksi 128 berkas source eksplisit:
`cf3e0d0440ea5b478885d56b9989ddc3f8fc14fdbf763d277993ac2b7df1dccc`,
UTC `2026-10-14T16:36:48.283797+00:00`. Manifest:
`followup/browser/inspection-source-manifest.json`.
Fingerprint ini bukan fingerprint checkout runtime dan bukan klaim source final.

Perbaikan assertion home-add dibatasi pada sinkronisasi: tunggu toast produk/aksi
serta badge terlihat bernilai 1, lalu periksa quantity JSON server tetap 1.
Bukti historis tidak cukup untuk menyatakan race sebagai akar masalah pasti:
pesan kegagalan lama berbeda dari assertion pada skrip tersimpan.
Jika assertion baru masih gagal, laporkan kebutuhan fix produk; jangan melemahkan regresi.

Fixture dua kali harus memiliki dua snapshot hitungan terpisah sebelum acceptance.
Persistence harus memiliki snapshot identik sebelum dan sesudah restart, sebelum
acceptance kedua menambah order. Browser mencatat page errors, kegagalan network,
HTTP >=400 dan pemblokiran request eksternal; PDF diuji melalui tombol dashboard.
Bukti lama di bawah tetap historis, tidak menggantikan pengujian source terkini.

**Hasil eksekusi followup (2026-10-14, checkout `zafira-manual-followup-8018`, fingerprint
`1ba9b1ccc667c9d0956fbcb381244b13c9f541a32a3b7041a162cc2cb59056c4`, drift 0):**
fixture ×2 exit 0 dengan hitungan identik; acceptance sebelum dan sesudah restart PASS;
snapshot order/stok identik di sekitar restart; browser **13 PASS, 1 FAIL**
(`catalog-toast-count`: katalog tidak punya toast/badge, hanya `#catalog-feedback`).
FAIL dipertahankan menunggu keputusan produk. `home-add-toast-count` PASS pada source ini;
akar kegagalan historis tetap tidak terbukti. Opsi `paid` tidak ada di select status admin.
Detail: [`followup/browser/RESULT.md`](execution/evidence/2026-10-14/followup/browser/RESULT.md).

## Bukti Historis — 2026-10-14, root `zafira-manual-sink-8018`, port 8018

Detail lengkap, perintah, dan hash: lampiran pada akhir dokumen ini.
Ringkas: setup exit 0 (24 migrasi, Vite build dari source salinan, drift 0 baris);
fixture run 1 dan 2 exit 0 dengan hitungan identik; 12 injeksi kegagalan exit 1;
acceptance sebelum dan sesudah restart PASS; order lama tetap terlihat setelah restart.
Batas: browser visual/manual tidak dijalankan; hanya HTTP dan assertion skrip.
Tidak mengklaim fingerprint final repo karena agen lain masih menulis source.

---

# Lampiran: Bukti Native Terisolasi — root `zafira-manual-sink-8018`, port 8018

Semua waktu UTC. Semua data sintetis. Tidak ada commit. Tidak ada nilai rahasia di berkas ini.

- Checkout: `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-sink-8018`
- Port: **8018** (`MANUAL_PORT=8018`). Port operasional 8015/8016 tidak disentuh.
  Server 8017 milik agen lain tetap LISTEN sepanjang pekerjaan ini; hanya PID 8018 milik saya yang di-`kill`.
- Jendela eksekusi: 2026-10-14T13:05Z sampai 2026-10-14T13:09:50Z.

## Perintah yang dijalankan (sink preflight)

```zsh
cd ~/Documents/online-shop
export MANUAL_ROOT=/private/var/.../T/opencode/zafira-manual-sink-8018
export MANUAL_PORT=8018
chmod +x scripts/manual/fixture.sh scripts/manual/start-server.sh
scripts/manual/setup.sh                                  # exit 0

cd "$MANUAL_ROOT"
bash scripts/manual/fixture.sh                           # run 1, exit 0
bash scripts/manual/fixture.sh                           # run 2, exit 0
MANUAL_ROOT=... MANUAL_PORT=8018 scripts/manual/start-server.sh   # background, timeout 0

cd ~/Documents/online-shop
MANUAL_ROOT=... MANUAL_PORT=8018 python3 scripts/manual/acceptance.py   # PASS
# kill PID 8018, start-server.sh lagi, acceptance kedua                 # PASS
```

Proses Artisan melalui launcher checkout didahului preflight: `setup.sh` memanggil `preflight.py setup` lalu
`preflight.py migrate`; `fixture.sh` memanggil `preflight.py fixture`; `start-server.sh`
memanggil `preflight.py serve`; `acceptance.py` memanggil `check_environment` +
`check_checkout(check_root(...))` sebelum request pertama. Semua mencetak
`PASS: inherited environment and isolated identity preflight.`

## Setup

- `setup.sh` exit 0. 24 migrasi biasa pada SQLite kosong baru. Tanpa `migrate:fresh`, `db:wipe`, `DROP TABLE`.
- Vite: `public/build` repo **tidak** disalin. Setup menyalin `node_modules` sementara, menjalankan
  `npm run build`, lalu menghapus `node_modules`. Checkout memuat
  `public/build/manifest.json` (331 byte) + `public/build/assets/`, dan tidak ada `public/hot`.
  Bukti runtime: HTML `/` menyajikan `/build/assets/app-Bb3VWUMd.js` dan `/build/assets/app-NFLYjlVv.css`.
  CSS hash berbeda dari `public/build` repo (`app-Bwsq0LBC.css`), membuktikan build berasal dari
  source salinan working tree (dirty), bukan artifact repo dan bukan HEAD.
- `source-fingerprint.txt`: `b80839540bb532b88be15747b892bf93aee0af9d31a8ce4cf90fcfc3152d98a1`
- `source-file-count.txt`: `274`
- `source-drift.txt`: 0 baris saat setup.

Fingerprint di atas adalah fingerprint **checkout ini pada waktu setup**, bukan fingerprint
final repo: agen lain masih menulis source, jadi klaim fingerprint final tidak dibuat.

## Hash skrip pada waktu uji (repo == salinan di checkout, byte-identik)

| Berkas | SHA-256 |
|---|---|
| `scripts/manual/fixture.php` | `230692a8790ee7b5146d4f2e7c34a3c72a121a8cee9eb87b2db59c69aea603f9` |
| `scripts/manual/fixture.sh` | `5e29b1f948183e3f030216cd5b1f28e2dca8a0564b2638790e3b2ebfb1330954` |
| `scripts/manual/preflight.py` | `86d618f0c248f8999e2bcbaffc5ea85829e01af3e83ec0e1b760c13ee546df85` |
| `scripts/manual/acceptance.py` | `b593d769dac4c190a863b23f0b47964bd685276761bf0765e91c3f122b6d6aad` |
| `scripts/manual/setup.sh` | `ff427d478372f512ed4adabbc8f677a9e863c4fe8b6b72347c3348012607ba93` |
| `scripts/manual/start-server.sh` | `c9b948abcf53eb1919830ebacf8c0913a4444c5a83496e27d88abe7066da41ac` |

Diverifikasi 2026-10-14T13:09:50Z: keenam berkas `root_match=yes`.
`docs/MANUAL_TESTING.md` diubah setelah pencatatan hash, jadi hash doc tidak dicantumkan.

## Hitungan dua run fixture (idempotensi)

| Tabel | Setelah run 1 | Setelah run 2 |
|---|---|---|
| categories (MANUAL) | 1 | 1 |
| products | 3 | 3 |
| users | 1 | 1 |
| admins | 1 | 1 |
| addresses | 1 | 1 |
| vouchers | 1 | 1 |
| orders | 0 | 0 |

## Acceptance dan persistensi lintas restart

Acceptance run 1 (server pertama): PASS, `order_id=1`, `persisted_prior_orders_visible=0`,
`http_idempotent_replay=200`, `http_idempotent_conflict_409=409`, `double_cancel_idempotent=200`,
`admin-pdf` 200 `application/pdf` 7945 byte, `inactive-pdp=404`, `soldout-jsonld=OutOfStock`.

Hitungan setelah acceptance run 1: categories=1, products=3, users=1, admins=1,
addresses=3, vouchers=1, orders=2. Fixture run 3 setelah itu exit 0 dan hitungan **tidak berubah**
(order dan alamat hasil acceptance tidak disentuh fixture).

Lalu PID 8018 di-`kill` (server keluar kode 143) dan `start-server.sh` dijalankan ulang pada port sama.
Acceptance run 2 di 2026-10-14T13:08:53Z: PASS, `order_id=3`, `persisted_prior_orders_visible=2`,
PDF 200. Order dari sebelum restart tetap terlihat, jadi state bertahan lintas restart.

Hitungan setelah restart + acceptance run 2: categories=1, products=3, users=1, admins=1,
addresses=5, vouchers=1, orders=4, `stock` sapphire=18 (dikurangi oleh checkout acceptance, bukan fixture).
`/manual/sapphire.svg` HTTP 200 `image/svg+xml` 214 byte.

## Injeksi kegagalan (semua exit 1, pesan refusal, tanpa kebocoran)

DB dan file kredensial dibackup di dalam checkout sebelum setiap injeksi, lalu direstore.
Pemeriksaan otomatis: `hexlike_lines_in_stderr=0` pada **semua** kasus (tidak ada nilai mirip
kredensial hex di stderr).

| Injeksi | Hasil |
|---|---|
| harga produk diubah | exit 1 — `product collision for manual-synthetic-out.` |
| gambar produk diubah | exit 1 — `product collision for manual-synthetic-sapphire.` |
| kota alamat diubah | exit 1 — `address collision.` |
| voucher di-nonaktifkan | exit 1 — `voucher collision.` |
| nama admin diubah | exit 1 — `admin account collision: password does not match credential file.` |
| kategori duplikat disisipkan | exit 1 — `category collision: duplicate titles.` |
| password customer diganti hash bcrypt valid lain | exit 1 — `customer account collision: password does not match credential file.` |
| password customer diganti hash rusak (bukan bcrypt) | exit 1, tanpa refusal: exception Laravel dari `Hash::check`. Tetap fail-closed. |
| akun ada tetapi file kredensial hilang | exit 1 — `account exists without its private credential file; inspect manually.` |
| mode file kredensial 0644 | exit 1 — `credential file must be a private regular file.` |
| skema kredensial rusak (password bukan 32 hex) | exit 1 — `credential file schema mismatch.` |
| **kegagalan tulis kredensial**: akun dikosongkan, path kredensial dibuat direktori | exit 1 — `credential file write failed; no account created.`; sesudahnya `users+admins=0` dan 0 berkas `credentials.json.tmp-*` tertinggal |

Pemulihan aman setelah crash: dengan file kredensial tetap ada tetapi akun dikosongkan
(simulasi rollback transaksi), fixture exit 0 dan membuat ulang akun dari file yang sama;
run berikutnya exit 0; hash file kredensial **tidak berubah** (`cred_hash_unchanged=yes`),
users=1, admins=1. Password tidak pernah direset.

## Celah yang ditutup pada siklus ini

1. `fixture.sh` dulu mewarisi exit code Tinker saja. Tinker dapat mencetak exception namun exit 0,
   sehingga kegagalan bisa lolos sebagai sukses. Sekarang sukses menuntut baris penanda persis
   (`grep -Fqx`) **dan** status 0; jika gagal hanya pesan `Manual fixture refused: ...` yang dicetak,
   output mentah ditahan agar kredensial tidak bocor.
2. Pemeriksaan tabrakan dulu sebagian (produk hanya kategori/brand/judul; alamat hanya `line1`;
   voucher hanya diskon/kuota; akun hanya password). Sekarang mencakup harga, aktif, gambar, alt,
   deskripsi, nama akun, seluruh kolom alamat, batas per user dan status aktif voucher,
   serta menolak kategori duplikat. `stock` sengaja dikecualikan karena checkout sah menguranginya;
   `used` pada voucher juga dikecualikan karena redemption sah menaikkannya.
3. Skema file kredensial kini divalidasi (email cocok, password 32 hex) sebelum dipakai.
4. Port dapat dipilih via `MANUAL_PORT`, dibatasi keras pada 8017/8018 oleh preflight,
   sehingga 8015/8016 tidak mungkin dipakai walau salah set.
5. Setup tidak lagi mewarisi `public/build` repo yang bisa basi; Vite dibangun dari source salinan.

## Batas dan risiko

- Browser visual/manual tidak dijalankan. Hanya HTTP dan assertion skrip.
- Payment eksternal, email delivery, concurrency, dan kegagalan dependency tidak diuji.
- Injeksi hash bcrypt rusak gagal lewat exception framework, bukan pesan refusal eksplisit.
- Fingerprint source hanya berlaku untuk checkout ini pada waktu setup; agen lain sedang menulis source.
- Bukti di berkas lain dalam folder ini berasal dari root berbeda dan bukan bukti untuk root ini.

## Runbook closure terkoreksi 2026-10-15 (native, port 8018)

Paket audit hanya berisi bukti dan bukan checkout yang dapat dijalankan. Jalankan perintah berikut dari repository asli.

```zsh
cd /Users/hanifpriyambodo/Documents/online-shop
export MANUAL_ROOT=/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-runbook-20261015
export MANUAL_PORT=8018
bash scripts/manual/setup.sh
cd /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-runbook-20261015
bash scripts/manual/fixture.sh
python3 scripts/manual/preflight.py serve
php artisan serve --host=127.0.0.1 --port=8018
```

Buka URL berikut:

- Storefront: `http://127.0.0.1:8018/`
- Login pelanggan: `http://127.0.0.1:8018/login`
- Katalog: `http://127.0.0.1:8018/koleksi`
- Login admin: `http://127.0.0.1:8018/admin/login`
- Dashboard admin: `http://127.0.0.1:8018/admin`

Baca kredensial dummy hanya secara lokal. Jangan cetak nilainya ke chat atau log:

```zsh
open /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-runbook-20261015/private-runtime/credentials.json
```

Langkah pelanggan:

1. Buka login pelanggan.
2. Baca email dan password dari berkas lokal.
3. Login.
4. Buka katalog.
5. Tambahkan produk ke tas.
6. Uji quick view dan jumlah penggantian.
7. Buka tas dan verifikasi jumlah akhir.

Langkah admin:

1. Buka login admin.
2. Baca kredensial admin dari berkas lokal.
3. Login.
4. Buka dashboard admin.
5. Periksa ringkasan pesanan dan katalog.
6. Jangan mengubah data operasional.

Stop dengan `Ctrl+C`. Jika proses perlu dihentikan, matikan hanya PID `artisan serve` milik root ini. Restart dari root isolasi:

```zsh
cd /private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/zafira-manual-runbook-20261015
export MANUAL_ROOT="$PWD"
export MANUAL_PORT=8018
python3 scripts/manual/preflight.py serve
php artisan serve --host=127.0.0.1 --port=8018
```

Katalog memakai label `Set 1 item di tas` dan `Terapkan jumlah ke tas`. Kontrak service tetap SET.
