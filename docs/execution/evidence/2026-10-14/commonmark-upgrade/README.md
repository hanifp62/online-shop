# CommonMark 2.10.3 Upgrade Evidence

Tanggal eksekusi: 2026-10-14 UTC  
Scope: upgrade terisolasi `league/commonmark` dari `2.10.0` ke `2.10.3`.

## Hasil

- **Berhasil, belum di-commit dan belum di-push.**
- `league/commonmark`: `2.10.0` → `2.10.3`.
- `composer.lock` hanya mengubah paket CommonMark dan `content-hash` terhadap baseline kandidat.
- `composer.json` tidak berubah.
- `vendor/composer/installed.json` cocok dengan lock setelah install.
- Guarded Composer audit selesai dengan return code `0` dan `advisories: []`.
- Targeted Markdown mail tests: **25 passed, 138 assertions**.
- Full guarded test suite: **158 passed, 1253 assertions**, duration `102.27s`.
- `bootstrap/cache` hashes tetap sama setelah install.
- Exact dompdf 3.1.6 five-package closure tetap dipertahankan.
- Temporary root dipertahankan, tidak dibersihkan:
  `/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode/commonmark-upgrade-20261014`

## Perintah penting

```text
composer update league/commonmark --no-install --no-interaction --no-ansi --prefer-dist
APP_ENV=manual composer install --no-interaction --no-ansi --prefer-dist
./scripts/amadis/launch.sh composer-audit --locked --format=json
./scripts/amadis/launch.sh test
```

Install dan test memakai guard Amadis. Tidak memakai production database. Full suite memakai isolated sqlite `:memory:` melalui runner guard.

## Hash terverifikasi

| Item | SHA-256 |
|---|---|
| `composer.json` | `11396e0b0c5fc144d23bc3ed5e1fdcaba249649f7b23fb49025874934f9e4954` |
| `composer.lock` setelah upgrade | `264a34fe618b23a99deaff6648c7d71595057755e74069d8830a47ed14d651b6` |
| `vendor/composer/installed.json` setelah install | `e97bc68ae6c088d4de4abd517fc3ed990364c745ab5238fff31643e9a68875e0` |

## Catatan test manual

Percobaan awal `php artisan test --env=manual` menghasilkan 22 kegagalan HTTP 419 dari 25 test karena environment manual menonaktifkan bypass CSRF testing. Ini artefak environment, bukan hasil validasi upgrade. Test diulang melalui runner Amadis dan lulus seluruhnya.

## Gap tersisa

- Test mail yang ada memeriksa queued mail, bukan render Blade Markdown secara langsung.
- Tidak ada commit atau push yang dilakukan.
- Working tree berisi banyak perubahan dan untracked files preexisting di luar scope; tidak disentuh.
