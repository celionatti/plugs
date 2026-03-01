<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;

/**
 * Fired when an authentication attempt fails.
 */
class AuthFailed extends Event
{
    public function __construct(
        public readonly string $guard,
        public readonly array $credentials,
    ) {
    }
}
