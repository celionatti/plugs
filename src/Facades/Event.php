<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static void listen(string $event, $listener, int $priority = 0)
 * @method static bool hasListeners(string $eventName)
 * @method static array|null dispatch($event, $payload = [], bool $halt = false)
 * @method static void forget(string $event)
 * 
 * @see \Plugs\Event\Dispatcher
 */
class Event extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'events';
    }
}
