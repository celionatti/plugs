# Advanced Routing

This guide explores the enterprise features of the Plugs routing system, designed for high-performance APIs and complex application architectures.

## API Versioning

Plugs provides built-in support for API versioning, allowing you to manage multiple versions of your API simultaneously. Versions can be resolved from the URI prefix, request headers, or query parameters.

### URI Versioning

The simplest way to version your API is through the URI:

```php
Route::prefix('v1')->group(function () {
    Route::get('/users', [V1\UserController::class, 'index']);
});

Route::prefix('v2')->group(function () {
    Route::get('/users', [V2\UserController::class, 'index']);
});
```

### Header or Query Parameter Versioning

The framework can automatically detect versions from the `X-API-Version` header or an `api_version` query parameter. This allows you to serve different versions on the same URI:

```php
Route::version('v1')->group(function () {
    Route::get('/users', [V1\UserController::class, 'index']);
});
```

When a request comes in, the `Plugs\Router\ApiVersion` class resolves the version:

1.  **Header**: `X-API-Version: v1`
2.  **Query Parameter**: `?api_version=v1`

## Route Macros

You may extend the `Router` class with custom methods using the "macro" pattern. Macros allow you to define reusable route registration logic.

For example, to register a custom "admin" route helper:

```php
use Plugs\Facades\Route;

Route::macro('admin', function (string $uri, $handler) {
    return $this->prefix('admin')->middleware('auth.admin')->get($uri, $handler);
});

// Usage
Route::admin('/settings', [SettingsController::class, 'index']);
```

## Internal Pipeline Architecture

The Plugs router utilizes a "Pipeline" pattern for executing middleware and capturing the final response. When a route is dispatched, it follows this flow:

1.  **Route Matching**: The `Router` finds the first route matching the URI and HTTP method.
2.  **Middleware Collection**: All middleware (Global -> Group -> Route) are gathered into a flat list.
3.  **Pipeline Execution**: The request is passed through the `Plugs\Http\Pipeline`.
4.  **Handler Execution**: If all middleware pass, the final destination (closure or controller) is executed.
5.  **Response Capture**: The handler's output is wrapped in a `ResponseInterface` and sent back through the middleware stack in reverse order.

## Route Model Binding

Plugs automatically resolves database models defined in your route signatures. If a parameter name matches a model class name, the framework will inject the model instance:

```php
use App\Models\User;

Route::get('/api/users/{user}', function (User $user) {
    return $user; // Autoresolved by ID
});
```

### Custom Keys

You may customize the key used for model resolution in the model class:

```php
class User extends Model
{
    public function getRouteKeyName()
    {
        return 'slug';
    }
}
```

## OpenAPI Specification

The framework can reflect on your routes to generate a standard OpenAPI (Swagger) documentation file.

```bash
php plg route:openapi
```

This command parses your route definitions, attributes, and docblocks to build an `openapi.json` file, which can be used to power interactive API documentation or client generation tools.
