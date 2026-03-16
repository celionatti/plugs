# Directives Reference

Complete reference for all available template directives in the Plugs View system.

## Echo Statements

| Syntax              | Description                                    |
| ------------------- | ---------------------------------------------- |
| `{{ $var }}` or `{{ var }}` | Context-aware escaped output (Safe by default) |
| `{{{ $var }}}` or `{{{ var }}}`    | Raw/unescaped output (Preferred)               |
| `{!! $var !!}` or `{!! var !!}`    | Raw/unescaped output (Alternative)             |
| `{{-- comment --}}` | Template comment (not rendered)                |

### Vue/JS-Style Variable Syntax (New in V5)

Plugs V5 automatically translates Vue/JS-style variables into PHP syntax. You can drop the `$` prefix and use dot-notation for object access.

```html
{{-- Before --}}
<h1>{{ $user->name }}</h1>
<p>{{ $post->author->getAge() }}</p>

{{-- After (Zero setup) --}}
<h1>{{ user.name }}</h1>
<p>{{ post.author.getAge() }}</p>
```

This works identically inside control structures and short attributes:

```blade
@if(user.isAdmin)
    <span :class="user.role === 'editor' ? 'text-blue' : 'text-red'">Admin</span>
@endif
```

> [!TIP]
> **Native PHP features:** The engine is smart enough to ignore native PHP keywords like `true`, `false`, `null`, and functions like `count()` or `empty()`. It will convert `count(users)` to `count($users)`.

---

## Control Structures

### Conditionals

```blade
@if($condition)
    ...
@elseif($otherCondition)
    ...
@else
    ...
@endif

@unless($condition)
    {{-- Renders if condition is false --}}
@endunless

@isset($variable)
    {{-- Renders if variable is set --}}
@endisset

@empty($variable)
    {{-- Renders if variable is empty --}}
@endempty
```

### Loops

```blade
@foreach($items as $item)
    {{ $item->name }}
@endforeach

@foreach($items as $key => $value)
    {{ $key }}: {{ $value }}
@endforeach

@forelse($items as $item)
    {{ $item->name }}
@empty
    No items found.
@endforelse

@for($i = 0; $i < 10; $i++)
    {{ $i }}
@endfor

@while($condition)
    ...
@endwhile
```

### Loop Variable

Available inside `@foreach` and `@forelse`:

| Property             | Description                 |
| -------------------- | --------------------------- |
| `$loop->index`       | Current index (0-based)     |
| `$loop->iteration`   | Current iteration (1-based) |
| `$loop->count`       | Total items                 |
| `$loop->first()`     | Is first iteration          |
| `$loop->last()`      | Is last iteration           |
| `$loop->even()`      | Is even iteration           |
| `$loop->odd()`       | Is odd iteration            |
| `$loop->remaining()` | Remaining iterations        |
| `$loop->depth`       | Nesting level               |
| `$loop->parent`      | Parent loop variable        |

---

## Template Inheritance

```blade
{{-- Layout --}}
@yield('section-name')
@yield('section-name', 'Default content')

{{-- Child view --}}
@extends('layouts.app')

@section('section-name')
    Content here
@endsection

@section('title', 'Inline content')

@parent {{-- Include parent section content --}}

#### Namespaced Views (Modules)
Directives like `@extends`, `@include`, and `@stream` support namespaced views using the `module::` prefix:

```blade
@extends('admin::layouts.admin')

@include('store::partials.product-card')
```
```

---

## 🏗️ Modern Tag Syntax (V5)

Plugs V5 introduces a modern, HTML-friendly tag syntax for control structures. It is highly recommended for building cleaner templates.

> [!IMPORTANT]
> For the complete reference on HTML-style tags (Layouts, Forms, Loops, etc.), see the dedicated **[Modern Tag Syntax](tags.md)** documentation.

---

## Security & Contextual Escaping

Plugs uses a context-aware escaping engine to prevent XSS. The `{{ $var }}` directive automatically detects the context and applies the best escaping method.

| Directive / Helper | Context         | Description                                           |
| ------------------ | --------------- | ----------------------------------------------------- |
| `{{ $var }}`       | Auto            | Automatically detects HTML, Script, or Attribute.     |
| `e($var)`          | HTML Body       | Default escaping for standard text.                   |
| `attr($var)`       | HTML Attributes | Escapes quotes and special characters for attributes. |
| `safeUrl($var)`    | Links/Assets    | Sanitizes protocols (e.g. `javascript:`) for URLs.    |
| `u($var)`          | URL Query       | Escapes values for query parameters (e.g. `?q=...`).  |
| `js($var)`         | Script Tags     | Safe JSON encoding with script tag protection.        |
| `css($var)`        | CSS Styles      | Sanitizes values for style attributes and inline CSS. |
| `id($var)`         | Element IDs     | Sanitizes values for safe HTML element IDs.           |
| `{{{ $var }}}`     | Raw             | Disables escaping (use with extreme caution).         |

