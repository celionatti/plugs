# View Basics

Learn how to create, render, and share data with Plugs views.

## File Structure

Views are stored in `resources/views` with the `.plug.php` extension.

```
resources/views/
├── layouts/
├── components/
├── partials/
└── welcome.plug.php
```

## Rendering Views

### From Controllers

Use the global `view()` helper:

```php
return view('welcome', [
    'name' => 'John Doe'
]);
```

### Fluent Data Binding

```php
return view('profile')
    ->with('user', $user)
    ->withData(['stats' => $stats]);
```

### Advanced Rendering

```php
// Check if a view exists
if ($view->exists('emails.welcome')) { ... }

// Render a view and get the string
$html = $view->render('template', $data);
```

---

## Shared Data

Share data across all views globally.

```php
// In a service provider or middleware
$view->share('company_name', 'Acme Corp');
```

---

## ⚡ Async Data Resolution

Plugs views can resolve `Promises` or `Fibers` in parallel before rendering.

```php
return view('dashboard', [
    'users' => $client->getAsync('/users'),
    'posts' => $client->getAsync('/posts'),
]);
```

The view will wait for both requests to complete simultaneously before rendering, providing `$users` and `$posts` as resolved data.

---

## 🏗️ Path Resolution

Plugs supports several extensions and naming conventions:

- `user.profile` -> `resources/views/user/profile.plug.php`
- Falls back to `.php` or `.html` if `.plug.php` is missing.
