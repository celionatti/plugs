# Plugs View System - Usage Guide

## Key Fixes Applied

### 1. **Component System Issues Fixed**

- **Attribute Parsing**: Fixed regex to properly handle attributes with and without quotes, including variable bindings (`$variable`)
- **Slot Content Escaping**: Properly escape single quotes in slot content to prevent PHP syntax errors
- **Data Array Building**: Correctly distinguish between variable references (`$var`) and string literals
- **Component Path Resolution**: Improved CamelCase to snake_case conversion for component filenames

### 2. **ViewEngine Improvements**

- Added `shareData()` method for sharing multiple data items
- Added `viewExists()` method to check if views exist
- Added `clearCache()` method to clear compiled view cache
- Improved error messages with file paths and line numbers
- Better handling of temporary files in direct rendering mode
- Fixed parent layout rendering to prevent infinite recursion

### 3. **View Class Enhancements**

- Added `getView()` and `getData()` methods
- Improved error handling in `__toString()` method
- Better debug mode detection (checks APP_DEBUG constant, $_ENV, and getenv)
- Enhanced debug error output with collapsible stack trace

### 4. **Additional Features**

- Added `@continue` and `@break` directives for loops with optional conditions
- Fixed `@php` block compilation to handle both inline and block syntax
- Added helper functions for common operations

## Directory Structure

```
your-project/
├── views/
│   ├── components/
│   │   ├── button.plug.php
│   │   ├── sidebar.plug.php
│   │   └── alert.plug.php
│   ├── layouts/
│   │   └── app.plug.php
│   └── pages/
│       └── home.plug.php
└── storage/
    └── cache/
        └── views/
```

## Component Examples

### Creating a Component: `views/components/button.plug.php`

```php
<button 
    type="{{ $type ?? 'button' }}" 
    class="btn {{ $variant ?? 'primary' }} {{ $class ?? '' }}"
    {{ isset($disabled) && $disabled ? 'disabled' : '' }}
>
    {{ $slot }}
</button>
```

### Creating a Component: `views/components/alert.plug.php`

```php
<div class="alert alert-{{ $type ?? 'info' }}" role="alert">
    @if(isset($title))
        <h4 class="alert-heading">{{ $title }}</h4>
    @endif
    {{ $slot }}
</div>
```

### Creating a Component: `views/components/sidebar.plug.php`

```php
<aside class="sidebar {{ $class ?? '' }}">
    <div class="sidebar-header">
        <h3>{{ $title ?? 'Sidebar' }}</h3>
    </div>
    <div class="sidebar-content">
        {{ $slot }}
    </div>
</aside>
```

## Using Components in Views

### Example 1: Basic Component Usage

```php
<!-- views/pages/home.plug.php -->
@extends('layouts.app')

@section('content')
    <h1>Welcome to Home Page</h1>
    
    <!-- Self-closing component -->
    <Button type="submit" variant="primary" />
    
    <!-- Component with slot content -->
    <Button type="button" variant="danger">
        Delete Item
    </Button>
    
    <!-- Component with multiple attributes -->
    <Alert type="success" title="Success!">
        Your action was completed successfully.
    </Alert>
@endsection
```

### Example 2: Passing PHP Variables to Components

```php
@php
    $userRole = 'admin';
    $buttonText = 'Save Changes';
@endphp

<!-- Pass variables using $ prefix (no quotes) -->
<Button type="submit" variant=$userRole>
    {{ $buttonText }}
</Button>

<!-- Or in quotes -->
<Alert type="warning" title="Warning">
    User role: {{ $userRole }}
</Alert>
```

### Example 3: Nested Components

```php
<Sidebar title="Navigation" class="main-sidebar">
    <Alert type="info">
        Welcome back, {{ $username }}!
    </Alert>
    
    <Button variant="primary">
        Dashboard
    </Button>
    
    <Button variant="secondary">
        Settings
    </Button>
</Sidebar>
```

## Using the View System

### Basic Usage

