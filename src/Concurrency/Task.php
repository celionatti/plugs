<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

use Fiber;

/**
 * Task represents a unit of work that runs in a Fiber.
 */
class Task
{
    private Fiber $fiber;

    public function __construct(callable $callback)
    {
        $this->fiber = new Fiber($callback);
    }

    public static function create(callable $callback): self
    {
        return new self($callback);
    }

    public function start(mixed ...$args): mixed
    {
        return $this->fiber->start(...$args);
    }

    public function resume(mixed $value = null): mixed
    {
        return $this->fiber->resume($value);
    }

    public function throw(\Throwable $exception): mixed
    {
        return $this->fiber->throw($exception);
    }

    public function isStarted(): bool
    {
        return $this->fiber->isStarted();
    }

    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }

    public function isRunning(): bool
    {
        return $this->fiber->isRunning();
    }

    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }

    public function getReturn(): mixed
    {
        return $this->fiber->getReturn();
    }
}
