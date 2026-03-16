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

### 2. Passing Attributes (Auto Props)

All attributes added to the component tag are **automatically available** as PHP variables inside the component template. You don't need any special declarations.

```html
<Link href="/login" class="btn btn-primary" id="login-link" :is-active="$isActive">
    Login
</Link>
```

```php
<!-- resources/views/components/link.plug.php -->
<!-- $href and $isActive are automatically available -->
<a href="{{ $href }}" class="{{ isset($isActive) && $isActive ? 'active' : '' }}">
    {{ $slot }}
</a>
```

Additionally, all incoming attributes are bundled into the `$attributes` ComponentAttributes bag. You can use `$attributes->merge()` to set default values and merge incoming attributes (like `class` which gets appended).

```php
<!-- Using $attributes->merge() to combine classes -->
<a href="{{ $href ?? '#' }}" {{ $attributes->merge(['class' => 'text-decoration-none']) }}>
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
You can also pass Vue/JS style JS syntax on dynamic component attributes.

```html
<x-card />
<!-- Output: <div class="bg-gray-100 p-4"></div> -->
```

### 7. Scoped Component Styles

If you want your CSS to only apply to the current component without leaking out globally, you can use the `<style scoped>` tag.

The framework will automatically assign a unique hash to your component (like `data-v-a1b2c3d4`) and rewrite your CSS selectors and HTML elements to encapsulate the styling natively, just like Vue and Svelte!

**`resources/views/components/card.blade.php`**
```html
<style scoped>
    .card {
        padding: 20px;
        border-radius: 8px;
        background: white;
    }
    
    .card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
</style>

<div class="card">
    <slot></slot>
</div>
```

**Compiled Output:**
```html
<style>
    .card[data-v-3aeb0f57] {
        padding: 20px;
        ...
    }
    .card[data-v-3aeb0f57]:hover {
        ...
    }
</style>

<div class="card" data-v-3aeb0f57>
    ...
</div>
```

### 8. Inline Components

You can define components directly within a view using the `@component` directive. This is useful for small, single-use components that don't deserve their own file.

```html
@component custom-alert(type, message)
    <div class="alert alert-{type}">
        {message}
    </div>
@endcomponent

<!-- Usage -->
<x-custom-alert type="error" message="Something went wrong!" />
```

Inline components are extracted during compilation and registered in memory, so they won't render where they are defined.


Alternatively, you could pass it without the `:` but use echo statements:

```html
<x-card title="{{ user.name }}" />
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
