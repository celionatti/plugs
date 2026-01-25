# Navigation & URL Generation

Plugs provides several helpers to generate URLs and manage navigation within your application.

## URL Generation

### The `url()` Helper
The `url` helper generates a fully qualified URL for a given path. It automatically uses the `APP_URL` defined in your `.env` file.

```php
$url = url('/posts/1');
// http://localhost/posts/1
```

### The `route()` Helper
If you have named routes, you can generate URLs using the `route` helper.

```php
// In routes/web.php
$router->get('/user/profile', [UserController::class, 'profile'])->name('profile');

// In a controller or view
$url = route('profile');
```

---

## ↩️ Redirects

### Redirecting to a Path
```php
return redirect('/dashboard');
```

### Redirecting with Named Routes
```php
return redirect()->route('profile');
```

### Redirecting Back
The `back()` helper creates a redirect response to the user's previous location. It's often used after failed validation.

```php
return back()->withInput();
```

---

## 🔄 Previous URL

### Accessing the Referer
You can get the previous URL using the `previousUrl()` helper.

```php
$referer = previousUrl();
```

---

## 🗺️ Navigation Helpers (Views)

### Checking the Active Route
You can check if a given route or path is active to apply active classes in your navigation menu.

```html
<a href="{{ url('/') }}" class="{{ is_active('/') ? 'active' : '' }}">Home</a>
<a href="{{ url('/blog') }}" class="{{ is_active('/blog*') ? 'active' : '' }}">Blog</a>
```

> [!TIP]
> The `is_active()` helper supports wildcards (`*`) for matching sub-paths.

### Current Path
To get the current request path:

```php
$path = currentPath();
```
