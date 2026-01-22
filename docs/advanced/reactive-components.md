# REACTIVE COMPONENTS (BOLT)

Reactive Components (also known as **Bolt**) allow you to build interactive UI components using only PHP. No JavaScript knowledge is required to create dynamic, real-time interfaces.

---

![Reactive Component Lifecycle](file:///C:/Users/celionatti/.gemini/antigravity/brain/a247115d-000b-4e87-b889-f91296d843e5/reactive_component_cycle_1769081142741.png)

## Overview

Reactive Components bridge the gap between your server-side PHP logic and the client-side DOM. When an action is triggered on the frontend (like clicking a button), the component's state is sent to the server, processed by your PHP class, and the updated HTML is sent back to be swapped seamlessly into the page.

### Key Features
- **Server-Side State**: Your component state lives in PHP public properties.
- **Auto-Hydration**: State is automatically preserved between requests.
- **Zero-JS Interactivity**: Use directives like `p-click` to trigger PHP methods.
- **Component Scoping**: Styles and logic are encapsulated within the component.

---

## Creating a Component

Reactive components consist of two parts: a **PHP Class** and a **Plug View**.

### 1. The PHP Class

Create your component class in `app/Components`. It must extend `Plugs\View\ReactiveComponent`.

```php
namespace App\Components;

use Plugs\View\ReactiveComponent;

class Counter extends ReactiveComponent
{
    // Public properties are automatically synced with the frontend
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }

    public function render()
    {
        // Return the name of the view file
        return 'components.counter';
    }
}
```

### 2. The View Template

Create the corresponding view in `resources/views/components/counter.plug.php`. Use the `p-click` directive to link elements to PHP methods.

```html
<div class="counter-card">
    <button p-click="decrement">-</button>
    <span class="count">{{ $count }}</span>
    <button p-click="increment">+</button>
</div>
```

---

## Usage in Views

To include a reactive component in any of your views, use the `@component` directive:

```html
@component('Counter', ['count' => 10])
```

The component will automatically initialize and handle its own reactivity.

---

## Directives

| Directive | Description | Example |
| :--- | :--- | :--- |
| `p-click` | Triggers a PHP method on click. | `<button p-click="save">Save</button>` |
| `p-change` | Triggers a PHP method on input change. | `<select p-change="filter">...</select>` |
| `p-submit` | Intercepts form submission and calls method. | `<form p-submit="search">...</form>` |

---

## How it Works (The Lifecycle)

1.  **Initial Render**: The server renders the component and serializes its initial state into a `data-plug-state` attribute.
2.  **User Action**: The user clicks an element with `p-click="increment"`.
3.  **Request**: The SPA Bridge sends an AJAX request to `/plugs/component/action` with the current state and the action name.
4.  **Hydration**: The server takes the serialized state, reconstructs the `Counter` class, and populates its properties.
5.  **Execution**: The `increment()` method is called on the PHP object.
6.  **Re-render**: The server re-renders the component's view with the updated state.
7.  **Swap**: The SPA Bridge receives the new HTML and replaces the component's inner DOM.

> [!IMPORTANT]
> Reactive components must be wrapped in a single root element in their view file to ensure correct DOM swapping.
