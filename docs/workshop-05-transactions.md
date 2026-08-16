# Workshop 05 — Learn Laravel by Building a POS: Transactions (Sales)

## Objective

Workshops 01-04 built two full CRUD modules (Category, Product) and one read-only aggregate
view (the Dashboard). This workshop builds a **Transaction** — a sale — and it's the first
module that is genuinely different in two ways: it has real **business logic** beyond
validate-and-save (snapshot a price, decrement stock, refuse to oversell, all atomically), and
it is deliberately **create-only** — a sale is never edited or voided in this simple version.
You will learn:

- Where multi-step business logic should live when more than one entry point needs it (the
  HTTP controller *and* a seeder), and why that's still not a "service layer"
- Row locking (`lockForUpdate()`) and why wrapping steps in `DB::transaction()` alone doesn't
  prevent two concurrent requests from overselling the same stock
- Price/quantity **snapshotting** — storing a copy of data at the moment it matters, instead of
  a live reference that would silently rewrite history if the source changes later
- Custom exceptions with a `render()` method, so a business-rule failure raised deep inside a
  model method turns into a clean HTTP response without a `try/catch` in the controller
- `Route::apiResource(...)->only([...])` to make "this module doesn't support every action" an
  enforced routing fact instead of an unwritten rule
- Extending the delete-guard pattern (from workshop 03) a second time, onto a different
  relationship, to confirm it's a general pattern and not something special to Category
- The `cascadeOnDelete()` vs `restrictOnDelete()` decision, made explicit as a rule of thumb

Each step below states **what we did**, **why**, and **how to validate it yourself** — run
the validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Everything from workshops [01](workshop-01-category-api.md) through
  [04](workshop-04-dashboard.md) completed: Category and Product fully working, with
  `CategorySeeder`/`ProductSeeder` seeding 50 rows each

## The confirmed scope

From the scoping conversation before this workshop: a Transaction has a `total` and a list of
line items, each with `product_id`, `quantity`, and a price **snapshot** (not a live reference
to `Product::price`). Stock decrements when a transaction is created. There's no `user_id` (no
auth wired up yet — that would be premature), and the module is create-only: no editing or
voiding a sale.

---

## Step 1 — Scaffold, then trim to a deliberately incomplete resource

**What we did:**
```bash
php artisan make:model Transaction -mfc --api -R
php artisan make:model TransactionItem -mf
php artisan make:resource TransactionResource
php artisan make:seeder TransactionSeeder
```
The generator produces the usual full CRUD skeleton (including `update`/`destroy` methods and
an `UpdateTransactionRequest`) — we deleted `UpdateTransactionRequest` and the `update`/
`destroy` methods, then routed only what remains:
```php
// routes/api.php
Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
```

**Why:** a sale being edited or deleted after the fact is a real business decision, not an
oversight — once a transaction exists, it should be a permanent record. `->only([...])` makes
that an enforced fact of the routing table: a `PUT`/`DELETE` to `/api/transactions/{id}`
doesn't hit a controller method that "does nothing" or forgot to be written, it gets Laravel's
normal `405 Method Not Allowed` because the route simply doesn't exist for those verbs.
`TransactionItem` only needed a model, migration, and factory — it's never its own API
endpoint, only ever created and read as part of a Transaction.

**Validate it:**
```bash
php artisan route:list --path=transactions
```
Expected: three `api/transactions...` routes (`index`, `store`, `show`) and no `update`/
`destroy` routes for the same path.

---

## Step 2 — Schema: two tables, two different delete rules

**What we did:**
```php
// database/migrations/..._create_transactions_table.php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->decimal('total', 10, 2);
    $table->timestamps();
});
```
```php
// database/migrations/..._create_transaction_items_table.php
Schema::create('transaction_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->restrictOnDelete();
    $table->unsignedInteger('quantity');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();
});
```

**Why the two foreign keys use opposite delete rules:** `transaction_id` uses
`cascadeOnDelete()` — a line item has no existence independent of its transaction; if the
transaction were ever deleted, its items should go with it. `product_id` uses
`restrictOnDelete()` — the same rule Category→Product already uses (workshop 03) — because a
product that's been sold has independently meaningful history; deleting it would silently
erase what was actually charged on a past sale. **The rule of thumb**: cascade when the child
row has no independent existence; restrict when the child row is independently meaningful
history.

