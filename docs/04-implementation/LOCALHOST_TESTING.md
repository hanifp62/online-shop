# Pengujian lokal dengan Artisan

**Task:** FE-COMP-PUB-20261023. **Requirement:** NFR-FE-002/003/004, NFR-CICD-002/004/005. **Diverifikasi:** 23 Oktober 2026 UTC.

Tujuan: pengguna dan penguji menjalankan checkout dan build yang sama melalui `php artisan serve`, bukan menganggap harness sintetis otomatis membuktikan runtime lokal. [Laporan dan bukti](../05-quality/LOCALHOST_PARITY_2026-10-23.md).

## 1. Menjalankan checkout yang sudah dikonfigurasi

Jalankan dari root `online-shop`, dengan database lokal yang sudah tersedia. Jangan memakai shell yang masih membawa `AMADIS_*`, override cache/storage, atau environment pengujian lain.

Sesudah menarik perubahan dependency/source:

```sh
composer install --no-interaction --prefer-dist
npm ci
npm run build
```

Gunakan lockfile; jangan mengganti langkah di atas dengan `composer update` atau `npm update`. Jangan memakai `--no-scripts`: hook Composer memperbarui penemuan package Laravel, termasuk provider Inertia.

Untuk profil localhost dengan port dan URL kanonis yang sama:

```sh
APP_URL=http://127.0.0.1:8001 php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

Buka **http://127.0.0.1:8001**. Gunakan host tersebut secara konsisten, bukan bergantian dengan `localhost`. Ini command native Artisan, bukan server/harness khusus. Stop dengan `Ctrl+C` dari terminal Anda.

`APP_URL` di atas bukan secret dan hanya berlaku untuk proses ini. `--no-reload` diperlukan agar override environment tersebut diteruskan ke child PHP, serta menonaktifkan restart otomatis ketika konfigurasi environment berubah. Restart command jika konfigurasi berubah. Jalankan dari shell bersih: flag ini juga meneruskan nilai environment lain, dan dapat mengaktifkan konfigurasi multi-worker yang sudah disetel.

**Prasyarat profil ini:** config cache tidak aktif, seperti pada bukti lokal. Jika checkout memang memakai config cache, override environment tidak menjamin perubahan konfigurasi. Periksa dan koordinasikan perubahan cache terlebih dahulu; jangan menjalankan `optimize:clear`/`cache:clear` pada runtime bersama sebagai tebakan.

Jika operator sudah menyetel URL kanonis lokal secara privat ke origin yang sama, command berikut cukup untuk server:

```sh
php artisan serve --host=127.0.0.1 --port=8001
```

Tidak ada file `.env` yang dibaca, disalin ke bukti, atau diubah dalam pemeriksaan ini. Jangan menjalankan ulang `key:generate`, seed, atau migrasi destruktif untuk mencoba memperbaiki error frontend pada database yang sudah berisi data.

## 2. Mengapa kadang muncul port8001?

`php artisan serve` tanpa opsi juga telah diuji langsung. Pada snapshot pengujian, proses PHP existing memakai8000, sehingga Artisan mencoba8001 dan smoke yang sama lulus. Itu bukan jaminan selalu8001.

Laravel terpasang mulai8000 bila port efektif tidak disetel; exit child nonzero dapat memicu percobaan port berikutnya. `SERVER_PORT` yang disetel mengubah perilaku tersebut. Dengan `--port=8001` eksplisit, port sibuk harus gagal, bukan pindah diam-diam.

```sh
lsof -nP -iTCP:8001 -sTCP:LISTEN
```

Jika ada listener, identifikasi pemiliknya. Jangan membunuh proses tak dikenal. Setelah pemeriksaan agent, server milik agent dihentikan dan8001 tersedia; proses existing8000 tetap hidup. Kedua proses membaca checkout/cache generated yang sama, sehingga pembaruan file dapat memengaruhi keduanya.

## 3. Pencegahan miss setelah upgrade Inertia

### Package discovery

Gejala yang ditemukan: beranda500, log menunjuk interface `Inertia\Ssr\Gateway`, sementara dependency sudah terpasang tetapi `bootstrap/cache/packages.php` dan `services.php` belum mencantumkan provider Inertia.

Perbaikan yang benar-benar dijalankan:

```sh
composer dump-autoload --optimize --no-interaction
```

Hook nonisolated existing meregenerasi autoload/discovery. Tidak perlu menambah binding manual, mengaktifkan SSR, atau mengubah transaksi/database. Instalasi Composer di harness terisolasi tidak otomatis memperbarui cache checkout biasa. Command ini menulis generated autoload/cache; jangan menyebutnya read-only.

### Build dan Vite

- Mode pengujian ini memakai `npm run build`, bukan Vite HMR.
- `public/hot` harus tidak ada. Keberadaannya membuat Laravel memilih dev server walaupun server tersebut mati. Build saja tidak menjamin stale hot file dihapus.
- Hentikan Vite milik Anda terlebih dahulu. Jika hot file tertinggal, pastikan tidak ada dev server aktif sebelum membersihkannya; jangan menghapus file proses orang lain.
- Manifest dan lazy chunks harus berasal dari build yang sama. Bukti lokal mencatat SHA-256 manifest/semua aset yang dirujuk, serta membandingkan16aset yang diterima browser dengan file disk.
- Tunggu `#app[data-react-boot="ready"]` sebelum berinteraksi untuk memeriksa React. HTTP200 atau tampilan Blade yang benar bukan bukti React aktif.

