# Routing

The Plugs router handles all incoming HTTP requests and directs them to the appropriate controllers or closures.

## Basic Routing

Routes are defined in `routes/web.php` or `routes/api.php`. You can use the `Route` facade for a clean, expressive syntax.

```php
use Plugs\Facades\Route;

// Closure route
Route::get('/welcome', function () {
    return 'Hello World';
});

// Controller route
Route::get('/home', 'HomeController@index');
```

### Available Methods

The router supports all standard HTTP verbs:

```php
Route::get($uri, $callback);
Route::post($uri, $callback);
Route::put($uri, $callback);
Route::patch($uri, $callback);
Route::delete($uri, $callback);
```

## Route Parameters

### Required Parameters

Capture segments of the URI by wrapping them in curly braces:

```php
Route::get('/user/{id}', function ($id) {
    return 'User '.$id;
});
```

### Optional Parameters

Place a `?` after the parameter name to make it optional:

```php
Route::get('/user/{name?}', function ($name = 'Guest') {
    return "Hello {$name}";
});
```

## Named Routes

Named routes allow you to generate URLs or redirects easily:

```php
Route::get('/user/profile', 'UserProfileController@show')->name('profile');

// Usage
$url = route('profile');
return redirect()->route('profile');
```

## Route Groups

Groups allow you to share attributes like middleware or prefixes across multiple routes.

### Middleware Groups

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/account', [AccountController::class, 'show']);
});
```

### Prefixes

```php
Route::prefix('admin')->group(function () {
    Route::get('/users', [AdminController::class, 'index']); // Matches /admin/users
});
```

## Resource Routing

Register all standard CRUD routes for a controller with one line:

```php
Route::resource('photos', PhotoController::class);
```

| Verb      | URI                 | Action  | Route Name       |
| :-------- | :------------------ | :------ | :--------------- |
| GET       | `/photos`           | index   | `photos.index`   |
| GET       | `/photos/create`    | create  | `photos.create`  |
| POST      | `/photos`           | store   | `photos.store`   |
| GET       | `/photos/{id}`      | show    | `photos.show`    |
| GET       | `/photos/{id}/edit` | edit    | `photos.edit`    |
| PUT/PATCH | `/photos/{id}`      | update  | `photos.update`  |
| DELETE    | `/photos/{id}`      | destroy | `photos.destroy` |

> [!TIP]
> Use `Route::apiResource('photos', PhotoController::class)` to exclude the `create` and `edit` routes for API-only resources.

## Attribute Routing

You can define routes directly on your controller methods using PHP 8 Attributes:

```php
use Plugs\Router\Attributes\Route;

class UserController extends Controller
{
    #[Route(path: '/profile', name: 'profile.show')]
    public function show() { ... }
}
```

To enable this, load them in your route file:

```php
$router->loadAttributes(app_path('Controllers'));
```

## API Features

Routes in `routes/api.php` automatically receive:

1. **`/api` Prefix**: All routes are prefixed (e.g., `/api/users`).
2. **JSON Enforcement**: Responses are automatically converted to JSON.
