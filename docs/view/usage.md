# Plugs View System - Complete Documentation

## Overview

The Plugs View System is a powerful, Laravel-inspired templating engine for PHP that compiles templates into optimized PHP code.

## Key Features

- ✅ **Fixed compilation order** - Directives now compile in the correct sequence
- ✅ **Improved regex patterns** - More reliable pattern matching
- ✅ **Better error handling** - Production-ready error messages
- ✅ **Component system** - Reusable UI components with slots
- ✅ **Template inheritance** - Extend layouts with sections
- ✅ **Custom directives** - Create your own template syntax
- ✅ **Caching support** - Compile once, render many times

---

## Installation & Setup

```php
use Plugs\View\ViewEngine;

$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache/views',
    cacheEnabled: true  // Enable for production
);
```

---

## Output Directives

### Escaped Echo (Safe HTML)

```php
{{ $variable }}
{{ $user->name }}
{{ $array['key'] }}
```

Automatically escapes HTML to prevent XSS attacks.

### Raw Echo (Unescaped)

```php
{{{ $html }}}
{{{ $trustedContent }}}
```

⚠️ **Warning:** Only use with trusted content!

### JSON Output

```php
@json($array)
<script>
    var data = @json($phpArray);
</script>
```

---

## Conditional Directives

### If Statements

```php
@if($condition)
    <p>Condition is true</p>
@elseif($other)
    <p>Other condition</p>
@else
    <p>All false</p>
@endif
```

### Unless (Inverted If)

```php
@unless($user->isPremium())
    <p>Upgrade to premium!</p>
@endunless
```

### Isset Check

```php
@isset($variable)
    <p>Variable is set: {{ $variable }}</p>
@endisset
```

### Empty Check

```php
@empty($array)
    <p>Array is empty</p>
@endempty
```

### Switch Statement

```php
@switch($status)
    @case('active')
        <span class="badge-success">Active</span>
        @break
    @case('pending')
        <span class="badge-warning">Pending</span>
        @break
    @default
        <span class="badge-secondary">Unknown</span>
@endswitch
```

---

## Loop Directives

### Foreach Loop

```php
@foreach($users as $user)
    <li>{{ $user->name }}</li>
@endforeach

// With key-value pairs
@foreach($users as $id => $user)
    <li>{{ $id }}: {{ $user->name }}</li>
@endforeach
```

**Safety:** Automatically checks if variable is set and iterable.

### For Loop

```php
@for($i = 0; $i < 10; $i++)
    <p>Item {{ $i }}</p>
@endfor
```

### While Loop

```php
@while($condition)
    <p>Still looping...</p>
@endwhile
```

### Forelse (Loop with Empty State)

```php
@forelse($users as $user)
    <li>{{ $user->name }}</li>
@empty
    <p>No users found</p>
@endforelse
```

### Continue & Break

```php
@foreach($users as $user)
    @continue($user->isInactive())
    
    <li>{{ $user->name }}</li>
    
    @break($loop->index >= 10)
@endforeach
```

---

## PHP Code Blocks

### Multi-line PHP

```php
@php
$total = 0;
foreach ($items as $item) {
    $total += $item->price;
}
$discount = $total * 0.1;
@endphp

<p>Total: ${{ $total }}</p>
<p>Discount: ${{ $discount }}</p>
```

### Inline PHP

```php
@php($counter = 0)
@php($result = calculateTotal($items))
```

---

## Template Inheritance

### Layout File (layout.plug.php)

```php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    @stack('styles')
</head>
<body>
    <header>
        @yield('header')
    </header>
    
    <main>
        @yield('content')
    </main>
    
    <footer>
        @yield('footer')
    </footer>
    
    @stack('scripts')
</body>
</html>
```

### Child View

```php
@extends('layout')

@section('title')
    My Page Title
@endsection

@section('content')
    <h1>Hello World</h1>
    <p>This is my content</p>
@endsection

@push('scripts')
    <script src="/js/app.js"></script>
@endpush
```

---

## Include Directives

### Basic Include

```php
@include('partials.header')
```

### Include with Data

```php
@include('partials.card', ['title' => 'My Card', 'content' => 'Card content'])
```

### Include with Variables

```php
@include('partials.item', $itemData)
```

---

## Stack Directives (Asset Management)

### Push to Stack

```php
@push('scripts')
    <script src="/js/module1.js"></script>
@endpush

@push('scripts')
    <script src="/js/module2.js"></script>
@endpush
```

### Prepend to Stack

```php
@prepend('scripts')
    <script src="/js/first.js"></script>
@endprepend
```

### Render Stack

```php
@stack('scripts')
```

**Output Order:**

1. Prepended items (in order)
2. Pushed items (in order)

---

## Component System

### Creating a Component (components/alert.plug.php)

```php
<div class="alert alert-{{ $type ?? 'info' }}">
    @isset($title)
        <h4>{{ $title }}</h4>
    @endisset
    
    <div class="alert-body">
        {{{ $slot }}}
    </div>
</div>
```

### Using Components

#### Self-closing

```php
<Alert type="success" title="Success!" />
```

#### With Slot Content

```php
<Alert type="danger" title="Error">
    <p>Something went wrong!</p>
    <ul>
        <li>Item 1</li>
        <li>Item 2</li>
    </ul>
</Alert>
```

#### With Dynamic Props

```php
<Alert type="{{ $alertType }}" title="{{ $alertTitle }}">
    {{ $alertMessage }}
</Alert>
```

### Component Naming Convention

- **File:** `alert.plug.php` (snake_case)
- **Usage:** `<Alert />` (PascalCase)

