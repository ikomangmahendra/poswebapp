# Workshop 01 — Learn Laravel by Building a POS: Category API

## Objective

By the end of this workshop you will understand how a Laravel feature is built end-to-end,
using a **Category** (`name`, `description`) module as the study case for a future
Point-of-Sale (POS) application. You will learn:

- How Laravel's `artisan make:model` scaffolding generates a full feature skeleton
- Migrations (schema versioning)
- Eloquent models and mass assignment (`$fillable`)
- Form Request classes for validation
- API Resources for shaping JSON responses
- Resource controllers and RESTful routing (`Route::apiResource`)
- Seeders for demo/dummy data
- Feature tests that exercise the API the way a real client would
- Manually verifying an API with `curl`

Each step below states **what we did**, **why**, and **how to validate it yourself** —
run the validation commands after reading each step to confirm your environment matches.

## Prerequisites

- PHP 8.3+, Composer, Node/npm installed
- A MariaDB (or MySQL) server reachable — this project's `.env` points at:
  ```
  DB_CONNECTION=mariadb
  DB_HOST=127.0.0.1
  DB_PORT=3306
  DB_DATABASE=poswebapp
  DB_USERNAME=root
  DB_PASSWORD=yourpassword
  ```
- Dependencies installed: `composer install && npm install`

---

## Step 1 — Point the app at MariaDB

**What we did:** updated `.env` from the default `sqlite` connection to `mariadb`, and created
the `poswebapp` database on the local server.

```
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=poswebapp
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

**Why:** the workshop targets a real relational database rather than the SQLite file Laravel
ships with by default, since that's closer to a production POS setup.

**Validate it:**
```bash
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' -e "SHOW DATABASES LIKE 'poswebapp';"
php artisan migrate:status
```
You should see the `poswebapp` database listed, and `migrate:status` should connect without
a "could not find driver" or "connection refused" error.

---

## Step 2 — Scaffold the Category feature with one Artisan command

**What we did:** generated the model plus every supporting class in a single command:

```bash
php artisan make:model Category -mfc --api -R
php artisan make:resource CategoryResource
php artisan make:seeder CategorySeeder
```

**Why:** Laravel's generators create consistent, idiomatic file skeletons so you spend time on
business logic, not boilerplate. The flags mean:

| Flag | Generates |
|---|---|
| `-m` | migration (`database/migrations/..._create_categories_table.php`) |
| `-f` | factory (`database/factories/CategoryFactory.php`) |
| `-c --api` | an **API resource controller** (`index/store/show/update/destroy`, no `create`/`edit` views) |
| `-R` | Form Request classes (`StoreCategoryRequest`, `UpdateCategoryRequest`) wired into the controller |

**Validate it:**
```bash
ls app/Models/Category.php \
   app/Http/Controllers/CategoryController.php \
   app/Http/Requests/StoreCategoryRequest.php \
   app/Http/Requests/UpdateCategoryRequest.php \
   app/Http/Resources/CategoryResource.php \
   database/factories/CategoryFactory.php \
   database/seeders/CategorySeeder.php
