# View Basics

Learn how to create, render, and share data with Plugs views.

## File Structure

Views are stored in `resources/` (or a configured path) with the `.plug.php` extension.
The default structure looks like this:

```
resources/
├── layouts/
├── components/
├── partials/
├── themes/
│   └── modern/
│       └── layouts/
└── welcome.plug.php
```

## 🎨 Themes

Plugs supports a powerful theming system. You can switch the look of your application by changing the `APP_THEME` environment variable.

### How it Works

1.  **Set the Theme**: Set `APP_THEME=modern` in your `.env` file.
2.  **Theme Lookup**: The framework first looks for the view in `resources/themes/modern/`.
3.  **Fallback**: If not found in the theme folder, it falls back to the default `resources/` folder.

**Example**:
If you request `view('home')` with `APP_THEME=dark`:

- Check: `resources/themes/dark/home.plug.php`
- Fallback: `resources/home.plug.php`

To disable theming, set `APP_THEME=default` or leave it empty.

## ⚙️ Configurable Paths

You can change the default view directory by setting `VIEW_PATH` in your `.env` file.

```env
VIEW_PATH=/path/to/your/custom/views
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

- `user.profile` -> `resources/user/profile.plug.php`
- Falls back to `.php` or `.html` if `.plug.php` is missing.
