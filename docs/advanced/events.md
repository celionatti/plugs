# Events

The Plugs Event System provides a simple observer implementation, allowing you to subscribe and listen for various events that occur in your application.

## Generating Events & Listeners

To create a new event and listener, you can use the `make:event` and `make:listener` CLI commands:

```bash
php theplugs make:event UserRegistered
php theplugs make:listener SendWelcomeEmail
```

## Registering Events

Normally, you register your event listeners in a Service Provider's `boot` method or in a dedicated `EventServiceProvider`.

```php
use Plugs\Facades\Event;
use App\Events\UserRegistered;
use App\Listeners\SendWelcomeEmail;

Event::listen(UserRegistered::class, SendWelcomeEmail::class);
```

### Closure Listeners

You can also register anonymous function listeners:

```php
Event::listen('user.login', function($user) {
    // Log user login...
});
```

## Dispatching Events

To dispatch an event, you can use the `Event` facade or the `dispatch` helper (if available):

```php
use Plugs\Facades\Event;
use App\Events\UserRegistered;

$user = User::find(1);
Event::dispatch(new UserRegistered($user));
```

## Defining Events

An event class is a simple data container that holds information related to the event.

```php
namespace App\Events;

use Plugs\Event\Event;
use App\Models\User;

class UserRegistered extends Event
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
```

## Defining Listeners

Event listeners receive the event instance in their `handle` method:

```php
namespace App\Listeners;

use App\Events\UserRegistered;

class SendWelcomeEmail
{
    public function handle(UserRegistered $event)
    {
        // Access the user via $event->user
        // Send email...
    }
}
```

## Stopping Propagation

If you want to stop the propagation of an event to other listeners, you may return `false` from a listener's `handle` method:

```php
public function handle(UserRegistered $event)
{
    // Logic...
    
    return false; // Stops other listeners from running
}
```

Or call the `stopPropagation` method on the event object itself:

```php
$event->stopPropagation();
```
$event->stopPropagation();
```

## ⚡ Async Events

Sometimes you want event listeners to run in parallel, especially for side effects like sending emails or notifications that shouldn't block the main response time (if waiting for them) or just to speed up the process.

To make an event asynchronous, implement the `Plugs\Event\AsyncEventInterface`:

```php
use Plugs\Event\Event;
use Plugs\Event\AsyncEventInterface;

class OrderPlaced extends Event implements AsyncEventInterface
{
    // ...
}
```

When you dispatch this event, all its listeners will be executed concurrently using the framework's Fiber system.
