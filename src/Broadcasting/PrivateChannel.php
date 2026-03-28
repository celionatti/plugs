<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

/**
 * Private Channel
 *
 * Represents an authenticated broadcast channel. Users must
 * be authorized via /broadcasting/auth before they can subscribe.
 *
 * The channel name is automatically prefixed with 'private-'.
 */
class PrivateChannel extends Channel
{
    public function __construct(string $name)
    {
        parent::__construct('private-' . $name);
    }
}
