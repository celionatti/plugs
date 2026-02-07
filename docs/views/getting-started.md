# Getting Started with Views

Learn how to set up and use the Plugs View system in your application.

## Installation

The View system is included in the Plugs framework. No additional installation required.

## Basic Setup

### Initialize the View Engine

```php
use Plugs\View\ViewEngine;
use Plugs\View\View;

// Create view engine instance
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/resources/views',
    cachePath: __DIR__ . '/storage/cache/views',
    cacheEnabled: true
);
```

### Directory Structure

```
resources/
└── views/
    ├── layouts/
    │   └── app.plug.php
    ├── components/
    │   ├── button.plug.php
    │   └── card.plug.php
    ├── pages/
    │   ├── home.plug.php
    │   └── about.plug.php
    └── partials/
        ├── header.plug.php
        └── footer.plug.php
```

## Creating Views

### Basic View File

Create a file with `.plug.php` extension:

```blade
{{-- views/welcome.plug.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
</head>
<body>
    <h1>{{ $heading }}</h1>
    <p>{{ $message }}</p>
</body>
</html>
```

### Rendering Views

```php
// Method 1: Using the view() helper
return view('welcome', [
    'title' => 'My App',
    'heading' => 'Welcome!',
    'message' => 'Thanks for visiting.'
]);

// Method 2: Direct engine rendering
$html = $viewEngine->render('welcome', [
    'title' => 'My App',
    'heading' => 'Welcome!',
    'message' => 'Thanks for visiting.'
]);

// Method 3: View object with chaining
return view('welcome')
    ->with('title', 'My App')
    ->with('heading', 'Welcome!')
    ->withData(['message' => 'Thanks for visiting.']);
```

## Echo Statements

### Escaped Output (Safe)

```blade
{{-- Escapes HTML entities --}}
{{ $userInput }}

{{-- Equivalent to: --}}
<?php echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8'); ?>
```

### Raw Output (Unescaped)

```blade
{{-- Outputs raw HTML - use carefully! --}}
{!! $trustedHtml !!}

{{-- Equivalent to: --}}
<?php echo $trustedHtml; ?>
```

### Comments

```blade
{{-- This is a comment - will not appear in output --}}
```

## Template Inheritance

### Layout File

```blade
{{-- views/layouts/app.plug.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Default Title')</title>
    @stack('styles')
</head>
<body>
    <header>
        @include('partials.header')
    </header>
    
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

### Child View

```blade
{{-- views/pages/home.plug.php --}}
@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <h1>Welcome Home</h1>
    <p>This is the home page content.</p>
@endsection

@push('styles')
    <link rel="stylesheet" href="/css/home.css">
@endpush

@push('scripts')
    <script src="/js/home.js"></script>
@endpush
```

## Control Structures

### Conditionals

```blade
@if($user->isAdmin())
    <p>Welcome, Admin!</p>
@elseif($user->isModerator())
    <p>Welcome, Moderator!</p>
@else
    <p>Welcome, {{ $user->name }}!</p>
@endif

{{-- Unless (inverse if) --}}
@unless($user->isBanned())
    <p>You have access.</p>
@endunless

{{-- Isset check --}}
@isset($records)
    <p>Records found: {{ count($records) }}</p>
@endisset

{{-- Empty check --}}
@empty($records)
    <p>No records found.</p>
@endempty
```

### Loops

```blade
{{-- Foreach loop --}}
@foreach($items as $item)
    <li>{{ $item->name }}</li>
@endforeach

{{-- Foreach with empty fallback --}}
@forelse($items as $item)
    <li>{{ $item->name }}</li>
@empty
    <li>No items found.</li>
@endforelse

{{-- For loop --}}
@for($i = 0; $i < 10; $i++)
    <p>Iteration {{ $i }}</p>
@endfor

{{-- While loop --}}
@while($condition)
    <p>Looping...</p>
@endwhile
```

### Loop Variable

Access the `$loop` variable inside loops:

```blade
@foreach($items as $item)
    <div class="@if($loop->first()) first @endif @if($loop->last()) last @endif">
        <span>Index: {{ $loop->index }}</span>
        <span>Iteration: {{ $loop->iteration }}</span>
        <span>Remaining: {{ $loop->remaining() }}</span>
        <span>{{ $item->name }}</span>
    </div>
@endforeach
```

## Including Views

```blade
{{-- Basic include --}}
@include('partials.sidebar')

{{-- Include with data --}}
@include('partials.user-card', ['user' => $user])

{{-- Include with variable data --}}
@include('partials.item', $itemData)
```

## Shared Data

Share data across all views:

```php
// In your bootstrap or service provider
$viewEngine->share('appName', 'My Application');
$viewEngine->share('currentUser', Auth::user());

// Now available in all views
// {{ $appName }}
// {{ $currentUser->name }}
```

## View Composers

Run logic before specific views render:

```php
$viewEngine->composer('partials.sidebar', function($view) {
    $view->with('categories', Category::all());
});

$viewEngine->composer('layouts.*', function($view) {
    $view->with('navigation', Navigation::main());
});
```

## Response Methods

```php
// Send with custom headers
return view('page', $data)
    ->withHeaders([
        'X-Custom-Header' => 'value',
        'Cache-Control' => 'no-cache'
    ])
    ->send();

// Send with status code
return view('errors.404')
    ->withStatus(404)
    ->send();
```

## Next Steps

- Learn about [Directives](directives.md)
- Build [Components](components.md)
- Explore [HTMX Integration](htmx-integration.md)
