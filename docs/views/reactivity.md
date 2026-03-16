# Hybrid Reactivity Engine

Plugs V4 introduces a powerful hybrid reactivity engine that combines the best of server-side morphing (Livewire-style) with instantaneous client-side state (Alpine.js-style).

This allows you to build rich, interactive user interfaces with zero build step and absolute minimal JavaScript.

---

## 🚀 Getting Started

To enable reactivity, ensure you have the `plugs-spa.js` script included in your layout. Every component in Plugs can now hold its own local state using the `p-data` directive.

```html
<div p-data="{ open: false, count: 0 }">
    <button p-on:click="open = !open">Toggle Menu</button>
    
    <div p-show="open">
        <p>The count is: <span p-text="count"></span></p>
        <button p-on:click="count++">Increment</button>
    </div>
</div>
```

---

## 📦 State Management (`p-data`)

The `p-data` directive initializes a local reactive scope. Any change to variables inside this scope will automatically trigger updates to any element using a `p-` directive within that scope.

> [!TIP]
> **Reactivity is Scoped:** `p-data` scopes are isolated. Nested components with their own `p-data` will manage their own state independently.

---

## 🛠️ Directives Reference

### Display & Text
| Directive | Description | Example |
|-----------|-------------|---------|
| `p-text`  | Updates the inner text of an element. | `<span p-text="username"></span>` |
| `p-html`  | Updates the inner HTML of an element. | `<div p-html="bio"></div>` |
| `p-show`  | Toggles `display: none` based on a boolean. | `<div p-show="isOpen">...</div>` |
| `p-if`    | Completely adds/removes element from DOM. | `<template p-if="isLoaded">...</template>` |

### Attributes & Binding
| Directive | Description | Example |
|-----------|-------------|---------|
| `p-bind:attr` | Binds any HTML attribute to state. | `<button p-bind:disabled="isLoading">` |
| `p-bind:class`| Conditional CSS classes (supports objects). | `:class="{ 'active': active }"` |
| `p-model` | Two-way data binding for inputs. | `<input p-model="query">` |

---

## ⚡ Events (`p-on`)

The `p-on` directive attaches event listeners to elements. It supports powerful modifiers to handle common UI patterns without writing custom JS.

### Syntax
`p-on:[event].[modifiers]="[expression]"`

### Modifiers
- `.prevent`: Calls `event.preventDefault()`.
- `.stop`: Calls `event.stopPropagation()`.
- `.outside`: Fires only when clicking outside the element.
- `.window`: Listens for the event on the global `window` object.
- `.document`: Listens for the event on `document`.
- `.self`: Only fire if `event.target` is the element itself.
- `.escape`: (Keydown/Keyup) Shorthand for targetting the Escape key.

```html
<div p-data="{ open: false }" p-on:keydown.escape.window="open = false">
    <button p-on:click.outside="open = false">Dropdown</button>
</div>
```

---

## 🔄 DOM Transitions

Animate elements when they are shown or hidden using `p-transition`. This works seamlessly with `p-show` and `p-if`.

| Attribute | Timing |
|-----------|--------|
| `p-transition:enter` | Applied during the entire enter phase. |
| `p-transition:enter-start` | Applied before the transition starts. |
| `p-transition:enter-end` | Applied after the transition starts. |
| `p-transition:leave` | Applied during the entire leave phase. |

```html
<div p-show="open" 
     p-transition:enter="transition ease-out duration-200"
     p-transition:enter-start="opacity-0 scale-95"
     p-transition:enter-end="opacity-100 scale-100">
    I will fade and scale in!
</div>
```

---

## ⚓ Lifecycle Hooks

Execute JavaScript at specific points in a component's lifecycle.

| Hook | Timing |
|------|--------|
| `p-mounted` | Fired when the component is injected into the DOM. |
| `p-updated` | Fired after a morph patch or state update occurs. |
| `p-unmounted` | Fired before the component is removed from the DOM. |

```html
<div p-mounted="initFlatpickr($el)" p-unmounted="destroyFlatpickr($el)">
    <input type="text" class="datepicker">
</div>
```

---

## 🌐 Global API & Custom Directives

You can extend the framework with your own custom directives.

```javascript
Plugs.directive('tooltip', (el, value) => {
    el.setAttribute('title', value);
});
```

Usage:
```html
<button p-tooltip="Click to save">Save</button>
```

---

## 🔌 Integration with Server Actions

The reactivity engine is designed to work with your existing server-side components. When a server action is called (via `p-click` or `p-submit`), the server can return a new state that is merged into the client-side state.

### Dispatched Events from PHP
From your backend controller or component, you can dispatch events that the client will listen for:

```php
// In a Plugs Action
return response()->component($this)
    ->dispatch('notify', ['message' => 'Profile Updated!']);
```

Listen for it in HTML:
```html
<div p-on:notify.window="showToast($event.detail.message)"></div>
```
