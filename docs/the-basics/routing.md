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

## Method Spoofing

HTML forms do not support `PUT`, `PATCH`, or `DELETE` actions. To use these methods, you will need to add a hidden `_method` field to the form. The `@method` Blade directive can create this field for you:

```html
<form action="/example" method="POST">
    @method('PUT')
    @csrf
</form>
```

## Fallback Routes

Using the `fallback` method, you may define a route that will be executed when no other route matches the incoming request:

```php
$router->fallback(function () {
    return view('errors.404');
});
```

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
