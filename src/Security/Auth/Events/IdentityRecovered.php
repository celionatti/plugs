<?php

declare(strict_types=1);

namespace Plugs\Security\Auth\Events;

use Plugs\Event\Event;
use Plugs\Security\Auth\Authenticatable;

/**
 * Fired when a user recovers their key-based identity
 * (e.g. by regenerating prompts or re-deriving keypair).
 */
class IdentityRecovered extends Event
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $newPublicKey,
    ) {
    }
}
