# Verifikasi manual ZAFIRA — Artisan native dengan SQLite terisolasi

Tanggal eksekusi: 14 Oktober 2026 waktu lokal; timestamp aplikasi 13 Oktober 2026 UTC. Host: `http://127.0.0.1:8016`.

Status: **PASS untuk alur manual yang diuji. Ini bukan klaim siap produksi.**

## Menjalankan aplikasi

Buka Terminal.app baru, lalu jalankan:

```sh
cd /Users/hanifpriyambodo/Documents/online-shop
php artisan serve --env=manual --host=127.0.0.1 --port=8016
```

Perintah polos di atas sudah diuji dari tab Terminal.app baru. Tidak ada prefix `env -u`.

- Akun lokal ada di `storage/app/manual-test/AKUN_LOKAL.txt` (mode 0600, diabaikan Git).
- Database uji ada di `storage/app/manual-test/database.sqlite`.
- Profil memakai `.env.manual` (mode 0600, diabaikan Git).
- Profil memakai SQLite, session `database`, dan `MAIL_MAILER=log`.

## Hasil

| ID | Alur | Hasil dan bukti |
|---|---|---|
| FR-MAN-001 | Beranda dan `/up` | PASS: `/` HTTP 200; `/up` HTTP 200. |
| FR-MAN-002 | Katalog: cari, filter, pagination | PASS: `q=ZAFIRA` menampilkan 1 produk. Kategori 3 menampilkan 2 produk. Halaman 1 berisi 8 produk dan halaman 2 berisi 4 dari total 12. |
| FR-MAN-003 | Detail produk | PASS: `/produk/saphir-clutch` merender Minaudière Saphir. |
| FR-MAN-004 | Login pelanggan, tas, alamat, checkout | PASS: pelanggan login lewat UI, menambah item dan alamat, lalu checkout pesanan #1 senilai Rp18.750.000. Status: `pending / belum dibayar`. |
| FR-MAN-005 | Pembatalan dan pemulihan stok | PASS: pesanan #1 dibatalkan lewat UI. Status menjadi `cancelled`. Stok Sac Séraphine kembali dari 36 ke 37. |
| FR-MAN-006 | Admin: daftar dan filter pesanan | PASS: admin login lewat UI. `/admin/orders?status=cancelled` mengembalikan pesanan #1. Endpoint ini memang mengembalikan JSON. |
| FR-MAN-007 | PDF laporan admin dengan teks Mandarin | PASS: nama pelanggan diubah menjadi `王小明` lewat UI `/akun`. Admin mengunduh `/admin/reports/orders.pdf?period=today`. PDFKit (`tests/Support/pdf-text.swift`) mengekstrak baris `#1 \| 13-10-2026 18:01 \| CANCELLED \| Pelanggan: 王小明 \| Rp 18.750.000`. Render Quick Look juga diperiksa. |
| FR-MAN-008 | Asset dan console | PASS: CSS dan JS Vite HTTP 200. Console halaman katalog: 0 error, 0 warning. |
| FR-MAN-009 | Restart dan session | PASS: server lama dihentikan, lalu perintah polos dijalankan di Terminal.app baru. Browser tetap login di `/belanja` dan pesanan #1 tetap terlihat. |

## Bukti

| Berkas | Isi |
|---|---|
| `koleksi.png` | Screenshot katalog. |
| `checkout-pending.png` | Screenshot checkout pending. |
| `invoice-mandarin.png` | Slip HTML. Ini terpisah dari bukti PDF laporan. |
| `laporan-today.pdf` | Unduhan asli PDF laporan. SHA-256 `2b568365ffaff324da286bd4434c85075f82d6cd167ec2c1e0da87b144c58008`. |
| `laporan-today.pdfkit.txt` | Hasil ekstraksi teks PDFKit dari PDF di atas. |
| `laporan-today.png` | Render Quick Look terbaru dari PDF yang sama; nama `王小明` tampak pada baris pesanan. |

## Proses dan isolasi

- Server lama dihentikan: parent PID 62964, child PID 63419.
- Server baru: parent PID 69286 di `ttys004`, child PID 69317 listen di 8016.
- Server 8015 PID 76656 tidak disentuh dan tetap listen.
- Tidak ada perubahan pada `.env` atau database MySQL operasional.
- Tidak ada perubahan pada guard Amadis, dependency, atau kode aplikasi.

## Batasan

- **Pembayaran:** checkout bukan COD. Checkout membuat pesanan `pending / belum dibayar`. Tidak ada pembayaran online dan tidak ada konfirmasi pembayaran otomatis.
- **Email:** profil memakai mailer `log`. Transport runtime diverifikasi `Illuminate\Mail\Transport\LogTransport`, dan satu pesan uji bertanda `FR-MAN-010 manual log-only probe` hanya tercatat di `storage/logs/laravel.log`. Tidak ada email keluar. Alur UI yang diuji sendiri tidak mengirim email.
- **Zona waktu laporan:** laporan `today` memakai UTC.
- **Opsi produk:** ukuran, warna pilihan, dan monogram belum didukung pada pesanan server.

## Keamanan

- Password uji lama sempat tampil di output Playwright. Password itu sudah dirotasi, dan password baru sudah diverifikasi lewat login.
- Pemindaian lokal tidak menemukan password lama maupun password baru di `.playwright-cli/` atau di bukti tanggal 14 Oktober.
- `.playwright-cli/` diabaikan Git dan tidak ada berkas di dalamnya yang dilacak Git.
- Jangan bagikan `.env.manual`, `AKUN_LOKAL.txt`, cookie browser, atau isi database uji.
