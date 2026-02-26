# Routing

The Plugs routing system is a powerful, flexible, and feature-rich way to direct incoming HTTP requests to their appropriate handlers. It supports traditional closure/controller routing, subdomain matching, PHP 8 attributes, and secure signed URLs.

## Basic Routing

The most basic routes accept a URI and a closure, providing a very simple and expressive method of defining routes:

```php
use Plugs\Facades\Route;

Route::get('/greeting', function () {
    return 'Hello World';
});
```

### Available Router Methods

The router allows you to register routes that respond to any HTTP verb:

```php
Route::get($uri, $callback);
Route::post($uri, $callback);
Route::put($uri, $callback);
Route::patch($uri, $callback);
Route::delete($uri, $callback);
Route::options($uri, $callback);
```

### Form Method Spoofing

HTML forms do not support `PUT`, `PATCH`, or `DELETE` actions. To define routes for these methods, you will need to add a hidden `_method` field to your form. The value of this field will be used as the HTTP request method:

```html
<form action="/user/1" method="POST">
  <input type="hidden" name="_method" value="DELETE" />
  <input type="hidden" name="_token" value="{{ csrf_token() }}" />
  <button type="submit">Delete User</button>
</form>
```

Sometimes you may need to register a route that responds to multiple HTTP verbs. You may do so using the `match` method. Or, you may even register a route that responds to all HTTP verbs using the `any` method:

```php
Route::match(['get', 'post'], '/', function () {
    // ...
});

Route::any('/anything', function () {
    // ...
});
```

## Route Parameters

### Required Parameters

Sometimes you will need to capture segments of the URI within your route. For example, you may need to capture a user's ID from the URL. You may do so by defining route parameters:

```php
Route::get('/user/{id}', function (string $id) {
    return 'User '.$id;
});
```

### Optional Parameters

Occasionally you may need to specify a route parameter, but make the presence of that route parameter optional. You may do so by placing a `?` mark after the parameter name. Make sure to give the route's corresponding variable a default value:

```php
Route::get('/user/{name?}', function (?string $name = null) {
    return $name;
});
```

### Regular Expression Constraints

You may constrain the format of your route parameters using the `where` method on a route instance. The `where` method accepts the name of the parameter and a regular expression defining how the parameter should be constrained:

```php
Route::get('/user/{name}', function (string $name) {
    // ...
})->where('name', '[A-Za-z]+');

Route::get('/user/{id}', function (string $id) {
    // ...
})->where('id', '[0-9]+');
```

The framework also provides several convenient helper methods for common constraints:

```php
Route::get('/user/{id}/{name}', function ($id, $name) {
    // ...
})->whereNumber('id')->whereAlpha('name');
```

## Named Routes

Named routes allow the convenient generation of URLs or redirects for specific routes. You may specify a name for a route by chaining the `name` method onto the route definition:

```php
Route::get('/user/profile', function () {
    // ...
})->name('profile');
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

Route groups allow you to share route attributes, such as middleware or prefixes, across a large number of routes without needing to define those attributes on each individual route.

### Middleware

To assign middleware to all routes within a group, you may use the `middleware` method before defining the group:

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/', function () {
        // Uses Auth Middleware
    });

    Route::get('/user/profile', function () {
        // Uses Auth Middleware
    });
});
```

### Controller Groups

If a group of routes all use the same controller, you may use the `controller` method to define the common controller for all of the routes within the group:

```php
use App\Http\Controllers\OrderController;

Route::controller(OrderController::class)->group(function () {
    Route::get('/orders/{id}', 'show');
    Route::post('/orders', 'store');
});
```

### Route Prefixes

The `prefix` method may be used to prefix each route in the group with a given URI. For example, you may want to prefix all route URIs within the group with `admin`:

```php
Route::prefix('admin')->group(function () {
    Route::get('/users', function () {
        // Matches The "/admin/users" URL
    });
});
```

## Subdomain Routing

The router can also handle subdomain matching. Subdomains may be assigned route parameters just like route URIs, allowing you to capture a portion of the subdomain for usage in your route or controller:

```php
Route::domain('{account}.example.com')->group(function () {
    Route::get('user/{id}', function (string $account, string $id) {
        // ...
    });
});
```

> [!IMPORTANT]
> To ensure your subdomain routes are reachable, you should register subdomain routes before registering root domain routes.

## Attribute Routing

Instead of defining routes in `routes/web.php`, you can use PHP 8 Attributes directly in your controllers.

### Basic Usage

Apply the `#[Route]` attribute to your controller methods:

```php
use Plugs\Router\Attributes\Route;

class UserController extends Controller
{
    #[Route(path: '/profile', methods: ['GET'], name: 'user.profile')]
    public function show()
    {
        // ...
    }
}
```

### Class-Level Attributes

You can also apply the `#[Route]` attribute to the controller class to define common prefixes or domains:

```php
#[Route(prefix: '/admin', domain: 'admin.example.com', middleware: ['auth'])]
class AdminController extends Controller
{
    #[Route(path: '/dashboard', methods: ['GET'])]
    public function index() { ... } // Path: /admin/dashboard on admin.example.com
}
```

## Secure Signed URLs

Signed URLs allow you to create "signed" links to named routes. These URLs have a "signature" hash appended to the query string which allows the framework to verify that the URL has not been modified since it was created.

### Creating Signed URLs

To create a signed URL to a named route, use the `signedRoute` method of the `Route` facade:

```php
use Plugs\Facades\Route;

$url = Route::signedRoute('unsubscribe', ['user' => 1]);
```

If you would like to generate a temporary signed URL that expires after a specified amount of time, you may use the `temporarySignedRoute` method:

```php
$url = Route::temporarySignedRoute(
    'unsubscribe',
    now()->addMinutes(30),
    ['user' => 1]
);
```

### Validating Signed URLs

To verify that an incoming request has a valid signature, you should call the `hasValidSignature` method on the incoming request:

```php
use Plugs\Http\Request;

public function unsubscribe(Request $request)
{
    if (! $request->hasValidSignature()) {
        abort(403);
    }

    // ...
}
```

## Route Caching

> [!TIP]
> Route caching should only be used in production environments to maximize performance.

Scanning all your controllers for attributes or parsing large route files on every request can be expensive. To speed up your application, you can cache your routes using the CLI:

```bash
php plg route:cache
```

This command will serialize all your routes into a single file in the `storage/framework` directory. On subsequent requests, the framework will load this simple file instead of parsing your source code.

To clear the route cache:

```bash
php plg route:clear
```
