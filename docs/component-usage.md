# Plugs Component System Usage Guide

Plugs features a powerful component system similar to Blade components in Laravel. Components allow you to split your UI into reusable pieces.

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
