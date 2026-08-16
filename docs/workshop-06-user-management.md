# Workshop 06 — Learn Laravel by Building a POS: User Management

## Objective

Every workshop so far built a **new** domain model from scratch (Category, Product,
Transaction). This one is different: Laravel already ships a `User` model, migration, and
factory out of the box — created for Sanctum/auth, but never turned into a manageable
resource. This workshop turns that scaffolding into a fourth full CRUD module, following the
exact same conventions as Category and Product, with one new wrinkle neither of them had:
**a secret field**. You will learn:

- Applying the established module pattern (Form Requests → Resource → controller → routes →
  seeder → tests → list/search/sort/pagination UI) to a model that already exists, instead of
  one freshly scaffolded with `make:model`
- Laravel's `'hashed'` cast — why `$user->password = 'plaintext'` followed by `save()` never
  stores plaintext, and why that means the controller never calls `Hash::make()` itself
- Validating a password with `Illuminate\Validation\Rules\Password` and Laravel's built-in
  `confirmed` rule (`password` + `password_confirmation`)
- Making a sensitive field **optional on update** at the *frontend* layer (only send `password`
  when the user actually typed one) rather than adding backend special-casing for "empty string
  means unchanged"
- Recognizing when a module *doesn't* need a piece of the established pattern — User has no
  delete guard, because (for now) nothing references it
- Why this workshop deliberately stops short of authentication, and what that sets up for
  workshop 07

Each step below states **what we did**, **why**, and **how to validate it yourself** — run the
validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Workshops 01-05 completed: Category, Product, Dashboard, and Transaction all in place and
  passing (`php artisan test` — 53 tests before this workshop's additions)

## The field set and the two design decisions

**User fields (already defined by Laravel):** `name` (required), `email` (required, unique),
`password` (required, never returned in a response). No new columns are added — the existing
`users` migration and `App\Models\User` (using the newer PHP-attribute style,
`#[Fillable(['name', 'email', 'password'])]` and `#[Hidden(['password', 'remember_token'])]`,
instead of `protected $fillable`/`protected $hidden` properties) already cover everything a
management CRUD needs.

**Decision 1 — no role/permission field yet.** A POS naturally wants roles (admin vs. cashier),
but nothing in this app checks a role today — there's no authorization logic to hang it off of.
Adding a `role` column now would be exactly the kind of speculative, unused field this project's
conventions warn against. Workshop 07 (authentication) or a later authorization workshop is the
right place to introduce it, once there's a real distinction to enforce.

**Decision 2 — no delete guard.** Category and Product both check for dependents before
deleting (`$category->products()->first()`, `$product->transactionItems()->first()`) because
something else in the schema has a foreign key pointing at them. Nothing points at `users` yet
— no `Transaction` isn't tied to the user who created it, because there's no authenticated
request to attach it to. So `UserController::destroy()` is a plain delete, same shape as the
others minus the guard clause. That will change the moment auth exists: the first real guard
this table needs is "you can't delete the account you're currently logged in as" — a check that
requires knowing who's logged in, which is exactly what's missing until workshop 07.

---

## Step 1 — What's already there, and what this workshop adds

**What we did:** confirmed the starting point before writing anything:
```bash
cat app/Models/User.php
cat database/migrations/0001_01_01_000000_create_users_table.php
cat database/factories/UserFactory.php
```
All three already exist, unmodified, from Laravel's default installation. What's missing is
everything *else* the module pattern requires: `StoreUserRequest`/`UpdateUserRequest`,
`UserResource`, `UserController`, API + web routes, a dedicated `UserSeeder`, list/form views +
JS, and `tests/Feature/UserTest.php`.

**Why start by reading, not generating:** running `php artisan make:model User -mfc --api -R`
here would happily overwrite the model, migration, and factory Laravel already ships — the
scaffolding command is for a model's *first* appearance, not a retrofit. Everything in this
workshop is added by hand around the existing three files instead.

**Validate it:**
```bash
php artisan tinker --execute="echo (new ReflectionClass(App\Models\User::class))->getAttributes()[0]->newInstance()->columns[0] ?? 'n/a';"
```
Confirms the model loads without errors before we start layering a controller stack on top of
it.

---

## Step 2 — Form Requests: validating a secret field

**What we did:**
```php
// app/Http/Requests/StoreUserRequest.php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', Password::defaults(), 'confirmed'],
    ];
}
```
```php
// app/Http/Requests/UpdateUserRequest.php
public function rules(): array
{
    return [
        'name' => ['sometimes', 'required', 'string', 'max:255'],
        'email' => [
            'sometimes', 'required', 'string', 'email', 'max:255',
            Rule::unique('users', 'email')->ignore($this->route('user')),
        ],
        'password' => ['sometimes', 'required', 'string', Password::defaults(), 'confirmed'],
    ];
}
```

**Why `Password::defaults()`:** this is Laravel's own password-strength rule object (minimum
length, and — configurable app-wide — requiring letters/numbers/symbols). It's the same rule
Laravel's own auth scaffolding (Breeze/Fortify) uses, so a POS built with plain Laravel
conventions already gets it for free instead of hand-rolling `min:8`.

