<?php

declare(strict_types=1);

namespace Plugs\Event;

use InvalidArgumentException;
use Plugs\Container\Container;
use Psr\EventDispatcher\ListenerProviderInterface;

class ListenerProvider implements ListenerProviderInterface
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

    public function __construct(?Container $container = null)
    {
        $this->container = $container ?: Container::getInstance();
    }

    /**
     * Register an event listener with the provider.
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
     */
    public function hasListeners(string $eventName): bool
    {
        return isset($this->listeners[$eventName]);
    }

    /**
     * Remove a set of listeners from the provider.
     */
    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * PSR-14: Get all relevant listeners for a given event object.
     * 
     * We also support getting listeners by string name to maintain backward compatibility.
     *
     * @param object|string $event
     * @return iterable<callable>
     */
    public function getListenersForEvent(object|string $event): iterable
    {
        $eventName = is_object($event) ? get_class($event) : $event;

        if (!$this->hasListeners($eventName)) {
            return [];
        }

        $listeners = $this->listeners[$eventName];
        krsort($listeners);

        $merged = array_merge(...$listeners);

        $resolved = [];
        foreach ($merged as $listener) {
            $resolved[] = $this->resolveListener($listener);
        }

        return $resolved;
    }

    /**
     * Resolve a string-based listener.
     */
    protected function resolveListener($listener): callable
    {
        if (is_callable($listener)) {
            return $listener;
        }

        if (is_string($listener)) {
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
        }

        throw new InvalidArgumentException("Listener is not a valid callable.");
    }
}
