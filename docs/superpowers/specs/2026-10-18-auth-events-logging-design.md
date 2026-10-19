# Pencatatan Peristiwa Autentikasi Admin (7476e5f8) — Design Spec

**Tanggal:** 2026-10-18  
**Fitur:** `2acf1964` (Masuk Aman untuk Admin — Task Terakhir)  
**Tujuan:** Merekam seluruh peristiwa autentikasi admin ke tabel `auth_events` secara tahan banting (fail-open), aman dari kebocoran rahasia, serta dapat ditinjau secara berpaginasi hanya oleh operator terautentikasi (admin + EnsureAdminTotp).

---

## 1. Arsitektur & Penyimpanan Data

### 1.1 Tabel `auth_events`
Migrasi baru khusus: `database/migrations/2026_10_18_000001_create_auth_events_table.php` (tanpa menyentuh tabel `admins`).

```sql
Schema::create('auth_events', function (Blueprint $table) {
    $table->id();
    $table->timestamp('occurred_at')->useCurrent()->index();
    $table->string('actor_type')->nullable(); // 'admin' | 'web' | null
    $table->unsignedBigInteger('actor_id')->nullable()->index();
    $table->string('event', 64)->index();
    $table->string('outcome', 16)->index(); // 'success' | 'failure'
    $table->string('ip', 45)->nullable()->index();
    $table->json('context')->nullable();
});
```

### 1.2 Enum / Nilai Konstanta Peristiwa
Class `App\Enums\AuthEventName` (atau interface konstanta):
1. `ENROL_CONFIRMED` = `'ENROL_CONFIRMED'`
2. `LOGIN_SUCCESS` = `'LOGIN_SUCCESS'`
3. `LOGIN_FAILURE` = `'LOGIN_FAILURE'`
4. `TOTP_FAILURE` = `'TOTP_FAILURE'`
5. `RECOVERY_CODE_USED` = `'RECOVERY_CODE_USED'`
6. `LOCKOUT_TRIGGERED` = `'LOCKOUT_TRIGGERED'`
7. `SESSION_ROTATED` = `'SESSION_ROTATED'`
8. `PASSWORD_RESET_TOKEN_ISSUED` = `'PASSWORD_RESET_TOKEN_ISSUED'`

*Invariant:* `RECOVERY_CODE_USED !== PASSWORD_RESET_TOKEN_ISSUED`.

---

## 2. Service Perekam: `AuthEventRecorder`

- Namespace: `App\Services\AuthEventRecorder`
- Metode publik:
  - `record(string $event, string $outcome, ?string $actorType, ?int $actorId, ?string $ip, array $context = []): ?AuthEvent`
  - Helper terarah: `recordLoginSuccess(...)`, `recordLoginFailure(...)`, `recordTotpFailure(...)`, `recordRecoveryUsed(...)`, `recordLockout(...)`, `recordSessionRotated(...)`, `recordEnrolConfirmed(...)`, `recordPasswordResetTokenIssued(...)`.
- **Fail-open Contract:**
  - Segala kegagalan DB (PDOException, deadlock, tabel hilang) dibungkus blok `try/catch (\Throwable $e)`.
  - Perekam memanggil `Log::critical('Auth event recorder failed', ['event' => $event, 'exception' => $e::class])`. Pesan exception TIDAK dicatat karena pesan PDO dapat memuat nilai binding.
  - Error tidak di-rethrow; alur autentikasi pemanggil **tetap lanjut** dan tidak boleh gagal karena perekam.
- **Kerahasiaan Mutlak (Context Sanitizer / Allowlist):**
  - Hanya kunci context yang diizinkan (allowlist): `guard`, `reason`, `scope`. Header `user_agent` mentah tidak disimpan karena dapat membawa token atau rahasia.
  - Cakupan: autentikasi admin. Login pelanggan di luar cakupan tugas ini.
  - Kunci terlarang otomatis dibuang atau ditolak: `secret`, `totp_secret`, `code`, `recovery_code`, `password`, `token`, `session_id`, `remember_token`, hash, dsb.
  - Parameter kode pada `consumeRecoveryCode` diberi atribut `#[\SensitiveParameter]` untuk melindungi stack trace.

---

## 3. Titik Pemicu dalam Kode

