# Components

Build reusable, self-contained UI components with props, slots, and attributes.

## Creating Components

### File Location

Components are stored in `resources/views/components/`:

```
resources/views/components/
├── button.plug.php
├── card.plug.php
├── forms/
│   ├── input.plug.php
│   └── select.plug.php
└── modals/
    └── dialog.plug.php
```

### Basic Component

```blade
{{-- components/alert.plug.php --}}
@props(['type' => 'info', 'message' => ''])

<div class="alert alert-{{ $type }}" {{ $attributes }}>
    {{ $message }}
    {{ $slot }}
</div>
```

Usage:

```html
{{-- Root level component (PascalCase) --}}
<Alert type="success" message="Operation completed!" />

{{-- Nested component (Module::Component) --}}
<Forms::Input type="text" name="email" label="Email" />

{{-- Component with content (Slots) --}}
<Alert type="warning">
  This is a warning message with <strong>HTML</strong>.
</Alert>
```

---

## Props

### Defining Props with Defaults

```blade
@props([
    'type' => 'primary',
    'size' => 'md',
    'disabled' => false,
    'icon' => null
])

<button
    class="btn btn-{{ $type }} btn-{{ $size }}"
    @disabled($disabled)
>
    @if($icon)
        <i class="icon-{{ $icon }}"></i>
    @endif
    {{ $slot }}
</button>
```

### Passing Props

```html
{{-- String values --}}
<button type="danger" size="lg">Delete</button>

{{-- Dynamic values with : prefix --}}
<button :type="$buttonType" :disabled="$isLoading">Submit</button>

{{-- Boolean props --}}
<button disabled>Can't Click</button>
```

---

## Slots

### Default Slot

```blade
{{-- Component --}}
<div class="card">
    {{ $slot }}
</div>

{{-- Usage --}}
<Card>
    <p>This is the card content.</p>
</Card>
```

### Named Slots

```blade
{{-- components/card.plug.php --}}
@props(['title' => ''])

<div class="card">
    <div class="card-header">
        {{ $header ?? $title }}
    </div>
    <div class="card-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
```

```html
{{-- Usage --}}
<Card>
  <slot:header>
    <h3>Custom Header</h3>
  </slot:header>

  <p>Card body content here.</p>

  <slot:footer>
    <button>Action</button>
  </slot:footer>
</Card>
```

---

## Attributes

### Accessing Merged Attributes

```blade
{{-- Component --}}
@props(['type' => 'button'])

<button type="{{ $type }}" {{ $attributes }}>
    {{ $slot }}
</button>

{{-- Usage - extra attributes are merged --}}
<Button class="mt-4" id="submit-btn" @click="handleClick">
    Submit
</Button>

{{-- Renders: --}}
<button type="button" class="mt-4" id="submit-btn" @click="handleClick">
    Submit
</button>
```

### Merging Classes

```blade
@props(['type' => 'primary'])

<button {{ $attributes->merge(['class' => 'btn btn-' . $type]) }}>
    {{ $slot }}
</button>
```

```html
<button class="mt-4">Click</button> {{-- Renders: class="btn btn-primary mt-4"
--}}
```

---

## Component Aliasing

Register short aliases for components:

```php
$viewEngine->alias('btn', 'forms.button');
$viewEngine->alias('modal', 'modals.dialog');
$viewEngine->alias('icon', 'ui.icon');
```

```blade
{{-- Instead of <x-forms-button> --}}
<x-btn type="primary">Click</x-btn>
```

---

---

## Scoped Component Referencing (New in V5)

For complex applications, you can organize components into sub-directories and reference them using the `::` scoped syntax.

### Automatic Directory Mapping

- `<User::Profile />` maps to `resources/views/components/user/profile.plug.php`.
- `<Admin::Dashboard::Stats />` maps to `resources/views/components/admin/dashboard/stats.plug.php`.

**Usage Example:**

```html
{{-- Nested directory resolution --}}
<Admin::Sidebar />

{{-- Deeply nested --}}
<Admin::Dashboard::Stats :total="500" />
```

---

## Class-Based Components

For more complex logic, you can back a component with a PHP class. This allows you to prepare data, dependency inject services, and manage state before rendering.

### 1. Create the Class

Create a class in `app/Components/` (namespace `App\Components`).

```php
namespace App\Components;

class UserProfile
{
    public $user;
    public $title;

    public function __construct($user, $title = 'Profile')
    {
        $this->user = $user;
        $this->title = $title;
    }

    public function render()
    {
        // View resolves to resources/views/components/user-profile.plug.php
        // OR resources/views/components/user/profile.plug.php
        return 'user-profile';
    }
}
```

### 2. Create the View

Create the corresponding view file. Public properties from the class are automatically available.

