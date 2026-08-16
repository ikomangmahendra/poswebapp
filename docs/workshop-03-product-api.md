# Workshop 03 — Learn Laravel by Building a POS: Product API + Relationships

## Objective

Workshops 01-02 built Category end-to-end and established a repeatable module pattern
(migration → model → factory → Form Requests → Resource → controller → routes → seeder →
tests → list/search/sort/pagination UI). This workshop applies that exact pattern to a second
module, **Product**, and — because a product always belongs to a category — introduces the
one thing Category alone couldn't teach: a real Eloquent relationship. You will learn:

- Scaffolding a second module that reuses every convention from Category, so the new parts
  stand out clearly
- `belongsTo` / `hasMany` — the two sides of the same foreign key relationship
- Nesting a related model inside an API Resource, and why that requires **eager loading**
  (`->with()` / `->load()`) to avoid an N+1 query problem
- A subtle factory/seeder pitfall: a factory's default foreign key pointing at
  `RelatedModel::factory()` will happily create a *new* related row for every seeded row
  unless you explicitly tell it to reuse existing ones
- Guarding a delete across a relationship: blocking it with a **friendly, specific** message
  instead of letting a database constraint error leak through — while still keeping the DB
  constraint as the real safety net
- Rendering a required foreign key as a `<select>` populated from the server
- Applying search, sort, and pagination to a new module **from day one**, instead of adding
  them iteratively the way Category did across workshops 01-02

Each step below states **what we did**, **why**, and **how to validate it yourself** — run
the validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Everything from [`workshop-01-category-api.md`](workshop-01-category-api.md) and
  [`workshop-02-category-list-ux.md`](workshop-02-category-list-ux.md) completed: Category's
  API, tests, seeder, and list/search/sort/pagination UI all in place and passing

## The field set and the one design decision

**Product fields:** `name` (required), `sku` (nullable, unique — a barcode/lookup code),
`price` (required, decimal), `stock` (integer, default 0), `category_id` (required foreign
key). This is the smallest field set where a Product is actually usable at checkout, not just
a name in a list.

**The delete-guard decision:** what should happen when someone tries to delete a Category that
still has Products? Two honest options exist — cascade (delete the products too) or restrict
(block the deletion). We chose **restrict, with a friendly, specific error** naming the
product that's blocking it (e.g. `"Category is being used by product \"Iced Latte\"."`),
because silently deleting products as a side effect of deleting their category is exactly the
kind of surprising data loss a POS system shouldn't allow. Deletion succeeds once no Product
references the category anymore.

---

## Step 1 — Scaffold Product the same way Category was scaffolded

**What we did:**
```bash
php artisan make:model Product -mfc --api -R
php artisan make:resource ProductResource
php artisan make:seeder ProductSeeder
```
Exactly the same command shape as workshop 01's Step 2 for Category — same flags, same
generated file set (migration, factory, API resource controller, Form Requests, model,
Resource, Seeder).

**Why:** reusing the identical scaffolding command for a second module is itself the lesson —
once you've learned the shape once, every new module starts from the same, predictable
skeleton.

**Validate it:**
```bash
ls app/Models/Product.php \
   app/Http/Controllers/ProductController.php \
   app/Http/Requests/StoreProductRequest.php \
   app/Http/Requests/UpdateProductRequest.php \
   app/Http/Resources/ProductResource.php \
   database/factories/ProductFactory.php \
   database/seeders/ProductSeeder.php
```

---

## Step 2 — Migration: the schema plus the foreign key

**What we did:**
```php
// database/migrations/..._create_products_table.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->restrictOnDelete();
    $table->string('name');
    $table->string('sku')->nullable()->unique();
    $table->decimal('price', 10, 2);
    $table->unsignedInteger('stock')->default(0);
    $table->timestamps();
});
```

**Why:** `foreignId('category_id')->constrained()` is shorthand for an unsigned big integer
column plus a foreign key constraint against `categories.id` (Laravel infers the table name
from the column name). `restrictOnDelete()` is the **database-level** half of the delete-guard
decision above — it's the real guarantee that survives even if some other code path (a
seeder, a queued job, direct SQL) ever bypasses the controller. `decimal('price', 10, 2)`
stores money as an exact decimal (10 total digits, 2 after the point), not a float — floats
lose precision on currency math.

