<?php

/*
|--------------------------------------------------------------------------
| Navigation & Route Helper Functions - Usage Examples
|--------------------------------------------------------------------------
|
| Comprehensive examples of navigation helpers for route checking,
| URL generation, and active state management in views.
*/

// ============================================================
// URL GENERATION
// ============================================================

// Generate URL for a named route
$profileUrl = route('user.profile', ['id' => 123]);
// Output: http://yourdomain.com/user/123

// Generate relative URL (without domain)
$relativeUrl = route('user.profile', ['id' => 123], false);
// Output: /user/123

// Generate URL from path
$url = url('/dashboard');
// Output: http://yourdomain.com/dashboard

// URL with query parameters
$searchUrl = url('/search', ['q' => 'php', 'page' => 2]);
// Output: http://yourdomain.com/search?q=php&page=2

// ============================================================
// CURRENT ROUTE INFORMATION
// ============================================================

// Get current route object
$route = currentRoute();
$routeName = $route?->getName();

// Get current route name directly
$name = currentRouteName();
// Example: 'user.profile'

// Get current path
$path = currentPath();
// Example: '/user/profile/123'

// Get current full URL
$currentUrl = currentUrl();
// Output: http://yourdomain.com/user/profile/123?tab=posts

// Get current URL without query string
$currentUrlNoQuery = currentUrl(includeQuery: false);
// Output: http://yourdomain.com/user/profile/123

// Get previous URL
$referer = previousUrl();
$refererWithFallback = previousUrl('/home');

// Shorthand for previous URL
$backUrl = back();

// ============================================================
// ROUTE CHECKING
// ============================================================

// Check if current route matches a name
if (routeIs('user.profile')) {
    // Current route is 'user.profile'
}

// Check multiple route names
if (routeIs(['home', 'dashboard'])) {
    // Current route is either 'home' or 'dashboard'
}

// Wildcard pattern matching
if (routeIs('admin.*')) {
    // Current route starts with 'admin.'
    // Matches: admin.dashboard, admin.users, admin.settings, etc.
}

if (routeIs('user.*.edit')) {
    // Matches: user.profile.edit, user.settings.edit, etc.
}

// Multiple patterns
if (routeIs(['admin.*', 'super.*'])) {
    // Matches routes starting with 'admin.' OR 'super.'
}

// Check if route exists
if (hasRoute('user.profile')) {
    $url = route('user.profile', ['id' => 1]);
}

// ============================================================
// ROUTE PARAMETERS
// ============================================================

// Get single route parameter
$userId = routeParams('id');

// Get with default value
$tab = routeParams('tab', 'overview');

// Get all route parameters
$allParams = routeParams();
// Returns: ['id' => '123', 'slug' => 'my-post']

// ============================================================
// HTTP METHOD CHECKING
// ============================================================

// Check specific method
if (isMethod('POST')) {
    // Handle POST request
}

// Check multiple methods
if (isMethod(['PUT', 'PATCH'])) {
    // Handle PUT or PATCH
}

// Shorthand method checks
if (isGet()) {
    // GET request
}

if (isPost()) {
    // POST request
}

if (isPut()) {
    // PUT request
}

if (isDelete()) {
    // DELETE request
}

if (isPatch()) {
    // PATCH request
}

// ============================================================
// REQUEST TYPE DETECTION
// ============================================================

// Check if AJAX request
if (isAjax()) {
    return ['status' => 'success', 'data' => $data];
}

// Check if client expects JSON
if (wantsJson()) {
    return ['result' => $result];
} else {
    return redirect('/dashboard')->withSuccess('Saved!');
}

// ============================================================
// NAVIGATION ACTIVE STATE HELPERS
// ============================================================

// Check if path/route is active
if (isActive('/dashboard')) {
    // Current path starts with '/dashboard'
}

// Exact match
if (isActive('/dashboard', exact: true)) {
    // Current path is exactly '/dashboard'
}

// Multiple paths
if (isActive(['/users', '/customers'])) {
    // Current path starts with '/users' OR '/customers'
}

// Works with route names too
if (isActive('admin.dashboard')) {
    // Current route is 'admin.dashboard'
}

// Check with wildcard
if (isActive('admin.*')) {
    // Current route starts with 'admin.'
}

// ============================================================
// ACTIVE CLASS HELPERS (FOR VIEWS)
// ============================================================
?>

<!-- Basic usage - returns 'active' if path matches -->
<nav>
    <a href="/dashboard" class="<?= activeClass('/dashboard') ?>">
        Dashboard
    </a>
</nav>

