# Webhook Pembayaran Midtrans Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Order hanya menjadi `paid` dari notifikasi Midtrans bersignature valid yang dikonfirmasi Status API, dengan ledger `payments` idempoten.

**Architecture:** Controller tipis → `PaymentWebhookService` (verifikasi signature, panggil Status API via `Http`, validasi integritas, tulis ledger + transisi dalam satu transaksi dengan `lockForUpdate`). Transisi `paid` lewat `CheckoutService::markPaid()` yang berbagi inti dengan `transition()`.

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit, `Http::fake()`. Tanpa dependency baru.

**Spec:** `docs/superpowers/specs/2026-10-20-midtrans-payment-webhook-design.md`

## Global Constraints

- Tanpa paket Composer baru.
- Signature: `hash('sha512', order_id . status_code . gross_amount . server_key)`, dibanding `hash_equals`, atas string mentah notifikasi.
- Status sukses: `settlement`, atau `capture` + `fraud_status = accept` — dari respons Status API.
- Nominal: regex `^\d+(\.00)?$` → int rupiah; tanpa float.
- Unique ledger `(provider, provider_transaction_id, event_status)`.
- Server key kosong ⇒ 503. Status API gagal ⇒ 503. Signature salah ⇒ 403. Payload/integritas tidak valid ⇒ 422.
- Log tidak memuat `signature_key`, server key, atau PII.
- `Order::MANUAL_BLOCKED` tetap memblokir `paid` dari admin.

## Review Focus

- `gross_amount` `"250000.00"` vs `"250000"` vs `"250000.50"` — dua pertama cocok, ketiga mismatch.
- Body notifikasi `settlement` tetapi Status API `pending` ⇒ tidak lunas.
- Status API mengembalikan `order_id`/`transaction_id` lain ⇒ 422, tanpa penulisan.
- Notifikasi duplikat ⇒ satu baris ledger dan satu entri history.
- Order sudah `cancelled` saat pembayaran sukses tiba ⇒ ledger `order_not_payable`, order tetap `cancelled`.

---

### Task 1: Ledger `payments` + `CheckoutService::markPaid`

**Files:**
- Create: `database/migrations/2026_10_20_000003_create_payments_table.php`
- Create: `app/Models/Payment.php`
- Modify: `app/Models/Order.php` (relasi `payments()`)
- Modify: `app/Services/CheckoutService.php` (`transition()` → inti privat `applyTransition()`; tambah `markPaid()`)
- Test: `tests/Feature/PaymentWebhookTest.php` (dibuat di Task 2; Task 1 diuji lewat suite yang ada)

**Interfaces:**
- Produces: `Payment` (fillable: `order_id, provider, provider_transaction_id, provider_order_id, event_status, amount, outcome, payload`; cast `payload` array, `amount` int; update/delete melempar `LogicException` seperti `OrderStatusHistory`). `Order::payments(): HasMany`. `CheckoutService::markPaid(Order $order): bool` — dipanggil di dalam transaksi pemanggil, CAS `pending→paid`, history `admin_id=null`, reservasi `committed`; return `false` jika CAS gagal.

- [ ] Migrasi: kolom sesuai spec, `order_id` FK nullable `nullOnDelete`, `amount` unsignedBigInteger, `payload` json nullable, unique tiga kolom.
- [ ] Ekstrak isi `DB::transaction` di `transition()` ke `private function applyTransition(Order $order, string $status, ?int $adminId): bool`; `transition()` tetap abort 409 bila false. `markPaid()` memanggil `applyTransition($order, 'paid', null)` tanpa membuka transaksi baru.
- [ ] Run: `php -d memory_limit=1G vendor/bin/phpunit` → semua hijau (regresi transition/history/reservasi).

### Task 2: `PaymentWebhookService` + endpoint

**Files:**
- Create: `app/Services/PaymentWebhookService.php`
- Create: `app/Http/Controllers/MidtransWebhookController.php`
- Modify: `routes/web.php`, `bootstrap/app.php`, `config/services.php`, `.env.example`
- Test: `tests/Feature/PaymentWebhookTest.php`

**Interfaces:**
- Consumes: `Payment`, `CheckoutService::markPaid`.
- Produces: `PaymentWebhookService::handle(array $notification): int` (HTTP status). Config `services.midtrans.server_key`, `services.midtrans.is_production`.

- [ ] Tulis test gagal di `PaymentWebhookTest` (helper: buat order pending via HTTP seperti `InventoryReservationTest`; `config(['services.midtrans.server_key' => 'test-key'])`; `notify(array $overrides)` menghitung signature; `Http::fake(['api.sandbox.midtrans.com/v2/*' => Http::response([...])])`):
  - `test_invalid_signature_is_rejected` → 403, `Payment::count()===0`, order `pending`.
  - `test_settlement_marks_order_paid` → 200, order `paid`, ledger outcome `paid`, history terakhir `pending→paid` `admin_id` null, reservasi `committed`.
  - `test_duplicate_notification_is_idempotent` → dua kali 200, 1 ledger, 1 history `paid`.
  - `test_status_api_overrides_notification_body` → Status API `pending` ⇒ order `pending`, outcome `recorded`.
  - `test_status_api_mismatched_transaction_is_rejected` → 422, tanpa ledger.
  - `test_amount_mismatch_does_not_mark_paid` → Status API `gross_amount` `"1.00"` ⇒ outcome `amount_mismatch`.
  - `test_decimal_gross_amount_matches` → `"{$total}.00"` ⇒ `paid`.
  - `test_status_api_failure_returns_503` → `Http::response([], 500)` ⇒ 503, tanpa ledger.
  - `test_cancelled_order_records_not_payable` → order dibatalkan dulu ⇒ outcome `order_not_payable`, status tetap `cancelled`.
  - `test_unknown_order_is_recorded` → `order_id` tidak ada ⇒ 200, outcome `unknown_order`, `order_id` null.
  - `test_missing_server_key_returns_503`.
  - `test_admin_still_cannot_set_paid_manually` → `transition($order,'paid')` melempar `ValidationException`.
- [ ] Run test → FAIL (route tidak ada).
- [ ] Implementasi service sesuai urutan spec; duplikat dicek di dalam transaksi setelah `lockForUpdate`; `UniqueConstraintViolationException` ditangkap ⇒ 200, galat lain dilempar. Log `alert` untuk `order_not_payable`, `warning` untuk `amount_mismatch`/`unknown_order`.
- [ ] Route `Route::post('/webhooks/midtrans', MidtransWebhookController::class)->middleware('throttle:60,1')->name('webhooks.midtrans')`; `$middleware->validateCsrfTokens(except: ['webhooks/midtrans'])`.
- [ ] Run: `php artisan test tests/Feature/PaymentWebhookTest.php` → PASS; lalu full suite `php -d memory_limit=1G vendor/bin/phpunit` → hijau.
