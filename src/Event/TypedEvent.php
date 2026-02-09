<?php

declare(strict_types=1);

namespace Plugs\Event;

/**
 * Base class for Typed Events.
 * Listeners can type-hint specific event classes instead of generic Event objects.
 */
abstract class TypedEvent extends Event
{
    public function __construct(
        public readonly string $name = self::class
    ) {
    }
}
