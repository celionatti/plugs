<?php

declare(strict_types=1);

namespace Plugs\Support;

use Stringable;

/**
 * Abstract Value Object.
 * Enforces immutability and equality checks.
 */
abstract class ValueObject implements Stringable
{
    public function __construct(
        protected string $value
    ) {
    }

    abstract public function __toString(): string;

    public function equals(self $other): bool
    {
        return $this::class === $other::class && (string) $this === (string) $other;
    }

    public static function from(string $value): static
    {
        return new static($value);
    }
}
