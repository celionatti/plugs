<?php

declare(strict_types=1);

namespace Plugs\Queue;

use Plugs\Queue\Drivers\SyncQueueDriver;
use Plugs\Queue\Drivers\DatabaseQueueDriver;
use Plugs\Database\Connection;
use InvalidArgumentException;

class QueueManager
{
    protected array $drivers = [];
    protected string $defaultDriver = 'sync';

    public function __construct()
    {
        // Register default sync driver
        $this->extend('sync', function () {
            return new SyncQueueDriver();
        });

        // Register database driver
        $this->extend('database', function () {
            return new DatabaseQueueDriver(
                Connection::getInstance(),
                config('queue.connections.database.table', 'jobs'),
                config('queue.connections.database.queue', 'default')
            );
        });
    }

    public function driver(?string $name = null): QueueDriverInterface
    {
        $name = $name ?: $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Queue driver [{$name}] is not defined.");
        }

        if (is_callable($this->drivers[$name])) {
            $this->drivers[$name] = $this->drivers[$name]();
        }

        return $this->drivers[$name];
    }

    public function extend(string $name, callable $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function setDefaultDriver(string $name): void
    {
        $this->defaultDriver = $name;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->driver()->push($job, $data, $queue);
    }

    public function later(int $delay, $job, $data = '', $queue = null)
    {
        return $this->driver()->later($delay, $job, $data, $queue);
    }

    public function pop($queue = null)
    {
        return $this->driver()->pop($queue);
    }

    public function size($queue = null): int
    {
        return $this->driver()->size($queue);
    }

    /**
     * Pass dynamic methods to the default driver.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
