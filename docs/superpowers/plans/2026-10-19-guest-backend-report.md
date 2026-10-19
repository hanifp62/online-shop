# Laporan Backend Guest Checkout

Tanggal: 2026-10-19

## Hasil

Backend guest checkout dan tracking telah diverifikasi melalui PHPUnit SQLite in-memory. Perubahan ini memperbaiki fallback identitas guest pada invoice dan laporan admin, melindungi rollback migrasi dari penghapusan data guest, serta menguji replay token sesi dan kedaluwarsa TTL.

## Berkas

- `app/Http/Controllers/AdminOrderController.php`: invoice memakai snapshot `customer_name`/`customer_email` dengan fallback akun dan escaping; laporan memakai `customer_name` guest.
- `database/migrations/2026_10_19_000001_add_guest_checkout_to_orders_table.php`: `down()` menolak rollback bila masih ada order dengan `user_id` null.
- `tests/Feature/AdminOrderReportTest.php`: invoice guest, escaping XSS, dan nama guest pada PDF report.
- `tests/Feature/GuestCheckoutTest.php`: replay token identik, TTL 30 menit tanpa rotasi hash, serta rollback migration dengan/ tanpa guest data.

## Kontrak API

`POST /checkout` menerima `idempotency_key`, `customer_name`, `customer_email`, `customer_phone`, `label`, `recipient_name`, `phone`, `line1`, `line2`, `city`, `province`, dan `postal_code`. Harga, status, total, user ID, address ID, dan voucher guest tidak boleh dikirim.

Respons sukses guest mengembalikan `order` dan `tracking_token`. Retry identik dalam sesi yang sama mengembalikan token sama selama TTL replay 30 menit. Setelah TTL, order tetap direplay tanpa mengembalikan token baru. Jika token sesi telah kedaluwarsa atau sesi hilang, replay idempoten berhasil (HTTP 200) dengan `tracking_token: null`, `tracking_recovery: "unavailable"`, dan `message` yang mengarahkan pembeli untuk menghubungi concierge menggunakan nomor pesanan. Hash token asli di database dijamin tidak berubah.

`POST /orders/track` menerima `token` di body. Lookup memakai hash token dan expiry, lalu mengembalikan proyeksi minimal order tanpa PII kontak atau alamat. `GET /orders/track` tidak menerima token dan mengarah ke `/belanja`. Jika token tidak ditemukan/kedaluwarsa, respons 404 diterima dengan baik oleh antarmuka dan memunculkan pesan informatif ramah pengguna tanpa memicu uncaught JavaScript exception. Log HTTP 404 di devtools/browser network tab adalah perilaku standar peramban untuk respons non-2xx dan merupakan hasil yang diterima.

## Respons Tampilan dan Overflow Mobile

CSS antarmuka Belanja (`resources/views/shop/index.blade.php`) telah diperbaiki untuk tampilan mobile (390×844):
- Elemen input form dan token konfirmasi (`code`) diberi `overflow-wrap: anywhere`, `word-break: break-all` / `break-word`, serta `min-width: 0`.
- Token 64 karakter dapat di-wrap dengan aman di perangkat mobile sehingga `scrollWidth` dokumen tidak melebihi lebar layar (`<= 390px`).
- Token tetap selectable (`user-select: all`) dan dapat disalin langsung oleh pembeli.
- Disediakan tombol hapus token agar pembeli dapat membersihkan token dari DOM setelah menyalinnya.

## Bukti pengujian

Verifikasi follow-up keamanan:

```text
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/GuestCheckoutTest.php tests/Feature/GuestTrackingTest.php > /tmp/o.txt 2>&1; echo EXIT=$?
EXIT=0; 26 passed, 132 assertions
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test > /tmp/o.txt 2>&1; echo EXIT=$?
EXIT=0; 337 passed, 4 skipped, 2537 assertions
```

Checkout route mengaktifkan Laravel session blocking agar requests dari session yang sama terserialisasi. Replay token dienkripsi dengan Laravel `Crypt`, lalu didekripsi hanya saat controller memproses retry. Test memverifikasi respons recovery dan memastikan session serialization tidak memuat token raw. UI menyediakan kontrol untuk mengosongkan token dari input serta halaman konfirmasi.

Verifikasi follow-up terbaru:

```text
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test tests/Feature/GuestCheckoutTest.php tests/Feature/GuestTrackingTest.php tests/Feature/AmadisShopTest.php > /tmp/o.txt 2>&1; echo EXIT=$?
EXIT=0; 33 passed, 166 assertions
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test > /tmp/o.txt 2>&1; echo EXIT=$?
EXIT=0; 337 passed, 4 skipped, 2539 assertions
```

Browser smoke test dengan Chrome pada viewport 390×844 menghasilkan `scrollWidth=390`, token tetap dapat dipilih, invalid token menghasilkan HTTP 404 dengan pesan ramah, tanpa uncaught JavaScript error, dan tombol hapus mengosongkan token. Browser network/devtools mencatat 404 non-2xx secara normal; ini diterima, UI menangani respons dengan baik.

Empat test skip belum dianalisis ulang. Tidak ada perubahan pada `config/session.php`, `config/security.php`, atau `CspReportOnlyTest`. Target MySQL belum diverifikasi.

## Batasan

Rollback migration dengan guest order sengaja gagal tertutup melalui `RuntimeException` agar data tidak dihancurkan. Operator harus memindahkan atau mempertahankan migration sebelum rollback. Audit browser, UI guest checkout, dan MySQL belum dilakukan dalam tahap backend ini.
