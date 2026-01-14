# Plugs View System - Complete Usage Guide

## Table of Contents

1. [Setup & Configuration](#setup--configuration)
2. [Basic Usage](#basic-usage)
3. [Template Syntax](#template-syntax)
4. [Components](#components)
5. [Template Inheritance](#template-inheritance)
6. [Control Structures](#control-structures)
7. [Loops](#loops)
8. [Includes](#includes)
9. [Stacks](#stacks)
10. [Custom Directives](#custom-directives)
11. [Advanced Features](#advanced-features)
12. [SPA Bridge & Fragment Loading](spa.md)

---

## Setup & Configuration

### Basic Initialization

```php
use Plugs\View\ViewEngine;

// Initialize the view engine
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache',
    cacheEnabled: true // Set to false in development
);
```

### Directory Structure

/views
  /components
    button.plug.php
    card.plug.php
  /layouts
    app.plug.php
  home.plug.php
  about.plug.php
/cache

```

---

## Basic Usage

### Creating a View Instance

```php
use Plugs\View\View;

// Method 1: Using ViewEngine
$html = $viewEngine->render('home', ['title' => 'Welcome']);

// Method 2: Using View class
$view = new View($viewEngine, 'home', ['title' => 'Welcome']);
$html = $view->render();

// Method 3: Fluent interface
$view = new View($viewEngine, 'home');
$html = $view->with('title', 'Welcome')
           ->with('user', $user)
           ->render();

// Method 4: Echo directly
echo $view; // Calls __toString() internally
```

### Sharing Data Across All Views

```php
// Share data globally
$viewEngine->share('appName', 'My Application');
$viewEngine->share('currentYear', date('Y'));

// Now all views have access to $appName and $currentYear
```

---

## Template Syntax

### Outputting Data

#### Escaped Output (Safe)

```php
{{ $variable }}
{{ $user->name }}
{{ $array['key'] }}
{{ strtoupper($name) }}
{{ $price * 1.1 }}
```

Compiles to:

```php
<?php echo htmlspecialchars((string)($variable), ENT_QUOTES, 'UTF-8'); ?>
```

#### Raw Output (Unescaped)

```php
{{{ $htmlContent }}}
{{{ $user->getFormattedBio() }}}
```

Compiles to:

```php
<?php echo $htmlContent; ?>
```

#### JSON Output

```php
<script>
    const user = @json($user);
    const config = @json($settings);
</script>
```

### Comments

```php
{{-- This comment will not appear in HTML --}}
{{-- 
    Multi-line comment
    Also won't appear
--}}
```

### Raw PHP

```php
@php
    $fullName = $firstName . ' ' . $lastName;
    $total = array_sum($prices);
@endphp

@php($count = count($items))
```

---

## Components

### Creating Components

**File: `/views/components/button.plug.php`**

```php
<button 
    type="{{ $type ?? 'button' }}" 
    class="btn {{ $variant ?? 'primary' }}"
    {{ $disabled ? 'disabled' : '' }}
>
    {{{ $slot }}}
</button>
```

**File: `/views/components/card.plug.php`**

```php
<div class="card {{ $class ?? '' }}">
    @if($title ?? false)
        <div class="card-header">
            <h3>{{ $title }}</h3>
        </div>
    @endif
    
    <div class="card-body">
        {{{ $slot }}}
    </div>
    
    @if($footer ?? false)
        <div class="card-footer">
            {{{ $footer }}}
        </div>
    @endif
</div>
```

### Using Components

#### Self-Closing Components

```php
<Button type="submit" variant="success" />
```

#### Components with Content (Slots)

```php
<Button type="submit" variant="primary">
    Save Changes
</Button>

<Card title="User Profile" class="mb-4">
    <p>{{ $user->name }}</p>
    <p>{{ $user->email }}</p>
</Card>
```

#### Components with Variables

```php
<Button type="button" variant="{{ $buttonStyle }}">
    Click Me
</Button>

<Card title="{{ $article->title }}" class="article-card">
    {{ $article->excerpt }}
</Card>
```

#### Component with Boolean Attributes

```php
<Button disabled type="submit">
    Submit
</Button>
```

### Component Management

```php
// Check if component exists
if ($viewEngine->componentExists('Button')) {
    // Render component
}

// Get all available components
$components = $viewEngine->getAvailableComponents();
// Returns: ['Button' => '/path/to/button.plug.php', ...]
```

---

## Template Inheritance

### Creating a Layout

**File: `/views/layouts/app.plug.php`**

```php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    <meta charset="UTF-8">
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
        @yield('footer', '<p>© 2024 My App</p>')
    </footer>
    
    @stack('scripts')
</body>
</html>
```

### Extending Layouts

**File: `/views/home.plug.php`**

```php
@extends('layouts.app')

@section('title', 'Home Page')

@section('header')
    <h1>Welcome to Our Site</h1>
@endsection

@section('content')
    <p>This is the home page content.</p>
    
    @if($featured)
        <div class="featured">
            {{ $featured->title }}
        </div>
    @endif
@endsection

@section('footer')
    @parent {{-- Include parent footer content --}}
    <p>Additional footer content</p>
@endsection
```

### Section Options

```php
// Define and show immediately
@section('sidebar')
    <div>Sidebar content</div>
@show

// Define for later use
@section('content')
    <div>Content</div>
@endsection

// Inline section
@section('title', 'Page Title')

// Use parent section content
@section('header')
    @parent
    <p>Additional header</p>
@endsection
```

---

## Control Structures

### Conditionals

#### If/Else

```php
@if($user->isAdmin())
    <p>Admin Panel</p>
@elseif($user->isModerator())
    <p>Moderator Panel</p>
@else
    <p>User Panel</p>
@endif
```

#### Unless

```php
@unless($user->hasAccess())
    <p>Access Denied</p>
@endunless

{{-- Equivalent to: @if(!$user->hasAccess()) --}}
```

#### Isset

```php
@isset($user)
    <p>Welcome, {{ $user->name }}</p>
@endisset
```

#### Empty

```php
@empty($items)
    <p>No items found</p>
@endempty
```

### Default Directives

#### Environment Checks

```php
@env('local')
    <div class="debug-bar">Debug Mode</div>
@endenv

@production
    <script src="analytics.js"></script>
@endproduction
```

#### Authentication

```php
@auth
    <a href="/dashboard">Dashboard</a>
@endauth

@guest
    <a href="/login">Login</a>
@endguest
```

#### Error Handling

```php
@error('email')
    <span class="error">{{ $message }}</span>
@enderror
```

---

## Loops

### Foreach

#### Basic Foreach

```php
@foreach($users as $user)
    <li>{{ $user->name }}</li>
@endforeach
```

#### With Key

```php
@foreach($products as $id => $product)
    <div data-id="{{ $id }}">
        {{ $product->name }}
    </div>
@endforeach
```

#### Safe Foreach (Auto-checks if iterable)

```php
{{-- Automatically checks if $items exists and is iterable --}}
@foreach($items as $item)
    <p>{{ $item }}</p>
@endforeach
```

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

### Continue and Break

```php
@foreach($users as $user)
    @continue($user->isDeleted())
    
    <li>{{ $user->name }}</li>
    
    @break($user->isAdmin())
@endforeach

{{-- Or without conditions --}}
@foreach($items as $item)
    @if($item->skip)
        @continue
    @endif
    
    <p>{{ $item->name }}</p>
    
    @if($item->last)
        @break
    @endif
@endforeach
```

---

## Includes

### Basic Include

```php
@include('partials.header')

@include('partials.sidebar')
```

### Include with Data

```php
@include('partials.alert', ['type' => 'success', 'message' => 'Saved!'])

@include('components.user-card', ['user' => $currentUser])
```

### Include Example

**File: `/views/partials/alert.plug.php`**

```php
<div class="alert alert-{{ $type }}">
    {{ $message }}
</div>
```

**Usage:**

```php
@include('partials.alert', ['type' => 'danger', 'message' => 'Error occurred'])
```

---

## Stacks

Stacks allow you to push content to named stacks that can be rendered elsewhere.

### Defining Stacks

**In Layout:**

```php
<head>
    @stack('styles')
</head>
<body>
    @yield('content')
    
    @stack('scripts')
</body>
```

### Pushing to Stacks

**In Child Views:**

```php
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="custom.css">
    <style>
        .special { color: red; }
    </style>
@endpush

@section('content')
    <p>Content here</p>
@endsection

@push('scripts')
    <script src="custom.js"></script>
    <script>
        console.log('Page loaded');
    </script>
@endpush
```

### Prepending to Stacks

```php
{{-- Add to beginning of stack --}}
@prepend('scripts')
    <script src="jquery.js"></script>
@endprepend

@push('scripts')
    <script src="app.js"></script>
@endpush

{{-- Output order: jquery.js, then app.js --}}
```

---

## Custom Directives

### Registering Custom Directives

```php
// Simple directive without parameters
$viewEngine->directive('datetime', function () {
    return "<?php echo date('Y-m-d H:i:s'); ?>";
});

// Directive with parameter
$viewEngine->directive('currency', function ($expression) {
    return "<?php echo '$' . number_format($expression, 2); ?>";
});

// Complex directive
$viewEngine->directive('alert', function ($expression) {
    return "<?php echo \"<div class='alert alert-info'>{$expression}</div>\"; ?>";
});

// Conditional directive
$viewEngine->directive('admin', function () {
    return "<?php if (auth()->check() && auth()->user()->isAdmin()): ?>";
});

$viewEngine->directive('endadmin', function () {
    return "<?php endif; ?>";
});
```

### Using Custom Directives

```php
{{-- Simple directive --}}
<p>Current time: @datetime</p>

{{-- With parameter --}}
<p>Price: @currency($product->price)</p>

{{-- Alert directive --}}
@alert('This is an important message!')

{{-- Custom conditional --}}
@admin
    <a href="/admin">Admin Panel</a>
@endadmin
```

### Checking Directives

```php
// Check if directive exists
if ($viewEngine->hasDirective('currency')) {
    // Directive is registered
}

// Get all registered directives
$directives = $viewEngine->getDirectives();
// Returns: ['dd', 'dump', 'env', 'currency', ...]
```

---

## Advanced Features

### CSRF Protection

```php
<form method="POST" action="/submit">
    @csrf
    {{-- Generates: <input type="hidden" name="_token" value="..."> --}}
    
    <input type="text" name="name">
    <button type="submit">Submit</button>
</form>
```

### Method Spoofing

```php
<form method="POST" action="/update">
    @csrf
    @method('PUT')
    {{-- Generates: <input type="hidden" name="_method" value="PUT"> --}}
    
    <button type="submit">Update</button>
</form>

<form method="POST" action="/delete">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

### Once Directive

Execute code only once, even if included multiple times:

```php
@once
    <script src="jquery.js"></script>
@endonce

@once
    <style>
        /* This CSS will only appear once */
        .special { color: blue; }
    </style>
@endonce
```

### Debug Directives

```php
{{-- Dump variable and continue --}}
@dump($user)

{{-- Dump and die --}}
@dd($user)
```

### Cache Management

```php
// Clear all compiled views
$viewEngine->clearCache();

// Disable cache for development
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache',
    cacheEnabled: false // Disable in development
);
```

---

## Complete Examples

### Example 1: Blog Post Page

**Layout: `/views/layouts/blog.plug.php`**

```php
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title') - My Blog</title>
    @stack('meta')
    <link rel="stylesheet" href="blog.css">
    @stack('styles')
</head>
<body>
    <nav>
        @include('partials.navigation')
    </nav>
    
    <main>
        @yield('content')
    </main>
    
    <footer>
        @include('partials.footer')
    </footer>
    
    @stack('scripts')
</body>
</html>
```

**Post View: `/views/blog/post.plug.php`**

```php
@extends('layouts.blog')

@section('title', $post->title)

@push('meta')
    <meta name="description" content="{{ $post->excerpt }}">
    <meta property="og:title" content="{{ $post->title }}">
@endpush

@section('content')
    <article>
        <h1>{{ $post->title }}</h1>
        
        <div class="meta">
            <span>By {{ $post->author->name }}</span>
            <span>{{ $post->published_at }}</span>
        </div>
        
        <div class="content">
            {{{ $post->content }}}
        </div>
        
        @if($post->tags)
            <div class="tags">
                @foreach($post->tags as $tag)
                    <span class="tag">{{ $tag }}</span>
                @endforeach
            </div>
        @endif
        
        @unless($post->comments_enabled)
            <p>Comments are disabled for this post.</p>
        @endunless
        
        @isset($relatedPosts)
            <h3>Related Posts</h3>
            @foreach($relatedPosts as $related)
                <Card title="{{ $related->title }}">
                    {{ $related->excerpt }}
                </Card>
            @endforeach
        @endisset
    </article>
@endsection

@push('scripts')
    <script src="post.js"></script>
@endpush
```

### Example 2: User Dashboard

**Component: `/views/components/stat-card.plug.php`**

```php
<div class="stat-card {{ $color ?? 'blue' }}">
    <div class="stat-icon">
        {{{ $icon ?? '' }}}
    </div>
    <div class="stat-content">
        <h4>{{ $label }}</h4>
        <p class="stat-value">{{ $value }}</p>
    </div>
    @if($change ?? false)
        <div class="stat-change {{ $change > 0 ? 'positive' : 'negative' }}">
            {{ $change > 0 ? '+' : '' }}{{ $change }}%
        </div>
    @endif
</div>
```

**Dashboard View:**

```php
@extends('layouts.app')

@section('content')
    <h1>Dashboard</h1>
    
    <div class="stats-grid">
        <StatCard 
            label="Total Users" 
            value="{{ $stats['users'] }}" 
            change="{{ $stats['users_change'] }}"
            color="blue" 
        />
        
        <StatCard 
            label="Revenue" 
            value="@currency($stats['revenue'])" 
            change="{{ $stats['revenue_change'] }}"
            color="green" 
        />
        
        <StatCard 
            label="Orders" 
            value="{{ $stats['orders'] }}" 
            color="purple" 
        />
    </div>
    
    @auth
        <div class="user-actions">
            <Button type="button" variant="primary">
                New Project
            </Button>
        </div>
    @endauth
@endsection
```

### Example 3: Form with Validation

```php
@extends('layouts.app')

@section('content')
    <Card title="Create Account">
        <form method="POST" action="/register">
            @csrf
            
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" value="{{ old('name') }}">
                @error('name')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" value="{{ old('email') }}">
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password">
                @error('password')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            
            <Button type="submit" variant="success">
                Register
            </Button>
        </form>
    </Card>
@endsection
```

---

## Error Handling

### Development Mode Errors

When `APP_DEBUG=true`, detailed errors are displayed:

- Exception message
- File and line number
- Full stack trace

### Production Mode Errors

When `APP_DEBUG=false`:

- Generic error message
- Errors logged via `error_log()`
- No sensitive information exposed

### Setting Debug Mode

```php
// Method 1: Define constant
define('APP_DEBUG', true);

// Method 2: Environment variable
$_ENV['APP_DEBUG'] = 'true';

// Method 3: .env file
putenv('APP_DEBUG=true');
```

---

## Best Practices

1. **Use caching in production:**

   ```php
   $viewEngine = new ViewEngine($viewPath, $cachePath, true);
   ```

2. **Always escape output unless rendering HTML:**

   ```php
   {{ $userInput }} // Escaped (safe)
   {{{ $trustedHtml }}} // Raw (only for trusted content)
   ```

3. **Organize components logically:**

   ```
   /components
     /forms
       input.plug.php
       select.plug.php
     /layout
       header.plug.php
       footer.plug.php
   ```

4. **Use layouts for consistent structure**

5. **Share common data globally:**

   ```php
   $viewEngine->share('siteName', 'My Site');
   $viewEngine->share('currentUser', $user);
   ```

6. **Clear cache after deploying:**

   ```php
   $viewEngine->clearCache();
   ```

7. **Use components for reusable UI elements**

8. **Leverage template inheritance for DRY code**

---

## Performance Tips

1. Enable caching in production
2. Use `@once` for assets that should only load once
3. Avoid heavy logic in templates (move to controllers)
4. Cache compiled views persist across requests
5. Clear cache only when templates change

---

This completes the comprehensive guide to the Plugs View System!