**Validate it:**
```bash
php artisan migrate
mysql -h127.0.0.1 -P3306 -uroot -p'yourpassword' poswebapp -e "
  SHOW CREATE TABLE transaction_items\G
"
```
Expected: `transaction_id`'s foreign key clause ends `ON DELETE CASCADE`, `product_id`'s ends
`ON DELETE RESTRICT`.

---

## Step 3 — The business logic lives on the model

**What we did:**
```php
// app/Models/Transaction.php
public static function createFromItems(array $items): self
{
    return DB::transaction(function () use ($items) {
        $transaction = self::create(['total' => 0]);
        $total = 0;

        foreach ($items as $item) {
            $product = Product::query()->lockForUpdate()->findOrFail($item['product_id']);

            if ($item['quantity'] > $product->stock) {
                throw new InsufficientStockException($product, $item['quantity']);
            }

            $unitPrice = $product->price;
            $subtotal = $unitPrice * $item['quantity'];

            $transaction->items()->create([
                'product_id' => $product->id,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);

            $product->decrement('stock', $item['quantity']);
            $total += $subtotal;
        }

        $transaction->update(['total' => $total]);

        return $transaction->load('items.product');
    });
}
```

**Why this isn't in the controller:** both `TransactionController::store()` and
`TransactionSeeder` need to do the exact same thing — validate stock, snapshot the price,
decrement it, compute the total. Putting it in the controller would mean either the seeder
duplicates all of this logic (and the two copies drift apart over time) or the seeder calls
into an HTTP controller method, which is backwards. A static model method is the natural home:
it's still not a separate "service layer" (this app's convention, documented since workshop 01,
is no service classes) — it's just where multi-step logic that has more than one caller
belongs.

**Why `DB::transaction()` alone isn't enough, and `lockForUpdate()` is:** without a row lock,
two concurrent requests selling the last few units of the same product can both read
`stock: 5`, both decide "yes, 5 is enough for my request of 3", and both proceed — the database
ends up with `stock: -1` after two "valid" sales. `Product::query()->lockForUpdate()` takes a
row-level lock when reading the product, so a second concurrent request selling the same
product has to wait until the first transaction commits (and its stock decrement is visible)
before it can even read the row — eliminating that race window entirely.

**Why `unit_price` is copied, not referenced:** if `TransactionItem` only stored `product_id`
and looked up the price live every time, editing a product's price later would silently rewrite
what every past sale "charged" for it. Copying `product.price` into `unit_price` at the moment
of sale means a transaction's total always reflects what actually happened, regardless of
later price changes.

**Validate it:**
```bash
php artisan test --filter=test_it_snapshots_the_product_price_at_the_time_of_sale
```
The test creates a transaction at price `10.00`, then changes the product's price to `99.00`,
then re-fetches the transaction and asserts its stored `unit_price` is still `10.00`.

---

## Step 4 — A custom exception instead of a controller try/catch

**What we did:**
```php
// app/Exceptions/InsufficientStockException.php
class InsufficientStockException extends Exception
{
    public function __construct(private Product $product, private int $requested)
    {
        parent::__construct(
            "Insufficient stock for product \"{$product->name}\": requested {$requested}, available {$product->stock}."
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
```

**Why:** `createFromItems()` can fail several `foreach` iterations deep inside a
`DB::transaction()` closure — by the time the failure happens, there's no controller code on
the call stack to wrap in a `try/catch` without threading the exception all the way back up
manually. Laravel's exception handler already knows to call `render($request)` on any thrown
exception that defines one, and use its return value as the HTTP response — so
`TransactionController::store()` doesn't catch anything at all; throwing from deep inside the
model method is enough to produce a clean `422` with a friendly, specific message. This is the
same "friendly error over a raw failure" instinct as the Category/Product delete guards
(workshop 03), applied via a different Laravel mechanism because this failure originates
somewhere a controller-level `if` check can't reach.

**Validate it:**
```bash
curl -s -X POST http://127.0.0.1:8000/api/transactions \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"items":[{"product_id":1,"quantity":999999}]}' -w "\n%{http_code}\n"
```
Expected: `422` with `{"message":"Insufficient stock for product \"...\": requested 999999,
available N."}`, and `php artisan test --filter=test_it_rejects_a_transaction_with_insufficient_stock`
passing — including its assertion that **no** transaction row was created (the whole
`DB::transaction()` rolled back, not just the failed item).

---

