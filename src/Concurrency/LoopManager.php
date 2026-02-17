<?php

declare(strict_types=1);

namespace Plugs\Concurrency;

use Plugs\Concurrency\Adapters\NativeLoop;
use Plugs\Concurrency\Adapters\ReactLoop;
use Plugs\Concurrency\Adapters\SwooleLoop;
use RuntimeException;

class LoopManager
{
    protected ?LoopInterface $activeLoop = null;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get the active event loop.
     */
    public function getLoop(): LoopInterface
    {
        if ($this->activeLoop === null) {
            $this->activeLoop = $this->resolveLoop();
        }

        return $this->activeLoop;
    }

    /**
     * Resolve the best available loop driver.
     */
    protected function resolveLoop(): LoopInterface
    {
        $driver = $this->config['driver'] ?? 'auto';

        if ($driver === 'swoole' || ($driver === 'auto' && extension_loaded('swoole'))) {
            return new SwooleLoop();
        }

        if ($driver === 'react' || ($driver === 'auto' && class_exists('React\EventLoop\Loop'))) {
            return new ReactLoop();
        }

        return new NativeLoop();
    }

    /**
     * Set the active loop.
     */
    public function setLoop(LoopInterface $loop): void
    {
        $this->activeLoop = $loop;
    }

    /**
     * Dynamically call the active loop.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->getLoop()->$method(...$parameters);
    }
}