**Examples:**

```html
{{-- Safe for page content --}}
<div>{{ $bio }}</div>

{{-- Safe for links (Auto-sanitizes protocols) --}}
<a href="{{ $profileUrl }}">Profile</a>

{{-- Safe for attributes (Auto-uses attr()) --}}
<button title="{{ $tooltip }}">Hover Me</button>

{{-- Safe for JS variables (Auto-uses js()) --}}
<script>
  const userRole = {{ $role }};
</script>

{{-- Explicitly forced helpers are ignored by auto-detection --}}
<div title="{{ e($unsafe) }}"></div>
<a href="/search?q={{ u($query) }}">Search</a>
```

---

## Stacks

```blade
{{-- In layout --}}
@stack('scripts')
@stack('styles')

{{-- In child views --}}
@push('scripts')
    <script src="/js/page.js"></script>
@endpush

@prepend('scripts')
    <script src="/js/priority.js"></script>
@endprepend

@pushOnce('scripts', 'custom-modal-logic')
    <script>
        // This will only be pushed to the 'scripts' stack once,
        // even if the component is included multiple times.
    </script>
@endpushOnce
```

---

## Form Helpers

```blade
{{-- CSRF token --}}
@csrf

{{-- Method spoofing --}}
@method('PUT')
@method('DELETE')

{{-- Validation Errors --}}
@error('email')
    <span class="error">{{ $message }}</span>
@enderror

@errors
    <div class="alert alert-danger">{{ $message }}</div>
@enderrors

{{-- Conditional attributes --}}
<input type="checkbox" @checked($isActive)>
<option @selected($isDefault)>Option</option>
<input @disabled($isReadOnly)>
<input @readonly($isLocked)>
<input @required($isRequired)>

### Tag-Based Syntax (Alternative)
You can also use HTML-like tags for form attributes:
`<input type="checkbox" <checked :when="$isActive" />>`
`<option <selected :when="$isDefault" />>Option</option>`
`<button <disabled :when="$isLoading" />>Submit</button>`
```

### Dynamic Attributes

#### @class

The `@class` directive conditionally compiles a CSS class string. It accepts an array of classes where the array key contains the class(es) you wish to add, while the value is a boolean expression. If the array element has a numeric key, it will always be included in the rendered class list:

```blade
@php($isBold = true)

<div @class([
    'p-4',
    'font-bold' => $isBold,
    'text-gray-500' => !$isBold,
    'bg-red-500' => false,
])></div>

{{-- Output: <div class="p-4 font-bold"></div> --}}
```

**Attribute Syntax:**
Alternatively, you can use the `:class` attribute directly on any tag:
`<div :class="['p-4', 'font-bold' => \$isBold]"></div>`

#### @style

The `@style` directive may be used to conditionally add inline CSS styles to an HTML element. Like the `@class` directive, it accepts an array of styles where the array key contains the style and the value is a boolean expression:

```blade
@php($isActive = true)

<div @style([
    'background-color: red',
    'font-weight: bold' => $isActive,
])></div>

{{-- Output: <div style="background-color: red; font-weight: bold;"></div> --}}
```

**Attribute Syntax:**
Alternatively, you can use the `:style` attribute directly on any tag:
`<div :style="['background-color: red', 'font-weight: bold' => \$isActive]"></div>`

---

## PHP & Namespace Helpers

### @use

The `@use` directive allows you to import PHP classes into the view scope, similar to the PHP `use` statement. This is cleaner than using `@php(use App\Models\User)`.

```blade
@use(App\Models\User)
@use('App\Models\Post', 'PostAlias')

@php
    $user = User::first();
    $post = PostAlias::find(1);
@endphp
```

---

## Variable Computation

### @let

The `@let` directive allows you to compute and store variables directly within a view template. This is useful for small computations that would otherwise require raw PHP blocks.

```blade
@let subtotal = product.price * quantity
@let total = subtotal * 1.2

<p>Subtotal: {{ subtotal }}</p>
<p>Total (incl. tax): {{ total }}</p>
```

### @calc

`@calc` is an alias for `@let` and can be used interchangeably based on developer preference.

```blade
@calc interest = principal * rate * time
<p>Interest: {{ interest }}</p>
```

### @needs

