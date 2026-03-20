# View Engine

The **Plug View Engine** is a high-performance, context-aware templating system. It combines the expressive power of Blade-style directives with a modern, HTML-friendly tag syntax (V5).

---

## 1. Template Basics

### Files and Locations
Views are stored in `resources/` with the `.plug.php` extension.
```text
resources/
  layouts/
  components/
  welcome.plug.php
```

### Rendering Views
```php
return view('welcome', ['name' => 'John']);
```

### Type Safety: `@needs`
Declare required variables to prevent silent `undefined` notices:
```blade
@needs user, posts
<h1>{{ user.name }}</h1>
```

---

## 2. Echoing Data

| Syntax | Description |
| --- | --- |
| `{{ $var }}` | **Escaped**: Context-aware escaping (Safe by default). |
| `{{{ $var }}}` | **Raw**: Unescaped output (Use with caution). |
| `{{-- comment --}}` | **Comment**: Not rendered in the final HTML. |

### Dot Notation (V5)
You can drop the `$` and Use dot-notation for cleaner object access:
```html
{{-- Before --}}
<h1>{{ $user->name }}</h1>

{{-- After --}}
<h1>{{ user.name }}</h1>
```

---

## 3. Control Structures

Plugs supports two interchangeable syntaxes: **Classic Directives** (`@`) and **Modern Tags** (`<tag>`).

### Conditionals
```html
{{-- Tag Syntax --}}
<if :condition="user.isAdmin">
    <p>Admin</p>
    <elseif :condition="user.isEditor" />
    <p>Editor</p>
    <else />
    <p>User</p>
</if>

{{-- Directive Syntax --}}
@if(user.isAdmin) ... @endif
```

> [!TIP]
> **Semantic Attributes**: For better readability, you can use `when`, `check`, `test`, or `if` instead of `condition`:
> `<button <disabled when="isLoading" />>Submit</button>`

### Loops
The `$loop` variable is automatically available inside all loops.

```html
<loop :items="users" as="user">
    <div @class(['even' => $loop->even()])>
        {{ $loop->iteration }}. {{ user.name }}
    </div>
</loop>
```

---

## 4. Layouts and Inheritance

### The `<layout>` Tag (V5 Recommended)
Anything outside a named slot is automatically injected into the default content area.

```html
<layout name="layouts.app">
    <slot:title>My Profile</slot:title>

    <div class="profile-content">
        <h1>Welcome back!</h1>
    </div>
</layout>
```

### Classic Inheritance
```blade
@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <h1>Welcome back!</h1>
@endsection
```

---

## 5. Components

Components are self-contained views located in `resources/components/`.

```html
{{-- Usage --}}
<Alert type="success" message="Saved!" />
<Admin::users::badge :user="$user" />

{{-- With Slots --}}
<Modal>
    <slot:header>Confirm Action</slot:header>
    Are you sure?
</Modal>
```

---

## 6. Performance & Streaming

### View Streaming
For heavy pages, stream content directly to the browser to improve Perceived Performance (TTFB).

```blade
@stream('reports.large', ['data' => $largeData])
```

### Buffering & Caching
- **`@flush`**: Force the browser to render the current buffer.
- **`@cache('key', 3600)`**: Cache a fragment of the view for 1 hour.

---

## Next Steps
Build interactive, real-time UIs using [Hybrid Reactivity](./reactivity.md).