**Why `confirmed`:** it's a built-in Laravel rule that looks for a sibling field named
`{field}_confirmation` — here, `password_confirmation` — and fails validation if the two don't
match. No custom rule needed.

**Why `email` uses `sometimes` + `Rule::unique(...)->ignore(...)` on update, exactly like
Category's `name`:** it's the same "keep your own value" problem — a user editing their own
profile without changing their email shouldn't trip the uniqueness check against themselves.

**Why `password` is `sometimes` (not `nullable`) on update:** `sometimes` means "validate this
field only if the request includes the key at all." The frontend (Step 7) is what decides
whether to include it — it only sends `password`/`password_confirmation` when the user actually
typed a new password, so an update that doesn't touch the password never sends the key, and
`sometimes` skips it entirely rather than needing to special-case an empty string.

**Validate it:**
```bash
php artisan test --filter=test_it_requires_a_name_email_and_password_to_create_a_user
php artisan test --filter=test_it_rejects_a_password_confirmation_mismatch
```

---

## Step 3 — Resource: what a user response looks like

**What we did:**
```php
// app/Http/Resources/UserResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
```

**Why this is worth calling out even though it looks just like `CategoryResource`:** the model
itself already declares `#[Hidden(['password', 'remember_token'])]`, so even a bare
`return $user` from a controller wouldn't leak the password hash. The Resource is still written
out explicitly and kept in the same shape as every other module's Resource — one predictable
place that defines the API contract, rather than leaning on a model attribute that could change
for reasons unrelated to this endpoint.

**Validate it:**
```bash
php artisan test --filter=test_it_does_not_expose_the_password_in_the_response
```

---

## Step 4 — Controller: the `'hashed'` cast does the hashing

**What we did:**
```php
// app/Http/Controllers/UserController.php
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());

    return UserResource::make($user)->response()->setStatusCode(Response::HTTP_CREATED);
}

public function update(UpdateUserRequest $request, User $user)
{
    $user->update($request->validated());

    return UserResource::make($user);
}
```
No `Hash::make()` call anywhere in the controller.

**Why:** `App\Models\User` casts `password` as `'hashed'` (visible in its `casts()` method).
That cast runs on **every** assignment to the attribute — mass assignment via `create()`/
`update()` included — and hashes the value automatically, using `Hash::isHashed()` internally
to avoid re-hashing something that's already a hash. So `$request->validated()['password']`
being plain text is exactly correct; by the time it reaches the database it's a bcrypt hash.
This is the same idea as Product's `'price' => 'decimal:2'` cast from workshop 03 — casts are
where a model enforces its own storage rules, so every caller gets them for free instead of
each one remembering to convert.

**Index and search:** same shape as Category/Product, but the search term matches **either**
`name` or `email` — this module is the first one where the natural lookup could reasonably be
either field:
```php
->when(
    $request->string('search')->trim()->toString(),
    fn ($query, $search) => $query->where(
        fn ($query) => $query->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
    )
)
```
The extra nested closure (`$query->where(fn ($query) => ...)`) groups the two `orWhere` calls in
parentheses in the generated SQL — without it, a bare `->where('name', ...)->orWhere('email',
...)` would OR against the *entire* rest of the query (including pagination's implicit
conditions), not just against each other.

