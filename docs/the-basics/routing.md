# Routing

The Plugs router is a powerful, flexible component that handles all incoming requests and directs them to the appropriate controllers or closures.

## Basic Routing

Routes are defined in your `routes/web.php` or `routes/api.php` files. The most basic routes accept a URI and a closure or controller action.

```php
use Plugs\Router\Router;

$router->get('/welcome', function () {
    return 'Hello World';
});

$router->get('/home', 'HomeController@index');
```

### Available Router Methods

The router allows you to register routes that respond to any HTTP verb:

```php
$router->get($uri, $callback);
$router->post($uri, $callback);
$router->put($uri, $callback);
$router->patch($uri, $callback);
$router->delete($uri, $callback);
$router->options($uri, $callback);
```

## Route Parameters

### Required Parameters

You may capture segments of the URI within your route:

```php
$router->get('/user/{id}', function ($id) {
    return 'User '.$id;
});
```

### Optional Parameters

To denote a parameter as optional, place a `?` after the parameter name:

```php
$router->get('/user/{name?}', function ($name = 'Guest') {
    return $name;
});
```

### Regular Expression Constraints

You may constrain the format of your route parameters using the `where` method on a route instance:

```php
$router->get('/user/{name}', function ($name) {
    // ...
})->where('name', '[A-Za-z]+');

$router->get('/user/{id}', function ($id) {
    // ...
})->where('id', '[0-9]+');
```

## Named Routes

Named routes allow the convenient generation of URLs or redirects for specific routes. You may specify a name for a route by chaining the `name` method onto the route definition:

```php
$router->get('/user/profile', 'UserProfileController@show')->name('profile');
```

### Generating URLs To Named Routes

Once you have assigned a name to a given route, you may use the route's name when generating URLs or redirects via the global `route` helper:

```php
// Generating URLs...
$url = route('profile');

// Generating Redirects...
return redirect()->route('profile');
```

## Route Groups

Route groups allow you to share route attributes, such as middleware or namespaces, across a large number of routes without needing to define those attributes on each individual route.

### Middleware

To assign middleware to all routes within a group, you may use the `middleware` method before defining the group:

```php
$router->middleware(['auth'])->group(function ($router) {
    $router->get('/', function () {
        // Uses Auth Middleware
    });

    $router->get('/user/profile', function () {
        // Uses Auth Middleware
    });
});
```

### Route Prefixes

The `prefix` method may be used to prefix each route in the group with a given URI:

```php
$router->prefix('admin')->group(function ($router) {
    $router->get('/users', function () {
        // Matches The "/admin/users" URL
    });
});
```

## Resource Routes

The Plugs router resource routing assigns the typical "CRUD" (Create, Read, Update, Delete) routes to a controller with a single line of code.

### RESTful Resource Controllers

```php
$router->resource('photos', PhotoController::class);
```

This single route declaration creates multiple routes to handle a variety of actions on the resource. The generated routes will handle:

| Verb | URI | Action | Route Name |
| :--- | :--- | :--- | :--- |
| GET | `/photos` | index | `photos.index` |
| GET | `/photos/create` | create | `photos.create` |
| POST | `/photos` | store | `photos.store` |
| GET | `/photos/{id}` | show | `photos.show` |
| GET | `/photos/{id}/edit` | edit | `photos.edit` |
| PUT/PATCH | `/photos/{id}` | update | `photos.update` |
| DELETE | `/photos/{id}` | destroy | `photos.destroy` |

### API Resource Routes

When declaring resource routes that will be consumed by APIs, you will commonly want to exclude routes that present HTML templates such as `create` and `edit`. For this reason, you may use the `apiResource` method:

```php
$router->apiResource('photos', PhotoController::class);
```

| Verb | URI | Action | Route Name |
| :--- | :--- | :--- | :--- |
| GET | `/photos` | index | `photos.index` |
| POST | `/photos` | store | `photos.store` |
| GET | `/photos/{id}` | show | `photos.show` |
| PUT/PATCH | `/photos/{id}` | update | `photos.update` |
| DELETE | `/photos/{id}` | destroy | `photos.destroy` |

### Specifying Resource Parameters

By default, `resource` routes use `{id}` as the parameter name. You can customize this using the `parameters` option:

```php
$router->resource('users', UserController::class, [
    'parameters' => 'user_id'
]);
// URIs will look like: /users/{user_id}
```

## Manual API Method Usage

While `apiResource` is convenient, you can always define API routes manually:

```php
$router->group(['prefix' => 'api/v1'], function($router) {
    $router->get('/posts', [PostController::class, 'index']);
    $router->post('/posts', [PostController::class, 'store']);
    
    // Updating a resource (PUT replaces, PATCH partially updates)
    $router->put('/posts/{id}', [PostController::class, 'update']);
    $router->patch('/posts/{id}', [PostController::class, 'update']);
    
    // Deleting a resource
    $router->delete('/posts/{id}', [PostController::class, 'destroy']);
});
```

## Method Spoofing

HTML forms do not support `PUT`, `PATCH`, or `DELETE` actions. To use these methods, you will need to add a hidden `_method` field to the form. The `@method` Blade directive can create this field for you:

```html
<form action="/example" method="POST">
    @method('PUT')
    @csrf
</form>
```

```php
$router->fallback(function () {
    return view('errors.404');
});
```

## Attribute-Based Routing

In addition to defining routes in your route files, Plugs supports declarative routing using PHP 8 Attributes. This allows you to define routes directly on your controller methods.

### Basic Usage

Use the `#[Route]` attribute to define a route. You may also use the `#[Middleware]` attribute to assign middleware to a class or method.

```php
namespace App\Http\Controllers;

use Plugs\Router\Attributes\Route;
use Plugs\Http\Attributes\Middleware;
use Plugs\Base\Controller\Controller;

#[Middleware('web')]
class UserController extends Controller
{
    #[Route(path: '/profile', name: 'profile.show')]
    public function show()
    {
        // ...
    }

    #[Route(path: '/profile', methods: 'POST')]
    #[Middleware('auth')]
    public function update()
    {
        // ...
    }
}
```

### Registering Attribute Routes

To enable attribute-based routing, you must tell the router which directories to scan for controllers. This is typically done in your `routes/web.php` or `routes/api.php` file:

```php
// routes/web.php

$router->registerAttributes('App\\Http\\Controllers', base_path('app/Http/Controllers'));
```

The first argument is the root namespace of the controllers, and the second is the absolute path to the directory containing them.

### Route Attribute Parameters

The `#[Route]` attribute supports several parameters:

- `path`: The URI of the route.
- `methods`: A string or array of HTTP methods (default: `GET`).
- `name`: The name of the route.
- `middleware`: An array of middleware.
- `where`: An array of regular expression constraints for parameters.

## API Routes

Any routes defined in `routes/api.php` are automatically assigned the following behaviors:

*   **Prefix**: All URIs are prefixed with `/api`. For example, a route defined as `/users` becomes `/api/users`.
*   **Response Enforcement**: All requests are forced to accept `application/json`, and all responses (including errors) will be returned as JSON.

This makes it perfect for building APIs without worrying about manually configuring headers or error handling for API clients.

```php
// routes/api.php

// This route serves: POST /api/users
Route::post('/users', [UserController::class, 'store']);
```
