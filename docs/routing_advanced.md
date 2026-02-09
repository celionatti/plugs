# Advanced Routing & API Versioning

Plugs introduces enterprise-grade routing capabilities including API versioning, route macros, and declarative middleware pipelines.

## API Versioning

You can now version your API routes easily using route groups. The `ApiVersion` class handles version extraction from headers, query parameters, or prefixes.

### Usage

```php
use Plugs\Router\Router;

$router->group(['prefix' => 'api', 'version' => 'v1'], function (Router $r) {
    $r->get('/users', [UserController::class, 'index']);
});
```

This creates a route at `/api/v1/users`.

### Version Resolution

The `Plugs\Router\ApiVersion` class automatically resolves the requested version from:
1.  **Header**: `X-API-Version: v1`
2.  **Query Parameter**: `?api_version=v1`

## Route Macros

Extend the router with custom methods using macros.

```php
use Plugs\Router\Router;

Router::macro('customRoute', function ($uri) {
    return $this->get($uri, function () {
        return 'Custom Logic';
    });
});

$router->customRoute('/test');
```

## OpenAPI Generation

Automatically generate an OpenAPI JSON specification from your defined routes.

```bash
php plg route:openapi
```

This command scans your application's routes and outputs an `openapi.json` file in the root directory.

## Declarative Middleware Pipelines

The `Plugs\Http\Pipeline` class allows you to chain middleware declaratively, similar to the "Pipe and Filter" pattern.

```php
use Plugs\Http\Pipeline;

$response = (new Pipeline())
    ->send($request)
    ->through([
        EnsureTokenIsValid::class,
        LogRequest::class,
    ])
    ->then(function ($request) {
        return new Response('Core Logic');
    });
```