**`destroy()` — the plain version:**
```php
public function destroy(User $user)
{
    $user->delete();

    return response()->noContent();
}
```
No dependent check, per Decision 2 above.

**Validate it:**
```bash
php artisan tinker --execute="
DB::enableQueryLog();
\$user = App\Models\User::create(['name' => 'Test', 'email' => 'hash-check@example.com', 'password' => 'plaintext-password']);
echo \$user->password !== 'plaintext-password' ? 'hashed' : 'NOT HASHED';
echo PHP_EOL . (Illuminate\Support\Facades\Hash::check('plaintext-password', \$user->password) ? 'verifies' : 'FAILS TO VERIFY');
"
php artisan test --filter=test_it_searches_users_by_email
```
Expected: `hashed` then `verifies`.

---

## Step 5 — Seeder: promoting the ad-hoc `DatabaseSeeder` line to a real `UserSeeder`

**What we did:**
```php
// database/seeders/UserSeeder.php
public function run(): void
{
    $users = [
        ['name' => 'Test User', 'email' => 'test@example.com'],
        ['name' => 'Alice Nguyen', 'email' => 'alice@possystem.test'],
        // ...3 more
    ];

    foreach ($users as $user) {
        User::query()->firstOrCreate(
            ['email' => $user['email']],
            [...$user, 'password' => 'password'],
        );
    }

    User::factory()->count(45)->create();
}
```
```php
// database/seeders/DatabaseSeeder.php — before
User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
$this->call(CategorySeeder::class);
// ...

// after
$this->call(UserSeeder::class);
$this->call(CategorySeeder::class);
// ...
```

**Why:** before this workshop, `DatabaseSeeder` created exactly one ad-hoc `User` inline —
every other module gets a dedicated `{Model}Seeder` with the established "5 named + 45 factory
= 50" shape (workshops 01 and 03). Folding user creation into that same pattern means the
`users` table now exercises pagination in the UI just like `categories` and `products` do, and
the one login-worthy account (`test@example.com`) is preserved as the first named row instead of
being a special case elsewhere. `firstOrCreate` keyed on `email` keeps re-seeding idempotent,
matching Category's keyed-on-`name` and Product's keyed-on-`sku` approach.

**Why `'password' => 'password'` (plain text) is fine here:** same reason as Step 4 — the
`'hashed'` cast on the model hashes it during `firstOrCreate()`'s insert, so every seeded user
(named or factory-generated) ends up with a real bcrypt hash, not a literal `"password"` string
in the database.

**Validate it:**
```bash
php artisan migrate:fresh --seed
php artisan tinker --execute="echo App\Models\User::count();"
```
Expected: `50`. Then confirm the seeded password actually works:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'test@example.com')->first();
echo Illuminate\Support\Facades\Hash::check('password', \$user->password) ? 'verifies' : 'FAILS';
"
```

---

## Step 6 — Routes and tests

**What we did:**
```php
// routes/api.php
Route::apiResource('users', UserController::class);
```
```php
// routes/web.php
Route::get('/users', fn () => view('users.index'))->name('users.list');
Route::get('/users/create', fn () => view('users.form'))->name('users.create');
Route::get('/users/{user}/edit', fn (User $user) => view('users.form', ['user' => $user]))->name('users.edit');
```
Named `users.list` (not `users.index`) from the start — the same collision workshop 02 hit and
workshop 03 already knew to avoid, since `apiResource` auto-registers `users.index` for the JSON
endpoint.

`tests/Feature/UserTest.php` mirrors `CategoryTest.php`'s coverage shape (list, `updated_at`
ordering, search — by name *and* by email, sort + invalid-sort fallback, create, validation
failures, duplicate-email rejection on create/update, show, update, delete) plus tests specific
to the password: that a response never includes it, that creating/updating actually hashes it
(`Hash::check`), that a mismatched confirmation is rejected, and that updating *without* a
password leaves the existing hash untouched.

**Validate it:**
```bash
php artisan route:list --path=users
php artisan test --filter=UserTest
php artisan test
```
Expected: 8 routes (5 API + 3 web) with no name collisions, all `UserTest` cases passing, and
the full suite (70 tests as of this workshop) still green.

---

## Step 7 — The UI: the one field that's conditionally sent

**What we did:** `resources/views/users/index.blade.php` + `resources/js/users.js` reuse the
list-page shape from Category/Product (debounced + submit search, sortable Name header,
Prev/Next pagination off `meta`), with Email replacing Description as the second column.
`resources/views/users/form.blade.php` adds `password` and `password_confirmation` fields on
top of the usual `name`/`email` inputs — both `required` on create, both optional on edit (with
a "leave blank to keep current password" hint next to the label).

`resources/js/users-form.js` is where the "optional on edit" behavior actually lives:
```js
const payload = { name: nameField.value, email: emailField.value };