```php
<?php
// In your controller or route handler

use Plugs\View\ViewEngine;
use Plugs\View\View;

// Create view engine
$engine = new ViewEngine(
    __DIR__ . '/views',           // Views directory
    __DIR__ . '/storage/cache/views', // Cache directory
    true                          // Enable caching
);

// Create and render a view
$view = new View($engine, 'pages.home', [
    'title' => 'Welcome',
    'username' => 'John Doe'
]);

echo $view->render();
// Or simply: echo $view;
```

### Using Helper Functions

```php
<?php
// Define constants first
define('VIEW_PATH', __DIR__ . '/views');
define('CACHE_PATH', __DIR__ . '/storage/cache/views');
define('VIEW_CACHE_ENABLED', true);

// Include the helpers
require_once 'ViewHelpers.php';

// Now you can use the helper function
echo view('pages.home', [
    'title' => 'Welcome',
    'username' => 'John Doe'
]);

// Render a component directly
echo component('Button', [
    'type' => 'submit',
    'variant' => 'primary',
    '__slot' => 'Click Me'
]);
```

### Sharing Data Across All Views

```php
<?php
$engine->share('appName', 'My Application');
$engine->share('currentYear', date('Y'));

// Or share multiple at once
$engine->shareData([
    'appName' => 'My Application',
    'currentYear' => date('Y'),
    'user' => $currentUser
]);

// Now all views have access to these variables
```

## Advanced Directives

### Loops with Continue/Break

```php
@foreach($users as $user)
    @continue($user->is_banned)
    
    <div>{{ $user->name }}</div>
    
    @break($loop->index >= 10)
@endforeach
```

### PHP Blocks

```php
<!-- Inline PHP -->
@php($count = 0)

<!-- Block PHP -->
@php
    $total = 0;
    foreach ($items as $item) {
        $total += $item->price;
    }
@endphp

<p>Total: {{ $total }}</p>
```

### Including Subviews

```php
@include('partials.header')

@include('partials.sidebar', ['title' => 'Custom Title'])

<main>
    @yield('content')
</main>

@include('partials.footer')
```

## Error Handling

The system provides detailed error messages in debug mode:

```php
<?php
// Enable debug mode
define('APP_DEBUG', true);

// Or via environment variable
$_ENV['APP_DEBUG'] = 'true';

// Now errors will show full stack traces
```

## Cache Management

```php
<?php
// Clear all compiled views
$engine->clearCache();

// Check if a view exists
if ($engine->viewExists('pages.home')) {
    // Render the view
}

// Check if a component exists
if ($engine->componentExists('Button')) {
    // Use the component
}

// Get all available components
$components = $engine->getAvailableComponents();
// Returns: ['Button', 'Alert', 'Sidebar', ...]
```

## Common Issues & Solutions

### Issue 1: Component Not Found

**Error**: `Component [Button] not found`

**Solution**: Ensure the component file exists at `views/components/button.plug.php` (lowercase, snake_case)

### Issue 2: Slot Content Not Rendering

**Error**: Slot shows escaped HTML or doesn't appear

**Solution**: In your component, use `{!! $slot !!}` for unescaped output or `{{ $slot }}` for escaped

### Issue 3: Variables Not Passing to Components

**Error**: Undefined variable in component

**Solution**: When passing variables, don't use quotes: `<Button variant=$myVar>` not `<Button variant="$myVar">`

### Issue 4: Cache Not Updating

**Solution**: Either disable caching during development or clear cache manually:

```php
$engine->clearCache();
```

## Best Practices

1. **Component Naming**: Use PascalCase for component names in templates: `<Button>`, `<Alert>`
2. **Component Files**: Use snake_case for filenames: `button.plug.php`, `alert.plug.php`
3. **Slot Content**: Always provide default content or check if slot exists
4. **Variable Passing**: Use `$variable` syntax without quotes for PHP variables
5. **Caching**: Enable caching in production, disable during development
6. **Error Handling**: Always enable debug mode during development

## Performance Tips

1. Enable caching in production
2. Use `@include` sparingly for better performance
3. Cache component lookups if rendering many times
4. Clear cache after deploying updates
5. Use shared data for common variables instead of passing to each view
