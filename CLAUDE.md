# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a Laravel 13 app (repo name `poswebapp` — intended as the base for a point-of-sale web application), built out module by module. Category (`app/Models/Category.php`) is the first domain module and establishes the conventions below — follow its shape for new modules (e.g. Product, Order) rather than inventing a new pattern. Auth is not yet wired up (Sanctum is installed but no routes are protected beyond the default `/user` example).

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

## Module conventions (established by Category)

Each domain module is a standard Laravel resource stack, wired as follows:

- **API**: `Route::apiResource('categories', CategoryController::class)` in `routes/api.php` (prefixed `/api` automatically). Controllers are plain resource controllers (`index`/`store`/`show`/`update`/`destroy`) with no service layer — validation lives in `FormRequest` classes, response shaping in `JsonResource` classes.
- **Validation**: separate `Store{Model}Request` and `Update{Model}Request` in `app/Http/Requests`. `authorize()` currently always returns `true` (no policies yet). Update requests use `sometimes` + `Rule::unique(...)->ignore($this->route('category'))` so a record can keep its own value on update; store requests use a plain `unique:table,column` rule.
- **Responses**: one `{Model}Resource` (`app/Http/Resources`) per model; `index` returns `Resource::collection(Model::latest()->paginate(15))`, `store` returns the resource wrapped with `->response()->setStatusCode(Response::HTTP_CREATED)`.
- **Tests**: one `tests/Feature/{Model}Test.php` per module using `RefreshDatabase`, hitting the JSON API directly (`getJson`/`postJson`/`putJson`/`deleteJson`) — covers list, create, validation failures, duplicate-name rejection, show, update (including "update with own existing value" and duplicate-name rejection), and delete.
- **Frontend**: each module gets its own vanilla-JS entry point (e.g. `resources/js/categories.js`) registered in `vite.config.js`'s `input` array, and its own Blade view (e.g. `resources/views/categories/index.blade.php`) that `@vite`s only `app.css` + its own JS file — not the shared `app.js`. The page is a single Tailwind-styled form + table calling the JSON API directly (fetch), with a `web.php` GET route (named `{module}.index`) that just returns the view. There is no SPA/router — each module is its own server-rendered page.
