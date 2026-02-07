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

```blade
<x-alert type="success" message="Operation completed!" />

<x-alert type="warning">
    This is a warning message with <strong>HTML</strong>.
</x-alert>
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

```blade
{{-- String values --}}
<x-button type="danger" size="lg">Delete</x-button>

{{-- Dynamic values with : prefix --}}
<x-button :type="$buttonType" :disabled="$isLoading">
    Submit
</x-button>

{{-- Boolean props --}}
<x-button disabled>Can't Click</x-button>
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
<x-card>
    <p>This is the card content.</p>
</x-card>
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

```blade
{{-- Usage --}}
<x-card>
    <x-slot:header>
        <h3>Custom Header</h3>
    </x-slot:header>
    
    <p>Card body content here.</p>
    
    <x-slot:footer>
        <button>Action</button>
    </x-slot:footer>
</x-card>
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
<x-button class="mt-4" id="submit-btn" @click="handleClick">
    Submit
</x-button>

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

```blade
<x-button class="mt-4">Click</x-button>
{{-- Renders: class="btn btn-primary mt-4" --}}
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

## Dynamic Components

Render components dynamically:

```php
// In controller
$component = $userType === 'admin' ? 'admin-dashboard' : 'user-dashboard';
return view('page', ['component' => $component]);
```

```blade
{{-- In view - using ViewEngine --}}
<?php echo $view->renderDynamic($component, ['user' => $user]); ?>
```

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

```blade
<x-forms-input 
    name="email" 
    type="email" 
    label="Email Address"
    :value="$user->email"
    :error="$errors->first('email')"
    required
    placeholder="you@example.com"
/>
```