**Validate it:**
```bash
php artisan migrate
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "DESCRIBE products;"
```
Expected columns: `id`, `category_id`, `name`, `sku`, `price`, `stock`, `created_at`,
`updated_at`, with `category_id` shown as an indexed foreign key.

---

## Step 3 — The relationship: `belongsTo` and `hasMany`

**What we did:**
```php
// app/Models/Product.php
class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'sku', 'price', 'stock'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```
```php
// app/Models/Category.php — the other side of the same relationship
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}
```

**Why:** a relationship is really just one foreign key described from two directions.
`Product::belongsTo(Category::class)` says "I hold the foreign key, and `$product->category`
gives me the one Category I point at." `Category::hasMany(Product::class)` says "some other
table holds a foreign key pointing at me, and `$category->products` gives me all of them."
Both sides get used in this workshop: `belongsTo` for nesting the category into
`ProductResource`, `hasMany` for the delete-guard check in Step 7.

**Validate it:**
```bash
php artisan tinker --execute="
\$category = App\Models\Category::first();
\$product = App\Models\Product::factory()->create(['category_id' => \$category->id]);
echo \$product->category->name . PHP_EOL;
echo \$category->products()->count();
"
```
Expected: the category's name printed once, then a product count of at least 1.

---

## Step 4 — Factory

**What we did:**
```php
// database/factories/ProductFactory.php
public function definition(): array
{
    return [
        'category_id' => Category::factory(),
        'name' => fake()->unique()->words(2, true),
        'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
        'price' => fake()->randomFloat(2, 1, 500),
        'stock' => fake()->numberBetween(0, 200),
    ];
}
```

**Why:** `'category_id' => Category::factory()` means "if nothing overrides this, create a
brand-new Category for this Product." That's convenient for one-off test data
(`Product::factory()->create()` in a test just works, with its own throwaway category) — but
it is exactly the thing Step 6 has to work around when seeding in bulk.

**Validate it:**
```bash
php artisan tinker --execute="echo App\Models\Product::factory()->count(3)->create()->count();"
```
Expected: `3`, with no errors — and `App\Models\Category::count()` should have gone up by 3
as well, since each one got its own new category.

---

## Step 5 — Form Requests, Resource, controller

**What we did — validation:**
```php
// app/Http/Requests/StoreProductRequest.php
'category_id' => ['required', 'integer', 'exists:categories,id'],
'name' => ['required', 'string', 'max:255'],
'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
'price' => ['required', 'numeric', 'min:0'],
'stock' => ['nullable', 'integer', 'min:0'],
```
`UpdateProductRequest` mirrors this with `sometimes` on `category_id`/`name`/`price`, and
`Rule::unique('products', 'sku')->ignore($this->route('product'))` on `sku` — the exact same
"allow keeping your own value" pattern Category already established for `name`.

**What we did — the nested resource:**
```php
// app/Http/Resources/ProductResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'sku' => $this->sku,
        'price' => $this->price,
        'stock' => $this->stock,
        'category' => [
            'id' => $this->category->id,
            'name' => $this->category->name,
        ],
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

**What we did — the controller, with eager loading:**
```php
// app/Http/Controllers/ProductController.php
public function index(Request $request)
{
    // ...same search/sort/paginate shape as CategoryController...
    return ProductResource::collection(
        Product::query()
            ->with('category')   // <-- the eager-loading fix
            ->when(/* search */)
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString()
    );
}