if (passwordField.value) {
    payload.password = passwordField.value;
    payload.password_confirmation = passwordConfirmationField.value;
}
```

**Why this belongs in the frontend, not the backend:** `UpdateUserRequest`'s `password` rule is
`sometimes` (Step 2) specifically so that "field absent from the request" means "don't touch
it." The alternative — always sending `password: ''` and teaching the backend that an empty
string means "no change" — would need extra rule logic (`nullable` plus a conditional exclusion
before the update call) just to recreate what "don't send the key" already gives for free.
Keeping the decision at the one place that actually knows the user's intent (did they type
anything?) is simpler than smuggling that intent through an empty string.

**Validate it:**
```bash
npm run build
php artisan serve
```
Open `http://127.0.0.1:8000/users` — search by typing part of a name *or* an email, sort by
Name, paginate through the 50 seeded rows. Open `/users/create`, fill in all four fields, save —
you land back on the list with the new user at the top. Edit an existing user, change only the
name, leave both password fields blank, save — then confirm in `php artisan tinker` that
`Hash::check('password', $user->password)` still returns `true` for that user (the original
seeded password survived the update).

---

## Key Laravel concepts covered

- Retrofitting the established module pattern onto a model Laravel already generated, instead
  of scaffolding a brand-new one — recognizing what's already there vs. what the pattern still
  requires
- The `'hashed'` cast: an attribute cast that hashes on every assignment (idempotently, via
  `Hash::isHashed()`), so `create()`/`update()` never need an explicit `Hash::make()` call — the
  same "cast owns the storage rule" idea as `'price' => 'decimal:2'` from workshop 03
- `Illuminate\Validation\Rules\Password` and the built-in `confirmed` rule for password
  validation, reusing Laravel's own auth-scaffolding conventions rather than inventing new rules
- Grouping `where`/`orWhere` inside a nested closure to control operator precedence in the
  generated SQL, needed the moment a search spans more than one column
- Recognizing when an established piece of the pattern (a delete guard) genuinely doesn't apply
  yet, and documenting *why*, instead of adding a guard against nothing or silently skipping the
  question
- Deciding whether a "should this field change?" decision belongs in the backend (validation
  rules operating on what's present in the request) or the frontend (deciding what to include in
  the request in the first place) — and choosing the frontend here because it's the only side
  that knows user intent
- Promoting an ad-hoc, single-purpose seeder line into the same named-records-plus-factory-batch
  shape every other module already uses, for the same reason: a paginated UI needs enough rows
  to actually exercise paging

## What's next

User management is now a complete CRUD module, but the app still has no concept of "who is
logged in" — every request today is anonymous, and any client can hit any endpoint. Workshop 07
wires up **authentication** on top of the User model this workshop just finished:

- Login/logout using the `password` field and `Hash::check()` this workshop already relies on
- Protecting routes with Sanctum (installed since the very first workshop, unused until now) or
  session-based guards
- A login page, and redirecting unauthenticated visitors to it instead of `/dashboard`
- The first real use for a delete guard on `users`: blocking a logged-in user from deleting
  their own account — the exact gap Decision 2 in this workshop left open on purpose
- Once "who is logged in" exists, it becomes possible to record *which* user rang up a
  Transaction (workshop 05) — a `user_id` foreign key that couldn't mean anything before there
  was a real session to attach it to
