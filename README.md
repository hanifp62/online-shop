# ZAFIRA — La Maison de Lumière

ZAFIRA is a Laravel storefront prototype for a luxury atelier. It includes a product catalog, product details, cart, guest and authenticated checkout, concierge flow, responsive storefront UI, and automated feature tests.

## Stack

- Laravel 13
- PHP 8.4+
- Node.js 22 (see `.nvmrc`)
- React 19 + Inertia 3, bundled with Vite
- Blade for initial HTML, fallback, admin shell, email and PDF
- SQLite or MySQL
- PHPUnit / Laravel test runner

## Menjalankan lokal

Untuk checkout dengan konfigurasi dan database lokal yang sudah tersedia:

```sh
composer install --no-interaction --prefer-dist
npm ci
npm run build
APP_URL=http://127.0.0.1:8001 php artisan serve --host=127.0.0.1 --port=8001 --no-reload
```

Buka **http://127.0.0.1:8001**. Profil ini memakai build, bukan Vite HMR, dan mensyaratkan config cache tidak aktif. `--no-reload` menjaga override URL kanonis pada child PHP; restart server jika konfigurasi berubah. Jangan menimpa `.env`, meregenerasi key, atau menjalankan seed pada database existing.

`php artisan serve` tanpa opsi juga bekerja, tetapi port bisa bergeser saat8000sibuk. [Runbook localhost](docs/04-implementation/LOCALHOST_TESTING.md) menjelaskan discovery Inertia, port, canonical URL, cache, browser dan perbedaan dengan harness. [Bukti pengujian native Artisan](docs/05-quality/LOCALHOST_PARITY_2026-10-23.md).

Untuk instalasi baru, siapkan konfigurasi/kredensial secara privat dan database khusus development terlebih dahulu. Jangan commit atau membagikan `.env` dan secret.

## Verification

```sh
node --test
npm run test:react
vendor/bin/pint --test
npm run build
```

Tes PHP/transaksi harus memakai fixture terisolasi, bukan DB lokal yang sedang dipakai pengguna. Ikuti [perintah dan gate CI](docs/04-implementation/CI_CD_RELEASE_RUNBOOK.md#43-php-dengan-guard-fixture-js-dan-python).

Browser evidence scripts live in `scripts/benchmarks/` and require a running local server plus Playwright:

```bash
python scripts/benchmarks/test_theme_lang.py
python scripts/benchmarks/capture_additional.py
```

## Project structure

```text
app/         Application code, controllers, models, services, middleware
database/    Migrations, factories, and seeders
resources/   Blade views, frontend assets, and fonts
routes/      Web routes
scripts/     Repeatable maintenance and verification scripts
tests/       PHP and Python tests
docs/        Product, architecture, UX, and execution evidence
prototype/   Interactive prototype assets
```

## Repository hygiene

Generated build output, local environment files, dependency directories, and temporary audit artifacts are ignored. Historical remediation packets are kept under `docs/archive/remediation-packets/` rather than the repository root.

## License

This project is proprietary project work unless a separate license is provided.
