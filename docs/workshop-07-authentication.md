# Workshop 07 — Learn Laravel by Building a POS: Authentication

## Objective

Every workshop so far treated every request as trusted — anyone who could reach the app could
list, create, edit, or delete anything, including other people's accounts (workshop 06). This
workshop closes that gap: a login page, session-based authentication guarding **every** page and
**every** API endpoint, and the app redirecting straight to `/login` the moment it starts if
nobody's signed in. You will learn:

- Why a server-rendered Blade app with same-origin `fetch()` calls should reach for Laravel's
  plain session `web` guard, not Sanctum API tokens — and what Sanctum is actually for
- The three built-in middleware pieces that make this work with almost no new code:
  `auth`, `guest`, and `redirectGuestsTo()`
- The one non-obvious piece: `routes/api.php` doesn't get a session by default, so protecting
  the JSON API the frontend already calls requires `statefulApi()` — Sanctum's "first-party
  frontend" recipe, repurposed here for a Blade app instead of a JS SPA
- Why session-based auth means every mutating `fetch()` call across the *entire* existing
  frontend now needs a CSRF header — and the small, non-entry helper module that avoids
  repeating that boilerplate seven times
- Deciding where authentication logic belongs: a `FormRequest` validates shape, a controller
  performs the side effect (`Auth::attempt()`) — not blurring the two
- Why every existing Feature test needed `actingAs()`, and the one file (`UserTest`) where the
  test's own authenticated actor could silently pollute the very assertions it was making

Each step below states **what we did**, **why**, and **how to validate it yourself** — run the
validation commands after reading each step to confirm your environment matches.

## Prerequisites

- Workshops 01-06 completed: Category, Product, Dashboard, Transaction, and User all in place
  and passing (`php artisan test` — 70 tests before this workshop's additions)

## The two design decisions

**Decision 1 — session guard, not Sanctum tokens.** Sanctum solves "a JS SPA on a different
origin, or a mobile app, needs to authenticate against this API." This app has neither: every
page is server-rendered Blade, and every `fetch()` call in `resources/js/*.js` already goes to
the same origin that served the page. A plain session cookie — Laravel's default `web` guard —
is the simplest thing that's actually correct here. Sanctum remains installed (it has been since
the very first workshop) and its stateful-frontend middleware gets used in Step 5, but token
issuance stays unused until a real external client shows up.

**Decision 2 — no self-registration.** There's no `/register` page. The User module (workshop
06) is how accounts get created — an admin adds staff, staff don't sign themselves up. That's
the honest shape of a POS: whoever manages the store's user list decides who gets an account.

---

## Step 1 — Login controller and request

**What we did:**
```php
// app/Http/Requests/LoginRequest.php
public function rules(): array
{
    return [
        'email' => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ];
}
```
```php
// app/Http/Controllers/LoginController.php
public function create()
{
    return view('auth.login');
}

public function store(LoginRequest $request)
{
    if (! Auth::attempt($request->validated())) {
        throw ValidationException::withMessages([
            'email' => 'These credentials do not match our records.',
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard'));
}

public function destroy(Request $request)
{
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
}
```

**Why the request only validates shape:** every other `FormRequest` in this app (`StoreCategoryRequest`,
`UpdateUserRequest`, ...) is a plain validator — the actual side effect happens in the
controller. `LoginRequest` follows that same line: it checks that `email`/`password` look like
an email and a non-empty string, nothing more. `Auth::attempt()` — the part that actually touches
authentication state — stays in `LoginController::store()`, not smuggled into the request class
the way some Laravel starter kits do it. Keeping FormRequests as pure validators, no exceptions,
is simpler to reason about than "usually just validation, except this one also logs you in."

**Why `throw ValidationException::withMessages(...)` instead of a manual redirect-back:**
Laravel's exception handler already knows what to do with a `ValidationException` — redirect
back with the error in the session (web request) or return a `422` (JSON request) —
automatically, the same "no try/catch needed" pattern `InsufficientStockException` used in
workshop 05.

