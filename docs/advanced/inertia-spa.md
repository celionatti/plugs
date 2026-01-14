# Inertia / SPA Mode

Plugs features a built-in "Inertia" mode that allows you to build single-page applications using server-side routing and controllers, similar to Inertia.js.

## Overview

Inertia mode works by intercepting clicks on internal links and performing an AJAX request instead of a full page reload. The server returns a JSON payload containing the view data, which is then rendered by the client-side SPA engine.

## Configuration

To enable SPA mode, ensure your main layout includes the necessary JavaScript and that the `InertiaMiddleware` is registered.

```php
$app->pipe(new \Plugs\Inertia\InertiaMiddleware());
```

## Returning Inertia Responses

In your controller, use the `Inertia` facade to return a response:

```php
use Plugs\Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::all()
        ]);
    }
}
```

## Lazy Properties

You can define properties that are only evaluated when explicitly requested by the client (e.g., during a partial reload):

```php
return Inertia::render('Users/Index', [
    'users' => User::all(),
    'stats' => Inertia::lazy(fn() => ExpensiveStat::calculate())
]);
```

## Shared Data

You can share data across all Inertia responses, such as the authenticated user or flash messages:

```php
Inertia::share([
    'auth' => [
        'user' => $request->user()
    ],
    'flash' => [
        'message' => $session->get('message')
    ]
]);
```

## Progress Indicators

Plugs SPA automatically handles NProgress-style bars during navigation. You can customize the behavior in your JavaScript configuration.