The `@needs` directive declares which variables a view **requires** from the controller. If any are missing at render time, a `ViewException` is thrown with a clear error message instead of a silent `undefined variable` notice.

```blade
@needs user posts

<h1>{{ $user->name }}</h1>
@foreach($posts as $post)
    <p>{{ $post->title }}</p>
@endforeach
```

Supports comma-separated, space-separated, and `$`-prefixed names:

```blade
@needs user, posts
@needs $user $posts
```

> [!IMPORTANT]
> `@needs` is a runtime check. If a required variable is not `isset()`, a `[PLUGS-VIEW-006]` error is thrown immediately.

### @defaults

The `@defaults` directive allows you to set fallback values for variables. If the variable is already passed from the controller, the default is ignored.

```blade
@defaults(['theme' => 'light', 'showSidebar' => true])

<body class="theme-{{ $theme }}">
    @if($showSidebar)
        <aside>...</aside>
    @endif
</body>
```

> [!TIP]
> Variables defined with `@let`, `@calc`, or `@defaults` are automatically available in the rest of the template and follow standard PHP scoping rules for the rendered view.

---

## Props & Component Data

Props passed to components are **auto-injected** — all attributes are available as variables inside the component template with zero setup.

```html
{{-- Usage --}}
<Card title="Welcome" description="Hello world" />
```

```blade
{{-- components/Card.plug.php --}}
<div class="card">
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
</div>
```

For optional props with defaults, use the null coalescing operator:

```blade
@php
    $type = $type ?? 'primary';
    $size = $size ?? 'md';
@endphp
```

> [!NOTE]
> `@props` is deprecated. It still works for backward compatibility but is no longer needed.

```blade
{{-- Access parent component data --}}
@aware(['theme', 'user'])
```



@aware(['theme', 'user'])
```

---

## HTMX/Turbo Support

### Fragments

```blade
@fragment('sidebar')
    <div id="sidebar">
        {{-- Can be updated independently via HTMX --}}
    </div>