<!-- Custom active class -->
<a href="/users" class="nav-link <?= activeClass('/users', 'nav-active') ?>">
    Users
</a>

<!-- With inactive class -->
<a href="/settings" class="<?= activeClass('/settings', 'active', 'inactive') ?>">
    Settings
</a>

<!-- Exact match only -->
<a href="/" class="<?= activeClass('/', 'active', '', exact: true) ?>">
    Home
</a>

<!-- Multiple paths -->
<a href="/products" class="<?= activeClass(['/products', '/inventory'], 'active') ?>">
    Products
</a>

<!-- Using route names -->
<a href="<?= route('user.profile') ?>" class="<?= activeRoute('user.profile') ?>">
    Profile
</a>

<!-- Route with wildcard -->
<li class="<?= activeRoute('admin.*', 'menu-open') ?>">
    <a href="#">Admin</a>
</li>

<!-- Alias: activePath (same as activeClass) -->
<a href="/blog" class="<?= activePath('/blog') ?>">
    Blog
</a>

<!-- Check specific URL segment -->
<!-- For URL: /admin/users/create -->
<li class="<?= activeSegment(1, 'admin') ?>">Admin</li>
<li class="<?= activeSegment(2, 'users') ?>">Users</li>
<li class="<?= activeSegment(2, ['users', 'customers']) ?>">People</li>

<!-- Check full URL match -->
<a href="https://example.com/page" class="<?= activeUrl('https://example.com/page', 'current') ?>">
    Link
</a>

<!-- Conditional active class -->
<?php $isUserAdmin = checkUserRole('admin'); ?>
<li class="<?= activeWhen($isUserAdmin, 'admin-active') ?>">
    Admin Panel
</li>

<!-- Active based on query parameter -->
<!-- For URL: /products?sort=price -->
<button class="<?= activeIfQuery('sort', 'price', 'btn-primary', 'btn-default') ?>">
    Sort by Price
</button>

<button class="<?= activeIfQuery('sort', ['name', 'title'], 'active') ?>">
    Sort Alphabetically
</button>

<?php
// ============================================================
// PRACTICAL VIEW EXAMPLES
// ============================================================
?>

<!-- Main Navigation -->
<nav class="main-nav">
    <a href="<?= route('home') ?>" class="nav-link <?= activeRoute('home') ?>">
        Home
    </a>
    <a href="<?= route('dashboard') ?>" class="nav-link <?= activeRoute('dashboard') ?>">
        Dashboard
    </a>
    <a href="<?= route('users.index') ?>" class="nav-link <?= activeRoute('users.*') ?>">
        Users
    </a>
    <a href="<?= route('settings') ?>" class="nav-link <?= activeRoute('settings.*') ?>">
        Settings
    </a>
</nav>

<!-- Sidebar with nested menus -->
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

    <!-- Content section -->
    <div class="menu-section <?= activeRoute('content.*', 'section-active') ?>">
        <h3>Content</h3>
        <ul>
            <li class="<?= activeRoute('content.posts.*') ?>">
                <a href="<?= route('content.posts.index') ?>">Posts</a>
            </li>
            <li class="<?= activeRoute('content.pages.*') ?>">
                <a href="<?= route('content.pages.index') ?>">Pages</a>
            </li>
        </ul>
    </div>
</aside>

<!-- Breadcrumbs -->
<nav class="breadcrumb">
    <a href="<?= route('home') ?>" class="<?= activeRoute('home', 'current') ?>">Home</a>
    <?php if (routeIs('user.*')): ?>
        <span>/</span>
        <a href="<?= route('user.index') ?>" class="<?= activeRoute('user.index', 'current') ?>">
            Users
        </a>
    <?php endif; ?>
    <?php if (routeIs('user.profile')): ?>
        <span>/</span>
        <span class="current">Profile</span>
    <?php endif; ?>
</nav>

<!-- Tabs with query parameters -->
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

<!-- Profile tabs using route segments -->
<!-- For URL: /profile/john/posts -->
<div class="profile-tabs">
    <a href="/profile/<?= routeParams('username') ?>/posts" class="<?= activeSegment(3, 'posts') ?>">
        Posts
    </a>
    <a href="/profile/<?= routeParams('username') ?>/followers" class="<?= activeSegment(3, 'followers') ?>">
        Followers
    </a>
    <a href="/profile/<?= routeParams('username') ?>/following" class="<?= activeSegment(3, 'following') ?>">
        Following
    </a>
</div>

<?php
// ============================================================
// CONTROLLER EXAMPLES
// ============================================================

