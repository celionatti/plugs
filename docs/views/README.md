# Views

The Plugs View system provides a powerful, expressive templating engine for building dynamic HTML pages. It features Blade-like syntax, component support, HTMX integration, and advanced caching capabilities.

## Documentation

| Document                                | Description                      |
| --------------------------------------- | -------------------------------- |
| [Getting Started](getting-started.md)   | Basic setup and usage            |
| [Directives Reference](directives.md)   | All available directives         |
| [Components](components.md)             | Building reusable components     |
| [HTMX Integration](htmx-integration.md) | Partial rendering and fragments  |
| [Caching](caching.md)                   | View and block caching           |
| [Advanced Usage](advanced.md)           | Streaming, preloading, debugging |

## Quick Example

```php
// Controller
public function dashboard()
{
    return view('dashboard', [
        'user' => Auth::user(),
        'stats' => $this->getStats(),
    ]);
}
```

```blade
{{-- views/dashboard.plug.php --}}
@extends('layouts.app')

@section('content')
    <h1>Welcome, {{ $user->name }}</h1>

    <x-stats-card :stats="$stats" />

    @foreach($stats as $stat)
        <div class="stat">{{ $stat->label }}: {{ $stat->value }}</div>
    @endforeach
@endsection
```

## Key Features

- **Modern Tag Syntax** - HTML-friendly `<if>` and `<loop>` tags (New in V5)
- **Reactive Components** - AJAX-powered interactivity via `plugs-spa.js` (New in V5)
- **Security-First** - Context-aware escaping and specialized helpers (New in V5)
- **Scoped Referencing** - Organizable components with `Module::Component` syntax (New in V5)
- **Blade-like Syntax** - Traditional `@` directives and `{{ }}` echo statements
- **HTMX Support** - Built-in fragment rendering for partial updates
- **Block Caching** - Cache expensive view sections
- **Streaming** - Stream large views for better performance
