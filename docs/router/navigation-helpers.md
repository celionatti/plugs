# Navigation & Route Helper Functions

Complete guide to using route helpers for navigation, URL generation, and active state management.

---

## Table of Contents

- [URL Generation](#url-generation)
- [Current Route Information](#current-route-information)
- [Route Checking](#route-checking)
- [Route Parameters](#route-parameters)
- [HTTP Method Checking](#http-method-checking)
- [Request Type Detection](#request-type-detection)
- [Navigation Active State](#navigation-active-state)
- [View Examples](#view-examples)
- [Best Practices](#best-practices)

---

## URL Generation

### `route()`
Generate a URL for a named route.

```php
// Absolute URL (default)
$url = route('user.profile', ['id' => 123]);
// Returns: http://yourdomain.com/user/123

// Relative URL
$url = route('user.profile', ['id' => 123], false);
// Returns: /user/123

// No parameters
$url = route('home');
```

### `url()`
Generate a full URL from a path.

```php
// Simple path
$url = url('/dashboard');
// Returns: http://yourdomain.com/dashboard

// With query parameters
$url = url('/search', ['q' => 'php', 'page' => 2]);
// Returns: http://yourdomain.com/search?q=php&page=2
```

---

## Current Route Information

### `currentRoute()`
Get the current route object.

```php
$route = currentRoute();
$name = $route?->getName();
```

### `currentRouteName()`
Get the current route name.

```php
$name = currentRouteName();
// Example: 'user.profile'
```

### `currentPath()`
Get the current URL path.

```php
$path = currentPath();
// Example: '/user/profile/123'
```

### `currentUrl()`
Get the current full URL.

```php
// With query string (default)
$url = currentUrl();
// Returns: http://yourdomain.com/page?tab=posts

// Without query string
$url = currentUrl(includeQuery: false);
// Returns: http://yourdomain.com/page
```

### `previousUrl()` / `back()`
Get the previous URL (HTTP referer).

```php
$referer = previousUrl();
$referer = previousUrl('/home'); // With fallback

// Shorthand
$backUrl = back();
```

---

## Route Checking

### `routeIs()`
Check if the current route matches a pattern.

```php
// Single route name
if (routeIs('user.profile')) {
    // Current route is 'user.profile'
}

// Multiple route names
if (routeIs(['home', 'dashboard'])) {
    // Current route is either 'home' or 'dashboard'
}

// Wildcard patterns
if (routeIs('admin.*')) {
    // Matches: admin.dashboard, admin.users, etc.
}

if (routeIs('user.*.edit')) {
    // Matches: user.profile.edit, user.settings.edit, etc.
}

// Multiple patterns
if (routeIs(['admin.*', 'super.*'])) {
    // Matches routes starting with 'admin.' OR 'super.'
}
```

### `hasRoute()`
Check if a named route exists.

```php
if (hasRoute('user.profile')) {
    $url = route('user.profile', ['id' => 1]);
}
```

---

## Route Parameters

### `routeParams()`
Get route parameters from the current request.

```php
// Get single parameter
$userId = routeParams('id');

// With default value
$tab = routeParams('tab', 'overview');

// Get all parameters
$allParams = routeParams();
// Returns: ['id' => '123', 'slug' => 'my-post']
```

---

## HTTP Method Checking

### `isMethod()`
Check if the request uses a specific HTTP method.

```php
// Single method
if (isMethod('POST')) {
    // Handle POST request
}

// Multiple methods
if (isMethod(['PUT', 'PATCH'])) {
    // Handle PUT or PATCH
}
```

### Shorthand Methods

```php
if (isGet()) { }
if (isPost()) { }
if (isPut()) { }
if (isDelete()) { }
if (isPatch()) { }
```

---

## Request Type Detection

### `isAjax()`
Check if the request is an AJAX request.

```php
if (isAjax()) {
    return ['status' => 'success', 'data' => $data];
}
```

### `wantsJson()`
Check if the client expects a JSON response.

```php
if (wantsJson()) {
    return ['result' => $result];
} else {
    return redirect('/dashboard')->withSuccess('Saved!');
}
```

---

## Navigation Active State

### `isActive()`
Check if a path or route is currently active.

```php
// Path matching (starts with)
if (isActive('/dashboard')) {
    // Current path starts with '/dashboard'
}

// Exact path match
if (isActive('/dashboard', exact: true)) {
    // Current path is exactly '/dashboard'
}

// Multiple paths
if (isActive(['/users', '/customers'])) {
    // Matches if path starts with '/users' OR '/customers'
}

// Route name matching
if (isActive('admin.dashboard')) {
    // Works with route names too
}

// Wildcard route patterns
if (isActive('admin.*')) {
    // Matches routes starting with 'admin.'
}
```

### `activeClass()`
Return an active class if the path/route matches (perfect for views).

```php
// Basic usage - returns 'active' if matches
<a href="/dashboard" class="<?= activeClass('/dashboard') ?>">
    Dashboard
</a>

// Custom active class
<a href="/users" class="nav-link <?= activeClass('/users', 'nav-active') ?>">
    Users
</a>

// With inactive class
<a href="/settings" class="<?= activeClass('/settings', 'active', 'inactive') ?>">
    Settings
</a>

// Exact match only
<a href="/" class="<?= activeClass('/', 'active', '', exact: true) ?>">
    Home
</a>

// Multiple paths
<a href="/products" class="<?= activeClass(['/products', '/inventory'], 'active') ?>">
    Products
</a>
```

### `activeRoute()`
Return active class if route name matches.

```php
<a href="<?= route('user.profile') ?>" class="<?= activeRoute('user.profile') ?>">
    Profile
</a>

// With wildcard
<li class="<?= activeRoute('admin.*', 'menu-open') ?>">
    <a href="#">Admin</a>
</li>

// Custom classes
<a href="#" class="nav-item <?= activeRoute('dashboard', 'current', 'not-current') ?>">
    Dashboard
</a>
```

### `activePath()`
Alias for `activeClass()`.

```php
<a href="/blog" class="<?= activePath('/blog') ?>">Blog</a>
```

### `activeSegment()`
Check if a specific URI segment matches a value.

```php
<!-- For URL: /admin/users/create -->
<li class="<?= activeSegment(1, 'admin') ?>">Admin</li>
<li class="<?= activeSegment(2, 'users') ?>">Users</li>

<!-- Multiple values -->
<li class="<?= activeSegment(2, ['users', 'customers']) ?>">People</li>
```

### `activeUrl()`
Return active class if full URL matches.

```php
<a href="https://example.com/page" 
   class="<?= activeUrl('https://example.com/page', 'current') ?>">
    Link
</a>

<!-- Starts with match -->
<a href="#" class="<?= activeUrl('https://example.com', 'active') ?>">
    Example
</a>

<!-- Exact match -->
<a href="#" class="<?= activeUrl('https://example.com/exact', 'active', '', exact: true) ?>">
    Exact
</a>
```

### `activeWhen()`
Return active class when a condition is true.

```php
<?php $isUserAdmin = checkUserRole('admin'); ?>
<li class="<?= activeWhen($isUserAdmin, 'admin-active') ?>">
    Admin Panel
</li>

<!-- With inactive class -->
<button class="<?= activeWhen($condition, 'btn-primary', 'btn-default') ?>">
    Button
</button>
```

### `activeIfQuery()`
Return active class if query parameter matches.

```php
<!-- For URL: /products?sort=price -->
<button class="<?= activeIfQuery('sort', 'price', 'btn-primary', 'btn-default') ?>">
    Sort by Price
</button>

<!-- Multiple values -->
<button class="<?= activeIfQuery('sort', ['name', 'title'], 'active') ?>">
    Sort Alphabetically
</button>
```

---

## View Examples

### Main Navigation

```php
<nav class="main-nav">
    <a href="<?= route('home') ?>" 
       class="nav-link <?= activeRoute('home') ?>">
        Home
    </a>
    <a href="<?= route('dashboard') ?>" 
       class="nav-link <?= activeRoute('dashboard') ?>">
        Dashboard
    </a>
    <a href="<?= route('users.index') ?>" 
       class="nav-link <?= activeRoute('users.*') ?>">
        Users
    </a>
    <a href="<?= route('settings') ?>" 
       class="nav-link <?= activeRoute('settings.*') ?>">
        Settings
    </a>
</nav>
```

### Sidebar with Nested Menus

```php
<aside class="sidebar">
    <!-- Admin section -->
    <div class="menu-section <?= activeRoute('admin.*', 'section-active') ?>">
        <h3>Administration</h3>
        <ul>
            <li class="<?= activeRoute('admin.dashboard') ?>">
                <a href="<?= route('admin.dashboard') ?>">Dashboard</a>
            </li>
            <li class="<?= activeRoute('admin.users.*') ?>">
                <a href="<?= route('admin.users.index') ?>">Users</a>
            </li>
            <li class="<?= activeRoute('admin.settings') ?>">
                <a href="<?= route('admin.settings') ?>">Settings</a>
            </li>
        </ul>
    </div>
</aside>
```

### Breadcrumbs

```php
<nav class="breadcrumb">
    <a href="<?= route('home') ?>" 
       class="<?= activeRoute('home', 'current') ?>">
        Home
    </a>
    <?php if (routeIs('user.*')): ?>
        <span>/</span>
        <a href="<?= route('user.index') ?>" 
           class="<?= activeRoute('user.index', 'current') ?>">
            Users
        </a>
    <?php endif; ?>
    <?php if (routeIs('user.profile')): ?>
        <span>/</span>
        <span class="current">Profile</span>
    <?php endif; ?>
</nav>
```

### Tabs with Query Parameters

```php
<div class="tabs">
    <a href="<?= url('/products', ['view' => 'grid']) ?>" 
       class="tab <?= activeIfQuery('view', 'grid', 'tab-active') ?>">
        Grid View
    </a>
    <a href="<?= url('/products', ['view' => 'list']) ?>" 
       class="tab <?= activeIfQuery('view', 'list', 'tab-active') ?>">
        List View
    </a>
</div>
```

### Profile Tabs (URL Segments)

```php
<!-- For URL: /profile/john/posts -->
<div class="profile-tabs">
    <a href="/profile/<?= routeParams('username') ?>/posts" 
       class="<?= activeSegment(3, 'posts') ?>">
        Posts
    </a>
    <a href="/profile/<?= routeParams('username') ?>/followers" 
       class="<?= activeSegment(3, 'followers') ?>">
        Followers
    </a>
    <a href="/profile/<?= routeParams('username') ?>/following" 
       class="<?= activeSegment(3, 'following') ?>">
        Following
    </a>
</div>
```

---

## Controller Examples

### Basic Controller Usage

```php
class DashboardController
{
    public function index($request)
    {
        // Get current route info
        $routeName = currentRouteName();
        $routeParams = routeParams();
        
        // Check route pattern
        if (routeIs('admin.*')) {
            // Load admin-specific data
        }
        
        return view('dashboard', [
            'currentRoute' => $routeName,
            'params' => $routeParams
        ]);
    }
}
```

### Form Handling

```php
public function handleForm($request)
{
    if (isGet()) {
        return view('form');
    }
    
    if (isPost()) {
        $data = $request->getParsedBody();
        
        // Check if AJAX/JSON request
        if (isAjax() || wantsJson()) {
            return ['success' => true, 'data' => $data];
        }
        
        // Regular redirect
        return redirectBack()->withSuccess('Form submitted!');
    }
}
```

### Dynamic Content

```php
public function dynamicContent($request)
{
    $userId = routeParams('id');
    $tab = routeParams('tab', 'overview');
    
    // Generate URLs for navigation
    $urls = [
        'profile' => route('user.profile', ['id' => $userId]),
        'settings' => route('user.settings', ['id' => $userId]),
        'back' => previousUrl('/dashboard')
    ];
    
    return view('user.profile', compact('userId', 'tab', 'urls'));
}
```

---

## Middleware Examples

### Authentication Middleware

```php
class AuthMiddleware
{
    public function handle($request, $next)
    {
        if (!isLoggedIn()) {
            return redirect('/login')
                ->with('intended', currentUrl())
                ->withError('Please login to continue');
        }
        
        return $next($request);
    }
}
```

### Admin Access Middleware

```php
class AdminMiddleware
{
    public function handle($request, $next)
    {
        if (routeIs('admin.*') && !isAdmin()) {
            return redirectBack()->withError('Access denied');
        }
        
        return $next($request);
    }
}
```

---

## Best Practices

### ✅ DO

1. **Use route names instead of hardcoded paths**
   ```php
   // Good
   $url = route('user.profile', ['id' => $id]);
   
   // Bad
   $url = "/user/profile/{$id}";
   ```

2. **Use wildcard patterns for grouped routes**
   ```php
   // Good
   if (routeIs('admin.*')) { }
   
   // Bad
   if (routeIs(['admin.dashboard', 'admin.users', 'admin.settings'])) { }
   ```

3. **Use exact matching for homepage**
   ```php
   // Good - prevents matching all routes
   activeClass('/', 'active', '', exact: true)
   
   // Bad - would match all routes starting with /
   activeClass('/')
   ```

4. **Prefer route names over paths in navigation**
   ```php
   // Good - easier to maintain
   activeRoute('user.profile')
   
   // Bad - harder to maintain
   activeClass('/user/profile/123')
   ```

5. **Check if route exists before using**
   ```php
   if (hasRoute($routeName)) {
       $url = route($routeName);
   }
   ```

6. **Use appropriate content type checking**
   ```php
   return wantsJson() 
       ? jsonResponse($data) 
       : redirect('/')->withSuccess('Done!');
   ```

### ❌ DON'T

1. Don't hardcode URLs - use route helpers
2. Don't use non-exact match for homepage navigation
3. Don't check too many route names individually - use wildcards
4. Don't forget to handle null cases for optional parameters

---

## Summary Table

| Function | Purpose | Returns |
|----------|---------|---------|
| `route()` | Generate URL for named route | string |
| `url()` | Generate URL from path | string |
| `currentRoute()` | Get current route object | ?Route |
| `currentRouteName()` | Get current route name | ?string |
| `currentPath()` | Get current URL path | string |
| `currentUrl()` | Get full current URL | string |
| `previousUrl()` / `back()` | Get referrer URL | string |
| `routeIs()` | Check if route matches pattern | bool |
| `hasRoute()` | Check if route exists | bool |
| `routeParams()` | Get route parameters | mixed |
| `isMethod()` | Check HTTP method | bool |
| `isGet/Post/Put/Delete/Patch()` | Check specific HTTP method | bool |
| `isAjax()` | Check if AJAX request | bool |
| `wantsJson()` | Check if expects JSON | bool |
| `isActive()` | Check if path/route is active | bool |
| `activeClass()` | Return class if active | string |
| `activeRoute()` | Return class if route active | string |
| `activePath()` | Alias for activeClass | string |
| `activeSegment()` | Return class if segment matches | string |
| `activeUrl()` | Return class if URL matches | string |
| `activeWhen()` | Return class when condition true | string |
| `activeIfQuery()` | Return class if query param matches | string |

---

## Production Checklist

- [x] All functions use `function_exists()` check
- [x] Type declarations enabled (`declare(strict_types=1)`)
- [x] Null safety with null coalescing operators
- [x] Fallback handling when request is null
- [x] Error handling with try-catch blocks
- [x] Consistent naming conventions
- [x] PSR-7 compatible
- [x] No global state pollution (except `setCurrentRequest`)
- [x] Memory efficient
- [x] No external dependencies

---

**Framework Version:** Plugs PHP Framework  
**Last Updated:** 2026-01-08  
**Status:** ✅ Production Ready
