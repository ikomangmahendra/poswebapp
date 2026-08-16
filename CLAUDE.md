# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a fresh, unmodified Laravel 13 skeleton (repo name `poswebapp` — intended as the base for a point-of-sale web application). No application-specific models, controllers, or routes exist yet beyond Laravel's defaults (`App\Models\User`, the `welcome` view). Treat architectural decisions (auth approach, POS domain models, admin/UI structure) as not yet made — expect to establish these conventions as the app is built rather than infer them from existing patterns.

## Stack

- PHP ^8.3, Laravel ^13.17, Laravel Sanctum ^4.0 (API token auth, installed but unused so far)
- SQLite by default (`database/database.sqlite`), config supports mysql/mariadb/pgsql/sqlsrv via `.env`
- Frontend build: Vite + Tailwind CSS v4 (`resources/css/app.css`, `resources/js/app.js`), no JS framework (React/Vue) installed
- Session driver: database; Queue driver: database; Cache: database

## Commands

Install dependencies:
```bash
composer install
npm install
```

Run the full local dev environment (server + queue listener + log tail + vite, via `concurrently`):
```bash
composer run dev
```

Run tests:
```bash
composer run test
# or directly:
php artisan test
php artisan test --filter=test_name          # single test
php artisan test tests/Feature/ExampleTest.php  # single file
```

Lint / format (Pint):
```bash
vendor/bin/pint          # fix
vendor/bin/pint --test   # check only, no changes
```

Build frontend assets:
```bash
npm run dev     # vite dev server
npm run build   # production build
```

Migrations:
```bash
php artisan migrate
php artisan migrate:fresh --seed
```

## Architecture notes

- Routing is registered in `bootstrap/app.php` via `Application::configure()->withRouting(...)` (Laravel 11+ style, not the old `Http/Kernel.php`): `routes/web.php`, `routes/api.php`, `routes/console.php`, plus a health-check at `/up`.
- Middleware and exception handling are configured inline in `bootstrap/app.php` (`withMiddleware`, `withExceptions`) rather than in separate Kernel/Handler classes.
- API requests get JSON error responses automatically (`shouldRenderJsonWhen` checks `api/*` paths or `expectsJson()`).
- Test environment (`phpunit.xml`) forces SQLite in-memory DB, array cache/session, sync queue, and disables Pulse/Telescope/Nightwatch regardless of `.env`.
