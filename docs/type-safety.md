# Type Safety Features

The Plugs Framework encourages strict typing to improve code reliability and developer experience.

## 1. Typed Events

Instead of using string-based event names, you should extend the `Plugs\Event\TypedEvent` class. This allows you to attach data directly to the event object.

```php
use Plugs\Event\TypedEvent;

class UserRegistered extends TypedEvent
{
    public function __construct(
        public readonly User $user
    ) {}
}

// Dispatching
Event::dispatch(new UserRegistered($user));

// Listening
Event::listen(UserRegistered::class, function (UserRegistered $event) {
    // $event->user is fully typed
});
```

## 2. Value Objects

Use `Plugs\Support\ValueObject` to create immutable domain types.

```php
use Plugs\Support\ValueObject;

class Email extends ValueObject
{
    public function __toString(): string
    {
        return $this->value;
    }
}

$email = Email::from('test@example.com');
```

**Automatic Route Binding:**

The Router automatically hydrates `ValueObject`s from route parameters.

```php
// Route
$router->get('/users/{id}', [UserController::class, 'show']);

// Controller
public function show(UserId $id)
{
    // $id is an instance of UserId, populated from the URI
}
```

## 3. Strict Routing

We recommend using array-based callables for routes to ensure refactoring safety.

```php
use App\Controllers\HomeController;

// ✅ Preferred (Safe)
$router->get('/', [HomeController::class, 'index']);

// ⚠️ Supported (Brittle)
$router->get('/', 'HomeController@index');
```

## 4. IDE Helpers

To improve auto-completion for dynamic features like `config()`, run:

```bash
php plg type:gen
```

This will generate helper files in your project root that your IDE (pbl PHPStorm or VS Code) can index.
