# SINGLE PAGE APPLICATIONS (SPA)

Plugs provides two powerful ways to build modern Single Page Applications while keeping your logic on the server.

1.  **Inertia Mode**: A specialized SPA engine (similar to Inertia.js) that returns JSON-based view data for client-side rendering.
2.  **SPA Bridge**: A lightweight progressive enhancement that turns traditional PHP views into a Single Page Application without any API boilerplate.

---

## 1. Inertia Mode

Inertia Mode works by intercepting clicks on internal links and performing an AJAX request. Instead of full HTML, the server returns a JSON payload containing the view data, which the client-side engine uses to update the page.

### Configuration

To enable Inertia mode, you need to register the `InertiaMiddleware` in your application bootstrap:

```php
$app->pipe(new \Plugs\Inertia\InertiaMiddleware());
```

### Returning Responses

In your controller, use the `Inertia` facade to render a component.

```php
use Plugs\Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard/Home', [
            'stats' => $this->getStats(),
            'user' => auth()->user()
        ]);
    }
}
```

### Shared Data

You can share data globally across all Inertia responses (e.g., authenticated user, session flash messages) using `Inertia::share()`.

```php
Inertia::share([
    'auth' => [
        'user' => $request->user()
    ],
    'flash' => [
        'success' => $session->get('success')
    ]
]);
```

### Lazy Properties

Lazy properties are data points that are only evaluated when explicitly requested by the client (e.g., during a partial reload).

```php
return Inertia::render('Users/Index', [
    'users' => User::all(),
    'expensiveStats' => Inertia::lazy(fn() => ExpensiveStat::calculate())
]);
```

---

## 2. SPA Bridge

The **SPA Bridge** is the easiest way to give your traditional PHP application an SPA feel. It intercepts links and form submissions, loading the content into your `#app-content` area automatically.

### Enabling SPA Bridge

To turn any link or form into an SPA action, simply add the `data-spa="true"` attribute:

```html
<!-- This link will load via AJAX -->
<a href="/profile" data-spa="true">Profile</a>

<!-- This form will submit via AJAX and update the content area -->
<form action="/login" method="POST" data-spa="true">
    <input type="text" name="username">
    <button type="submit">Login</button>
</form>
```

### Progress Indicators

SPA Bridge automatically manages a top-loaded progress bar (similar to NProgress). You can customize the look in your CSS:

```css
#spa-progress-bar {
    background-color: #10b981; /* Custom Green */
    height: 4px;
}
```

### Prefetching

By default, the SPA Bridge prefetches links when a user hovers over them, making transitions feel instantaneous.

> [!TIP]
> Use `data-spa="true"` on your main navigation links to provide the fastest possible user experience.
