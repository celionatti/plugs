# Live Components

Live Components bring real-time reactivity to your Plugs application without the need for complex JavaScript frameworks. It is inspired by tools like Laravel Livewire and Phoenix LiveView, allowing you to build reactive UIs primarily using PHP.

> [!TIP]
> **Hybrid Power:** For instant client-side interactions (like toggling modals or simple counters) that don't require the server, pair Live Components with **[Hybrid Reactivity](reactivity.md)**.

## Rendering a Live Component

Use the `@live` directive to render a reactive component class.

```html
@live('Counter', ['count' => 5])
```

## Shorthand Event Handlers

Live Components support an intuitive `@` shorthand for binding browser events to reactive actions.

```html
<button @click="increment">Add One</button>
<input type="text" @input="updateSearch">
```

Supported event shorthands include: `@click`, `@input`, `@change`, `@blur`, `@submit`, `@keyup`, and `@keydown`.

## Inline Expressions

For simple state updates, you can write expressions directly in your view without creating a dedicated PHP method for every action.

```html
<!-- Increment/Decrement counters -->
<button @click="count++">+</button>
<button @click="count--">-</button>

<!-- Update properties directly -->
<button @click="status = 'completed'">Complete Order</button>
```

## Creating a Live Component Class

Your component should extend `Plugs\View\ReactiveComponent`. Any **public** properties in this class are automatically synchronized with the frontend.

```php
namespace App\Components;

use Plugs\View\ReactiveComponent;

class Counter extends ReactiveComponent
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }

    public function render()
    {
        return 'components.counter';
    }
}
```

## Why use Live Components?

- **Simple Mental Model**: State lives on the server, but updates feel instant on the client.
- **No API Design Required**: You don't need to build REST or GraphQL endpoints for UI interactivity.
- **Secure**: Sensitive data is never exposed to the client in plain text; the state is securely serialized and signed.
- **Performant**: Only the parts of the component that change are updated in the DOM, using our optimized morphing engine.
