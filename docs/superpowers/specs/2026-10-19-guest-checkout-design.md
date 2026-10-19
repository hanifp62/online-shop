# Guest Checkout dengan Pelacakan Aman

## Status

Draft untuk review setelah persetujuan desain percakapan. Requirement: R-34.

## Tujuan

Memungkinkan pelanggan membeli tanpa membuat akun, maksimal tiga langkah checkout, dengan ringkasan pesanan selalu terlihat dan pelacakan pascapembelian yang tidak dapat ditebak dari nomor pesanan saja.

## Batasan

- Checkout akun yang ada tetap berjalan.
- Harga, stok, voucher, dan idempotensi tetap dihitung serta ditegakkan server-side.
- Tidak menyimpan rahasia pelacakan di URL log, HTML publik, atau log aplikasi.
- Tidak mengubah alur pembayaran terverifikasi yang belum tersedia.
- Tidak menggabungkan task shipment/payment ledger yang berada di task lain.

## Desain yang dipilih

Tambahkan mode checkout tamu berbasis data kontak dan alamat yang disnapshot pada order. `user_id` menjadi nullable; pesanan tamu menyimpan email/nomor kontak ter-normalisasi pada snapshot, bukan membuat akun otomatis. Pelacakan memakai token acak berentropi tinggi yang disimpan sebagai hash, memiliki kedaluwarsa, dibatasi rate limiter, dan hanya mengembalikan data order yang aman.

Token mentah hanya diberikan sekali melalui respons konfirmasi checkout atau kanal konfirmasi yang sudah tersedia. Endpoint tracking menerima token, membandingkan hash secara aman, menolak token kedaluwarsa, dan tidak menerima pencarian berdasarkan `order_id` saja. Data pribadi yang dikembalikan dibatasi pada status, item, total, waktu order, dan tujuan yang telah disamarkan bila diperlukan.

## Alur pengguna

1. Keranjang → checkout: pilih `guest` atau akun; tamu mengisi email, nama penerima, telepon, dan alamat.
2. Ringkasan: item, harga, diskon, ongkir, total, dan data penerima tampil berdampingan dengan aksi konfirmasi.
3. Konfirmasi: server membuat order idempoten, mengurangi stok dalam transaksi, lalu mengembalikan nomor order dan token tracking satu kali.
4. Tracking: pelanggan memasukkan token atau membuka tautan privat; server mengembalikan status aman tanpa login.

## Batas data dan keamanan

- `user_id` nullable hanya untuk guest order.
- `guest_contact` atau snapshot setara menyimpan email dan kontak yang diperlukan untuk pelacakan/konfirmasi.
- Token mentah tidak disimpan; simpan hash dan `expires_at`.
- Indeks unik/hash mencegah ambiguitas token.
- Rate limit tracking berdasarkan IP dan token fingerprint.
- Validasi input memakai Form Request atau validasi controller yang konsisten.
- Query tracking harus memuat order berdasarkan hash dan waktu kedaluwarsa.
- Admin invoice/report harus aman untuk order tanpa relasi user.

## Recovery token dan masa berlaku

- Token mentah dipulihkan pada respons replay yang sah selama cache sesi terenkripsi aktif (maksimal 30 menit).
- Token tidak pernah di-generate ulang untuk order yang sama.
- Jika cache sesi berakhir atau sesi hilang, replay idempoten berhasil (HTTP 200) dengan:
  - `tracking_token: null`
  - `tracking_recovery: "unavailable"`
  - `message`: instruksi berbahasa Indonesia untuk menghubungi concierge atelier dengan menyertakan nomor order.
- Hash token pada database tidak pernah berubah atau dihapus akibat replay tanpa token.
- Lookup berbasis `order_id` saja tetap ditolak; order guest tidak dapat dilacak tanpa token valid.

## Kontrak endpoint minimum

- `GET /checkout`: **Desain awal digantikan (superseded).** Rancangan halaman checkout mandiri dengan mode akun/tamu dan ringkasan tidak menjadi kontrak rute aktual: `routes/web.php:111` mengarahkan GET ini ke `/belanja`.
- `POST /checkout`: menerima mode akun atau tamu, kontak/alamat, idempotency key, voucher; mengembalikan order dan token tracking saat order baru atau token yang sama pada replay sah selama cache sesi masih valid; jika token tidak dapat dipulihkan, `tracking_token` bernilai `null`.
- `GET /orders/track`: **Desain awal digantikan (superseded).** Rancangan GET penerima token dengan status terbatas dan `404/410` tidak menjadi kontrak rute aktual: `routes/web.php:113` mengarahkan GET ini ke `/belanja`. Pelacakan aktual memakai `POST /orders/track` (`GuestCheckoutController::track`, `routes/web.php:114`), dengan token di body request, bukan URL.

Nama route dan bentuk respons mengikuti konvensi Laravel yang sudah ada. Tidak menambah dependency.

## Acceptance criteria

- **FR-034-01:** pengguna tanpa sesi login dapat membuat order valid dari cart aktif.
- **FR-034-02:** order guest tidak membuat akun dan tidak dapat dibaca melalui endpoint order akun.
- **FR-034-03:** retry idempotency tidak menggandakan stok atau order; retry dengan payload berbeda ditolak.
- **FR-034-04:** token tracking acak, tidak disimpan mentah, rate-limited, kedaluwarsa, dan tidak dapat diganti dengan nomor order.
- **FR-034-05:** tracking tidak membocorkan email penuh, token, atau data akun lain.
- **FR-034-06:** akun checkout lama, admin order, invoice, transisi status, dan laporan tetap bekerja.
- **FR-034-07:** checkout memiliki maksimal tiga langkah dan ringkasan biaya selalu terlihat pada desktop/mobile.

## Verifikasi

- Unit/integration: guest order, validasi kontak, token hash, expiry, rate limit, IDOR, replay, stok, voucher, invoice.
- Regression: seluruh suite Laravel dengan SQLite memory.
- Browser: desktop dan viewport mobile; checkout guest, konfirmasi, tracking valid, token invalid/expired, console bebas error.
- Audit: grep route auth, user relation nullable, secret leakage, output PII, dan diff scoped.
