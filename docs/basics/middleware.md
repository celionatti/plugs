# Middleware

Middleware provides a convenient mechanism for filtering HTTP requests entering your application. For example, Plugs includes middleware that verifies whether the user is authenticated.

---

## 1. How it Works

Middleware sits between the **Request** and your **Controller**. Each middleware can:
1. Inspect or modify the request.
2. Terminate the request and return a response (e.g., redirecting unauthorized users).
3. Pass the request to the next layer in the pipeline.

---

## 2. Defining Middleware

### Generating Middleware
Scaffold new middleware using the CLI:
```bash
php theplugs make:middleware CheckAge
```

### Middleware Logic
Every middleware must implement a `handle()` method.

```php
namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckAge
{
    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('age') < 18) {
            return redirect('/home');
        }

        return $handler->handle($request);
    }
}
```

---

## 3. Registering Middleware

### Global Middleware
Global middleware runs on **every** request. Register them in your application's bootstrap or via the `MiddlewareRegistry`.

### Route Middleware
Assign middleware to specific routes in your `web.php` or `api.php`:

```php
Route::get('/profile', $callback)->middleware('auth');

// Multiple middleware
Route::get('/admin', $callback)->middleware(['auth', 'admin']);
```

### Middleware Groups
Group multiple middlewares under a single alias for convenience:
```php
Route::middleware(['web'])->group(function () {
    // CSRF, Sessions, etc. are applied here
});
```

---

## 4. Built-in Middleware

Plugs comes with several powerful middlewares out of the box:

- **`auth`**: Ensures the user is logged in.
- **`csrf`**: Protects against Cross-Site Request Forgery.
- **`shield`**: The **Security Shield** that blocks common attack patterns (XSS, SQLi).
- **`json`**: Forces the request/response to handle JSON exclusively.
- **`rate_limit`**: Limits the number of requests a user can make in a given window.

---

## Next Steps
Learn more about the [Request and Response](./requests-responses.md) objects that middleware interacts with.
