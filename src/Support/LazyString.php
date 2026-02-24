<?php

declare(strict_types=1);

namespace Plugs\Support;

use JsonSerializable;
use Stringable;
use Closure;

/**
 * A string wrapper that defers its resolution until it's actually used.
 * Useful for parallelizing I/O (like AI calls) with other processing.
 */
class LazyString implements Stringable, JsonSerializable
{
    private ?string $resolvedValue = null;
    private ?Closure $resolver;

    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Resolve the value if not already done.
     */
    protected function resolve(): string
    {
        if ($this->resolvedValue === null && $this->resolver !== null) {
            $value = ($this->resolver)();
            $this->resolvedValue = (string) $value;
            $this->resolver = null; // Free memory
        }

        return $this->resolvedValue ?? '';
    }

    public function __toString(): string
    {
        return $this->resolve();
    }

    public function jsonSerialize(): mixed
    {
        return $this->resolve();
    }
}
