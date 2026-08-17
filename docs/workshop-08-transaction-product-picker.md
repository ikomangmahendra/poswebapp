# Workshop 08 — Learn Laravel by Building a POS: Searchable Product Picker

## Objective

Workshop 05's checkout page picked a product from a single `<select>` listing every product in
the catalog. That's fine with a handful of rows in a demo seed, but it doesn't match how a real
cashier works: they look a product up by its SKU first (that's what's printed on the shelf label
or scanned), and only fall back to searching by name when the SKU is unclear, mistyped, or
missing. A dropdown holding the entire catalog doesn't support either of those — it doesn't
search, and it doesn't know about SKUs at all. This workshop replaces that `<select>` with a
debounced, type-to-search picker. You will learn:

- Extending an existing single-column search (`name`) into a multi-column search (`name` **or**
  `sku`) using the same grouped-`where` shape this app already established for User's
  name-or-email search
- Recognizing when a "just dump the whole related table into a `<select>`" shortcut has outgrown
  its use case, and replacing it incrementally rather than rewriting the page
- Modeling a small client-side "no selection / has a selection" state machine in plain JS, reusing
  this app's existing debounce/search-guard constants instead of inventing new ones
- Why a frontend UX change like this needs **zero** changes to the actual sale logic
  (`Transaction::createFromItems()`) — the picker only changes how a `product_id`/`quantity` pair
  gets built client-side, not what happens once it's submitted

Each step below states **what we did**, **why**, and **how to validate it yourself**.

## Prerequisites

- Workshops 01-07 completed and passing (`php artisan test` — 80 tests, plus the `transactions.user_id`
  follow-up noted at the end of workshop 07)

---

## Step 1 — Product search: name, or SKU

**What we did:**
```php
// app/Http/Controllers/ProductController.php
->when(
    $request->string('search')->trim()->toString(),
    fn ($query, $search) => $query->where(
        fn ($query) => $query->where('name', 'like', "%{$search}%")
            ->orWhere('sku', 'like', "%{$search}%")
    )
)
```

**Why:** the picker's whole point is "search by SKU first, fall back to name" — so the API it
calls has to actually search both columns. This isn't a bespoke picker-only endpoint: it's the
same `GET /api/products?search=` the Products list page already calls, upgraded in place, the
same way `UserController::index()` already searches `name` **or** `email` (workshop 06). Grouping
both conditions inside one `->where(fn ($q) => ...)` closure keeps them parenthesized together in
the generated SQL — without the wrapper, an `orWhere` here would silently widen *every* other
`where` clause already applied to the query (e.g. this endpoint's `paginate`/`orderBy` still work
correctly, but a naive top-level `orWhere` next to unrelated conditions is the classic way this
bug sneaks in on a busier query).

**Validate it:**
```bash
php artisan test --filter=test_it_searches_products_by_sku
php artisan test --filter=test_it_searches_products_by_name
```

---

## Step 2 — Dropping the server-rendered product list

**What we did:**
```php
// routes/web.php — before
Route::get('/transactions/create', function () {
    return view('transactions.create', ['products' => Product::orderBy('name')->get()]);
})->name('transactions.create');

// after
Route::get('/transactions/create', function () {
    return view('transactions.create');
})->name('transactions.create');
```

**Why:** `CLAUDE.md` already called this out as the natural upgrade path — "a searchable/paginated
picker would be the natural upgrade once a module has too many related rows for one dropdown."
Fetching every product into the page on load doesn't scale past a small catalog, and it doesn't
actually solve the real problem: a cashier scrolling or typeahead-filtering a giant native
`<select>` still has to *find* the right row themselves. An on-demand, server-searched picker
solves the actual workflow instead of just moving the same list into the browser.

**Validate it:** load `/transactions/create` and confirm the page renders with no product list —
the "Product" field is now a search box, not a dropdown.

---

## Step 3 — The search-and-select picker

**What we did:** replaced the `<select id="product-select">` with a search input and a results
list:
```html
<form id="product-search-form" class="relative">
    <input type="search" id="product-search" minlength="3" ...>
    <ul id="product-search-results" class="hidden absolute ..."></ul>
</form>

<div id="selected-product" class="hidden items-end gap-2 mt-3">
    <!-- "Selected: {name} … Change" + Qty + Add, shown only once a product is picked -->
</div>
```
```js
// resources/js/transactions-create.js
const SEARCH_MIN_LENGTH = 3;
const SEARCH_DEBOUNCE_MS = 300;

productSearchInput.addEventListener('input', () => {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => applySearch(productSearchInput.value.trim()), SEARCH_DEBOUNCE_MS);
});
```
Selecting a result (`selectProduct()`) hides the search form and shows the "Selected: …" panel;
`resetProductPicker()` does the reverse. The existing `Add` button logic (stock-vs-cart-quantity
validation, pushing into the `cart` array) is untouched — it just reads from a `selectedProduct`
object instead of a `<select>` option's `data-*` attributes.

