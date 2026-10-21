# Webhook Pembayaran Midtrans: Signature + Idempotensi

## Status

Draft untuk review setelah persetujuan desain percakapan (2026-10-20). Requirement: R-01 (inti), R-02 (ledger minimum). Task: `baseline-codebase/konfirmasi-bayar-otomatis/webhook-pembayaran-server-side-verifikasi-signatur`.

## Tujuan

Status `paid` hanya boleh berasal dari notifikasi server-side Midtrans yang signature-nya valid dan statusnya dikonfirmasi ulang lewat GET Status API. Redirect browser tidak pernah menandai order lunas. Notifikasi ganda tidak mengubah order dua kali.

## Di luar cakupan task ini

- Membuat transaksi Snap/Core API (mengirim pelanggan ke halaman bayar).
- Retry + dead-letter beralarm, circuit breaker (R-20, task `e566b7b2…`).
- Refund dan rekonsiliasi terjadwal (R-02 lanjutan).
- Pembatalan order otomatis dari notifikasi `cancel`/`expire`/`deny`; event ini hanya dicatat. Pembatalan tetap lewat job reservasi kedaluwarsa atau admin.
- **Batas yang diketahui (review HIGH #3):** job reservasi kedaluwarsa dapat membatalkan order yang sudah dibayar di Midtrans sebelum webhook tiba. Task ini hanya memastikan pembayaran telat itu tercatat durable (`order_not_payable`) dan memicu log `alert` untuk refund manual. Cek Status API sebelum pembatalan menjadi bagian rekonsiliasi R-20 (`e566b7b2…`).

## Konfigurasi

`config/services.php` → `midtrans.server_key` (`MIDTRANS_SERVER_KEY`) dan `midtrans.is_production` (`MIDTRANS_IS_PRODUCTION`, default `false`). Kunci kosong ⇒ endpoint menolak semua notifikasi (503) dan mencatat galat konfigurasi; tidak pernah memverifikasi dengan kunci kosong. Base URL Status API: sandbox `https://api.sandbox.midtrans.com`, produksi `https://api.midtrans.com`.

## Kontrak `order_id`

`order_id` Midtrans = `orders.id` sebagai string digit. Nilai non-digit ditolak sebagai payload tidak valid.

## Endpoint

`POST /webhooks/midtrans` di `routes/web.php`, nama `webhooks.midtrans`. Pengecualian CSRF di `bootstrap/app.php` memakai `$middleware->validateCsrfTokens(except: ['webhooks/midtrans'])`. Rute dibatasi `throttle:60,1`. Controller tipis memanggil `PaymentWebhookService`.

## Alur verifikasi

1. Validasi field wajib: `order_id`, `status_code`, `gross_amount`, `signature_key`, `transaction_id`, `transaction_status`. Gagal ⇒ 422.
2. `expected = hash('sha512', order_id . status_code . gross_amount . server_key)`; bandingkan dengan `hash_equals`. Gagal ⇒ 403, tidak ada penulisan DB.
3. GET `/v2/{order_id}/status` dengan Basic auth `server_key:` (timeout 10 detik). Gagal jaringan/non-2xx ⇒ 503 agar Midtrans mengulang.
4. **Validasi integritas respons Status API:** `order_id` respons harus sama persis dengan `order_id` notifikasi, `transaction_id` respons harus sama dengan `transaction_id` notifikasi, `currency` harus `IDR` (atau tidak ada), dan `gross_amount` respons dinormalisasi ketat (`^\d+(\.00)?$` → integer rupiah). Jika tidak cocok ⇒ 422, catat galat integritas.
5. Status sukses: `settlement`, atau `capture` dengan `fraud_status = accept`. Semua field status yang dipakai untuk keputusan diambil dari respons Status API, bukan notifikasi mentah.
6. **Eksekusi atomik:** dalam satu `DB::transaction`:
   - Kunci baris order dengan `Order::whereKey($id)->lockForUpdate()->first()`.
   - Cek apakah `(provider, provider_transaction_id, event_status)` sudah ada di ledger. Jika ya ⇒ commit dan respons 200 (idempoten).
   - Tulis baris `payments`.
   - Jika sukses, nominal cocok, dan status order masih `pending`: transisikan `pending → paid` via CAS `where('status', 'pending')->update(['status' => 'paid'])`, catat `order_status_history` (`admin_id = null`), dan ubah status reservasi stok menjadi `committed`.
   - Jika order sudah bukan `pending` saat pembayaran sukses tiba (mis. sudah `cancelled` oleh job kedaluwarsa): tulis ledger `outcome = order_not_payable`, jangan ubah order, buat log `alert` terstruktur (butuh refund manual oleh tim keuangan). Commit dan respons 200 agar Midtrans tidak terus mengulang.

## Ledger `payments`

Kolom: `id`, `order_id` (FK nullable, `nullOnDelete`), `provider` (`midtrans`), `provider_transaction_id`, `provider_order_id`, `event_status` (transaction_status dari Status API), `amount` (unsigned bigint rupiah), `outcome` (`paid` | `recorded` | `amount_mismatch` | `order_not_payable` | `unknown_order`), `payload` (JSON berisi subset field Status API tanpa `signature_key`), timestamps. Unique `(provider, provider_transaction_id, event_status)`. Model append-only seperti `OrderStatusHistory`.

## Keputusan per kasus (semua di satu transaksi DB)

| Kasus | Ledger | Order | Respons |
|---|---|---|---|
| Event sudah tercatat (unique hit) | tidak berubah | tidak berubah | 200 |
| Sukses, nominal cocok, order `pending` | `paid` | `pending → paid` + history + reservasi `committed` | 200 |
| Sukses, nominal beda | `amount_mismatch` | tidak berubah, log warning | 200 |
| Sukses, order bukan `pending` (mis. sudah `cancelled`) | `order_not_payable` | tidak berubah, log warning (butuh refund manual) | 200 |
| Order tidak ditemukan | `unknown_order` | — | 200 |
| Status non-sukses | `recorded` | tidak berubah | 200 |

Respons 200 dipakai untuk kasus yang tidak akan berubah bila diulang, supaya Midtrans berhenti mengirim ulang. Log tidak memuat `signature_key`, server key, atau PII pelanggan.

## Transisi `paid`

`Order::MANUAL_BLOCKED` tetap memblokir `paid` dari admin. `CheckoutService` mendapat method `markPaid(Order)` yang memakai inti transisi yang sama (compare-and-set status, history dengan `admin_id = null`, reservasi `committed`). Isi `transition()` dipindah ke method privat bersama agar tidak ada logika ganda.

## Acceptance criteria

- **FR-001-01:** signature salah ⇒ 403, tidak ada baris `payments`, order tetap `pending`.
- **FR-001-02:** notifikasi valid + Status API `settlement` + nominal cocok ⇒ order `paid`, satu baris ledger `paid`, history `pending → paid` tanpa admin.
- **FR-001-03:** notifikasi yang sama dikirim dua kali ⇒ satu baris ledger, satu entri history.
- **FR-001-04:** body notifikasi berkata `settlement` tetapi Status API berkata `pending` ⇒ order tidak lunas.
- **FR-001-05:** nominal beda ⇒ ledger `amount_mismatch`, order tidak lunas.
- **FR-001-06:** Status API gagal ⇒ 503, tidak ada penulisan DB.
- **FR-001-07:** order sudah `cancelled` ⇒ ledger `order_not_payable`, status tidak berubah.
- **FR-001-08:** server key kosong ⇒ 503, tidak memverifikasi.
- **FR-001-09:** admin tetap tidak bisa menyetel `paid` manual.

## Pengujian

Feature test dengan `Http::fake()` untuk Status API dan signature dihitung dari server key uji. Tidak memakai kredensial sandbox nyata; uji end-to-end sandbox dicatat sebagai verifikasi terbuka.

## Sumber

- https://docs.midtrans.com/docs/https-notification-webhooks
- https://docs.midtrans.com/docs/testing-payment-on-sandbox
