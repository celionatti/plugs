<?php

declare(strict_types=1);

namespace Plugs\Concurrency\Adapters;

use Plugs\Concurrency\LoopInterface;

class NativeLoop implements LoopInterface
{
    protected bool $running = false;
    protected array $timers = [];
    protected array $readStreams = [];
    protected array $writeStreams = [];
    protected array $futureTicks = [];

    public function run(): void
    {
        $this->running = true;

        while ($this->running) {
            $this->tick();

            if (empty($this->timers) && empty($this->readStreams) && empty($this->writeStreams) && empty($this->futureTicks)) {
                $this->running = false;
            }
        }
    }

    protected function tick(): void
    {
        // Handle future ticks
        $ticks = $this->futureTicks;
        $this->futureTicks = [];
        foreach ($ticks as $callback) {
            $callback();
        }

        // Handle timers
        $now = microtime(true);
        foreach ($this->timers as $id => $timer) {
            if ($now >= $timer['at']) {
                ($timer['callback'])();
                if ($timer['periodic']) {
                    $this->timers[$id]['at'] = $now + $timer['interval'];
                } else {
                    unset($this->timers[$id]);
                }
            }
        }

        // Handle I/O
        if (!empty($this->readStreams) || !empty($this->writeStreams)) {
            $read = array_keys($this->readStreams);
            $write = array_keys($this->writeStreams);
            $except = null;

            if (@stream_select($read, $write, $except, 0, 10000) > 0) {
                foreach ($read as $stream) {
                    ($this->readStreams[(int) $stream])($stream);
                }
                foreach ($write as $stream) {
                    ($this->writeStreams[(int) $stream])($stream);
                }
            }
        } else {
            usleep(10000); // Prevent CPU pegging
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function futureTick(callable $callback): void
    {
        $this->futureTicks[] = $callback;
    }

    public function addTimer(float $interval, callable $callback): mixed
    {
        $id = uniqid();
        $this->timers[$id] = [
            'at' => microtime(true) + $interval,
            'interval' => $interval,
            'callback' => $callback,
            'periodic' => false,
        ];
        return $id;
    }

    public function addPeriodicTimer(float $interval, callable $callback): mixed
    {
        $id = uniqid();
        $this->timers[$id] = [
            'at' => microtime(true) + $interval,
            'interval' => $interval,
            'callback' => $callback,
            'periodic' => true,
        ];
        return $id;
    }

    public function cancelTimer(mixed $timer): void
    {
        unset($this->timers[$timer]);
    }

    public function addReadStream($stream, callable $callback): void
    {
        $this->readStreams[(int) $stream] = $callback;
    }

    public function addWriteStream($stream, callable $callback): void
    {
        $this->writeStreams[(int) $stream] = $callback;
    }

    public function removeReadStream($stream): void
    {
        unset($this->readStreams[(int) $stream]);
    }

    public function removeWriteStream($stream): void
    {
        unset($this->writeStreams[(int) $stream]);
    }
}
