<?php

declare(strict_types=1);

namespace Plugs\Cache;

use InvalidArgumentException;
use Plugs\Cache\Drivers\FileCacheDriver;

class CacheManager
{
    private array $drivers = [];
    private string $defaultDriver = 'file';

    public function __construct()
    {
        $this->defaultDriver = config('cache.default', 'file');

        // Register default file driver
        $this->extend('file', function () {
            return new FileCacheDriver();
        });
    }

    public function driver(string $name = null): CacheDriverInterface
    {
        $name = $name ?: $this->defaultDriver;

        if (!isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Cache driver [{$name}] is not defined.");
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

    public function get(string $key, $default = null)
    {
        return $this->driver()->get($key, $default);
    }

    public function set(string $key, $value, int|null $ttl = null): bool
    {
        return $this->driver()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->driver()->delete($key);
    }

    public function clear(): bool
    {
        return $this->driver()->clear();
    }

    public function has(string $key): bool
    {
        return $this->driver()->has($key);
    }

    public function getMultiple(iterable $keys, $default = null): iterable
    {
        return $this->driver()->getMultiple($keys, $default);
    }

    public function setMultiple(iterable $values, int|null $ttl = null): bool
    {
        return $this->driver()->setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        return $this->driver()->deleteMultiple($keys);
    }

    /**
     * Pass dynamic methods to the default driver.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->$method(...$parameters);
    }
}