1. **`AdminAuthController`**:
   - `LOGIN_SUCCESS` dicatat tepat sekali, saat akses konsol diberikan: setelah password untuk admin tanpa flag TOTP, atau setelah TOTP/recovery lolos untuk admin ber-flag. Tahap password admin ber-flag hanya mencatat `SESSION_ROTATED`.
   - Gagal password: catat `LOGIN_FAILURE` (outcome=failure, reason='invalid_credentials').
   - `SESSION_ROTATED`: dicatat saat regenerasi sesi terjadi tanpa mencatat nilai ID sesi.
2. **`AdminTotpController`**:
   - `verify` gagal: `TOTP_FAILURE` (reason='invalid_code' | 'replayed' | 'broken_secret').
   - `recovery` sukses: `RECOVERY_CODE_USED` (outcome=success, enum terpisah).
   - Sukses verifikasi TOTP: `LOGIN_SUCCESS` (outcome=success, actor_type='admin').
3. **`AdminTotpEnrolController`**:
   - Konfirmasi kode enrol pertama berhasil: `ENROL_CONFIRMED`.
4. **RateLimiter / Lockout**:
   - Limiter TOTP (controller): catat `LOCKOUT_TRIGGERED` ketika `hit()` membuat `tooManyAttempts()` berubah menjadi true — tepat sekali per episode.
   - Limiter `login` (routes/web.php callback 429): catat dengan guard `Cache::add('auth-lockout:'.$key, 1, $retryAfter)` sehingga 429 berulang dalam satu episode tidak menambah baris. Batas limiter tidak diubah.
5. **`App\Services\PasswordResetTokenIssuer::issue(CanResetPassword $user): string`** (wrapper broker, pilihan pengguna):
   - Memanggil `Password::broker('users')->createToken($user)` lalu mencatat `PASSWORD_RESET_TOKEN_ISSUED` (actor_type='web') tanpa nilai token. Laravel tidak menyediakan event untuk `createToken`, jadi wrapper adalah satu-satunya titik catat. Repo belum punya rute forgot-password; wrapper tidak membuat rute baru.

---

## 4. Antarmuka Peninjauan Operator

- Rute: `GET /admin/auth-events`
- Middleware: `['auth:admin', EnsureAdminTotp::class]`
- Controller: `AdminAuthEventController@index`
- Tampilan: view `admin.auth-events` (tabel berpaginasi `paginate(25)` berurutan terbaru `occurred_at DESC`, filter sederhana by event & outcome).
- Proteksi: guard `web` (pelanggan biasa) atau admin tanpa TOTP akan diarahkan / ditolak (302 ke `/admin/login` atau `/admin/totp`, atau 403). Tidak boleh menampilkan 200/daftar kosong.

---

## 5. Retensi & Pembersihan: `auth-events:prune`

- Command: `php artisan auth-events:prune --days=30`
- Logika: `AuthEvent::where('occurred_at', '<', now()->subDays($days))->delete()`.
- Validasi integer minimal 1 hari, default 30 hari.

---

## 6. Verifikasi & Pengujian Wajib

1. **Uji Positif**:
   - Login sukses admin -> 1 record `LOGIN_SUCCESS` lengkap IP, waktu, ID.
   - Gagal login -> `LOGIN_FAILURE`.
   - TOTP salah -> `TOTP_FAILURE` (bukan login failure).
   - Recovery code -> `RECOVERY_CODE_USED !== PASSWORD_RESET_TOKEN_ISSUED`.
   - Lockout -> `LOCKOUT_TRIGGERED` 1 record per penguncian.
   - Sesi dirotasi -> `SESSION_ROTATED` tanpa nilai session ID.
2. **Uji Negatif**:
   - **Pemindai Rahasia Terbukti Bekerja (Tanam -> Deteksi -> Bersihkan)**.
   - Sisir penuh alur enrol + login + recovery + gagal: tabel `auth_events` dan log bersih dari secret, kode 6 digit, recovery code, password, token reset.
   - Hak akses rute `/admin/auth-events` terisolasi dari pelanggan `web`.
   - Exception `Google2FAException` pada verifikasi tidak membocorkan kode dalam message maupun trace.
   - Perekam melempar exception sengaja -> login tetap berhasil (assert status 302/200 & sesi ada), `Log::critical` tercatat.
   - Retensi menghapus data lama dan mempertahankan data baru.