**Why these exact constants:** `SEARCH_MIN_LENGTH`/`SEARCH_DEBOUNCE_MS` and the
clear-then-restart-timer debounce pattern are copied verbatim from every list page's search box
(`categories.js`, `products.js`, `users.js`). Reusing them here — for a one-off picker widget
rather than a full list+pagination page — keeps the "type 3 characters, wait 300ms, then search"
behavior consistent everywhere in the app a cashier or admin types into a search box.

**Why a plain click-to-select list instead of a full keyboard-navigable combobox:** this app's
frontend is deliberately plain vanilla JS with no framework and no component library. A clickable
`<ul>` is the simplest thing that solves "find a product by SKU or name without loading the whole
catalog." A real POS terminal optimized for speed would likely want arrow-key navigation and
Enter-to-select next — that's a legitimate future upgrade, deliberately left out here to keep this
step's scope to the actual reported problem (findability), not a full combobox rebuild.

**Validate it:** on `/transactions/create`, type a known SKU (e.g. `SNK-001`) — one matching result
appears. Clear it and type part of a product name instead (e.g. `latte`) — the same product is
found by name. Typing fewer than 3 characters shows no dropdown at all (same guard as every other
search box in the app).

---

## Step 4 — Resetting the picker after adding an item

**What we did:** the `Add` button's success path now calls `resetProductPicker()` (clear the
search box, hide the "Selected" panel, re-show and refocus the search input) instead of just
resetting the quantity field back to `1`.

**Why:** the real workflow is a loop — search an item, add it, search the *next* item — not "keep
the last product selected and let the cashier manually clear it." Resetting fully after every
successful `Add` matches that loop and removes a footgun the old `<select>` didn't have: without
resetting, a second accidental click on `Add` would silently add the same product again, since
the picker would still show it as selected.

**Validate it:** add a product to the cart and confirm the search box reappears, empty and
focused, ready to search for the next item — not still showing the product you just added.

---

## Step 5 — Tests and a full manual walkthrough

**What we did:** added `test_it_searches_products_by_sku` to `ProductTest`, then manually
exercised the whole flow in a real browser: logged in, searched a product by SKU, searched a
second product by name, added both to the cart, and completed the sale — confirming the resulting
`Transaction` detail page showed the correct cashier, items, and total.

**Why manual verification mattered here specifically:** this is a frontend interaction change
(debounce timing, DOM show/hide state, click handling) that Feature tests hitting the JSON API
directly can't exercise — `ProductTest`'s new case proves the *API* supports SKU search, but only
a real browser session proves the *picker* built on top of it actually finds, selects, and adds a
product correctly end to end.

**Validate it:**
```bash
php artisan test
```
Expected: all 81 tests passing (80 before this workshop, plus this workshop's SKU-search case).

---

## Key Laravel concepts covered

- Growing a single-column `->where('column', 'like', ...)` search into a grouped
  `->where(fn ($q) => $q->where(...)->orWhere(...))` multi-column search — reusing, not
  reinventing, the pattern User's name-or-email search already established
- Recognizing when a "just eager-load and dump the whole related table" shortcut (fine for a
  small, bounded list like Category) stops being the right shape once the related table is large
  or needs to be searched by more than one field, and replacing it with an on-demand API-backed
  picker instead of a bigger dropdown
- A small client-side state machine (search-mode vs. selected-mode) built from `classList`
  toggles on two sibling elements, rather than trying to keep a native `<select>`'s options in
  sync with asynchronously fetched data
- Reusing an established debounce/min-length search pattern for a one-off UI widget instead of a
  full list+pagination page, so the same "type 3 characters, wait 300ms" behavior feels consistent
  everywhere the app has a search box
- Confirming that a frontend-only UX change genuinely didn't need to touch business logic:
  `Transaction::createFromItems()`, its stock-locking, and its price-snapshotting are completely
  unaffected, because the picker only changes how the `{product_id, quantity}` payload gets built,
  not what the backend does with it

## What's next

This workshop was scoped to the one reported problem — finding a product to sell — and
deliberately left the picker's interaction model simple (click-to-select, no keyboard
navigation). The two items already flagged as open at the end of workshop 07 are still open and
still the most natural candidates for a workshop 09: a `role`/permission field on `User` (there's
still no authorization check anywhere, only authentication), and a delete guard preventing a user
from deleting the account they're currently logged in as.