## Step 5 — Validation, Resource, and index/store/show

**What we did:**
```php
// app/Http/Requests/StoreTransactionRequest.php
'items' => ['required', 'array', 'min:1'],
'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
'items.*.quantity' => ['required', 'integer', 'min:1'],
```
```php
// app/Http/Resources/TransactionResource.php (excerpt)
'items' => $this->items->map(fn ($item) => [
    'id' => $item->id,
    'product' => ['id' => $item->product->id, 'name' => $item->product->name],
    'quantity' => $item->quantity,
    'unit_price' => $item->unit_price,
    'subtotal' => $item->subtotal,
]),
```
`TransactionController::index()` sorts by `['created_at', 'total']` (default `created_at desc`)
— the same whitelist-and-fallback shape as Category/Product's `index()` — but has **no search
box**: there's no natural text field to search a transaction by. `store()` validates, then
calls `Transaction::createFromItems($request->validated('items'))` and returns `201`; `show()`
eager-loads `items.product` and returns the same `TransactionResource`.

**Why stock *sufficiency* isn't a validation rule:** the `FormRequest` only checks *shape* —
does `product_id` exist, is `quantity` a positive integer. Whether there's *enough* stock right
now is live database state that can change between the moment a request is validated and the
moment it's actually written — checking it in the `FormRequest` and again inside
`createFromItems()` would still leave the same race window `lockForUpdate()` closes. So it's
checked exactly once, at the last possible moment, inside the locked transaction.

**Validate it:**
```bash
php artisan test --filter=TransactionTest
```
Expected: all 9 tests passing (creation + total math, stock decrement, price snapshot,
insufficient stock, validation failures, show, index ordering, and the `405` create-only
check from Step 1).

---

## Step 6 — Extend the delete guard to Product, a second time

**What we did:**
```php
// app/Models/Product.php
public function transactionItems(): HasMany
{
    return $this->hasMany(TransactionItem::class);
}
```
```php
// app/Http/Controllers/ProductController.php
public function destroy(Product $product)
{
    if ($item = $product->transactionItems()->first()) {
        return response()->json([
            'message' => "Product is referenced by transaction #{$item->transaction_id}.",
        ], Response::HTTP_CONFLICT);
    }

    $product->delete();

    return response()->noContent();
}
```

**Why doing this twice matters:** workshop 03 introduced the two-layer delete guard (DB
`restrictOnDelete()` as the real guarantee, a friendly `409` check in the controller for UX)
specifically for Category→Product. Applying the *exact same shape* here — unchanged in
structure, just a different relationship — is what confirms it's actually a general pattern
for any `restrictOnDelete()` FK, not something that happened to work once for Category.

**Validate it:**
```bash
php artisan test --filter=test_it_prevents_deleting_a_product_used_by_a_transaction
```
Manually: seed data, then `curl -X DELETE .../api/products/{id}` for a product that's been
sold — expect `409` naming the transaction; the same request for a never-sold product should
succeed with `204`.

---

## Step 7 — Seeder: reusing the same business logic, safely

**What we did:**
```php
// database/seeders/TransactionSeeder.php
for ($i = 0; $i < 20; $i++) {
    $products = Product::query()->where('stock', '>', 5)->inRandomOrder()->limit(rand(1, 3))->get();

    if ($products->isEmpty()) {
        break;
    }

    $items = $products->map(fn (Product $product) => [
        'product_id' => $product->id,
        'quantity' => rand(1, min(3, $product->stock)),
    ])->all();

    Transaction::createFromItems($items);
}
```
Registered in `DatabaseSeeder` after `ProductSeeder` (transactions need products with stock to
exist first).

**Why `where('stock', '>', 5)`, re-queried every iteration:** each seeded transaction actually
decrements real stock through the same `createFromItems()` the API uses — so a naive seeder
picking arbitrary products/quantities could eventually hit the exact
`InsufficientStockException` a real client would, and crash the seed. Filtering to
comfortably-stocked products (and re-querying fresh stock on every loop iteration, since
earlier iterations in the same run have already decremented some) is what keeps 20 seeded
transactions from ever tripping the very check they're supposed to exercise safely. This is the
same "a seeder is real code with real side effects, verify it" lesson as workshop 03's
category-spawning seeder bug — caught here by design instead of by accident.

