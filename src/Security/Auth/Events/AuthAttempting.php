<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;

/**
 * Fired before credential validation is attempted.
 */
class AuthAttempting extends Event
{
    public function __construct(
        public readonly string $guard,
        public readonly array $credentials,
        public readonly bool $remember = false,
    ) {
    }
}
