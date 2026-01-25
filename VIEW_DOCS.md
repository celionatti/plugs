# Plugs View Engine Documentation

The Plugs View Engine is a powerful, lightweight templating engine that compiles to native PHP code. It offers a familiar syntax similar to Blade, with support for components, layouts, and custom directives.

## Basic Usage

Views are stored in `resources/views`. A simple view file `welcome.plug.php`:

```html
<!-- resources/views/welcome.plug.php -->
<h1>Hello, {{ $name }}!</h1>
```

Render it from a controller or route:

```php
use Plugs\Container\Container;
$view = Container::getInstance()->make('view');
return $view->render('welcome', ['name' => 'Plugs']);
```

## Directives

### Loops

The `@foreach` directive provides a `$loop` variable for easy iteration control.

```html
@foreach($users as $user)
    <div class="{{ $loop->first() ? 'bg-primary' : '' }}">
        {{ $loop->iteration }}. {{ $user->name }}
        @if($loop->remaining() > 0)
            <hr>
        @endif
    </div>
@endforeach
```

**Loop Properties:**
- `$loop->index`: 0-based index
- `$loop->iteration`: 1-based iteration counter
- `$loop->remaining()`: Items remaining in loop
- `$loop->count`: Total items
- `$loop->first()`: Boolean, true if first item
- `$loop->last()`: Boolean, true if last item
- `$loop->even()` / `$loop->odd()`: Boolean

### Conditionals

```html
@if($user->isAdmin())
    <button>Delete</button>
@elseif($user->isEditor())
    <button>Edit</button>
@else
    <span>View Only</span>
@endif

<!-- Semantic Helpers -->
@auth
    User is logged in.
@endauth

@guest
    Please login.
@endguest
```

### Form Helpers

Simplified form attribute handling:

```html
<input type="checkbox" name="active" @checked($user->isActive)>
<option value="admin" @selected($role == 'admin')>Admin</option>
<button @disabled($isProcessing)>Submit</button>
<input type="text" @readonly($isReadOnly)>
<input type="email" @required($isRequired)>
```

### Injection & Styling

**Dependency Injection**:
Inject services directly into your view.
```html
@inject('metrics', 'App\Services\MetricsService')
Visitors: {{ $metrics->getDailyVisitors() }}
```

**Dynamic Styles**:
Bind inline styles conditionally.
```html
<div @style([
    'background-color: red' => $hasError,
    'font-weight: bold' => $isImportant,
])>
    Alert
</div>
```

**Smart Assets**:
Directives for assets automatically handle cache busting (`?v=timestamp` is appended if the file exists).
```html
<link rel="stylesheet" href="@asset('css/app.css')">
```

## Components

Components allow you to reuse UI elements. Components live in `resources/views/components`.

### Creating a Component

Create `resources/views/components/alert.plug.php`:

```html
<!-- resources/views/components/alert.plug.php -->
<div class="alert alert-{{ $type }}" role="alert">
    <strong>{{ $title }}</strong>
    {{ $slot }}
</div>
```

### Using a Component

Use the `x-` syntax (converted to `App\Components` or rendered directly):

```html
<x-alert type="danger" title="Error!">
    Something went wrong.
</x-alert>
```

### Slots

The content between the opening and closing tags is passed as `$slot`.

**Named Slots**:
Pass extra content using proper variable binding in the component controller or data array if strictly view-based, but normally standard slots are default. *Note: Advanced Multi-slot support is planned.*

### Attributes

Access attributes passed to the component using the `$attributes` bag.

```html
<!-- resources/views/components/button.plug.php -->
<button {{ $attributes->merge(['class' => 'btn btn-primary']) }}>
    {{ $slot }}
</button>
```

Usage:
```html
<x-button class="btn-lg" type="submit">Save</x-button>
<!-- Renders: <button class="btn btn-primary btn-lg" type="submit">Save</button> -->
```

## Error Handling

Display validation errors easily using `@error`.

```html
<label for="email">Email</label>
<input type="email" name="email" id="email">

@error('email')
    <span class="text-danger">{{ $message }}</span>
@enderror
```

To enable this, ensure `ShareErrorsFromSession` middleware is registered. It shares an `$errors` object (instance of `Plugs\View\ErrorMessage`) with all views.

**Checking for any errors:**
```html
@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

## Layouts

Use `@extends` and `@section` for page layouts.

**Master Layout (`layouts/app.plug.php`):**
```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'My App')</title>
</head>
<body>
    @yield('content')
</body>
</html>
```

**Page View:**
```html
@extends('layouts.app')

@section('title', 'Home Page')

@section('content')
    <p>Welcome to the home page.</p>
@endsection

## Security

Plugs View Engine takes security seriously.

### XSS Protection

By default, standard echo statements `{{ $variable }}` are automatically escaped using `htmlspecialchars`, preventing Cross-Site Scripting (XSS) attacks.

```html
<!-- If $name = '<script>alert("XSS")</script>' -->
Hello, {{ $name }}
<!-- Renders: Hello, &lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt; -->
```

**Raw Output:**
If you strictly need to output unescaped content (e.g., HTML from a trusted helper), use `{!! $variable !!}`.
> [!WARNING]
> Only use raw output for trusted content. Never output user-provided data using this syntax.

```html
{!! $trustedHtml !!}
```

### Content Security Policy (CSP)

The view engine supports injecting a CSP nonce into scripts generated by `@jsonScript`.
To use this, set the nonce on the `ViewEngine` instance in `Plugs.php` or a middleware.

```php
// In a middleware
$engine = Container::getInstance()->make('view');
$nonce = base64_encode(random_bytes(16));
$engine->setCspNonce($nonce);
```

```
