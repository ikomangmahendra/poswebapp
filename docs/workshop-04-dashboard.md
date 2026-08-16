# Workshop 04 — Learn Laravel by Building a POS: Inventory Dashboard

## Objective

Workshops 01-03 built Category and Product as full CRUD modules — the same shape repeated
twice, now documented in `CLAUDE.md`'s "Module conventions". This workshop builds something
structurally different: a **dashboard**, which isn't a CRUD resource at all — there's nothing
to create, update, or delete, only numbers and lists to compute from data that already exists.
You will learn:

- Why a dashboard doesn't fit the resource-controller shape, and the plain, multi-action
  controller Laravel offers instead of `Route::apiResource`
- Aggregate queries: `selectRaw('SUM(...) as total')` for a computed total,
  `withCount()` for a per-related-model count without an N+1 query
- Shaping a bespoke JSON response by hand instead of through a `JsonResource`, when the
  response doesn't represent one model — and reusing an existing `JsonResource` where the
  response *is* just a collection of an existing model
- Giving each independent section of one page its own real pagination, and the one Laravel
  quirk that trips up when you try: a paginator you `->through()`-transform yourself doesn't
  serialize the same way `Resource::collection()` does
- Scoping a feature honestly to the data that actually exists — no sales/revenue metrics here,
  because there's no Order module yet
- A small but real usability gap the last three workshops left open: none of the pages could
  link to each other

Each step below states **what we did**, **why**, and **how to validate it yourself** — run
the validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Everything from workshops [01](workshop-01-category-api.md), [02](workshop-02-category-list-ux.md),
  and [03](workshop-03-product-api.md) completed: Category and Product both fully working, with
  `CategorySeeder`/`ProductSeeder` seeding 50 rows each

## Scoping the dashboard to data that actually exists

A "POS dashboard" usually implies sales numbers — revenue, order count, best sellers. None of
that is possible yet, because there is no Order module (it's flagged as the module *after*
this one). Building fake sales metrics now would mean tearing them out or reworking them once
Order exists. Instead, this workshop scopes the dashboard to what Category and Product can
honestly support today:

- Total products, total categories
- Total inventory value (`Σ price × stock`)
- Low-stock products (an actionable, POS-relevant signal available right now)
- Products-per-category breakdown
- Recently updated products

Once Order exists, this is the natural place to add sales metrics — the dashboard doesn't need
to be rebuilt, just extended.

---

## Step 1 — Scaffold a plain controller, not a resource controller

**What we did:**
```bash
php artisan make:controller DashboardController
```
```php
// app/Http/Controllers/DashboardController.php
class DashboardController extends Controller
{
    public function index(Request $request) { /* summary stats */ }
    public function lowStock(Request $request) { /* paginated low-stock products */ }
    public function categoryBreakdown(Request $request) { /* paginated products-per-category */ }
    public function recentProducts(Request $request) { /* paginated recently-updated products */ }
}
```
```php
// routes/api.php
Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock']);
Route::get('/dashboard/categories', [DashboardController::class, 'categoryBreakdown']);
Route::get('/dashboard/recent-products', [DashboardController::class, 'recentProducts']);
```

