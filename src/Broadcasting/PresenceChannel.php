<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

/**
 * Presence Channel
 *
 * Represents a presence-enabled broadcast channel. Like Private
 * channels, users must be authenticated. Additionally, the system
 * tracks which users are currently connected and broadcasts
 * join/leave events automatically.
 *
 * The channel name is automatically prefixed with 'presence-'.
 */
class PresenceChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('presence-' . $name);
    }
}