public function show(Product $product)
{
    return ProductResource::make($product->load('category'));
}
```

**Why `exists:categories,id` matters:** without it, a client could POST any integer as
`category_id` and the database's foreign key constraint would be the *only* thing stopping it
— resulting in an ugly `500` instead of a clean `422` validation error. Same two-layer idea as
the delete guard: the DB constraint is the real guarantee, the validation rule is the friendly
front door.

**Why eager loading matters:** `$this->category` inside `ProductResource` triggers a query the
first time it's accessed on a given `Product` instance. Rendering 15 products on a page
without `->with('category')` means **1 query for the products, plus 15 more — one per row —
for their categories.** That's the classic N+1 problem. `->with('category')` fetches all the
needed categories in a single extra query (2 total, regardless of page size). `show`/`update`
use `->load('category')` — the same idea, applied to an already-fetched single model instead
of a query builder.

**Validate it:**
```bash
php artisan tinker --execute="
DB::enableQueryLog();
App\Http\Resources\ProductResource::collection(App\Models\Product::with('category')->paginate(15));
echo count(DB::getQueryLog());
"
```
Expected: a small, constant number of queries (2-3) regardless of how many products exist —
not 1-per-row. Try removing `->with('category')` temporarily and re-running to see the count
jump with more seeded rows — then put it back.

---

## Step 6 — Seeder: the "recycle existing rows, don't spawn new ones" gotcha

**What we did:**
```php
// database/seeders/ProductSeeder.php
foreach ($products as $product) {                    // 5 named products, firstOrCreate,
    $category = Category::query()                    // each pinned to a real named category
        ->where('name', $product['category'])->first();
    Product::query()->firstOrCreate(
        ['sku' => $product['sku']],
        ['category_id' => $category->id, /* ... */],
    );
}

$categoryIds = Category::query()->pluck('id')->all();

Product::factory()
    ->count(45)
    ->sequence(fn () => ['category_id' => fake()->randomElement($categoryIds)])
    ->create();
```
And in `DatabaseSeeder`, `CategorySeeder` is called **before** `ProductSeeder` — products need
categories to already exist.

**Why this needed a fix, not just `Product::factory()->count(45)->create()`:** that simpler
call was tried first. It ran without error and created 45 products — but it also silently
created **45 brand-new categories**, one per product, because of `ProductFactory`'s own
default (`'category_id' => Category::factory()`, from Step 4) creating a fresh category every
time nothing overrides it. The bug was only visible by checking `Category::count()`
afterwards — it jumped from the expected 50 (from `CategorySeeder`) to 95. `->sequence(fn () =>
['category_id' => fake()->randomElement($categoryIds)])` overrides `category_id` on every
generated row with a **real, already-existing** category id instead, so the factory's own
`Category::factory()` default is never reached at all.

**The lesson:** when a factory's own definition creates related models by default, that
default is exactly right for isolated test data (Step 4's `php artisan tinker` check depended
on it) but exactly wrong for bulk-seeding against data that already exists — always check the
*side effects* of a seeder (row counts on related tables), not just whether it ran without
errors.

**Validate it:**
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="
echo 'Categories: ' . App\Models\Category::count() . PHP_EOL;
echo 'Products: ' . App\Models\Product::count();
"
```
Expected: `Categories: 50` (unchanged from workshop 02 — 5 named + 45 factory), `Products: 50`
(5 named + 45 factory). If Categories comes back higher than 50, the sequence override isn't
being applied.

---

## Step 7 — The delete guard

**What we did:**
```php
// app/Http/Controllers/CategoryController.php
public function destroy(Category $category)
{
    if ($product = $category->products()->first()) {
        return response()->json([
            'message' => "Category is being used by product \"{$product->name}\".",
        ], Response::HTTP_CONFLICT);
    }

    $category->delete();

    return response()->noContent();
}
```

**Why:** this is `hasMany` from Step 3 put to use. `$category->products()->first()` (note the
`()` — a query, not the loaded collection) checks *before* attempting the delete, so a blocked
delete returns a clean `409 Conflict` with a message naming the actual product responsible,
instead of the database's `restrictOnDelete()` constraint throwing a raw `QueryException` that
would otherwise surface as an ugly `500`. The migration's `restrictOnDelete()` is still there
underneath as the real guarantee — this check is the friendly front door on top of it, the
same two-layer shape as unique-name validation in workshop 02.

**Validate it:**
```bash
php artisan test --filter=test_it_prevents_deleting_a_category_used_by_a_product
php artisan test --filter=test_it_deletes_a_category_once_its_product_is_removed
```
Manually, with the seeded data from Step 6:
```bash
curl -s -X DELETE http://127.0.0.1:8000/api/categories/1 -H "Accept: application/json" -w "\n%{http_code}\n"
```
Expected: `409` with `{"message":"Category is being used by product \"Iced Latte\"."}` (id 1
is the seeded "Beverages" category). Delete that product first, then repeat the same request —
it should now succeed with a `204`.