@endfragment
```

### Teleport

```blade
@teleport('#modals')
    <div class="modal">
        {{-- Content will be moved to #modals element --}}
    </div>
@endteleport
```

---

## Caching

```blade
{{-- Cache for 1 hour --}}
@cache('sidebar-menu', 3600)
    <nav>
        {{-- Expensive menu generation --}}
    </nav>
@endcache

{{-- Cache with default TTL --}}
@cache('footer-stats')
    <div class="stats">...</div>
@endcache
```

---

## Lazy Loading

```blade
{{-- Lazy load component --}}
@lazy('analytics-dashboard')

{{-- With data --}}
@lazy('user-activity', ['userId' => $user->id])
```

---

## Security

```blade
{{-- Sanitize HTML --}}
@sanitize($userContent)

{{-- Strict mode (no tags) --}}
@sanitize($content, 'strict')

{{-- Basic formatting --}}
@sanitize($content, 'basic')

{{-- Rich text --}}
@sanitize($content, 'rich')
```

| Mode      | Allowed Tags                               |
| --------- | ------------------------------------------ |
| `strict`  | None                                       |
| `basic`   | `<p><br><strong><em><b><i>`                |
| `default` | `<p><br><strong><em><b><i><ul><ol><li><a>` |
| `rich`    | Above + `<h1-h6><blockquote><code><pre>`   |

### Content Security Policy (CSP)

The `@csp` directive automatically generates a meta tag with a secure default policy. It also supports nonces if configured in your application.

```blade
@csp
```

### Element ID Sanitization

The `@id` directive ensures a value is safe to use as an HTML `id` attribute by removing non-alphanumeric characters.

```blade
<div id="@id($username)">...</div>
```

---

## Performance & Streaming

### View Streaming

For large views or long-running processes, use `@stream` to send the response in chunks to the browser, improving perceived performance.

```blade
@stream('views.large-report', ['data' => $data])
```

---

## Data Binding

```blade
{{-- Two-way data binding attribute --}}
<input type="text" @entangle('username')>
```

---

## Hybrid Reactivity (Client-Side)

Plugs supports Alpine/Vue-style reactivity for instant client-side interactions. These directives start with the `p-` prefix.

| Directive | Description |
|-----------|-------------|
| `p-data`  | Initializes a reactive scope with local JSON state. |
| `p-text`  | Reactively updates inner text. |
| `p-html`  | Reactively updates inner HTML. |
| `p-show`  | Toggles visibility via CSS. |
| `p-if`    | Adds/Removes element from DOM. |
| `p-on`    | Attaches event listeners (e.g. `p-on:click`). |
| `p-bind`  | Binds attributes to state (e.g. `p-bind:class`). |
| `p-model` | Two-way data binding for forms. |

> [!TIP]
> For a full guide on building interactive UIs with these directives, see the **[Hybrid Reactivity Guide](reactivity.md)**.

---

## Utility Directives

### JSON & JS Output

```blade
<script>
    const data = @json($data);
    const config = @json($config, JSON_PRETTY_PRINT);

    // Use @js for a JS-safe representation of data
    const settings = @js($settings);
</script>
```

### Verbatim (No Compilation)

```blade
@verbatim
    {{ This will not be compiled }}
    @if (true) Neither will this @endif
@endverbatim
```

### PHP Code

````blade
@php
    $now = new DateTime();
    $formatted = $now->format('Y-m-d');
@endphp

@php($counter = 0)

### Flush (Buffering)

The `@flush` directive forces a PHP `flush()` call, sending the current output buffer to the browser. This is extremely useful for large data processing or slow views.

```blade
@foreach($largeData as $item)
    {{-- Process item --}}
    @if($loop->iteration % 100 === 0)
        @flush
    @endif
@endforeach
````

> [!TIP]
> Loops now support **Automatic Flushing** when streaming is enabled globally. See [Advanced Documentation](advanced.md) for details.

````

### Once (Render Once)

The `@once` directive ensures a block of code is only rendered once per request cycle. You can provide an optional key for scoped deduplication.

```blade
{{-- Basic usage --}}
@once
    <script src="/js/library.js"></script>
@endonce

{{-- Scoped usage with a key --}}
@once('alpine-init')
    <script>
        document.addEventListener('alpine:init', () => { ... })
    </script>
@endonce
````

---

## UI Components

### Skeleton Loaders

Generate CSS-based skeleton loaders for loading states. Presets include `text`, `avatar`, `image`, and `button`.

```blade
{{-- Default (text) --}}
@skeleton('text')

{{-- Avatar circle --}}
@skeleton('avatar', '48px')

{{-- Professional Image placeholder --}}
@skeleton('image', '100%', '200px')

{{-- Dark mode variants --}}
@skeleton('text-dark')
```

---

## Helper Directives

### Assets & URLs

```blade
{{-- Asset with cache busting --}}
<link href="@asset('css/app.css')" rel="stylesheet">

{{-- Route URL --}}
<a href="@route('users.show', ['id' => $user->id])">Profile</a>

{{-- URL --}}
<a href="@url('/about')">About</a>

{{-- Vite assets (Standard) --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Vite assets (Tag-based) --}}
<vite entry="resources/js/app.js" />
<vite :entries="['resources/js/app.js']" />
```

### Environment

```blade
@env('production')
    {{-- Production only --}}
@endenv

@env(['staging', 'production'])
    {{-- Multiple environments --}}
@endenv

{{-- Config value --}}
{{ @config('app.name') }}
```

### Date/Time Formatting

```blade
@date($timestamp)
@date($timestamp, 'Y-m-d')
@time($timestamp)
@datetime($timestamp)
@relative($timestamp)  {{-- "2 hours ago" --}}
```

### Number Formatting

```blade
@number($value)
@number($value, 2)  {{-- 2 decimal places --}}
@currency($amount)
@currency($amount, 'EUR')
```

### String Helpers

```blade
@upper($text)
@lower($text)
@title($text)
@slug($text)
@truncate($text, 100)
@excerpt($text, 200)
```

---

## Content Helpers

### Reading Time

Calculate estimated reading time for blog content:

```blade
{{-- Default: 200 words/min, short format --}}
@readtime($post->content)
{{-- Output: "5 min read" --}}

{{-- Custom words per minute --}}
@readtime($post->content, 250)
{{-- Output: "4 min read" --}}

{{-- Different formats --}}
@readtime($post->content, 200, 'short')   {{-- "5 min read" --}}
@readtime($post->content, 200, 'long')    {{-- "5 minutes read" --}}
@readtime($post->content, 200, 'minutes') {{-- "5" --}}
```

### Word Count

```blade
@wordcount($post->content)
{{-- Output: "1250" --}}
```

---

## Service Injection

```blade
@inject('metrics', 'App\Services\MetricsService')

{{ $metrics->getVisitorCount() }}
```

---

## Localization

### Translation

The `@t` directive is a shorthand for the translation helper.

```blade
<h1>@t('messages.welcome')</h1>
<p>@t('auth.failed', ['user' => $username])</p>
```

---

## Debugging

### @debug

Dumps all currently defined variables in the view's scope.

```blade
@debug
```

### @dump

Dumps a specific variable (only in debug mode).

```blade
@dump($user)
```
