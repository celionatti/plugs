# Plugs Component System Usage Guide

Plugs features a powerful component system similar to Blade components in Laravel. Components allow you to split your UI into reusable pieces.

---

## Quick Generation

Use the CLI to quickly generate components:

```bash
# Simple component (view only)
php theplugs make:component Name

# Reactive component (class + view)
php theplugs make:component Name --bolt
```

**Alias:** `g:comp`

---

## Creating Components

Components are stored in `resources/views/components`. The file extension can be `.plug.php`, `.php`, or `.html`. The filename determines the component name (snake_case filename becomes PascalCase component name).

**Example:** `resources/views/components/link.plug.php`
```php
<a {{ $attributes->merge(['href' => '#']) }}>
    {{ $slot }}
</a>
```

## Using Components

You can use components in your views using the `<x-component>` tag or the class-based XML-style syntax `<Component>`.

### 1. Basic Usage

Given the `Link` component above:

```html
<Link href="/docs">
    <i class="fas fa-book"></i> Documentation
</Link>
```

### 2. Passing Attributes

Any attributes added to the component tag are available in the component via the `$attributes` variable.

```html
<Link href="/login" class="btn btn-primary" id="login-link">
    Login
</Link>
```

In the component definition, use `$attributes->merge()` to set default values and merge incoming attributes (like `class` which gets appended).

```php
<!-- resources/views/components/link.plug.php -->
<a {{ $attributes->merge(['class' => 'text-decoration-none']) }}>
    {{ $slot }}
</a>
```

### 3. Slots

The content between the opening and closing tags is injected into the `{{ $slot }}` variable.

```html
<Card>
    This is the card content.
</Card>
```

```php
<!-- resources/views/components/card.plug.php -->
<div class="card">
    <div class="card-body">
        {{ $slot }}
    </div>
</div>
```

### 4. Dynamic Attributes (Expressions)

You can pass PHP expressions to attributes using Blade syntax `{{ ... }}`.

```html
<Link href="/profile" class="{{ $isActive ? 'active' : '' }}">
    Profile
</Link>
```

### 5. Self-Closing

Components can be self-closing if they have no slot content.

```html
<Icon name="rocket" />
```

## Advanced Features

### Attribute Bag Methods

The `$attributes` object provides several helpful methods:

-   `$attributes->merge(['class' => 'default'])`: Merges default attributes. `class` attributes are concatenated.
-   `$attributes->get('name')`: Get a specific attribute.
-   `$attributes->has('name')`: Check if an attribute exists.
-   `$attributes->start('class', 'foo')`: Prepend string to an attribute (useful for classes).

### Variable Logic in Attributes

As observed in your recent issue, ensure that any complex logic in attributes is correctly formatted.

**Correct:**
```html
<Link class="{{ request()->is('home') ? 'active' : '' }}">Home</Link>
```

The system will now correctly handle quoted string attributes that contain expressions.

## Reactive Components

Reactive components are a special type of component that can maintain state between the server and the frontend. They are perfect for building interactive UIs like search bars, infinite scrollers, or real-time counters.

### Creating a Reactive Component

To create a reactive component, extend the `Plugs\View\ReactiveComponent` class. Public properties are automatically made available to the frontend.

```php
namespace App\View\Components;

use Plugs\View\ReactiveComponent;

class SearchBar extends ReactiveComponent
{
    public string $query = '';
    public array $results = [];

    public function render()
    {
        return view('components.search-bar');
    }
}
```

### State Dehydration

The system automatically "dehydrates" your component state into a JSON object for the frontend. Complex types like `DateTime` are automatically handled. You can customize this by overriding the `dehydrate` method:

```php
protected function dehydrate(mixed $value): mixed
{
    if ($value instanceof User) {
        return $value->only(['id', 'name']);
    }

    return parent::dehydrate($value);
}
```

### JS Bridge Integration

Each reactive component generates a JavaScript bridge to synchronize state. You can retrieve this script using the `getJavaScript()` method:

```php
// In your view or layout
<script>
    {{ $component->getJavaScript() }}
</script>
```

This bridge allows you to interact with the component from your own custom JS logic or using Plugs's built-in reactivity scripts.