### Browser, origin, dan service worker

Mulai dengan profil/context browser terpisah. Jangan menghapus cookie/cache profil pribadi untuk menyamakan pengujian. Service worker dan localStorage berbeda per origin termasuk port, sedangkan cookie tidak diisolasi oleh port. Akun pada8000dan8001dapat berbagi cookie pada host yang sama.

Fallback `?frontend=blade` dan katalog tanpa JavaScript ikut diuji. Service worker existing tidak menyimpan navigasi HTML, tetapi dapat menyimpan aset `/build/*`. Bukti juga mencatat apakah worker mengontrol halaman setelah mengunjungi fallback; bukan klaim semua browser lama mempunyai state yang sama.

## 4. Checklist manual

| Langkah | Hasil yang diharapkan |
|---|---|
| Buka `/` | Home tampil, React ready, tidak ada error/warning console |
| Buka `/koleksi`, lalu produk | Katalog dan PDP React, gambar dan stylesheet berhasil dimuat |
| Buka `/belanja` | Shop React; tidak perlu melakukan transaksi pada data operasional |
| Buka `/login`, `/register`, `/lupa-kata-sandi`, `/admin/login` | Form native React tampil; jangan kirim kredensial ke laporan |
| Home pada viewport390px | Tidak ada overflow horizontal |
| Buka `/?frontend=blade`, lalu kembali `/` | Fallback tampil, navigasi berikutnya kembali React |
| Nonaktifkan JavaScript pada context uji, buka `/koleksi` | Tautan produk server-rendered tetap tersedia |

Pengujian login/admin/transaksi lengkap memakai fixture terisolasi dan [harness CI](CI_CD_RELEASE_RUNBOOK.md), bukan akun/DB operasional diam-diam. Jangan menukar target harness ke8001 atau menonaktifkan guard.

## 5. Batas paritas

| Dimensi | Native lokal yang diperiksa | Acceptance otomatis |
|---|---|---|
| Source/build | Checkout84file manifest yang sama; byte aset diperiksa | Source yang sama, konfigurasi fixture tersendiri |
| Server | Native Artisan, satu listener child pada invocation tercatat | `php -S` melalui supervisor, konfigurasi worker berbeda |
| DB | MySQL lokal, GET nontransaksional | SQLite sintetis untuk browser/PHP; MySQL CI gate terpisah |
| Session/cache/queue | Driver database | File/array/sync sesuai harness |
| Mail | Driver log; tidak mengirim email | Sink sintetis |
| Flags | React20halaman aktif, operations summary OFF | Flag/config fixture harus dinyatakan per skenario |
| Lingkungan | macOS, PHP8.5.11, Node24.21.0 | Linux runner/toolchain dari workflow |

Request GET dapat menulis session/cache. Bukti tidak mengklaim zero writes, kesamaan isi database, benchmark beban, atau kesiapan produksi.

Override `APP_URL` pada server **tidak mengubah** queue worker/scheduler/command CLI terpisah yang sudah berjalan. Sebelum menguji email, signed links, atau job terjadwal end-to-end, operator harus menyamakan origin pada semua proses terkait. Bukti lokal hanya memeriksa origin signed URL pada CLI tanpa mengirim email atau mencetak signature.
