<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;
use Plugs\Security\Auth\Authenticatable;

/**
 * Fired when a user successfully authenticates via key-based identity.
 */
class IdentityAuthenticated extends Event
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $guard = 'key',
    ) {
    }
}
