# Routing

The **Plugs Router** is a high-performance engine that directs incoming HTTP requests to the appropriate controllers or closures. It supports RESTful routing, groups, middleware, and advanced subdomain matching.

---

## 1. Basic Routing

The simplest routes consist of a URI and a closure:

```php
use Plugs\Facades\Route;

Route::get('/welcome', function () {
    return 'Welcome to Plugs!';
});
```

### Supported HTTP Methods
Plugs supports all standard HTTP verbs: `get`, `post`, `put`, `patch`, `delete`, and `options`.

```php
Route::match(['get', 'post'], '/submit', $callback);
Route::any('/anything', $callback);
```

### Redirect & View Routes
```php
Route::redirect('/old', '/new', 301);
Route::view('/about', 'pages.about');
```

---

## 2. Route Parameters

Capture segments of the URI using curly braces:
```php
Route::get('/user/{id}', function (string $id) {
    return "User ID: {$id}";
})->where('id', '[0-9]+');
```

Append a `?` for optional parameters:
```php
Route::get('/post/{slug?}', function (?string $slug = 'index') { ... });
```

---

## 3. Route Groups & Attributes

Groups allow you to share attributes like middleware and prefixes across multiple routes.

```php
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', $callback); // /admin/dashboard
});
```

---

## 4. Advanced Features

### API Versioning
Plugs can automatically detect versions from the URI prefix, `X-API-Version` header, or query parameters.

```php
Route::version('v1')->group(function () {
    Route::get('/users', [V1\UserController::class, 'index']);
});
```

### Route Model Binding
Automatically resolve models from URI segments. If a parameter name matches a model class name, the framework injects the instance:

```php
Route::get('/api/users/{user}', function (User $user) {
    return $user; // Autoresolved by ID
});
```

### OpenAPI Specification
Generate standard API documentation from your routes:
```bash
php theplugs route:openapi
```

---

## Next Steps
Learn how to handle these routes using [Controllers](./controllers.md) and [Middleware](./middleware.md).
