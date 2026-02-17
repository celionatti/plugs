<?php

declare(strict_types=1);

namespace Plugs\Concurrency\Adapters;

use Plugs\Concurrency\LoopInterface;
use Swoole\Event;
use Swoole\Timer;

class SwooleLoop implements LoopInterface
{
    public function run(): void
    {
        // Swoole loop is usually started by the Server or implicitly
        if (extension_loaded('swoole')) {
            Event::wait();
        }
    }

    public function stop(): void
    {
        Event::exit();
    }

    public function futureTick(callable $callback): void
    {
        Event::defer($callback);
    }

    public function addTimer(float $interval, callable $callback): mixed
    {
        return Timer::after((int) ($interval * 1000), $callback);
    }

    public function addPeriodicTimer(float $interval, callable $callback): mixed
    {
        return Timer::tick((int) ($interval * 1000), $callback);
    }

    public function cancelTimer(mixed $timer): void
    {
        Timer::clear($timer);
    }

    public function addReadStream($stream, callable $callback): void
    {
        Event::add($stream, $callback, null, SWOOLE_EVENT_READ);
    }

    public function addWriteStream($stream, callable $callback): void
    {
        Event::add($stream, null, $callback, SWOOLE_EVENT_WRITE);
    }

    public function removeReadStream($stream): void
    {
        Event::del($stream);
    }

    public function removeWriteStream($stream): void
    {
        Event::del($stream);
    }
}
