# Views & Components

Views contain the HTML served by your application and separate your controller/application logic from your presentation logic.

## Creating & Rendering Views

Views are stored in the `resources/views` directory. A simple view might look like this:

```html
<!-- View stored in resources/views/greeting.plug.php -->
<html>
    <body>
        <h1>Hello, {{ $name }}</h1>
    </body>
</html>
```

Since this view is stored at `resources/views/greeting.plug.php`, we may return it using the global `view` helper like so:

```php
$router->get('/', function () {
    return view('greeting', ['name' => 'James']);
});
```

## Directives

The Plugs view engine provides several directives for common PHP operations:

### Echoing Data

```html
{{ $variable }}        <!-- Escaped -->
{{{ $rawVariable }}}   <!-- Unescaped -->
```

### Control Structures

```html
@if($condition)
    // ...
@elseif($anotherCondition)
    // ...
@else
    // ...
@endif

@foreach($users as $user)
    <p>This is user {{ $user->id }}</p>
@endforeach
```

## Layouts & Inheritance

### Defining A Layout

```html
<!-- resources/views/layouts/app.plug.php -->
<html>
    <head>
        <title>App Name - @yield('title')</title>
    </head>
    <body>
        <div class="container">
            @yield('content')
        </div>
    </body>
</html>
```

### Extending A Layout

```html
<!-- resources/views/child.plug.php -->
@extends('layouts.app')

@section('title', 'Page Title')

@section('content')
    <p>This is my body content.</p>
@endsection
```

## Components

Components and slots provide similar benefits to sections and layouts; however, some may find the mental model of components and slots easier to understand.

### Creating A Component

```html
<!-- resources/views/components/alert.plug.php -->
<div class="alert alert-{{ $type }}">
    {{ $slot }}
</div>
```

### Displaying A Component

```html
<Alert type="danger">
    <strong>Whoops!</strong> Something went wrong!
</Alert>
```

### Passing Data To Components

You may pass data to components using HTML attributes. Plain strings are passed as-is, while PHP variables should be prefixed with `$`:

```html
<Alert type="info" :message=$message />
```
