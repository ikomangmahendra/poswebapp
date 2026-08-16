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
- **Responses**: one `{Model}Resource` (`app/Http/Resources`) per model; `index` orders by `latest('updated_at')`, optionally filters with `->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))`, then `->paginate(15)->withQueryString()` — 15 per page is the module's standard (server-side, via Laravel's built-in paginator; the JSON response's `meta`/`links` blocks drive the frontend, there is no separate `?per_page=` override), `store` returns the resource wrapped with `->response()->setStatusCode(Response::HTTP_CREATED)`.
- **Tests**: one `tests/Feature/{Model}Test.php` per module using `RefreshDatabase`, hitting the JSON API directly (`getJson`/`postJson`/`putJson`/`deleteJson`) — covers list, create, validation failures, duplicate-name rejection, show, update (including "update with own existing value" and duplicate-name rejection), and delete.
- **Seeders**: `{Model}Seeder` combines a handful of named, meaningful records (via `firstOrCreate`, so re-seeding is idempotent) with a larger batch of `{Model}::factory()->count(N)->create()` (e.g. 45 for Category, for 50 total) so the paginated list has enough rows to exercise multiple pages including a partial last page.
- **Frontend**: each module gets its own set of vanilla-JS entry points registered in `vite.config.js`'s `input` array (e.g. `resources/js/categories.js`, `resources/js/categories-form.js`) — not the shared `app.js`. Pages are split by concern rather than combined into one: a list page (`{module}/index.blade.php`) rendering a `#search-form` (just a `#search` input with `minlength="3"` — no submit button, since debounce covers keystroke search and Enter still submits a single-input form), a table + "New" link, and a `#pagination` div, calling the JSON API via fetch with `?page=`/`?search=` query params — search fires both on debounced keystroke (~300ms) and on submit (Enter), sharing one `applySearch()` guard that rejects non-empty values under 3 characters (an empty value clears the search); the current search term is retained across page navigation and delete-then-refetch — and driving Prev/Next buttons + a "Page X of Y" label off the response's `meta` block (edit → link to the edit route, delete → confirm + `DELETE` fetch then re-fetch the current page); and a shared create/edit form page (`{module}/form.blade.php`, routes named `{module}.create` and `{module}.edit`) that Blade-renders existing values when a model instance is passed in and reads the record id off a `data-*-id` attribute on the form to decide `POST` vs `PUT`, redirecting back to the list page on success. Name the list route `{module}.list`, not `{module}.index` — `Route::apiResource` in `api.php` already auto-registers `{module}.index` for the JSON index endpoint, and since `web.php` loads before `api.php` (`bootstrap/app.php`), a same-named web route silently loses the name and `route('{module}.index')` resolves to the API URL instead. There is no SPA/router — each page is its own server-rendered view backed by its own JS file.
