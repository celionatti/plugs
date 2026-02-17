<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

interface LoopInterface
{
    /**
     * Run the event loop.
     */
    public function run(): void;

    /**
     * Stop the event loop.
     */
    public function stop(): void;

    /**
     * Execute a callback in the next tick.
     */
    public function futureTick(callable $callback): void;

    /**
     * Add a one-off timer.
     */
    public function addTimer(float $interval, callable $callback): mixed;

    /**
     * Add a periodic timer.
     */
    public function addPeriodicTimer(float $interval, callable $callback): mixed;

    /**
     * Cancel a timer.
     */
    public function cancelTimer(mixed $timer): void;

    /**
     * Add a stream reader.
     */
    public function addReadStream($stream, callable $callback): void;

    /**
     * Add a stream writer.
     */
    public function addWriteStream($stream, callable $callback): void;

    /**
     * Remove a stream reader.
     */
    public function removeReadStream($stream): void;

    /**
     * Remove a stream writer.
     */
    public function removeWriteStream($stream): void;
}
