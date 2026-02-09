<?php

declare(strict_types=1);

namespace Plugs\Event;

use Plugs\Container\Container;

class Dispatcher implements DispatcherInterface
{
    /**
     * The registered event listeners.
     *
     * @var array
     */
    protected array $listeners = [];

    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Create a new event dispatcher instance.
     *
     * @param Container|null $container
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container ?: Container::getInstance();
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param string $event
     * @param callable|string $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $event, $listener, int $priority = 0): void
    {
        $this->listeners[$event][$priority][] = $listener;
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]);
    }

    /**
     * Fire an event and call all relevant listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    /**
     * Dispatch an event and call all listeners asynchronously (in parallel).
     *
     * @param string|object $event
     * @param array $payload
     * @return array The results of the listeners.
     */
    public function dispatchAsync($event, array $payload = []): array
    {
        $eventName = is_object($event) ? get_class($event) : $event;

        if (is_object($event) && empty($payload)) {
            $payload = [$event];
        }

        if (!$this->hasListeners($eventName)) {
            return [];
        }

        // Gather all listeners as tasks
        $tasks = [];
        foreach ($this->getListeners($eventName) as $listener) {
            $tasks[] = fn() => $this->callListener($listener, $payload);
        }

        // Run them in parallel using our FiberManager/Async helper
        // We use full namespace to avoid import conflicts if not imported
        return \Plugs\Concurrency\Async::parallel($tasks);
    }

    /**
     * Fire an event and call all relevant listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], bool $halt = false): ?array
    {
        // If the event is an object, we use its class name as the event name
        $eventName = is_object($event) ? get_class($event) : $event;

        if (is_object($event) && $event instanceof AsyncEventInterface && !$halt) {
            return $this->dispatchAsync($event, $payload);
        }

        // Optional: Enforce TypedEvent usage for object events
        if (is_object($event) && !$event instanceof \Plugs\Event\Event) {
            // We can log a warning or just proceed. For now, we proceed to maintain BC.
            // But we can ensure it follows our TypedEvent contract if strict mode was enabled.
        }

        if (is_object($event) && empty($payload)) {
            $payload = [$event];
        }

        if (!$this->hasListeners($eventName)) {
            return null;
        }

        $responses = [];

        foreach ($this->getListeners($eventName) as $listener) {
            $response = $this->callListener($listener, $payload);

            if ($halt && !is_null($response)) {
                return [$response];
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;

            // Check if the event object has propagation stopped
            if (is_object($event) && $event instanceof Event && $event->isPropagationStopped()) {
                break;
            }
        }

        return $responses;
    }

    /**
     * Get the listeners for a given event name, sorted by priority.
     *
     * @param string $eventName
     * @return array
     */
    protected function getListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName];

        krsort($listeners);

        return array_merge(...$listeners);
    }

    /**
     * Call the given listener with the payload.
     *
     * @param callable|string $listener
     * @param array $payload
     * @return mixed
     */
    protected function callListener($listener, array $payload)
    {
        if (is_string($listener)) {
            $listener = $this->resolveListener($listener);
        }

        return call_user_func_array($listener, $payload);
    }

    /**
     * Resolve a string-based listener.
     *
     * @param string $listener
     * @return callable
     */
    protected function resolveListener(string $listener): callable
    {
        if (strpos($listener, '@') !== false) {
            [$class, $method] = explode('@', $listener);
            $instance = $this->container->make($class);

            return [$instance, $method];
        }

        if (class_exists($listener)) {
            $instance = $this->container->make($listener);
            if (method_exists($instance, 'handle')) {
                return [$instance, 'handle'];
            }
            if (is_callable($instance)) {
                return $instance;
            }
        }

        throw new \InvalidArgumentException("Listener [{$listener}] is not a valid callable.");
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }
}