```
All paths should exist (no "No such file" errors).

---

## Step 3 — Define the schema in the migration

**What we did:** edited the generated migration to add the two columns the study case needs:

```php
// database/migrations/2026_08_16_044650_create_categories_table.php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->timestamps();
});
```

**Why:** migrations are version-controlled, executable schema definitions — every developer
(and CI) runs the same `php artisan migrate` and ends up with an identical database structure.
`description` is `nullable()` because not every category needs one.

**Validate it:**
```bash
php artisan migrate
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "DESCRIBE categories;"
```
Expected columns: `id`, `name`, `description`, `created_at`, `updated_at`.

---

## Step 4 — Allow mass assignment on the model

**What we did:**
```php
// app/Models/Category.php
protected $fillable = [
    'name',
    'description',
];
```

**Why:** Eloquent blocks mass assignment (`Category::create($array)`) by default as a security
guard against attackers injecting unexpected fields (e.g. `is_admin`). Explicitly listing
`$fillable` says "these fields — and only these — may be set this way."

**Validate it:**
```bash
php artisan tinker --execute="echo App\Models\Category::create(['name' => 'Tinker Test'])->id;"
```
Should print a new numeric ID with no `MassAssignmentException`. Clean it up afterward:
```bash
php artisan tinker --execute="App\Models\Category::where('name', 'Tinker Test')->delete()"
```

---

## Step 5 — Validate input with Form Requests

**What we did:**
```php
// app/Http/Requests/StoreCategoryRequest.php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
    ];
}
```
```php
// app/Http/Requests/UpdateCategoryRequest.php
public function rules(): array
{
    return [
        'name' => ['sometimes', 'required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
    ];
}
```
Both also set `authorize(): true` (generated as `false` by default, which would reject every
request with a 403 until real authorization/policies are introduced later in the workshop).

**Why:** Form Requests keep validation logic out of the controller. `sometimes` on update lets a
client send a partial payload (e.g. only `description`) without being forced to resend `name`.

**Validate it:** covered by the automated test in Step 9, or manually:
```bash
curl -s -X POST http://127.0.0.1:8000/api/categories \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"description":"no name given"}'
```
Expected: HTTP 422 with `{"errors":{"name":["The name field is required."]}}`.

---

## Step 6 — Shape the JSON response with an API Resource

**What we did:**
```php
// app/Http/Resources/CategoryResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'description' => $this->description,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

**Why:** API Resources decouple your JSON contract from your database columns — you can rename
a DB column, add a computed field, or hide an internal one, without breaking API consumers.

**Validate it:** see the `curl` output in Step 10 — the JSON keys should match exactly this list.

---

## Step 7 — Wire up the controller

**What we did:** implemented all five REST actions in `app/Http/Controllers/CategoryController.php`:

```php
public function index()
{
    return CategoryResource::collection(Category::latest()->paginate(15));
}

public function store(StoreCategoryRequest $request)
{
    $category = Category::create($request->validated());

    return CategoryResource::make($category)->response()->setStatusCode(201);
}

public function show(Category $category)
{
    return CategoryResource::make($category);
}

public function update(UpdateCategoryRequest $request, Category $category)
{
    $category->update($request->validated());

    return CategoryResource::make($category);
}

public function destroy(Category $category)
{
    $category->delete();

    return response()->noContent();
}
```

**Why:** notice `show`, `update`, and `destroy` type-hint `Category $category` directly —
this is **route-model binding**: Laravel resolves the `{category}` URL segment into a model
instance for you (and auto-returns a 404 if the ID doesn't exist), so there's no manual
`Category::findOrFail()` needed.

**Validate it:**
```bash
php artisan route:list --path=categories
```
You should see 5 routes: `index`, `store`, `show`, `update`, `destroy` all pointing at
`CategoryController`.

---

## Step 8 — Expose it as a RESTful API

**What we did:**
```php
// routes/api.php
Route::apiResource('categories', CategoryController::class);
```

**Why:** `apiResource` is shorthand for registering all 5 CRUD routes (no `create`/`edit` forms,
since this is a JSON API, not server-rendered HTML) with conventional names and URIs.
Laravel automatically prefixes everything in `routes/api.php` with `/api`.

**Validate it:** same `route:list` command as Step 7 — confirm the URIs are
`api/categories` and `api/categories/{category}`.

---

## Step 9 — Seed 5 dummy categories

**What we did:**
```php
// database/seeders/CategorySeeder.php
$categories = [
    ['name' => 'Beverages', 'description' => 'Soft drinks, juices, and bottled water'],
    ['name' => 'Snacks', 'description' => 'Chips, crackers, and other packaged snacks'],
    ['name' => 'Groceries', 'description' => 'Everyday staples like rice, oil, and canned goods'],
    ['name' => 'Electronics', 'description' => 'Small electronics and accessories'],
    ['name' => 'Household', 'description' => 'Cleaning supplies and home essentials'],
];

foreach ($categories as $category) {
    Category::query()->firstOrCreate(['name' => $category['name']], $category);
}
```
and registered it in `database/seeders/DatabaseSeeder.php` via `$this->call(CategorySeeder::class);`.

**Why:** seeders give every developer (and CI) the same baseline demo data with one command,
instead of manually clicking/POSTing data into a fresh database. `firstOrCreate` makes the
seeder **idempotent** — running it twice won't create duplicates.

**Validate it:**
```bash
php artisan db:seed --class=CategorySeeder
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "SELECT id, name FROM categories;"
```
Expected: exactly 5 rows (Beverages, Snacks, Groceries, Electronics, Household), regardless of
how many times you re-run the seeder.

---

## Step 10 — Automated tests

**What we did:** `tests/Feature/CategoryTest.php` exercises the API the way a real HTTP client
would, using `RefreshDatabase` to reset the schema between tests:

- `test_it_lists_categories` — `GET /api/categories` returns the expected count
- `test_it_creates_a_category` — `POST /api/categories` persists a row and returns 201
- `test_it_requires_a_name_to_create_a_category` — missing `name` returns 422
- `test_it_shows_a_category` — `GET /api/categories/{id}` returns the right record
- `test_it_updates_a_category` — `PUT /api/categories/{id}` persists the change
- `test_it_deletes_a_category` — `DELETE /api/categories/{id}` returns 204 and removes the row

**Why:** feature tests document the contract of your API and catch regressions automatically —
no need to manually `curl` every endpoint after every change.

**Validate it:**
```bash
php artisan test --filter=CategoryTest
```
Expected: `Tests: 6 passed`.

---

## Step 11 — Manual verification with curl

Start the app (`php artisan serve` or `composer run dev`), then:

```bash
# List
curl -s http://127.0.0.1:8000/api/categories -H "Accept: application/json" | jq

# Show one
curl -s http://127.0.0.1:8000/api/categories/1 -H "Accept: application/json" | jq

# Create
curl -s -X POST http://127.0.0.1:8000/api/categories \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Frozen Foods","description":"Ice cream and frozen meals"}' | jq

# Update (partial)
curl -s -X PUT http://127.0.0.1:8000/api/categories/1 \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Beverages & Drinks"}' | jq

# Delete
curl -s -X DELETE http://127.0.0.1:8000/api/categories/1 \
  -H "Accept: application/json" -o /dev/null -w "%{http_code}\n"

# Validation error
curl -s -X POST http://127.0.0.1:8000/api/categories \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"description":"no name given"}' | jq
```

**Validate it:** each response's JSON keys should match the `CategoryResource` shape from
Step 6 (`id`, `name`, `description`, `created_at`, `updated_at`); the last command should
return HTTP 422 with a `name` error.

---

## Step 12 — A simple UI on top of the API

**What we did:** built a plain Blade page that talks to the JSON API with vanilla JS —
no frontend framework needed.

- `routes/web.php` — added `GET /categories` (named `categories.index`) returning a view
- `resources/views/categories/index.blade.php` — a create/edit form plus a table, styled with
  the Tailwind classes already configured in this project
- `resources/js/categories.js` — uses `fetch()` against `/api/categories` for list, create,
  update, and delete, and swaps the form between "Add" and "Edit" mode
- `vite.config.js` — added `resources/js/categories.js` as a second build entry so it's
  compiled alongside `app.js`

```js
// resources/js/categories.js (excerpt)
async function fetchCategories() {
    const response = await fetch('/api/categories', { headers: { Accept: 'application/json' } });
    const { data } = await response.json();
    renderTable(data);
}
```

**Why:** this is the same decoupled-frontend pattern used by real SPAs — the UI is just
another API consumer, exactly like the `curl` commands in Step 11. Two details worth noting:

- Rows are built with `document.createElement` + `textContent`, not `innerHTML` with
  interpolated strings — category names/descriptions come from user input, so rendering them
  via `innerHTML` would be a stored-XSS hole (a category named `<img src=x onerror=...>` would
  execute). `textContent` always renders as plain text.
- The routes in `routes/api.php` have no `auth` middleware and `routes/web.php` doesn't put
  this page behind the `web` session/CSRF stack in a way that blocks the fetch calls, so no
  CSRF token handling was needed yet — that will matter once auth is introduced.

**Validate it:**
```bash
npm run build          # or `npm run dev` / `composer run dev` for hot reload
php artisan serve
```
Open `http://127.0.0.1:8000/categories` in a browser. You should see the 5 seeded categories
in a table. Try:
- Typing a name + description and clicking **Save** → new row appears, form clears
- Clicking **Edit** on a row → form switches to "Edit Category" and pre-fills the fields
- Changing the name and saving → the row updates in place
- Clicking **Delete** → a confirm dialog, then the row disappears

Open the browser's DevTools console — there should be no errors during any of the above.

---

## Step 13 — Enforce unique category names

**What we did:** added a database-level unique constraint plus matching validation, since the
original migration and Form Requests allowed two categories to share the same name.

```php
// database/migrations/2026_08_16_050914_add_unique_index_to_categories_name_column.php
Schema::table('categories', function (Blueprint $table) {
    $table->unique('name');
});
```

```php
// app/Http/Requests/StoreCategoryRequest.php
'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
```

```php
// app/Http/Requests/UpdateCategoryRequest.php
'name' => [
    'sometimes', 'required', 'string', 'max:255',
    Rule::unique('categories', 'name')->ignore($this->route('category')),
],
```

**Why:** two layers, each doing a different job:

- The **unique index** is the real guarantee — it protects the data even if some other code
  path (a seeder, a queued job, direct SQL) skips the Form Request entirely.
- The **validation rule** exists so a duplicate name fails with a friendly `422` and a
  `name` error message instead of an ugly `500` database exception bubbling up.

On update, `Rule::unique(...)->ignore($this->route('category'))` matters: without `ignore()`,
saving a category with its *own* unchanged name would incorrectly fail as "a duplicate of
itself." Passing the route-bound model directly (rather than an ID) works because Laravel's
`ignore()` reads the model's primary key for you.

**Validate it:**
```bash
php artisan migrate
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "SHOW INDEX FROM categories WHERE Key_name='categories_name_unique';"
php artisan test --filter=CategoryTest
```
Expect the index to show up, and all 9 tests (including the new duplicate-name cases) to pass.
Manually:
```bash
curl -s -X POST http://127.0.0.1:8000/api/categories \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"Beverages"}'
```
Expected: HTTP 422, `{"errors":{"name":["The name has already been taken."]}}`.

---

## Key Laravel concepts covered

- Migrations as version-controlled schema
- Mass assignment protection (`$fillable`)
- Form Requests for validation, decoupled from controllers
- API Resources for a stable JSON contract
- Resource controllers + `Route::apiResource` for conventional REST routes
- Route-model binding (`Category $category` auto-resolves from the URL)
- Seeders for reproducible demo data
- Feature tests using `RefreshDatabase`
- A decoupled UI consuming the same JSON API as `curl`, via Vite-compiled vanilla JS
- Database-level uniqueness (`unique` index) backed up by matching validation, with
  `Rule::unique()->ignore()` to exclude the current record on update

## What's next

A natural next module: a **Product** model that `belongsTo` `Category`, introducing Eloquent
relationships, eager loading (`with('category')`), and nested/foreign-key validation.
