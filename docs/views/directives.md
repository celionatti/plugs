# Directives Reference

Complete reference for all available template directives in the Plugs View system.

## Echo Statements

| Syntax              | Description                                    |
| ------------------- | ---------------------------------------------- |
| `{{ $var }}`        | Context-aware escaped output (Safe by default) |
| `{{{ $var }}}`      | Raw/unescaped output (Preferred)               |
| `{!! $var !!}`      | Raw/unescaped output (Alternative)             |
| `{{-- comment --}}` | Template comment (not rendered)                |

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
```

---

## Form Helpers

```blade
{{-- CSRF token --}}
@csrf

{{-- Method spoofing --}}
@method('PUT')
@method('DELETE')

{{-- Conditional attributes --}}
<input type="checkbox" @checked($isActive)>
<option @selected($isDefault)>Option</option>
<input @disabled($isReadOnly)>
<input @readonly($isLocked)>
<input @required($isRequired)>
```

---

## Props & Component Data

```blade
{{-- Define default props --}}
@props(['type' => 'primary', 'size' => 'md', 'disabled' => false])

{{-- Access parent component data --}}
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

---

## Data Binding

```blade
{{-- Two-way data binding attribute --}}
<input type="text" @entangle('username')>
```

---

## Utility Directives

### JSON Output

```blade
<script>
    const data = @json($data);
    const config = @json($config, JSON_PRETTY_PRINT);
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

```blade
@php
    $now = new DateTime();
    $formatted = $now->format('Y-m-d');
@endphp

@php($counter = 0)
```

### Once (Render Once)

```blade
@once
    {{-- Only rendered once, even if included multiple times --}}
    <script src="/js/library.js"></script>
@endonce
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
