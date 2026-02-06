<?php

declare(strict_types=1);

namespace Plugs\Event;

/**
 * Interface DispatcherInterface
 * 
 * Defines the contract for an event dispatcher.
 */
interface DispatcherInterface
{
    /**
     * Register an event listener with the dispatcher.
     *
     * @param string $event
     * @param callable|string $listener
     * @param int $priority
     * @return void
     */
    public function listen(string $event, $listener, int $priority = 0): void;

    /**
     * Determine if a given event has listeners.
     *
     * @param string $eventName
     * @return bool
     */
    public function hasListeners(string $eventName): bool;

    /**
     * Fire an event and call all relevant listeners.
     *
     * @param string|object $event
     * @param mixed $payload
     * @param bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], bool $halt = false): ?array;

    /**
     * Remove a set of listeners from the dispatcher.
     *
     * @param string $event
     * @return void
     */
    public function forget(string $event): void;
}
