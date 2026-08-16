# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a Laravel 13 app (repo name `poswebapp` — intended as the base for a point-of-sale web application), built out module by module. Category (`app/Models/Category.php`) is the first domain module and establishes the conventions below; Product (`app/Models/Product.php`) is the second and adds a `belongsTo`/`hasMany` relationship to Category on top of the same conventions — follow their shape for new modules (e.g. Order) rather than inventing a new pattern. Auth is not yet wired up (Sanctum is installed but no routes are protected beyond the default `/user` example).

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

## Module conventions (established by Category, extended by Product)

Each domain module is a standard Laravel resource stack, wired as follows:

- **API**: `Route::apiResource('categories', CategoryController::class)` in `routes/api.php` (prefixed `/api` automatically); one `Route::apiResource(...)` line per module (e.g. `products`). Controllers are plain resource controllers (`index`/`store`/`show`/`update`/`destroy`) with no service layer — validation lives in `FormRequest` classes, response shaping in `JsonResource` classes.
- **Validation**: separate `Store{Model}Request` and `Update{Model}Request` in `app/Http/Requests`. `authorize()` currently always returns `true` (no policies yet). Update requests use `sometimes` + `Rule::unique(...)->ignore($this->route('category'))` so a record can keep its own value on update; store requests use a plain `unique:table,column` rule.
- **Responses**: one `{Model}Resource` (`app/Http/Resources`) per model; `index` optionally filters with `->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))`, sorts via `->orderBy($sort, $direction)` where `$sort`/`$direction` come from `?sort=`/`?direction=asc|desc` validated against a column whitelist (`['name', 'updated_at']`) — an unrecognized `sort` resets *both* sort and direction back to the default (`updated_at`, `desc`), it doesn't keep a stray `direction` from the invalid request — then `->paginate(15)->withQueryString()` — 15 per page is the module's standard (server-side, via Laravel's built-in paginator; the JSON response's `meta`/`links` blocks drive the frontend, there is no separate `?per_page=` override), `store` returns the resource wrapped with `->response()->setStatusCode(Response::HTTP_CREATED)`.
- **Tests**: one `tests/Feature/{Model}Test.php` per module using `RefreshDatabase`, hitting the JSON API directly (`getJson`/`postJson`/`putJson`/`deleteJson`) — covers list, create, validation failures, duplicate-name rejection, show, update (including "update with own existing value" and duplicate-name rejection), and delete.
- **Seeders**: `{Model}Seeder` combines a handful of named, meaningful records (via `firstOrCreate`, so re-seeding is idempotent) with a larger batch of `{Model}::factory()->count(N)->create()` (e.g. 45 for Category, for 50 total) so the paginated list has enough rows to exercise multiple pages including a partial last page. `DatabaseSeeder` calls seeders in FK dependency order (`CategorySeeder` before `ProductSeeder`). When a factory's default definition points a foreign key at `RelatedModel::factory()` (as `ProductFactory` does for `category_id`), bulk-seeding with the plain default spawns one brand-new related row *per* created row instead of reusing the ones already seeded — `ProductSeeder` avoids this by overriding `category_id` with `->sequence(fn () => ['category_id' => fake()->randomElement($categoryIds)])` against the real, already-seeded category IDs.
- **Relationships**: Product's `belongsTo(Category::class)` (and Category's matching `hasMany(Product::class)`) is the reference pattern for any FK relationship. The owning side's resource (`ProductResource`) nests the related record as `'category' => ['id' => ..., 'name' => ...]` rather than just the FK id, and the controller's `index`/`show`/`update` eager-load it (`->with('category')` on the query, `->load('category')` on a single model) to avoid an N+1 query per row. Deleting the "many" side of a relationship that still has dependents must not surface a raw DB constraint error: the FK migration column still declares `restrictOnDelete()` as the real guarantee, but the owning controller's `destroy()` checks for a dependent first and returns a friendly `409` naming it (e.g. `CategoryController::destroy()` checks `$category->products()->first()` before deleting) — the same two-layer pattern (DB constraint as backstop + application-level check for UX) already used for unique `name` validation.
- **Frontend**: each module gets its own set of vanilla-JS entry points registered in `vite.config.js`'s `input` array (e.g. `resources/js/categories.js`, `resources/js/categories-form.js`) — not the shared `app.js`. Pages are split by concern rather than combined into one: a list page (`{module}/index.blade.php`) rendering a `#search-form` (just a `#search` input with `minlength="3"` — no submit button, since debounce covers keystroke search and Enter still submits a single-input form), a table with a clickable sortable column header (e.g. `#sort-name`, toggling asc/desc with a `▲`/`▼` indicator span) + "New" link, and a `#pagination` div, calling the JSON API via fetch with `?page=`/`?search=`/`?sort=`/`?direction=` query params — search fires both on debounced keystroke (~300ms) and on submit (Enter), sharing one `applySearch()` guard that rejects non-empty values under 3 characters (an empty value clears the search); the current search term and sort/direction are retained across page navigation and delete-then-refetch, and changing sort resets to page 1 — and driving Prev/Next buttons + a "Page X of Y" label off the response's `meta` block (edit → link to the edit route, delete → confirm + `DELETE` fetch then re-fetch the current page); and a shared create/edit form page (`{module}/form.blade.php`, routes named `{module}.create` and `{module}.edit`) that Blade-renders existing values when a model instance is passed in and reads the record id off a `data-*-id` attribute on the form to decide `POST` vs `PUT`, redirecting back to the list page on success. When the form has a required FK field (e.g. Product's `category_id`), the `{module}.create`/`{module}.edit` web routes fetch the full related-model list server-side (`Category::orderBy('name')->get()`) and pass it to the view, which Blade-renders as a plain `<select>` (disabled placeholder option, `@selected(...)` on edit) — simple and fine at this scale; a searchable/paginated picker would be the natural upgrade once a module has too many related rows for one dropdown. Name the list route `{module}.list`, not `{module}.index` — `Route::apiResource` in `api.php` already auto-registers `{module}.index` for the JSON index endpoint, and since `web.php` loads before `api.php` (`bootstrap/app.php`), a same-named web route silently loses the name and `route('{module}.index')` resolves to the API URL instead. There is no SPA/router — each page is its own server-rendered view backed by its own JS file.
