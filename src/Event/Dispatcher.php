<?php

declare(strict_types=1);

namespace Plugs\Event;

use Plugs\Concurrency\Async;
use Plugs\Container\Container;
use Plugs\Debug\Profiler;
use Psr\EventDispatcher\StoppableEventInterface;

class Dispatcher implements DispatcherInterface
{
    /**
     * The listener provider instance.
     *
     * @var ListenerProvider
     */
    protected ListenerProvider $provider;

    /**
     * Create a new event dispatcher instance.
     *
     * @param Container|null $container
     * @param ListenerProvider|null $provider
     */
    public function __construct(?Container $container = null, ?ListenerProvider $provider = null)
    {
        $container = $container ?: Container::getInstance();
        $this->provider = $provider ?: new ListenerProvider($container);
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
        $this->provider->listen($event, $listener, $priority);
    }

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners(string $eventName): bool
    {
        return $this->provider->hasListeners($eventName);
    }

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
        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            $tasks[] = fn() => call_user_func_array($listener, $payload);
        }

        // Run them in parallel using our FiberManager/Async helper
        return Async::parallel($tasks);
    }

    /**
     * Fire an event and call all relevant listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|object|null
     */
    public function dispatch(object|string $event, mixed $payload = [], bool $halt = false): object|array|null
    {
        // If the event is an object, we use its class name as the event name
        $eventName = is_object($event) ? get_class($event) : $event;

        if (is_object($event) && $event instanceof AsyncEventInterface && !$halt) {
            $this->dispatchAsync($event, $payload);
            return $event; // PSR-14: always return the object
        }

        if (is_object($event) && empty($payload)) {
            $payload = [$event];
        }

        if (!$this->hasListeners($eventName)) {
            return is_object($event) ? $event : null;
        }

        $start = microtime(true);
        $responses = [];

        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            $response = call_user_func_array($listener, $payload);

            if ($halt && !is_null($response)) {
                $this->recordEvent($eventName, microtime(true) - $start);
                return is_object($event) ? $event : [$response];
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;

            // Check if the event object has propagation stopped (PSR-14)
            if (is_object($event) && $event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                break;
            }
        }

        $this->recordEvent($eventName, microtime(true) - $start);

        return is_object($event) ? $event : $responses;
    }

    protected function recordEvent(string $event, float $duration): void
    {
        if (class_exists(Profiler::class)) {
            Profiler::getInstance()->recordEvent($event, $duration);
        }
    }

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget(string $event): void
    {
        $this->provider->forget($event);
    }
}
