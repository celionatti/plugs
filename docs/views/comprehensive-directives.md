# Comprehensive Directives Guide

This guide covers all available template directives in the Plugs View system, including both the classic Blade-style `@` syntax and the modern HTML tag-style syntax.

---

## 💎 Variable Interpolation

| Syntax | Description |
| :--- | :--- |
| `{{ $var }}` or `{{ var }}` | Context-aware escaped output (HTML body, attributes, JS, etc.) |
| `{{{ $var }}}` | Raw/unescaped output (Use with caution!) |
| `{!! $var !!}` | Alternative syntax for raw/unescaped output. |
| `{{-- comment --}}` | Template comment (not rendered in HTML). |

**Dot Notation Support**: You can use `user.name` instead of `$user->name`.
```html
<h1>Welcome, {{ user.name }}</h1>
```

---

## 🚦 Control Structures

Plugs supports both `@` directives and clean HTML tags for flow control.

### Conditionals
| Tag Syntax | Directive Syntax | Supported Condition Attributes |
| :--- | :--- | :--- |
| `<if ...>` | `@if(...)` | `condition`, `check`, `test`, `expr`, `when`, `if` |
| `<elseif ...>` | `@elseif(...)` | `condition`, `check`, `test`, `expr`, `when`, `if` |
| `<else />` | `@else` | - |
| `<unless ...>` | `@unless(...)` | `condition`, `check`, `test`, `expr`, `when`, `if` |

#### 💡 Semantic Style Guide
While all keywords function identically, choosing the right one can make your templates more readable:

| Keyword | Best Usage Case | Example |
| :--- | :--- | :--- |
| **`if`** | Classic, most direct logic. | `<checked if="count > 0" />` |
| **`when`** | State or event-based logic. | `<div <hidden when="isMinimized" />>` |
| **`check`** | Permissions or status flags. | `<if check="user.isAdmin">` |
| **`condition`**| Formal or complex boolean logic. | `<if condition="(a && b) || c">` |
| **`test`** | Temporary or state testing. | `<unless test="isDevMode">` |
| **`expr`** | Pure PHP physical expressions. | `<while expr="iterator.valid()">` |

**Example:**
```html
<if check="user.isAdmin">
    <p>Admin Access granted.</p>
<elseif test="user.isEditor">
    <p>Editor Access granted.</p>
<else>
    <p>Standard User.</p>
</if>
```

### Loops
| Tag Syntax | Directive Syntax | Condition Attributes (for `while`/`for`) |
| :--- | :--- | :--- |
| `<foreach items="..." as="...">` | `@foreach(... as ...)` | - |
| `<forelse items="..." as="...">` | `@forelse(... as ...)` | - |
| `<empty />` | `@empty` | - |
| `<for init="..." condition="..." step="...">` | `@for(...)` | `condition`, `check`, `test`, `expr`, `when`, `if` |
| `<while condition="...">` | `@while(...)` | `condition`, `check`, `test`, `expr`, `when`, `if` |

**Example:**
```html
<foreach items="posts" as="post">
    <li>{{ post.title }}</li>
</foreach>

<while check="count(items) > 0">
    <php>$item = array_pop($items);</php>
    {{ item }}
</while>
```

---

## 🏗️ Layouts & Inheritance

### Defining Structure
| Tag Syntax | Directive Syntax | Description |
| :--- | :--- | :--- |
| `<layout name="...">` | `@extends('...')` | Inherit from a base layout. |
| `<slot name="...">` | `@section('...')` | Define a content section. |
| `<yield name="..." />` | `@yield('...')` | Output a section in the layout. |
| `<section name="...">` | `@section('...')` | Alternative for defining sections. |

**Example:**
```html
<layout name="layouts.main">
    <section name="title">Page Title</section>
    
    <div class="content">
        Main page content here...
    </div>
</layout>
```

---

## 🛡️ Forms & Security

### Tokens & Methods
| Tag Syntax | Directive Syntax |
| :--- | :--- |
| `<csrf />` | `@csrf` |
| `<method value="PUT" />` | `@method('PUT')` |

### Conditional Attributes
These tags render the attribute string (e.g., `checked`) if the condition matches.
**Supported Attributes**: `condition`, `check`, `test`, `expr`, `when`, `if`.

```html
<input type="checkbox" <checked when="isActive" />>
<option <selected if="id == currentId" />>Option</option>
<button <disabled test="isLoading" />>Submit</button>
<input <readonly check="isLocked" />>
<input <required expr="true" />>
```

---

## 📦 Components & UI

### Directives
- **`<include view="..." :data="..." />`**: Include a partial.
- **`<push name="...">` / `<stack name="..." />`**: Manage asset stacks.
- **`<once>`**: Render content only once per request.
- **`<teleport to="...">`**: Move content to another DOM element.
- **`<fragment name="...">`**: HTMX/Turbo fragment.
- **`<skeleton type="..." />`**: Render loading states (text, image, avatar).
- **`<php>`**: Run raw PHP code blocks.

**Example:**
```html
<push name="scripts">
    <script src="chart.js"></script>
</push>

<php>
    $totalCount = count($items);
</php>

<skeleton type="image" width="100%" height="200px" />
```

---

## 🧪 Environmental & RBAC
Control visibility based on auth or environment.

| Tag Syntax | Description |
| :--- | :--- |
| `<auth [guard="..."]>` | Visible only to logged-in users. |
| `<guest>` | Visible only to guests. |
| `<can ability="...">` | Check for permissions. |
| `<role name="...">` | Check for user roles. |
| `<production>` | Visible only in production environment. |
| `<local>` | Visible only in local development. |
| `<debug>` | Visible only when `app.debug` is true. |

---

## 🛠️ Utility Directives

| Directive | Description |
| :--- | :--- |
| `@date($ts)` | Format timestamp to date. |
| `@time($ts)` | Format timestamp to time. |
| `@relative($ts)` | "2 hours ago" format. |
| `@currency($val)` | Format value as currency. |
| `@truncate($text, $len)` | Shorten text with ellipsis. |
| `@readtime($content)` | Estimated reading time. |
| `@t('key')` | Shorthand for translations. |

---

## 📦 Dynamic Class & Style
Supported both as tags and as short-hand attributes on any element.

**Tag Syntax:**
```html
<class :map="['active' => isActive, 'disabled' => !canEdit]" />
```

**Attribute Syntax (Recommended):**
```html
<div :class="['p-4', 'bg-blue' => isActive]" :style="['font-weight' => 'bold']">
    Dynamic content
</div>
```
