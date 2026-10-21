# ZAFIRA — La Maison de Lumière

ZAFIRA is a Laravel storefront prototype for a luxury atelier. It includes a product catalog, product details, cart, guest and authenticated checkout, concierge flow, responsive storefront UI, and automated feature tests.

## Stack

- Laravel 13
- PHP 8.4+
- Node.js 22 (see `.nvmrc`)
- Vite
- Blade and vanilla JavaScript
- SQLite or MySQL
- PHPUnit / Laravel test runner

## Local setup

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000` after starting the server.

Do not commit `.env` or other credentials. Use `.env.example` as the configuration reference.

## Verification

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

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
