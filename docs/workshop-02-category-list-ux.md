# Workshop 02 — Learn Laravel by Building a POS: Category List UX

## Objective

Workshop 01 built the Category API and a bare-bones single-page UI (one form + one table).
This workshop takes that UI to something closer to a real admin screen, using the same
**Category** module as the study case. You will learn:

- Splitting a CRUD page by concern (list page vs. create/edit form page) instead of one
  page doing everything
- A route-naming pitfall: `Route::apiResource` auto-registers route names that can silently
  collide with your own
- Server-side pagination with Laravel's built-in paginator, and consuming its `meta`/`links`
  JSON from vanilla JS
- Seeding enough dummy data to actually exercise pagination (including a partial last page)
- Truncating long text safely in a table cell (with the full value still available via `title`)
- Ordering a list by "most recently changed" instead of "most recently created"
- Server-side search filtering, and a debounce pattern that avoids firing a request on every
  keystroke while still feeling instant
- Server-side, whitelisted column sorting — and why an unrecognized `sort` value must reset
  the whole ORDER BY, not just the invalid part
- A quick, practical way to sanity-check a layout change actually renders well on a
  laptop/desktop viewport

Each step below states **what we did**, **why**, and **how to validate it yourself** — run
the validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Everything from [`workshop-01-category-api.md`](workshop-01-category-api.md) completed:
  MariaDB configured, migrations run, Category API working, and the single-page UI in place
- `npm install` already run (Vite + Tailwind, no JS framework)

---

## Step 1 — Split the single page into a list page and a create/edit form page

**What we did:** the workshop-01 UI packed an "Add/Edit Category" form and the table onto one
page, toggling the form between modes with JS. We split that into two pages instead:

```php
// routes/web.php
Route::get('/categories', function () {
    return view('categories.index');
})->name('categories.list');

Route::get('/categories/create', function () {
    return view('categories.form');
})->name('categories.create');

Route::get('/categories/{category}/edit', function (Category $category) {
    return view('categories.form', ['category' => $category]);
})->name('categories.edit');
```

- `resources/views/categories/index.blade.php` — table + a "New Category" link, nothing else
- `resources/views/categories/form.blade.php` — one shared view for both create and edit;
  Blade pre-fills `$category->name`/`$category->description` when editing
- `resources/js/categories.js` — list page only: fetch + render rows, edit → link to the edit
  route, delete → confirm + `DELETE` fetch
- `resources/js/categories-form.js` — form page only: reads the category id off a
  `data-category-id` attribute on the `<form>` to decide `POST` vs `PUT`, redirects back to
  the list on success
- `vite.config.js` — added `categories-form.js` as a third build entry

**Why:** a list page and a create/edit form are different concerns with different lifecycles
(one fetches a collection and paginates it, the other loads/submits a single record) — keeping
them as separate server-rendered pages mirrors how most real admin UIs are structured, and
avoids one file trying to juggle two very different DOM states.

**The route-name pitfall we hit:** the list route was first named `categories.index` — the
same name `Route::apiResource('categories', ...)` in `routes/api.php` auto-registers for its
JSON index endpoint. Since `bootstrap/app.php` loads `web.php` before `api.php`, the API route
silently won the name, so `route('categories.index')` resolved to `/api/categories`, not the
web page. Fixed by naming the web route `categories.list` instead.

**Validate it:**
```bash
php artisan route:list --path=categories
php artisan tinker --execute="echo route('categories.list');"
```
You should see both the `api/categories` routes (named `categories.index`, etc.) and the three
web routes (`categories.list`, `categories.create`, `categories.edit`) with no name collision,
and `route('categories.list')` should print `.../categories`, not `.../api/categories`.

---

## Step 2 — Server-side pagination

**What we did:** the controller already paginated at a sane default; we made sure the frontend
actually consumes it instead of only ever showing page 1:

```php
// app/Http/Controllers/CategoryController.php
Category::query()
    ->paginate(15)
    ->withQueryString()
```

```js
// resources/js/categories.js (excerpt)
async function fetchCategories(page = 1) {
    const response = await fetch(`${apiUrl}?page=${page}`, { headers: { Accept: 'application/json' } });
    const { data, meta } = await response.json();

    currentPage = meta.current_page;
    renderTable(data);
    renderPagination(meta); // Prev/Next buttons + "Page X of Y", driven off meta
}
```

**Why:** 15 rows/page is Laravel's own paginator default and a common admin-table size.
Laravel's paginated JSON response already includes everything a client needs
(`meta.current_page`, `meta.last_page`, `meta.total`, etc.) — there's no need to hand-roll
pagination math on the frontend, just read the `meta` block.

