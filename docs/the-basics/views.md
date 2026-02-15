# Views & Components

Views contain the HTML served by your application and separate your controller/application logic from your presentation logic. The Plugs View Engine is a powerful, lightweight templating engine that compiles to native PHP code.

## Basic Usage

Views are stored in the `resources/views` directory. A simple view file `welcome.plug.php`:

```html
<!-- resources/views/welcome.plug.php -->
<h1>Hello, {{ $name }}!</h1>
```

Render it from a controller or route using the global `view` helper:

```php
$router->get('/', function () {
    return view('welcome', ['name' => 'Plugs']);
});
```

---

## 🧩 Directives

The Plugs view engine provides several directives for common PHP operations:

### Echoing Data

```html
{{ $variable }}
<!-- Context-Aware Escaped (XSS Protection) -->
{{{ $rawVariable }}}
<!-- Raw/Unescaped (Preferred) -->
{!! $rawVariable !!}
<!-- Raw/Unescaped (Alternative) -->
```

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
@auth User is logged in. @endauth @guest Please login. @endguest
```

### Loops

The `@foreach` directive provides a `$loop` variable for easy iteration control.

```html
@foreach($users as $user)
<div class="{{ $loop->first() ? 'bg-primary' : '' }}">
  {{ $loop->iteration }}. {{ $user->name }}
</div>
@endforeach
```

**Loop Properties:**

- `$loop->index`: 0-based index
- `$loop->iteration`: 1-based iteration counter
- `$loop->remaining()`: Items remaining in loop
- `$loop->count`: Total items
- `$loop->first()` / `$loop->last()`: Booleans
- `$loop->even()` / `$loop->odd()`: Booleans

### Form Helpers

Simplified form attribute handling:

```html
<input type="checkbox" name="active" @checked($user- />isActive)>
<option value="admin" @selected($role="" ="admin" )>Admin</option>
<button @disabled($isProcessing)>Submit</button>
<input type="text" value="@old('username')" />
```

### Session & Flash

```html
@session('success')
<div class="alert alert-success">@flash('success')</div>
@endsession @error('email')
<span class="error text-danger">{{ $errors->first('email') }}</span>
@enderror
```

---

## 🏗️ Layouts & Inheritance

### Defining A Layout

```html
<!-- resources/views/layouts/app.plug.php -->
<html>
  <head>
    <title>@yield('title', 'My App')</title>
  </head>
  <body>
    <div class="container">@yield('content')</div>

    <x-flash />
  </body>
</html>
```

### Extending A Layout

```html
<!-- resources/views/child.plug.php -->
@extends('layouts.app') @section('title', 'Home Page') @section('content')
<p>This is my body content.</p>
@endsection
```

---

## 📦 Components

Components allow you to reuse UI elements. They live in `resources/views/components`.

### Creating A Component

```html
<!-- resources/views/components/alert.plug.php -->
<div class="alert alert-{{ $type }}" role="alert">
  <strong>{{ $title }}</strong>
  {{ $slot }}
</div>
```

### Using A Component

Use the PascalCase syntax:

```html
<Alert type="danger" title="Whoops!"> Something went wrong. </Alert>
```

### Passing Data

You may pass data using HTML attributes. Plain strings are passed as-is, while PHP variables should be prefixed with `:`:

```html
<button class="btn-lg" type="submit">Save</button>
<User::Profile :user="$user" />
```

---

## 🛡️ Security

### XSS Protection

By default, `{{ $variable }}` is automatically escaped. The engine is **context-aware**, meaning it chooses the best escaping strategy based on where the echo is placed:

- **HTML Body**: Applied `e()` for standard text.
- **Attributes**: Applies `attr()` (e.g., `title="{{ $var }}"`).
- **Scripts**: Applies `js()` (safe JSON encoding) inside `<script>` tags.
- **Links/Assets**: Applies `safeUrl()` for `href` and `src` to sanitize protocols.

### Content Security Policy (CSP)

The engine supports injecting a CSP nonce into scripts.

```php
// In a middleware
$viewEngine->setCspNonce($nonce);
```

---

## 💡 Advanced Directives

| Directive                          | Description                                    |
| ---------------------------------- | ---------------------------------------------- |
| `@inject('service', 'Class')`      | Inject a service directly into the view.       |
| `@asset('path')`                   | Link to an asset with automatic cache busting. |
| `@style([...])`                    | Dynamically bind inline styles.                |
| `@json($data)`                     | Output data as a JSON string.                  |
| `@production` ... `@endproduction` | Render content only in production.             |

---

## ⚡ Async Data Loading

The view engine has built-in support for asynchronous data loading using Promises (e.g., from `HTTPClient`).

When you pass a `PromiseInterface` or `Fiber` to a view, the engine will automatically wait for it to resolve before rendering. This allows you to fetch data in parallel.

### Example

```php
use Plugs\Http\HTTPClient;

public function index()
{
    $client = new HTTPClient();

    // These requests run in PARALLEL
    return view('dashboard', [
        'users' => $client->getAsync('/users'),
        'posts' => $client->getAsync('/posts'),
    ]);
}
```

In your view, `$users` and `$posts` will be the actual response objects, fully resolved.