---

## Step 8 — Routes and tests

**What we did:**
```php
// routes/api.php
Route::apiResource('products', ProductController::class);
```
```php
// routes/web.php
Route::get('/products', fn () => view('products.index'))->name('products.list');

Route::get('/products/create', fn () =>
    view('products.form', ['categories' => Category::orderBy('name')->get()])
)->name('products.create');

Route::get('/products/{product}/edit', fn (Product $product) =>
    view('products.form', ['product' => $product, 'categories' => Category::orderBy('name')->get()])
)->name('products.edit');
```
The list route is named `products.list` from the very start — workshop 02 hit a real bug
where naming a web list route `categories.index` silently collided with `apiResource`'s
auto-generated `categories.index` (for the JSON index endpoint), and `route('categories.index')`
resolved to the wrong URL. Knowing that pitfall now, Product's web routes are named `.list`
from day one instead of rediscovering the same bug.

`tests/Feature/ProductTest.php` mirrors `CategoryTest.php`'s full coverage (list, updated_at
ordering, search, sort asc/desc + invalid-sort fallback, create, validation failures including
an invalid `category_id`, duplicate-sku rejection on create/update, show, update, delete) plus
one assertion specific to the relationship: that the response's `data.category` is the nested
`{id, name}` shape from Step 5.

**Validate it:**
```bash
php artisan route:list --path=products
php artisan test --filter=ProductTest
```
Expected: 5 API routes plus 3 web routes with no name collisions, and every `ProductTest` case
passing.

---

## Step 9 — The UI: search, sort, and pagination from day one

**What we did:** `resources/views/products/index.blade.php` + `resources/js/products.js`
reuse the exact list-page shape from workshop 02 (debounced + submit search, sortable Name
header, Prev/Next pagination driven by `meta`) with extra columns for SKU, Category, Price,
and Stock. `resources/views/products/form.blade.php` + `resources/js/products-form.js` reuse
Category's create/edit shape, with one addition: a `<select name="category_id">`, built from
the `$categories` collection the web route already passed in (Step 8), with a disabled
"Select a category" placeholder and `@selected(...)` pre-selecting the current category on
edit.

**Why this is different from how Category got here:** Category's list started as a single
form+table page (workshop 01) and only gained search/sort/pagination in workshop 02, one
feature at a time. Product skips that path entirely — it gets the finished list-page pattern
from its very first commit, because the pattern is now known to work. This is the payoff of
having built Category first: the second module is mostly assembly, not design.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/products` — search, sort by Name, and paginate through the 50
seeded rows exactly as you would on `/categories`. Open
`http://127.0.0.1:8000/products/create`, pick a category from the dropdown, fill in the rest,
and save — you should land back on the list with the new product at the top (most recently
updated first).

---

## Key Laravel concepts covered

- Reusing an established scaffolding command and module shape for a second, unrelated model
- `belongsTo` / `hasMany` as two views of the same foreign key, and using both sides of the
  same relationship for different purposes (nesting vs. a dependency check)
- Nesting a related model inside an API Resource, and why that requires eager loading
  (`->with()` on a query, `->load()` on a single model) to avoid an N+1 query per row —
  verified directly by counting queries, not just assumed
- `exists:table,column` validation as the friendly-error counterpart to a foreign key
  constraint, the same two-layer idea as unique-name validation
- A factory's default relationship attribute is right for isolated test data and wrong for
  bulk seeding against existing rows — `->sequence()` to override it with real, already-seeded
  IDs, and validating a seeder by its *side effects* (related-table row counts), not just "it
  ran without error"
- Blocking a delete across a relationship with a friendly, specific `409` response backed by a
  `restrictOnDelete()` database constraint underneath, rather than either silently cascading or
  leaking a raw DB error
- Rendering a required foreign key as a server-populated `<select>`
- Applying a previously-learned UX pattern (search/sort/pagination) to a new module
  immediately, instead of re-deriving it

## What's next

With two independent modules (Category, Product) both following the same conventions, the
natural next step is an **Order** module that ties them together — `Order hasMany OrderItem`,
`OrderItem belongsTo Product` — introducing a many-to-many-through-a-pivot-like shape, stock
decrementing on sale, and a total computed from line items rather than stored as a plain
column.