class DashboardController
{
    public function index($request)
    {
        // Get current route info
        $routeName = currentRouteName();
        $routeParams = routeParams();

        // Check route
        if (routeIs('admin.*')) {
            // Load admin-specific data
        }

        return view('dashboard', [
            'currentRoute' => $routeName,
            'params' => $routeParams
        ]);
    }

    public function handleForm($request)
    {
        // Check request method
        if (isGet()) {
            return view('form');
        }

        if (isPost()) {
            // Process form
            $data = $request->getParsedBody();

            // Check if AJAX
            if (isAjax() || wantsJson()) {
                return ['success' => true, 'data' => $data];
            }

            // Regular redirect
            return redirectBack()->withSuccess('Form submitted!');
        }
    }

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
}

// ============================================================
// MIDDLEWARE EXAMPLES
// ============================================================

class AuthMiddleware
{
    public function handle($request, $next)
    {
        if (!isLoggedIn()) {
            // Save intended URL for redirect after login
            return redirect('/login')
                ->with('intended', currentUrl())
                ->withError('Please login to continue');
        }

        return $next($request);
    }
}

class AdminMiddleware
{
    public function handle($request, $next)
    {
        // Check if on admin routes
        if (routeIs('admin.*') && !isAdmin()) {
            return redirectBack()
                ->withError('Access denied');
        }

        return $next($request);
    }
}

// ============================================================
// ADVANCED PATTERNS
// ============================================================

// Dynamic menu builder
function buildMenu(array $items): array
{
    return array_map(function ($item) {
        return [
            'label' => $item['label'],
            'url' => route($item['route']),
            'active' => routeIs($item['route']),
            'children' => isset($item['children'])
                ? buildMenu($item['children'])
                : []
        ];
    }, $items);
}

$menu = buildMenu([
    ['label' => 'Dashboard', 'route' => 'dashboard'],
    [
        'label' => 'Users',
        'route' => 'users.*',
        'children' => [
            ['label' => 'All Users', 'route' => 'users.index'],
            ['label' => 'Add User', 'route' => 'users.create'],
        ]
    ],
]);

// Conditional rendering based on route
function renderForRoute(string $pattern, callable $callback)
{
    if (routeIs($pattern)) {
        return $callback();
    }
    return '';
}

// Usage
echo renderForRoute('admin.*', function () {
    return '<div class="admin-toolbar">...</div>';
});

// Helper to check if we're on a specific section
function onSection(string $section): bool
{
    return activeSegment(1, $section) === 'active';
}

if (onSection('admin')) {
    // Load admin assets
}

// ============================================================
// ROUTE UTILITIES
// ============================================================

// Safe route generation with fallback
function safeRoute(string $name, array $params = [], string $fallback = '/'): string
{
    return hasRoute($name) ? route($name, $params) : $fallback;
}

// Generate route only if it exists
$profileUrl = safeRoute('user.profile', ['id' => 123], '/');

// Get all current route data
function getCurrentRouteData(): array
{
    return [
        'name' => currentRouteName(),
        'path' => currentPath(),
        'url' => currentUrl(),
        'params' => routeParams(),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'is_ajax' => isAjax(),
        'wants_json' => wantsJson(),
    ];
}

// ============================================================
// TIPS & BEST PRACTICES
// ============================================================

/*
1. Use route names instead of hardcoded paths:
   ✅ Good: route('user.profile', ['id' => $id])
   ❌ Bad: "/user/profile/{$id}"

2. Use wildcard patterns for grouped routes:
   ✅ Good: routeIs('admin.*')
   ❌ Bad: routeIs(['admin.dashboard', 'admin.users', ...])

3. Use exact matching for homepage to avoid false positives:
   ✅ Good: activeClass('/', 'active', '', exact: true)
   ❌ Bad: activeClass('/') // Would match all routes starting with /

4. For navigation state, prefer route names over paths:
   ✅ Good: activeRoute('user.profile')
   ❌ Bad: activeClass('/user/profile/123') // Harder to maintain

5. Use activeSegment for tab-like navigation within a section:
   ✅ Good: activeSegment(3, 'posts') for /profile/{user}/posts

6. Combine helpers for complex conditions:
   if (routeIs('admin.*') && isPost() && isAjax()) {
       // Handle admin AJAX POST
   }

7. Use hasRoute() before generating routes dynamically:
   if (hasRoute($routeName)) {
       $url = route($routeName);
   }

8. For API responses, check wantsJson():
   return wantsJson() 
       ? jsonResponse($data) 
       : redirect('/')->withSuccess('Done!');
*/
?>