**Validate it:**
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="
echo 'Transactions: ' . App\Models\Transaction::count() . PHP_EOL;
echo 'Items: ' . App\Models\TransactionItem::count();
"
```
Expected: `Transactions: 20`, `Items:` between 20 and 60 (1-3 items each), no exception thrown
during seeding.

---

## Step 8 — The UI: list + read-only detail

**What we did:** `resources/views/transactions/index.blade.php` + `resources/js/transactions.js`
— the same list-page shape as Category/Product (sortable headers, `#pagination`) minus the
search box, with two sortable columns (`Total`, `Created`) instead of one, each with its own
`▲`/`▼` indicator. `resources/views/transactions/show.blade.php` +
`resources/js/transactions-show.js` renders one transaction's line items and total, fetched
client-side from `/api/transactions/{id}` — no edit or delete affordance anywhere on the page,
reinforcing create-only visually as well as at the routing level. `resources/views/partials/nav.blade.php`
gets a fourth link, Transactions.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/transactions` — a paginated, sortable list with no search box;
click a row's **View** — its detail page lists line items (product, quantity, unit price,
subtotal) and the total, with no edit/delete controls in sight.

---

## Step 9 — The checkout page: a cart, not a single-record form

**What we did:** `resources/views/transactions/create.blade.php` +
`resources/js/transactions-create.js`. Unlike Category/Product's create form (one record, a
handful of fields, submit once), a transaction is created from a **variable-length list** built
up interactively: a product `<select>` (each `<option>` carries `data-price`/`data-stock` so
the page doesn't need a second round-trip just to know a product's price or stock) plus a
quantity input and an **Add** button push `{productId, name, price, stock, quantity}` entries
into an in-memory `cart` array; adding the same product twice merges into one line by summing
quantity instead of duplicating a row. The cart renders as a table with a running total and a
per-row **Remove**; **Complete Sale** only enables once the cart is non-empty, and posts
`{items: [{product_id, quantity}, ...]}` to the same `POST /api/transactions` the API already
exposed, then redirects to the new transaction's detail page (`/transactions/{id}`) using the
`id` from the `201` response.

**Why stock is checked client-side too, even though the server is the real guarantee:**
`createFromItems()` (Step 3) is still the authority — nothing about the client trusts its own
math for correctness. But telling a user "only 5 in stock" *before* they click Complete Sale,
using the `data-stock` already sitting in the `<option>` the page rendered, is a strictly better
experience than only finding out via a `422` after submitting a whole cart. It's the same
"friendly check in front of the real guarantee" shape as the delete guards (workshop 03), just
happening in the browser this time instead of the controller.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/transactions/create`, pick a product, click **Add** — it appears in
the cart table with a running total; try adding a quantity larger than the shown stock — a red
message names the product and how much is already in the cart; click **Complete Sale** — you
land on the new transaction's detail page showing exactly what was added.

---

## Key Laravel concepts covered

- Business logic used by more than one entry point (an HTTP controller *and* a seeder) belongs
  on the model as a dedicated method, not duplicated per-caller — while still not introducing a
  separate service-layer class
- `lockForUpdate()` for pessimistic row locking, and specifically *why* `DB::transaction()` by
  itself doesn't prevent a concurrent overselling race
- Snapshotting a value (price) at the moment it matters instead of referencing it live, so
  later changes to the source don't rewrite history
- Custom exceptions with a `render($request)` method, so a deeply-nested business-rule failure
  becomes a clean HTTP response without manual exception-catching in the controller
- `Route::apiResource(...)->only([...])` to make a restricted action set an enforced routing
  fact (a real `405`), not just an unwritten convention
- `cascadeOnDelete()` vs `restrictOnDelete()` as a rule of thumb: does the child row have
  independent existence, or is it meaningful history on its own
- Re-applying an established pattern (the two-layer delete guard) a second time, unchanged, to
  confirm it generalizes rather than reinventing something similar
- A seeder that exercises the same code path as the real API is real code with real side
  effects — verify it the same way, don't just check "it ran without error"
- A create page doesn't have to be a single-record form — a checkout-style page builds a
  variable-length list client-side (with its own light client-side validation for UX) before
  ever making one request, and the server's validation/business rules remain the only real
  authority regardless of what the client already checked

## What's next

With real transaction data now flowing through `/transactions/create`, the Dashboard (workshop
04) is the natural place to add the sales metrics it was deliberately scoped to skip — today's
revenue, transaction count, and top-selling products — now that real transaction data exists to
compute them from.