**Why `session()->regenerate()` on login and `regenerateToken()` on logout:** regenerating the
session ID after a successful login prevents session fixation (an attacker who fixed a session
ID before login can't reuse it as an authenticated session afterward); regenerating the CSRF
token on logout means a lingering browser tab can't replay a stale token against the now-logged-
out session.

**Validate it:**
```bash
php artisan test --filter=test_it_logs_in_with_valid_credentials
php artisan test --filter=test_it_rejects_an_incorrect_password
```

---

## Step 2 — The login view: a plain form, not a fetch call

**What we did:** `resources/views/auth/login.blade.php` — a standard `<form method="POST"
action="{{ route('login.store') }}">` with `@csrf`, `email`/`password` inputs, and
`@if ($errors->any())` printing validation messages. No JS file, no `fetch()`.

**Why this is different from every other form in this app:** `categories-form.js`,
`products-form.js`, and `users-form.js` all submit via `fetch()` to a JSON API endpoint. Login
doesn't, on purpose — those forms edit a resource *through* an already-authenticated session;
login is what *creates* that session in the first place. A plain form post is the traditional,
well-understood mechanism for that (it's what Laravel's own Breeze scaffolding does too), and it
means the one endpoint responsible for authenticating a browser doesn't depend on any
JavaScript running first. `@csrf` renders a hidden `_token` input — the classic form-based CSRF
mechanism, distinct from (but validated the same way as) the JSON API's `X-CSRF-TOKEN` header
introduced in Step 5.

**Validate it:**
```bash
php artisan test --filter=test_it_shows_the_login_form_to_a_guest
```

---

## Step 3 — Route protection: `auth`, `guest`, and where `/` sends you

**What we did:**
```php
// routes/web.php
Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    // ...every other existing web route (dashboard, categories, products, transactions, users)
});
```
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->redirectGuestsTo(fn () => route('login'));
    $middleware->statefulApi();
})
```

**Why `/` checks `Auth::check()` directly instead of always redirecting to `/dashboard`:**
before this workshop, `/` was `Route::redirect('/', '/dashboard')` unconditionally, and
`/dashboard` had no protection at all. The literal ask — "the app should redirect to login on
start" — is satisfied more directly by checking auth state at `/` itself than by bouncing
through `/dashboard` and letting *that* route's middleware redirect a second time. Same
destination, one fewer hop.

**Why `guest` wraps `/login`:** `RedirectIfAuthenticated` (Laravel's `guest` middleware) sends an
already-logged-in visitor away from the login form — to whatever `Route::has('dashboard')`
resolves to, which is already this app's dashboard route, no extra config needed. Trying to
view the login page while already logged in doesn't make sense; bouncing to `/dashboard` instead
of showing a form is the expected behavior.

**Why `redirectGuestsTo()` had to be added explicitly:** Laravel's `auth` middleware, out of the
box in this minimal `bootstrap/app.php`-based skeleton, throws an `AuthenticationException` with
no redirect target configured — hitting a protected page as a guest would return a bare `401`
with no body, not a redirect to a login form. `redirectGuestsTo(fn () => route('login'))` is
what tells the framework where "please log in" actually is. (A JSON request, e.g. from `fetch()`,
still gets a clean `401` — the redirect only applies to a normal browser navigation, checked via
`$request->expectsJson()`.)

**Why `throttle:5,1` only on the login *POST*, not the GET:** rate-limiting is about slowing down
repeated login *attempts* (a brute-force guess of the password), not repeated *views* of the form.

**Validate it:**
```bash
php artisan route:list
php artisan test --filter=test_a_guest_is_redirected_to_login_from_a_protected_page
php artisan test --filter=test_the_root_url_redirects_a_guest_to_login
php artisan test --filter=test_it_redirects_an_already_authenticated_user_away_from_login
```

---

## Step 4 — Protecting the API the frontend already calls

**What we did:**
```php
// routes/api.php
Route::middleware('auth')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // ...the rest of the dashboard endpoints
});
```
Left outside the group: the framework's original example route, `GET /api/user` on
`auth:sanctum`, untouched.

**Why this matters as much as protecting the pages:** the Blade pages are just a UI in front of
this JSON API — `categories.js` calls `/api/categories` directly, and so does any other client
that knows the URL. Protecting `/categories` (the page) while leaving `/api/categories` (the
data) open would make login purely cosmetic: `curl http://.../api/categories` would still return
every category to anyone, logged in or not. Wrapping every module's `apiResource` (and the
dashboard's aggregate endpoints) in the same `auth` middleware is what actually makes
"authentication" mean something.

**Validate it:**
```bash
php artisan test --filter=test_a_guest_gets_a_401_from_a_protected_api_endpoint
```
Manually:
```bash
curl -s -o /dev/null -w "%{http_code}\n" -H "Accept: application/json" http://127.0.0.1:8000/api/categories
```
Expected: `401`, with no session cookie sent.

---

## Step 5 — Why `auth` middleware on `api.php` needed `statefulApi()`

