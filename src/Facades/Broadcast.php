<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * Broadcast Facade
 *
 * Provides a static interface to the BroadcastManager.
 *
 * @method static void channel(string $pattern, \Closure $callback)
 * @method static bool|array authorize(object $user, string $channel)
 * @method static void broadcast(\Plugs\Broadcasting\ShouldBroadcast $event)
 * @method static array|null authorizeAndSign(object $user, string $channel)
 *
 * @see \Plugs\Broadcasting\BroadcastManager
 */
class Broadcast extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'broadcast';
    }

    /**
     * Resolve the facade root instance.
     *
     * Since BroadcastManager uses a singleton pattern, we resolve it directly.
     */
    protected static function resolveFacadeInstance($name)
    {
        return \Plugs\Broadcasting\BroadcastManager::getInstance();
    }
}
