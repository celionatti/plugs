<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;
use Plugs\Security\Auth\Authenticatable;

/**
 * Fired when a user logs out.
 */
class LogoutOccurred extends Event
{
    public function __construct(
        public readonly string $guard,
        public readonly ?Authenticatable $user = null,
    ) {
    }
}
