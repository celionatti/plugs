# Middleware

Middleware provides a mechanism for inspecting and filtering HTTP requests entering your application. For example, Plugs includes middleware that verifies the user is authenticated, validates CSRF tokens, or forces JSON responses for API routes.

## Defining Middleware

A middleware class must implement `Psr\Http\Server\MiddlewareInterface`:

```php
<?php

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExampleMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Perform actions before the request is handled...

        $response = $handler->handle($request);

        // Perform actions after the response is created...

        return $response;
    }
}
```

Place your custom middleware in `app/Http/Middleware/`.

---

## Registering Middleware

### Route Middleware

Attach middleware directly to a single route:

```php
use Plugs\Facades\Route;

Route::get('/profile', [ProfileController::class, 'show'])->middleware('auth');
```

### Route Group Middleware

Apply middleware to a group of routes using the fluent `Route::middleware()` syntax:

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/settings', [SettingsController::class, 'edit']);
});
```

Or using the array-based `group` syntax:

```php
Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

---

## Middleware Configuration

All middleware aliases and groups are configured in `config/middleware.php`:

```php
return [
    'aliases' => [
        'csrf'  => \Plugs\Http\Middleware\CsrfMiddleware::class,
        'guest' => \App\Http\Middleware\GuestMiddleware::class,
        'auth'  => \Plugs\Http\Middleware\AuthenticateMiddleware::class,
    ],

    'groups' => [
        'web' => [
            \Plugs\Http\Middleware\ShareErrorsFromSession::class,
        ],
        'api' => [
            \Plugs\Http\Middleware\ForceJsonMiddleware::class,
        ],
    ],
];
```

Once registered, you can use the short alias string instead of the full class name:

```php
// Use the alias:
Route::get('/admin', [AdminController::class, 'index'])->middleware('auth');

// Instead of the full class:
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware(\Plugs\Http\Middleware\AuthenticateMiddleware::class);
```

---

## Middleware Groups

Middleware groups bundle multiple middleware under a single key, making it easy to apply several middleware at once.

### The `web` Group

The `web` group is designed for traditional browser-based routes that use sessions, forms, and validation. It includes:

| Middleware               | Purpose                                                                 |
| :----------------------- | :---------------------------------------------------------------------- |
| `ShareErrorsFromSession` | Reads validation errors from the session and shares them with all views |

**How `ShareErrorsFromSession` works:** When a form validation fails, the framework stores the error messages in the `$_SESSION['errors']` array (typically via a redirect with flashed errors). On the next request, `ShareErrorsFromSession` reads those errors, wraps them in an `ErrorMessage` object, and shares them globally with all views via `ViewEngine::share('errors', $errors)`. After sharing, it clears the errors from the session so they only display once — this is the "flash" behavior.

In your views, you can then access validation errors like this:

```php
<?php if ($errors->has('email')): ?>
    <span class="error"><?= $errors->first('email') ?></span>
<?php endif; ?>
```

**Usage:**

```php
Route::middleware('web')->group(function () {
    Route::get('/contact', [ContactController::class, 'show']);
    Route::post('/contact', [ContactController::class, 'submit']);
});
```

### The `api` Group

The `api` group is designed for stateless API endpoints. It includes:

| Middleware            | Purpose                                                                                                               |
| :-------------------- | :-------------------------------------------------------------------------------------------------------------------- |
| `ForceJsonMiddleware` | Forces the `Accept` header to `application/json` and ensures the `Content-Type` response header is `application/json` |

**How `ForceJsonMiddleware` works:** It overrides the incoming request's `Accept` header to `application/json`, which tells the framework to treat the request as an API call. This means error responses (404, 500, validation errors) will automatically be returned as JSON instead of HTML. It also sets the `Content-Type: application/json` header on responses that don't already have one.

**Usage:**

Routes in `routes/api.php` are automatically wrapped with the `api` group and prefixed with `/api`. You can also use it manually:

```php
Route::middleware('api')->prefix('api/v2')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
```

### Custom Groups

Add your own groups in `config/middleware.php`:

```php
'groups' => [
    'web' => [
        \Plugs\Http\Middleware\ShareErrorsFromSession::class,
    ],
    'api' => [
        \Plugs\Http\Middleware\ForceJsonMiddleware::class,
    ],
    'admin' => [
        \Plugs\Http\Middleware\AuthenticateMiddleware::class,
        \App\Http\Middleware\VerifyIsAdmin::class,
    ],
],
```

Then use them:

```php
Route::middleware('admin')->prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
});
```

---

## Built-in Middleware Reference

### `AuthenticateMiddleware` (alias: `auth`)

**Class:** `Plugs\Http\Middleware\AuthenticateMiddleware`

Protects routes from unauthenticated access. If the user is not logged in:

- **Browser requests** → Redirected to `/login`
- **JSON/API requests** → Returns `401 Unauthenticated` JSON response

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

### `GuestMiddleware` (alias: `guest`)

**Class:** `App\Http\Middleware\GuestMiddleware`

The opposite of `auth` — restricts routes to unauthenticated users only. If the user **is** logged in, they are redirected to `/dashboard`. Ideal for login and registration pages.

```php
Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'index']);
    Route::get('/register', [RegisterController::class, 'index']);
});
```

### `CsrfMiddleware` (alias: `csrf`)

**Class:** `Plugs\Http\Middleware\CsrfMiddleware`

Validates that POST, PUT, PATCH, and DELETE requests include a valid CSRF token. This protects against cross-site request forgery attacks. Use `csrf_field()` in your forms:

```html
<form method="POST" action="/contact">
  <?= csrf_field() ?>
  <!-- form fields -->
</form>
```

### `ShareErrorsFromSession`

**Class:** `Plugs\Http\Middleware\ShareErrorsFromSession`

Included in the `web` middleware group. Reads flashed validation errors from `$_SESSION['errors']`, wraps them in an `ErrorMessage` object, and shares them globally with all views. The errors are cleared from the session after being shared, so they only appear once (flash behavior).

### `ForceJsonMiddleware`

**Class:** `Plugs\Http\Middleware\ForceJsonMiddleware`

Included in the `api` middleware group. Sets the request `Accept` header to `application/json` and ensures responses have the correct `Content-Type: application/json` header.

### `RateLimitMiddleware`

**Class:** `Plugs\Http\Middleware\RateLimitMiddleware`

Limits the number of requests a client can make within a time window. Features behavior-based throttling — suspicious clients (detected via `ThreatDetector`) receive stricter limits.

**Constructor parameters:**

- `$maxRequests` (default: `60`) — Maximum requests allowed
- `$perMinutes` (default: `1`) — Time window in minutes

**Response headers added:**

- `X-RateLimit-Limit` — The maximum number of requests allowed
- `X-RateLimit-Remaining` — Requests remaining in the current window
- `X-RateLimit-Reset` — Unix timestamp when the window resets

**Per-route throttling** is also available via the `throttle()` method on a route:

```php
Route::get('/api/search', [SearchController::class, 'index'])->throttle(30, 1);
// 30 requests per minute
```

---

## Global Middleware

Global middleware runs on **every** request. It is registered in `bootstrap/Bootstrapper.php` via the `$app->pipe()` method and executes before the router:

```php
$this->app->pipe(new \Plugs\Http\Middleware\FlashMiddleware());
$this->app->pipe(new \Plugs\Http\Middleware\HandleValidationExceptions());
$this->app->pipe(new \Plugs\Http\Middleware\SecurityHeadersMiddleware($config));
```

These apply to all routes regardless of route-level middleware configuration.