---

## Form Helpers

### CSRF Token

```php
<form method="POST">
    @csrf
    <input type="text" name="username">
    <button type="submit">Submit</button>
</form>
```

**Output:**

```html
<input type="hidden" name="_token" value="[token_value]">
```

### Method Spoofing

```php
<form method="POST">
    @method('PUT')
    @csrf
    <!-- form fields -->
</form>
```

---

## Custom Directives

### Registering Custom Directives

```php
// Simple directive
$viewEngine->directive('datetime', function ($expression) {
    return "<?php echo date('Y-m-d H:i:s', {$expression}); ?>";
});

// Conditional directive
$viewEngine->directive('admin', function () {
    return "<?php if (auth()->user()->isAdmin()): ?>";
});

$viewEngine->directive('endadmin', function () {
    return "<?php endif; ?>";
});
```

### Using Custom Directives

```php
// Simple
@datetime($timestamp)

// Conditional
@admin
    <a href="/admin">Admin Panel</a>
@endadmin
```

---

## Built-in Custom Directives

### Debug Helpers

```php
@dump($variable)      // var_dump without exit
@dd($variable)        // var_dump and exit
```

### Environment Checks

```php
@production
    <script src="/js/app.min.js"></script>
@endproduction

@env('development')
    <div class="debug-bar">Debug Info</div>
@endenv
```

### Authentication

```php
@auth
    <p>Welcome, {{ auth()->user()->name }}</p>
@endauth

@guest
    <a href="/login">Login</a>
@endguest
```

---

## Error Handling

### Error Directives

```php
<input type="email" name="email" value="{{ old('email') }}">

@error('email')
    <span class="error">{{ $errors->first('email') }}</span>
@enderror
```

---

## Once Directive

Execute code only once even if template is rendered multiple times:

```php
@once
    <script src="/js/library.js"></script>
@endonce
```

---

## Comments

### Template Comments (Removed at Compile Time)

```php
{{-- This comment will not appear in rendered HTML --}}
{{-- 
    Multi-line comment
    These are stripped during compilation
--}}
```

---

## Best Practices

### 1. Use Escaped Echo by Default

```php
// Good
{{ $userInput }}

// Only when needed
{{{ $trustedHtml }}}
```

### 2. Organize Views

views/
├── layouts/
│   ├── app.plug.php
│   └── admin.plug.php
├── components/
│   ├── alert.plug.php
│   ├── card.plug.php
│   └── button.plug.php
├── partials/
│   ├── header.plug.php
│   └── footer.plug.php
└── pages/
    ├── home.plug.php
    └── about.plug.php

### 3. Enable Caching in Production

```php
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache/views',
    cacheEnabled: $_ENV['APP_ENV'] === 'production'
);
```

### 4. Clear Cache When Deploying

```php
$viewEngine->clearCache();
```

### 5. Use Components for Reusable UI

```php
// Instead of repeating markup
<Alert type="success" title="Saved!">
    Your changes have been saved.
</Alert>
```

### 6. Validate Data Before Passing to Views

```php
// Good
$viewEngine->render('user.profile', [
    'user' => $user ?? null,
    'posts' => $posts ?? []
]);

// Then in template
@isset($user)
    <h1>{{ $user->name }}</h1>
@endisset
```

---

## Performance Tips

1. **Enable caching in production** - Compiles once, renders fast
2. **Use `@once` for external resources** - Prevents duplicate includes
3. **Leverage components** - Reduces duplication and improves maintainability
4. **Minimize PHP logic in views** - Keep business logic in controllers
5. **Clear cache after deployments** - Ensures fresh compilation

---

## Security Considerations

1. **Always escape user input** with `{{ }}`
2. **Never use `{{{ }}}` with user-provided data**
3. **Validate includes** - Path traversal protection is built-in
4. **Use CSRF tokens** - `@csrf` for all forms
5. **Sanitize before rendering** - Don't rely solely on view escaping

---

## Troubleshooting

### Directive Not Working

1. Check compilation order (comments → verbatim → php → conditionals → loops → echo)
2. Verify syntax (spacing matters)
3. Clear cache: `$viewEngine->clearCache()`
4. Enable debug mode to see compiled output

### Component Not Found

1. Check file naming: `alert.plug.php` → `<Alert />`
2. Verify component directory exists
3. Check file permissions

### Cache Issues

1. Ensure cache directory is writable
2. Clear cache after code changes
3. Disable caching during development

---

## Complete Example

```php
{{-- Layout: views/layouts/app.plug.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'My App')</title>
    @stack('styles')
</head>
<body>
    @include('partials.nav')
    
    <main class="container">
        @yield('content')
    </main>
    
    @stack('scripts')
</body>
</html>

{{-- Page: views/users/index.plug.php --}}
@extends('layouts.app')

@section('title', 'Users List')

@push('styles')
    <link rel="stylesheet" href="/css/users.css">
@endpush

@section('content')
    <h1>Users</h1>
    
    @auth
        <a href="/users/create" class="btn">Add User</a>
    @endauth
    
    @forelse($users as $user)
        <UserCard 
            name="{{ $user->name }}" 
            email="{{ $user->email }}"
            role="{{ $user->role }}"
        />
    @empty
        <Alert type="info">No users found.</Alert>
    @endforelse
@endsection

@push('scripts')
    <script src="/js/users.js"></script>
@endpush
```

---

## Version Information

- **Version:** 2.0 (Fixed)
- **PHP Requirement:** 7.4+
- **License:** MIT

For issues or contributions, please visit the project repository.