**Why:** `Route::apiResource(...)` (used for Category and Product) generates five routes
mapped to five controller methods — appropriate when there's a model with a full CRUD
lifecycle. A dashboard has none of that: no `store`, no `update`, no `destroy`. But it also
isn't just *one* read either — a dashboard page is several independent sections (cheap
summary stats, plus three separately-paginated lists), each needing its own endpoint so each
can be fetched, paginated, and refreshed independently. So this is a plain controller with one
public method per concern, each routed individually — not a resource controller (nothing to
map to `apiResource`'s five conventional actions), and not a single invokable action either
(there's more than one job here).

**Validate it:**
```bash
php artisan route:list --path=dashboard
```
Expected: four `GET|HEAD api/dashboard...` routes (`index`, `low-stock`, `categories`,
`recent-products`), each pointing at a different `DashboardController` method, with no
`store`/`update`/`destroy` routes alongside them.

---

## Step 2 — The aggregate queries and the per-section endpoints

**What we did — cheap summary stats, no pagination needed:**
```php
// app/Http/Controllers/DashboardController.php
private const LOW_STOCK_THRESHOLD = 10;
private const PER_PAGE = 10;

public function index(Request $request)
{
    return [
        'total_products' => Product::query()->count(),
        'total_categories' => Category::query()->count(),
        'inventory_value' => Product::query()->selectRaw('SUM(price * stock) as total')->value('total') ?? 0,
        'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
        'low_stock_count' => Product::query()->where('stock', '<', self::LOW_STOCK_THRESHOLD)->count(),
    ];
}
```

**What we did — three paginated list endpoints, 10 rows per page:**
```php
public function lowStock(Request $request)
{
    return ProductResource::collection(
        Product::query()
            ->with('category')
            ->where('stock', '<', self::LOW_STOCK_THRESHOLD)
            ->orderBy('stock')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
    );
}

public function categoryBreakdown(Request $request)
{
    $categories = Category::query()
        ->withCount('products')
        ->orderByDesc('products_count')
        ->paginate(self::PER_PAGE)
        ->withQueryString()
        ->through(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'product_count' => $category->products_count,
        ]);

    return JsonResource::collection($categories);
}

public function recentProducts(Request $request)
{
    return ProductResource::collection(
        Product::query()->with('category')->latest('updated_at')->paginate(self::PER_PAGE)->withQueryString()
    );
}
```

**Why each piece:**
- `selectRaw('SUM(price * stock) as total')->value('total')` computes the total inventory
  value **in the database**, in one query, rather than pulling every product into PHP and
  summing there. The `?? 0` guards the case where there are no products at all (`SUM` over zero
  rows returns `null`, not `0`).
- `low_stock_count` is a plain `count()` query, deliberately separate from the paginated
  `lowStock()` list — the "Low Stock Products" stat tile needs the *true total*, not the size
  of whatever page happens to be loaded.
- `Category::withCount('products')` is `hasMany` eager loading applied to a **count** instead
  of the full related collection — one extra query gets every category's product count at
  once, the same N+1 avoidance instinct as `Product::with('category')` from workshop 03, just
  counting instead of fetching.
- `lowStock()` and `recentProducts()` both reuse **`ProductResource`** (from workshop 03)
  wrapped in `paginate()` — exactly the same shape `ProductController::index()` already
  produces. A "top N products" list and "all products, paginated" are the same kind of
  response; there was no reason to hand-roll a second mapping.

**The pagination-shape gotcha in `categoryBreakdown()`:** there's no existing
`CategoryBreakdownResource` to reuse, since the shape (`{id, name, product_count}`) isn't just
a `Category`. The first version called `->through($mapper)` directly on the paginator and
returned it as-is — `->through()` transforms each item but the *paginator itself*, returned
bare, serializes to Laravel's flat pagination array (`current_page`, `total`, `data`, ... all
as sibling keys), not the `{data, links, meta}` envelope `ProductResource::collection(...)`
produces for the other two endpoints. Every dashboard test asserting `meta.total` failed with
"null" until the transformed paginator was wrapped in `JsonResource::collection($categories)` —
a generic Resource collection wrapper needs no model-specific `toArray()` logic (the items are
already plain arrays), it just re-applies the standard pagination envelope on top.

**Validate it:**
```bash
php artisan route:list --path=dashboard
curl -s "http://127.0.0.1:8000/api/dashboard/categories" -H "Accept: application/json" | python3 -m json.tool | head -20
```
Expected: the JSON has top-level `data`, `links`, and `meta` keys (not `current_page`/`total`
as siblings of `data`) — the same shape as `/api/categories` or `/api/products`.

---

## Step 3 — Route, view, and three independently-paginated sections

**What we did:**
```php
// routes/web.php
Route::get('/dashboard', function () {
    return view('dashboard.index');
})->name('dashboard');
```
`resources/views/dashboard/index.blade.php` renders a `#stat-tiles` container, three tables
(low stock, category breakdown, recently updated), and a `<div>` per table for that section's
own pagination controls. `resources/js/dashboard.js` fetches `/api/dashboard` once for the
stat tiles, then wires up three independent paginated sections via one shared helper:
```js
function createPaginatedSection({ apiUrl, tableBodyId, paginationId, columnCount, emptyMessage, renderRow }) {
    // fetches `${apiUrl}?page=N`, renders rows via renderRow(item), and renders
    // Prev/Next + "Page X of Y" off the response's `meta` block — same pattern as
    // categories.js/products.js, generalized so it isn't rewritten three times.
}

createPaginatedSection({ apiUrl: '/api/dashboard/low-stock', /* ... */ });
createPaginatedSection({ apiUrl: '/api/dashboard/categories', /* ... */ });
createPaginatedSection({ apiUrl: '/api/dashboard/recent-products', /* ... */ });
```

**Why one section's pagination doesn't affect the others:** each call to
`createPaginatedSection(...)` closes over its own `apiUrl`, its own `<tbody>`/pagination
`<div>` elements, and its own current-page state — there's no shared "the page" variable for
the whole dashboard. Clicking **Next** on "Products per Category" only re-fetches
`/api/dashboard/categories?page=2`; the Low Stock and Recently Updated sections are untouched.
10 rows per page (not the module list pages' 15) keeps three sections' tables a reasonable
height on one screen.

**Why no route-naming pitfall this time:** workshop 02 hit a real bug where naming a web list
route `categories.index` collided with `Route::apiResource`'s auto-generated name of the same
value. That can't happen here — the web route is just named `dashboard`, and there's no
`Route::apiResource('dashboard', ...)` in `api.php` to collide with, since Step 1 deliberately
used individual `Route::get(...)` calls instead.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/dashboard` — four stat tiles populate, followed by three tables
each with their own "Showing X-Y of Z" / Prev / "Page A of B" / Next controls. With the seeded
data (50 categories), "Products per Category" should show "Page 1 of 5"; clicking its **Next**
should advance only that section.

---

## Step 4 — Make every page reachable from every other page

**What we did:** `resources/views/partials/nav.blade.php` — a plain three-link `<nav>`
(Dashboard, Categories, Products) — `@include`d at the top of `dashboard/index.blade.php`,
`categories/index.blade.php`, and `products/index.blade.php`.

**Why this needed fixing now:** across workshops 01-03, `/categories` and `/products` never
linked to each other — each was only reachable by typing its URL directly. That was a minor
gap while there were only two modules; adding a third page with the same problem made it worth
fixing properly rather than repeating it a third time. A single shared partial, included on
each list page, is enough — this still isn't a layout/master-template system (there's no
shared `<head>`, no slot/section inheritance), just one small reused fragment, consistent with
"no SPA/router, each page is its own server-rendered view."

**Validate it:** from `/dashboard`, click **Categories** — you should land on `/categories`
with the same nav visible; click **Products** — same, to `/products`; click **Dashboard** from
either — back to `/dashboard`.

---

## Step 5 — Tests: hand-computed values, plus pagination boundaries

**What we did:** `tests/Feature/DashboardTest.php` seeds small, explicit datasets — known
`price`/`stock` values, not random factory output — so the expected numbers can be computed by
hand and asserted exactly:
```php
Product::factory()->create(['category_id' => $beverages->id, 'price' => 4.50, 'stock' => 40]);
Product::factory()->create(['category_id' => $beverages->id, 'price' => 2.00, 'stock' => 5]);
Product::factory()->create(['category_id' => $beverages->id, 'price' => 1.00, 'stock' => 50]);
Product::factory()->create(['category_id' => $snacks->id, 'price' => 3.00, 'stock' => 8]);
// inventory_value = 4.50*40 + 2.00*5 + 1.00*50 + 3.00*8 = 264.00, low_stock_count = 2
```
Alongside the ordering/arithmetic tests, three tests specifically exercise the 10-per-page
boundary — e.g. for low stock:
```php
public function test_it_paginates_low_stock_products_at_ten_per_page(): void
{
    for ($i = 0; $i < 12; $i++) {
        Product::factory()->create(['stock' => $i % 10]);
    }

    $firstPage = $this->getJson('/api/dashboard/low-stock');
    $firstPage->assertJsonCount(10, 'data');
    $firstPage->assertJsonPath('meta.total', 12);
    $firstPage->assertJsonPath('meta.last_page', 2);

    $secondPage = $this->getJson('/api/dashboard/low-stock?page=2');
    $secondPage->assertJsonCount(2, 'data');
}
```
The same shape repeats for `categoryBreakdown` and `recentProducts` — 12 seeded rows, page 1
has 10, page 2 has the remaining 2.

**Why hand-computed values *and* boundary tests matter here:** a dashboard's whole job is
arithmetic and correct slicing. A test that only checks "some number came back" wouldn't catch
a wrong formula (e.g. `price + stock` instead of `price * stock`), and a test that only checks
"the list has some items" wouldn't have caught the flat-vs-nested pagination shape bug from
Step 2 — that bug only surfaced once a test actually asserted `meta.total`.

**Validate it:**
```bash
php artisan test --filter=DashboardTest
```
Expected: all 7 tests passing.

---

## Key Laravel concepts covered

- A plain, multi-action controller as the right shape for a page with several independent
  read-only sections — not a resource controller, and not a single invokable action either,
  when there's genuinely more than one endpoint's worth of work
- `selectRaw()` for a database-computed aggregate instead of pulling rows into PHP to sum them
- `withCount()` as `with()`'s counting counterpart — a per-related-model count in one extra
  query instead of N
- Reusing an existing `JsonResource` (`ProductResource`) for a "top N, paginated" list, since
  it's the same shape as that model's own index endpoint
- `->through()` on a paginator transforms its items but not its serialization shape — wrapping
  the result in `JsonResource::collection(...)` is what restores the standard
  `{data, links, meta}` envelope every other list page's JS already expects
- Giving each section of a multi-section page its own independent pagination state via one
  shared, parameterized JS helper, instead of one shared "current page" for the whole screen
- Deliberately scoping a feature to the data that exists today rather than building ahead of a
  module that doesn't exist yet
- Testing aggregate/arithmetic endpoints with hand-computed expected values, and testing
  pagination specifically at its boundary (exactly `per_page + 2` rows), not just "some data
  came back"
- A shared, minimal navigation partial as the smallest fix for cross-page reachability,
  without introducing a full layout/template system

## What's next

**Order** — flagged since workshop 03 — is next: `Order hasMany OrderItem`, `OrderItem
belongsTo Product`, stock decrementing on sale, and a total computed from line items. Once
Order exists, this dashboard is the natural place to extend with real sales metrics: today's
revenue, order count, and top-selling products, alongside the inventory stats already here.