**What we did:** added `$middleware->statefulApi();` alongside `redirectGuestsTo()` in
`bootstrap/app.php` (Step 3).

**Why this was necessary, not optional:** `routes/web.php` runs under the `web` middleware
group by default, which starts a session on every request. `routes/api.php` runs under the `api`
group instead, which does **not** — no session, no cookies, no CSRF handling. Before this line,
logging in (a `web.php` request) would correctly start a session, but the very next
`fetch('/api/categories')` call (an `api.php` request) would run entirely outside that session's
awareness — `Auth::check()` inside the `auth` middleware would see a guest, and every
already-logged-in user would get `401`s from the API their own page just tried to call.

`statefulApi()` turns on Sanctum's `EnsureFrontendRequestsAreStateful` middleware for `api.php`
requests. Despite the name "Sanctum" (usually associated with API tokens), this specific piece
has nothing to do with tokens — it detects requests coming from a "frontend" (matched by
`Referer`/`Origin` header against `config('sanctum.stateful')`, which defaults to
`localhost`/`127.0.0.1` plus this app's own `APP_URL` host) and, only for those, layers in the
same session/cookie/CSRF middleware the `web` group already has. It's the standard recipe for
"a JS client on the same domain as the API" — normally written for a real SPA framework, reused
here verbatim because a Blade page calling its own API via `fetch()` is the exact same shape.

**Validate it:**
```bash
php artisan tinker --execute="echo implode(',', config('sanctum.stateful'));"
```
Then, with a real browser session (Step 7 gives you one): open `/categories` while logged in and
confirm the page's list loads — that's `statefulApi()` working. Removing the line and reloading
the page would turn every list into "no categories" (a silent `401` the frontend JS doesn't
surface, since it doesn't check `response.ok`) — a good way to see the failure mode once, then
put the line back.

---

## Step 6 — CSRF: the cost of session-based API auth

**What we did:** every Blade view's `<head>` gained one line:
```html
<meta name="csrf-token" content="{{ csrf_token() }}">
```
A new, tiny helper module:
```js
// resources/js/csrf.js
export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}
```
And every existing **mutating** `fetch()` call — `categories.js`'s delete, `categories-form.js`'s
create/update, the same pair for `products`/`users`, and `transactions-create.js`'s checkout
POST — gained an import and a header:
```js
import { csrfToken } from './csrf';
// ...
headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() }
```
Read-only `fetch()` calls (every list/show page's `GET`) were left untouched.

**Why session auth brings CSRF along whether you want it or not:** a token in an `Authorization`
header (what Sanctum's *token* mode, or `auth:sanctum`, would use) can't be replayed by a
malicious third-party site, because that site has no way to read or set your `Authorization`
header. A *session cookie* is different — the browser attaches it automatically to any request
to this domain, from any page, including one an attacker controls. That's exactly what CSRF
protection defends against, and it's why `statefulApi()` (Step 5) pulled in `VerifyCsrfToken`
along with the session middleware — protecting the API with a session necessarily means
protecting it from CSRF too, there's no version of "session auth, skip CSRF" that's actually
safe.

**Why a shared `csrf.js` helper instead of repeating the same three lines in seven files:** this
is the one piece of logic needed identically everywhere a mutating request is made. It's *not*
registered in `vite.config.js`'s `input` array — it stays a plain module that entry files
`import` from, so the "each page loads only its own module's JS file" rule (every `<script>` tag
still points at exactly one entry) still holds; Vite bundles the shared code into each entry that
needs it.

**Validate it:**
```bash
php artisan test --filter=CategoryTest
php artisan test --filter=UserTest
```
Manually, once logged in in a browser: open `/categories`, delete a row, confirm it disappears
(that's the `X-CSRF-TOKEN` header working) — then, in the browser's dev tools, remove the
`csrf-token` meta tag from the page and try again; the delete should now fail (a `419` status,
surfaced in the Network tab even though the UI doesn't show it) instead of silently succeeding.

---

## Step 7 — Updating every existing test for a now-authenticated app

**What we did:** `CategoryTest`, `ProductTest`, `TransactionTest`, and `DashboardTest` each
gained the same `setUp()`:
```php
protected function setUp(): void
{
    parent::setUp();
    $this->actingAs(User::factory()->create());
}
```
A new `LoginTest.php` covers the login/logout flow itself (form display, valid/invalid
credentials, guest-vs-authenticated redirects, the `401` from a protected API endpoint).
`ExampleTest` was updated for the new root-route behavior (guest → `/login`, authenticated →
`/dashboard`).

**Why every Feature test needed this:** as of Step 4, every API endpoint requires a session.
Without `actingAs()`, every existing `getJson`/`postJson`/`putJson`/`deleteJson` call in
`CategoryTest` etc. would now get a `401` instead of the response the test expects — not because
the module broke, but because the test was written before there was a concept of "logged in" to
satisfy.

**The one file that needed more care — `UserTest`:** its `setUp()` isn't the generic one-liner
above:
```php
protected function setUp(): void
{
    parent::setUp();

    $this->actingAs(User::factory()->create([
        'name' => 'Test Actor',
        'email' => 'test-actor@example.com',
        'updated_at' => now()->subYear(),
    ]));
}
```
**Why:** every other test file's `actingAs()` user is a row in a table (`users`) that test file
*isn't* asserting exact counts or ordering against. `UserTest` is the one file where the
authenticated actor **is** a row in the very table being tested — an ordinary `User::factory()->create()`
with a random name and a "just now" timestamp would silently become a 4th row in
`test_it_lists_users`'s "expect exactly 3" assertion, and could tie (or even beat) an
explicitly-timestamped row in `test_it_lists_users_ordered_by_updated_at_desc`. Giving the actor
a fixed, out-of-the-way name/email and a deliberately old `updated_at` (`subYear()`) means it
never collides with a search term, never wins a sort-order tie, and always sorts last —
`test_it_lists_users` was updated to expect `4` (3 created + the actor), and
`test_it_sorts_users_by_name_ascending` now expects `'Test Actor'` appended after `'Carla'`.
This is the general lesson: when a test's authentication fixture and its subject-under-test
share a table, the fixture's *values*, not just its existence, need to be chosen so it can't be
confused for real test data.

**Validate it:**
```bash
php artisan test
```
Expected: all 79 tests passing (70 before this workshop, plus `LoginTest`'s 8 and `ExampleTest`'s
1 additional case).

---

## Key Laravel concepts covered

- Choosing the session `web` guard over Sanctum tokens based on what the client actually is
  (same-origin Blade + `fetch()`, not an SPA or mobile app) — and knowing what Sanctum is for so
  "it's already installed" isn't mistaken for "so use it for everything"
- `auth`/`guest` middleware aliases and `redirectGuestsTo()`/`redirectUsersTo()` — the
  Laravel 11+ fluent replacement for overriding `Authenticate`/`RedirectIfAuthenticated` in a
  Kernel-based app
- Keeping a `FormRequest` a pure validator even for a security-sensitive action, with the actual
  side effect (`Auth::attempt()`) in the controller — consistent with how every other module in
  this app separates "is this input valid" from "what do we do about it"
- `ValidationException::withMessages()` thrown directly from a controller, relying on Laravel's
  exception handler to render it correctly for both a web redirect-back and a JSON `422` —
  the same "no try/catch" pattern as `InsufficientStockException`
- Session fixation and why login regenerates the session ID (`session()->regenerate()`) while
  logout regenerates the CSRF token (`regenerateToken()`)
- `routes/web.php` vs `routes/api.php` running under different default middleware groups (`web`
  starts a session, `api` doesn't) — and `statefulApi()` as the supported way to bridge them for
  a first-party frontend, without needing real Sanctum tokens
- Why CSRF protection is the unavoidable cost of session-based auth (a cookie is sent
  automatically by the browser to any requester; a token in a header is not), verified by adding
  the `X-CSRF-TOKEN` header to only the mutating requests that actually needed it
- A shared, non-entry JS helper module (`csrf.js`) imported by multiple Vite entries — reuse
  without violating the "one JS file per page" rule, since the rule is about entries, not about
  forbidding code sharing
- Retrofitting authentication onto an app whose tests were written assuming no login existed —
  and the specific hazard of a test's own auth fixture sharing a table with what it's asserting
  against

## What's next

Every request is now attributable to a logged-in user, which was the one thing missing to
close a loose end from workshop 05: `Transaction` currently has no record of *who* rang up a
sale. A `user_id` foreign key on `transactions` (nullable or not, depending on whether every
sale must have a cashier) — restricted-on-delete, following this app's "restrict when the child
row is independently meaningful history" rule — becomes possible now that
`Auth::user()` inside `TransactionController::store()` actually refers to someone real. Beyond
that, this workshop deliberately left two things open: a `role`/permission field on `User` (there's
still no authorization check anywhere — only authentication) and a delete guard preventing a
user from deleting the account they're currently logged in as. Both are natural candidates for
a workshop 08 once there's a concrete reason to enforce them.
