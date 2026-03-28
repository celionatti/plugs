<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

/**
 * Channel Value Object
 *
 * Represents a public broadcast channel. Anyone can subscribe
 * without authentication.
 */
class Channel
{
    public function __construct(public readonly string $name)
    {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
