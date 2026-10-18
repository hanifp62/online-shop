# Session Hardening (86c14287)

Status: **lulus uji lokal (SQLite in-memory); belum diverifikasi di browser, HTTPS nyata, atau MySQL.**

## Ringkasan perubahan

| Area | File | Perilaku |
|------|------|----------|
| Rotasi sesi login | `AuthController.php:71`, `AdminAuthController.php:27` (sudah ada) | `session()->regenerate()` setelah password benar, terpisah untuk guard `web` dan `admin`. |
| Rotasi sesi TOTP | `AdminTotpController.php:69`, `AdminTotpEnrolController.php:123` (sudah ada) | `regenerate(true)` setelah TOTP lolos. |
| Batas login | `routes/web.php` limiter `login` | 10/menit per akun (email dinormalisasi, lintas IP) **dan** 10/menit per IP (lintas akun). Kunci memuat path sehingga `/login` dan `/admin/login` terpisah. Email kosong/non-string tidak memakai bucket akun bersama. Respons 429 menyebut sisa detik dari `Retry-After`. |
| Cookie aman | `config/session.php` | `secure` bawaan `true` bila `APP_ENV=production`, tetap bisa ditimpa `SESSION_SECURE_COOKIE`. `http_only=true`, `same_site=lax`. |
| Error produksi | `config/app.php` | `debug` dipaksa `false` saat `APP_ENV=production`, walau `APP_DEBUG=true`. |
| Kredensial pihak ketiga | `config/services.php` | Hanya `env(...)`; tidak ada nilai tertanam. |

## Guard dan driver sesi

`config/auth.php`: guard `web` dan `admin` sama-sama `driver=session`. Keduanya memakai satu store dari `config/session.php` (`SESSION_DRIVER`, bawaan `database`). Konsekuensi: satu browser memakai satu cookie sesi untuk dua guard; rotasi ID memengaruhi keduanya.

## Bukti uji

`tests/Feature/SessionHardeningTest.php`, `tests/Feature/LoginAccountLimitTest.php`:

| Syarat | Tes |
|--------|-----|
| (a)(b)(c) ID berubah, terpisah per guard, ID lama tidak valid | `test_customer_and_admin_password_sessions_rotate_and_old_ids_cannot_authenticate` |
| Rotasi setelah TOTP | `AdminTotpTest::test_full_flow_rotates_session_id_twice_and_grants_console` |
| (d) batas per akun lintas IP + sisa detik | `test_login_account_limit_spans_ips_and_normalizes_email`, `LoginAccountLimitTest` |
| (d) batas per IP lintas akun | `test_login_ip_limit_spans_accounts` |
| Email malformed tidak mengunci bucket global | `test_malformed_email_requests_do_not_share_a_global_account_bucket_and_response_cookie_flags_are_set` |
| Cookie Secure/HttpOnly/SameSite pada respons | tes yang sama (atribut cookie sesi aktual) |
| (e) error produksi tanpa jejak teknis | `test_production_error_response_hides_exception_details` |
| Default config produksi | `test_production_defaults_hide_debug_and_secure_session_cookie` |

Hasil (2026-10-18, `DB_CONNECTION=sqlite DB_DATABASE=:memory:`):

- Fokus: EXIT=0, 6 passed (85 assertions).
- Mutasi: limiter akun 10→100 → tes akun **merah** (EXIT=1); dipulihkan, md5 identik, hijau (EXIT=0).
- Full suite: EXIT=0, 254 passed, 4 skipped, 1874 assertions.

## Celah tersisa

- Belum diuji di browser nyata, di balik HTTPS/proxy, atau dengan MySQL.
- `secure` hanya aktif otomatis bila `APP_ENV=production`; staging HTTPS perlu `SESSION_SECURE_COOKIE=true`.
- Security-reviewer otomatis gagal (model subagent deprecated); hanya code-reviewer yang berjalan.
