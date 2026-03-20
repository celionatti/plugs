# Hybrid Reactivity

Plugs provides a powerful **Hybrid Reactivity Engine** that combines instantaneous client-side interactions with the robust logic of server-side PHP. This allows you to build rich, interactive UIs without the complexity of a heavy JavaScript framework.

---

## 1. Client-Side Reactivity (`p-` Directives)

For instant interactions like toggling menus or tracking input without a server round-trip, use the `p-` directives (inspired by Alpine.js).

### Basic Usage
Initialize a reactive scope with `p-data`.

```html
<div p-data="{ open: false, count: 0 }">
    <button p-on:click="open = !open">Toggle Menu</button>
    
    <div p-show="open">
        <p>The count is: <span p-text="count"></span></p>
        <button p-on:click="count++">Increment</button>
    </div>
</div>
```

### Core Directives
| Directive | Description |
| --- | --- |
| `p-data` | Initializes a local reactive scope. |
| `p-on` | Attach event listeners (e.g., `p-on:click`, `p-on:keyup.enter`). |
| `p-show` | Toggles visibility via CSS (`display: none`). |
| `p-text` / `p-html` | Updates element content. |
| `p-bind` | Binds attributes (e.g., `p-bind:disabled="isLoading"`). |
| `p-model` | Two-way data binding for form inputs. |

---

## 2. Server-Side Live Components

**Live Components** allow you to build reactive UIs using only PHP. State is synchronized between the server and the client via an optimized DOM morphing engine.

### Rendering a Component
Use the `@live` directive to render a reactive class.

```html
@live('Counter', ['initial' => 10])
```

### The Component Class
Extend `Plugs\View\ReactiveComponent`. Public properties are automatically synced.

```php
class Counter extends ReactiveComponent
{
    public int $count = 0;

    public function increment() { $this->count++; }

    public function render() { return 'pages.counter'; }
}
```

### Event Binding Shorthand
Live components support a clean `@` shorthand for server-side actions:
```html
<button @click="increment">Add One</button>
<button @click="count++">Inline Increment</button>
```

---

## 3. Async & Fetch Components

### Async Loading
Load heavy parts of your page on-demand using the `<async>` tag.

```html
<async component="UserHistory" :user-id="user.id">
    <div class="skeleton">Loading history...</div>
</async>
```

### Data Fetching
The `<fetch>` tag simplifies API consumption with built-in loading and success states.

```html
<fetch url="/api/products">
    <loading>Scanned items...</loading>
    <success>
        <loop :items="products" as="product">
            <div>{{ product.name }}</div>
        </loop>
    </success>
</fetch>
```

---

## 4. DOM Morphing & Transitions

When state changes, Plugs only updates the parts of the DOM that actually changed. You can add smooth animations using `p-transition`.

```html
<div p-show="open" 
     p-transition:enter="fade-in duration-200"
     p-transition:leave="fade-out duration-150">
    Smooth Experience
</div>
```

---

## Next Steps
Optimize your frontend assets using [Asset Management](./asset-management.md).