**Validate it:** seed more than 15 rows (see Step 2b below), then:
```bash
curl -s "http://127.0.0.1:8000/api/categories?page=2" -H "Accept: application/json" | jq .meta
```
Expect `current_page: 2`, `per_page: 15`, and a `total` matching your seeded row count.

### Step 2b — Seed enough dummy data to see it

```php
// database/seeders/CategorySeeder.php
foreach ($categories as $category) {
    Category::query()->firstOrCreate(['name' => $category['name']], $category);
}

Category::factory()->count(45)->create();
```

**Why:** 5 named categories alone never triggers a second page. Adding 45 factory-generated
rows (50 total) gives 4 pages at 15/page — including a partial last page (5 rows) — which is
exactly the kind of boundary case pagination bugs hide in.

**Validate it:**
```bash
php artisan migrate:fresh --seed
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "SELECT COUNT(*) FROM categories;"
```
Expected: `50`. Then open `http://127.0.0.1:8000/categories` and click **Next** through to a
final page with only 5 rows and a disabled **Next** button.

---

## Step 3 — Truncate long descriptions in the table

**What we did:**
```js
// resources/js/categories.js (excerpt)
const DESCRIPTION_MAX_LENGTH = 100;

function truncate(text, maxLength) {
    return text.length > maxLength ? `${text.slice(0, maxLength)}...` : text;
}

descriptionCell.textContent = truncate(description, DESCRIPTION_MAX_LENGTH);
if (description.length > DESCRIPTION_MAX_LENGTH) {
    descriptionCell.title = description;
}
```

**Why:** a long description would otherwise blow out the row height or overflow the column.
Truncating client-side (rather than in the API) keeps the full value available to the page —
the `title` attribute gives a native hover tooltip with the untruncated text, so nothing is
actually lost, just visually collapsed.

**Validate it:** seed or create a category with a description longer than 100 characters, then
check the list page — it should show `... ` after 100 characters, and hovering the cell should
show the full text in a tooltip.

---

## Step 4 — Order by most-recently-updated, not most-recently-created

**What we did:**
```php
// app/Http/Controllers/CategoryController.php
Category::query()->orderBy('updated_at', 'desc') // ...composed with sorting in Step 6
```

**Why:** `Category::latest()` (the workshop-01 default) orders by `created_at`, so editing an
old category wouldn't surface it. For an admin list, "what did I touch most recently" is
usually more useful than "what was inserted first."

**Validate it:**
```bash
php artisan test --filter=test_it_lists_categories_ordered_by_updated_at_desc
```
The test creates three categories with different `updated_at` timestamps and asserts the
newest-updated one comes back first.

---

## Step 5 — Search by name

**What we did:**
```php
// app/Http/Controllers/CategoryController.php
->when($request->string('search')->trim()->toString(), fn ($query, $search) =>
    $query->where('name', 'like', "%{$search}%"))
```

```js
// resources/js/categories.js (excerpt)
const SEARCH_MIN_LENGTH = 3;
const SEARCH_DEBOUNCE_MS = 300;

function applySearch(value) {
    if (value.length > 0 && value.length < SEARCH_MIN_LENGTH) {
        return; // too short — ignore, don't hit the API
    }
    currentSearch = value;
    fetchCategories(1); // reset to page 1 on every new search
}

searchField.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applySearch(searchField.value.trim()), SEARCH_DEBOUNCE_MS);
});

searchForm.addEventListener('submit', (event) => {
    event.preventDefault();       // Enter still works immediately, no debounce wait
    clearTimeout(searchDebounceTimer);
    applySearch(searchField.value.trim());
});
```

The `<input>` also carries `minlength="3"` as a native HTML5 backstop.

**Why:**
- `->when(...)` only adds the `WHERE` clause when a search term is actually present — no
  branching `if` needed in the query itself.
- A **debounce** (wait for typing to pause ~300ms before firing) is the standard way to give
  "search as you type" without spamming the server on every keystroke — but firing on *every*
  keystroke after just 1-2 characters is noisy and usually low-signal, so a **minimum length**
  guard is layered on top: below 3 characters, nothing is sent at all. An empty value clears
  the search and shows the full list again.
- Wrapping the input in a `<form>` (rather than a bare `<input>`) means pressing **Enter**
  still triggers an immediate search without waiting for the debounce — a fast path for users
  who don't want to wait, with no extra button needed once debounce already covers the common
  case.

**Validate it:**
```bash
php artisan test --filter=test_it_searches_categories_by_name
php artisan test --filter=test_it_returns_no_categories_for_a_search_with_no_matches
```
Manually: on `/categories`, type 2 characters — nothing happens. Type a 3rd — the list filters
after a short pause. Clear the field — the full list returns.

---

## Step 6 — Sort by name (ascending/descending)

