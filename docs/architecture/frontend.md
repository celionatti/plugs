# Frontend Engine: Reactive & SPA

Plugs bridges the gap between traditional SSR and modern SPAs with a native, zero-config Frontend Engine.

## 1. Reactive Components
Think of Reactive Components as "Live" templates. They allow you to update parts of your UI in response to server-side state changes without full page reloads.

```php
// resources/views/components/counter.plug.php
<div class="reactive-counter">
    <span>Counter: {{ $count }}</span>
    <button wire:click="increment">+</button>
</div>
```

**How it works:**
The framework tracks "wire" attributes and sends small AJAX payloads to the server, which re-renders the component and diffs the DOM on the frontend.

## 2. SPA Bridge (The "Plugs-SPA")
Our built-in SPA engine turns standard multi-page applications into high-performance Single Page Applications.

- **Non-Blocking Navigation**: Only the `@yield('content')` section is replaced during navigation.
- **State Preservation**: Global elements like the Navigation Bar and Sidebar persist across page loads.
- **Auto-Discovery**: Simply add `data-spa="true"` to any link, and the Plugs Bridge handles the rest.

```php
<a href="/profile" data-spa="true">View Profile</a>
```

## 3. Asset Bundling
Plugs automatically manages your CSS/JS dependencies, ensuring that only necessary assets are loaded for the current view, reducing initial load times.

## 4. Integration with HTMX
For power users, Plugs provides deep integration with **HTMX**, allowing you to use declarative attributes to handle complex interactions with zero JavaScript.
