# Event-Driven Core

The Plugs framework is built on a fully observable event-driven core. Every significant moment in the application lifecycle emits an event that you can subscribe to. This allows for loose coupling and massive extensibility.

## Core Lifecycle Events

The following events are emitted by the framework core during a typical request-response cycle:

| Event                                      | Dispatched When                                                                  |
| ------------------------------------------ | -------------------------------------------------------------------------------- |
| `Plugs\Event\Core\ApplicationBootstrapped` | After the application has fully bootstrapped all services and modules.           |
| `Plugs\Event\Core\RequestReceived`         | Immediately after a new HTTP request enters the system.                          |
| `Plugs\Event\Core\RouteMatched`            | After the router successfully matches the request to a defined route.            |
| `Plugs\Event\Core\ActionExecuting`         | Just before a controller action or closure handler is executed.                  |
| `Plugs\Event\Core\ActionExecuted`          | Immediately after a controller action or closure handler has finished execution. |
| `Plugs\Event\Core\ResponseSending`         | Before the HTTP response headers and status line are sent to the client.         |
| `Plugs\Event\Core\ResponseSent`            | After the entire response body has been sent to the client.                      |
| `Plugs\Event\Core\ExceptionThrown`         | When an uncaught exception is reported by the exception handler.                 |
| `Plugs\Event\Core\QueryExecuted`           | After a database query has been executed (includes SQL, params, and time).       |

## Subscribing to Events

You can listen to these events in your Module's `boot()` method or in a Service Provider.

### Simple Closure Listener

```php
use Plugs\Event\Core\RequestReceived;

public function boot()
{
    events()->listen(RequestReceived::class, function (RequestReceived $event) {
        $path = $event->request->getUri()->getPath();
        logger()->info("Request received for: {$path}");
    });
}
```

### Event Subscribers

For more complex logic, you can create dedicated subscriber classes:

```php
namespace App\Listeners;

use Plugs\Event\Core\QueryExecuted;
use Plugs\Event\Core\ExceptionThrown;

class MonitorSubscriber
{
    public function handleQuery(QueryExecuted $event)
    {
        if ($event->time > 0.5) {
            logger()->warning("Slow query detected: {$event->sql}");
        }
    }

    public function handleException(ExceptionThrown $event)
    {
        // Send alert to Discord/Slack
    }

    public function subscribe($events)
    {
        $events->listen(QueryExecuted::class, [self::class, 'handleQuery']);
        $events->listen(ExceptionThrown::class, [self::class, 'handleException']);
    }
}
```

Register the subscriber in your module:

```php
public function boot()
{
    events()->subscribe(new \App\Listeners\MonitorSubscriber());
}
```

## Stopping Event Propagation

If an event extends the base `Plugs\Event\Event` class, you can stop further listeners from executing:

```php
events()->listen(ActionExecuting::class, function (ActionExecuting $event) {
    if ($event->request->getAttribute('blocked')) {
        $event->stopPropagation();
    }
});
```

## Why use Events?

1. **Debug Tools**: Hook into `QueryExecuted` and `ActionExecuting` to build a real-time profiler.
2. **Audit Logs**: Listen to `ActionExecuted` to log user actions across the system.
3. **AI Monitoring**: Feed `ExceptionThrown` and `ResponseSent` data to an AI module for performance analysis.
4. **Clean Architecture**: Keep your controllers and models clean. Instead of putting logging/metrics inside them, emit an event or listen to framework events.
