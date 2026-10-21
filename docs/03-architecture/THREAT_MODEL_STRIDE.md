# Threat Model STRIDE — Jalur Uang & Konsol Admin

**Cakupan:** checkout, pembayaran (webhook Midtrans), pembatalan, retur/refund, dan konsol admin.
**Metode:** STRIDE per aset. Setiap kontrol menunjuk ke kode atau test yang ada.
**Status bukti:** Fakta terverifikasi dari kode per 2026-10-20. Risiko sisa ditandai **Belum ditutup**.

## Aset & Batas Kepercayaan

| Aset | Batas kepercayaan |
|---|---|
| Pesanan & ledger pembayaran | Browser pelanggan → `checkout.store`; Midtrans → `webhooks.midtrans` |
| Refund | Pelanggan → `refunds.store`; admin → `admin.refunds.update` |
| Konsol admin | Browser admin → guard `admin` + TOTP + Gate |
| Log audit (`auth_events`) | Aplikasi menulis, admin membaca, tidak ada jalur ubah |

## Jalur Uang

| STRIDE | Ancaman | Kontrol (bukti) | Risiko sisa |
|---|---|---|---|
| **S**poofing | Pihak ketiga memalsukan notifikasi "lunas" | `PaymentWebhookService`: signature SHA-512 (`hash_equals`), lalu konfirmasi ulang lewat Status API; order_id, transaction_id, mata uang dicocokkan | Bergantung pada kerahasiaan `MIDTRANS_SERVER_KEY` |
| **T**ampering | Mengubah harga/ongkir/pajak saat checkout | `payload_hash` dibandingkan dengan hash ulang di `CheckoutService`; total dihitung server | — |
| **T**ampering | Nominal webhook tidak cocok | `gross_amount` diambil byte-for-byte dari body dan dicek terhadap Status API | — |
| **R**epudiation | Sengketa "sudah bayar/belum" | Ledger `Payment` idempoten (unique), `PaymentDeadLetter` untuk replay, notifikasi pesanan tercatat | Tidak ada rekonsiliasi harian otomatis. **Belum ditutup** |
| **I**nformation disclosure | Pelanggan membaca pesanan orang lain | Query selalu `where user_id`, `firstOrFail` → 404; pelacakan tamu memakai token ber-hash | — |
| **D**oS | Banjir webhook/checkout | `throttle:60,1` webhook, `throttle:checkout`, batas body 16 KiB, circuit breaker Status API | Rate limit memakai cache driver aplikasi |
| **E**levation | Pelanggan menyetujui refund sendiri | Transisi refund hanya lewat `admin.refunds.update` (Gate `manage-refunds`, admin saja); pencairan fail-closed | Pencairan belum diuji E2E sandbox. **Belum ditutup** |

## Konsol Admin

| STRIDE | Ancaman | Kontrol (bukti) | Risiko sisa |
|---|---|---|---|
| **S**poofing | Pencurian kata sandi admin | Guard terpisah, throttle login, TOTP + kode pemulihan sekali pakai (`EnsureAdminTotp`) | Admin lama tanpa secret masih lolos (legacy). **Belum ditutup** |
| **T**ampering | CSRF ke aksi admin | Middleware CSRF grup `web`; hanya `webhooks/midtrans` dan callback Apple dikecualikan | — |
| **R**epudiation | Admin menyangkal aksi | `auth_events` append-only (model menolak update/delete), korelasi `request_id` | Aksi data (ubah produk) belum tercatat di log audit. **Belum ditutup** |
| **I**nformation disclosure | Halaman admin ter-cache | `NoStore` pada grup admin; galat tanpa trace; `error_id` hanya ID | — |
| **D**oS | Brute force login | `throttle:login` | — |
| **E**levation | Staff mengakses fungsi admin; peran rusak | Gate terpusat + `AuthorizeAdmin`; peran tak dikenal ditolak; pelanggan tidak punya guard admin | Belum ada permission granular |

## Verifikasi Otorisasi Berkelanjutan

`tests/Feature/AuthorizationInventoryTest.php` membaca daftar route nyata dan gagal bila:
- ada route `admin/*` (selain login/logout/TOTP) tanpa `EnsureAdminTotp` dan `admin.can:*`;
- Gate yang dipakai route tidak terdefinisi;
- route uang pelanggan kehilangan `auth` atau throttle yang dijanjikan di atas;
- route baru bermethod tulis muncul di luar daftar yang telah ditinjau (daftar putih eksplisit).

Menambah route admin baru tanpa guard akan menggagalkan CI sehingga tinjauan keamanan tidak bergantung pada ingatan.

## Risiko Terbuka (ringkas)
1. Rekonsiliasi pembayaran harian belum ada.
2. Pencairan refund belum diuji sandbox E2E.
3. Admin legacy tanpa TOTP masih diizinkan.
4. Mutasi katalog/kategori belum masuk log audit.
5. Tidak ada permission granular per staf.
