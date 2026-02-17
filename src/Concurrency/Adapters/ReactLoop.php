<?php

declare(strict_types=1);

namespace Plugs\Concurrency\Adapters;

use Plugs\Concurrency\LoopInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface as ReactLoopInterface;

class ReactLoop implements LoopInterface
{
    protected ReactLoopInterface $loop;

    public function __construct(?ReactLoopInterface $loop = null)
    {
        $this->loop = $loop ?: Loop::get();
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->loop->stop();
    }

    public function futureTick(callable $callback): void
    {
        $this->loop->futureTick($callback);
    }

    public function addTimer(float $interval, callable $callback): mixed
    {
        return $this->loop->addTimer($interval, $callback);
    }

    public function addPeriodicTimer(float $interval, callable $callback): mixed
    {
        return $this->loop->addPeriodicTimer($interval, $callback);
    }

    public function cancelTimer(mixed $timer): void
    {
        $this->loop->cancelTimer($timer);
    }

    public function addReadStream($stream, callable $callback): void
    {
        $this->loop->addReadStream($stream, $callback);
    }

    public function addWriteStream($stream, callable $callback): void
    {
        $this->loop->addWriteStream($stream, $callback);
    }

    public function removeReadStream($stream): void
    {
        $this->loop->removeReadStream($stream);
    }

    public function removeWriteStream($stream): void
    {
        $this->loop->removeWriteStream($stream);
    }
}
