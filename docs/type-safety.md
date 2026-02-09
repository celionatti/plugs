# Type Safety Features

Plugs is built for strict typing, helping you catch errors at development time rather than in production.

## 1. Typed Events

Plugs encourages the use of `TypedEvent` classes instead of string-based event names.

```php
use Plugs\Event\TypedEvent;

class OrderPaid extends TypedEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly float $amount
    ) {}
}

// Dispatching (fully typed)
Event::dispatch(new OrderPaid(1234, 99.99));

// Listening
Event::listen(OrderPaid::class, function (OrderPaid $event) {
    // $event->amount is float
});
```

## 2. Value Objects & Route Binding

Domain Driven Design (DDD) is first-class. If you define a controller argument as a `ValueObject`, the router automatically hydrates it from the URL.

```php
use Plugs\Support\ValueObject;

class UserId extends ValueObject {}

// In Controller
public function show(UserId $id) 
{
    // $id is automatically instantiated from path parameter
}
```

## 3. Strict Routing

Always use the array-callable syntax for route definitions. This allows IDEs to track usages and enables refactoring safety.

```php
// Safe and Refactorable
$router->get('/profile', [ProfileController::class, 'index']);
```

## 4. IDE Helpers & Generators

Keep your IDE smart with the Plugs Generator:

```bash
# Generates auto-completion for config(), view(), etc.
php theplugs type:gen
```
