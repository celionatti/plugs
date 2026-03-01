<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;
use Plugs\Security\Auth\Authenticatable;

/**
 * Fired after a user is successfully authenticated.
 */
class AuthSucceeded extends Event
{
    public function __construct(
        public readonly string $guard,
        public readonly Authenticatable $user,
        public readonly bool $remember = false,
    ) {
    }
}