```blade
{{-- components/user-profile.plug.php --}}
<div class="profile-card">
    <h3>{{ $title }}</h3>
    <img src="{{ $user->avatar }}" alt="{{ $user->name }}">
    <p>{{ $user->bio }}</p>
</div>
```

### 3. Usage

```html
<UserProfile :user="$currentUser" title="My Account" />
```

---

## Reactive Components (Livewire Style)

Plugs V5 includes a built-in reactive component bridge powered by `plugs-spa.js`. This allows you to create interactive components that update via AJAX without complex JavaScript.

### 1. The PHP Component

Extend the `ReactiveComponent` class to enable state management.

```php
namespace App\Components;

use Plugs\View\ReactiveComponent;

class Counter extends ReactiveComponent {
    public int $count = 0;

    public function increment() {
        $this->count++;
    }

    public function render() {
        return 'counter'; // Resolves to resources/views/components/counter.plug.php
    }
}
```

> [!TIP]
> **Smart View Resolution**: The `render()` method is optional. If you do provide it, you can return a view name like `'counter'`. The engine is smart enough to check `resources/views/components/` first. If found, it renders as a component. If not, it falls back to `resources/views/` (standard view). This allows you to use both dedicated component views and standard partials seamlessly. All **public properties** are automatically available in the view.

### 2. The Interactive View

Use the `p-` attributes to bind events and data.

```html
{{-- components/counter.plug.php --}}
<div>
  <h1>Count: {{ $count }}</h1>

  {{-- Trigger a server-side method --}}
  <button p-click="increment">+</button>

  {{-- Bind input to a method (setName($value)) --}}
  <input type="text" p-model="setName" placeholder="Your name" />
</div>
```

### Available Interactive Attributes (`plugs-spa.js`)

| Attribute     | Description                                                  |
| ------------- | ------------------------------------------------------------ |
| `p-click`     | Trigger action on click.                                     |
| `p-submit`    | Handle form submission via AJAX.                             |
| `p-model`     | Two-way data binding (syncs input value to property/method). |
| `p-poll`      | Poll the server at intervals (e.g., `p-poll="3000"`).        |
| `p-intersect` | Trigger action when the element enters the viewport.         |
| `p-debounce`  | Delay the action in milliseconds (e.g., `p-debounce="500"`). |

---

## Parent Data Access

Access data from parent components:

```blade
{{-- Parent component: form.plug.php --}}
@props(['theme' => 'light'])

<?php \Plugs\View\ViewCompiler::pushParentData(['theme' => $theme]); ?>
<div class="form form-{{ $theme }}">
    {{ $slot }}
</div>
<?php \Plugs\View\ViewCompiler::popParentData(); ?>
```

```blade
{{-- Child component: input.plug.php --}}
@aware(['theme'])

<input class="input input-{{ $theme ?? 'light' }}" {{ $attributes }}>
```

---

## Component Examples

### Button Component

```blade
{{-- components/button.plug.php --}}
@props([
    'type' => 'button',
    'variant' => 'primary',
    'size' => 'md',
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'href' => null
])

@php
    $baseClasses = 'btn';
    $variantClasses = 'btn-' . $variant;
    $sizeClasses = 'btn-' . $size;
    $classes = "$baseClasses $variantClasses $sizeClasses";
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i class="icon-{{ $icon }}"></i>@endif
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $type }}"
        @disabled($disabled || $loading)
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if($loading)
            <span class="spinner"></span>
        @elseif($icon)
            <i class="icon-{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif
```

### Modal Component

```blade
{{-- components/modal.plug.php --}}
@props([
    'id' => 'modal',
    'title' => '',
    'size' => 'md',
    'closable' => true
])

<div id="{{ $id }}" class="modal modal-{{ $size }}" {{ $attributes }}>
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>{{ $title }}</h3>
            @if($closable)
                <button class="modal-close" data-close-modal>&times;</button>
            @endif
        </div>
        <div class="modal-body">
            {{ $slot }}
        </div>
        @isset($footer)
            <div class="modal-footer">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
```

### Form Input Component

```blade
{{-- components/forms/input.plug.php --}}
@props([
    'name',
    'type' => 'text',
    'label' => null,
    'value' => '',
    'error' => null,
    'hint' => null,
    'required' => false
])

<div class="form-group @if($error) has-error @endif">
    @if($label)
        <label for="{{ $name }}">
            {{ $label }}
            @if($required)<span class="required">*</span>@endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        @required($required)
        {{ $attributes->merge(['class' => 'form-control']) }}
    >

    @if($error)
        <span class="error-message">{{ $error }}</span>
    @elseif($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
</div>
```

Usage:

```html
<Forms::Input
  name="email"
  type="email"
  label="Email Address"
  :value="$user->email"
  :error="$errors->first('email')"
  required
  placeholder="you@example.com"
/>
```