**What we did:**
```php
// app/Http/Controllers/CategoryController.php
$sortable = ['name', 'updated_at'];
if (in_array($request->query('sort'), $sortable, true)) {
    $sort = $request->query('sort');
    $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
} else {
    $sort = 'updated_at';
    $direction = 'desc';
}

Category::query()
    ->when(/* search, as above */)
    ->orderBy($sort, $direction)
    ->paginate(15)
    ->withQueryString();
```

```html
<!-- resources/views/categories/index.blade.php (excerpt) -->
<th class="px-4 py-2 border-b border-gray-200">
    <button type="button" id="sort-name" class="flex items-center gap-1 font-medium hover:underline">
        Name
        <span id="sort-name-indicator" class="text-gray-400"></span>
    </button>
</th>
```

```js
// resources/js/categories.js (excerpt)
sortNameButton.addEventListener('click', () => {
    currentDirection = currentSort === 'name' && currentDirection === 'asc' ? 'desc' : 'asc';
    currentSort = 'name';
    updateSortIndicator(); // shows ▲ or ▼ next to "Name"
    fetchCategories(1);
});
```

**Why:** a clickable column header that toggles asc/desc with a `▲`/`▼` indicator is the
standard pattern for sortable tables (spreadsheets, admin panels, DataTables-style grids all
do this) — clearer than a separate dropdown, and it puts the control right where the eye
already is.

The `$sortable` **whitelist** matters for two reasons: it's the only thing standing between
`?sort=` and letting a client `ORDER BY` an arbitrary column name (or, if this ever became raw
SQL instead of Eloquent's parameter-safe `orderBy`, a bigger problem than that). Just as
important: when `sort` *isn't* recognized, **both** `$sort` and `$direction` fall back to the
default together. An earlier version only defaulted the column and kept whatever `direction`
was requested — so `?sort=nonsense&direction=asc` silently reordered results ascending by
`updated_at` instead of falling back to the real default (`updated_at desc`). A request with
an invalid sort should behave exactly like no sort was requested at all.

**Validate it:**
```bash
php artisan test --filter=test_it_sorts_categories_by_name_ascending
php artisan test --filter=test_it_sorts_categories_by_name_descending
php artisan test --filter=test_it_ignores_an_unsupported_sort_column
```
Manually: click **Name** on `/categories` — rows sort A→Z with a `▲`. Click again — Z→A with
a `▼`.

---

## Step 7 — Verify the layout actually fits a laptop/desktop screen

**What we did:** widened the list page's container and visually checked it at a real desktop
resolution instead of just trusting the CSS class name:

```html
<!-- resources/views/categories/index.blade.php -->
<div class="max-w-7xl mx-auto">
```

`max-w-7xl` (1280px) is the same page-container width Tailwind UI and most admin dashboards
(Filament, Nova, etc.) use — enough room for a multi-column table without going edge-to-edge
on a wide monitor. It replaced `max-w-3xl` (768px), which was cramped for a 3-column table.

**Why this needed a visual check, not just a build:** `npm run build` only proves the CSS
compiles — it says nothing about whether the table actually looks right at a typical laptop
width. A Tailwind class typo (`max-w-7x1` instead of `max-w-7xl`, say) would build fine and
silently do nothing.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/categories` in a browser resized to a typical laptop width
(~1440px), and confirm the table, search box, and pagination controls fill the width
sensibly with balanced margins on either side — not still narrow-and-centered, and not
stretched edge-to-edge with no margin at all.

---

## Key Laravel & frontend concepts covered

- Splitting a CRUD screen into a list view and a create/edit view, each with its own JS entry
  point, rather than one page handling every state
- `Route::apiResource`'s auto-generated route names can collide with manually-named web
  routes when both share a resource name — route load order (`web.php` before `api.php` in
  `bootstrap/app.php`) decides which name "wins"
- Laravel's paginator (`paginate()`) and its JSON shape (`data`, `meta`, `links`) as the
  contract a frontend consumes, instead of hand-rolling pagination
- `->when()` for conditionally composing query clauses without an `if` around the whole query
- Debounced input + a minimum-length guard as a pattern for "search as you type" that doesn't
  hammer the server, plus a `<form>` submit as an immediate fast-path
- Whitelisting sortable/filterable columns driven by user input, and making sure invalid input
  falls back to a fully-consistent default rather than a half-defaulted, half-requested state
- Truncating displayed text while preserving the full value via the `title` attribute
- Treating a CSS layout change like any other change that needs verification — a successful
  build doesn't prove a class name is correct or that the result looks right

## What's next

With Category's list UX now representative of a real admin screen, the natural next step is
introducing the **Product** module from workshop 01's "what's next" note — `belongsTo`
`Category` — and carrying these same list-page patterns (pagination, search, sort) over to it,
since a Product list will have the same needs plus a category filter.
