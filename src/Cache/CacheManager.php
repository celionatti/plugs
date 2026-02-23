<?php

declare(strict_types=1);

namespace Plugs\Cache;

use InvalidArgumentException;
use Plugs\Cache\Drivers\FileCacheDriver;
use Plugs\Cache\Drivers\MemoryCache;

class CacheManager
{
    private array $drivers = [];
    private string $defaultDriver = 'tiered';

    public function __construct()
    {
        $this->defaultDriver = config('cache.default', 'tiered');

        // Register tiered driver (memory → file) as default
        $this->extend('tiered', function () {
            return new TieredCache([
                'memory' => new MemoryCache(),
                'file' => new FileCacheDriver(),
            ]);
        });

        // Keep file driver available for explicit use
        $this->extend('file', function () {
            return new FileCacheDriver();
        });

        // Memory-only driver
        $this->extend('memory', function () {
            return new MemoryCache();
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
        $value = $this->driver()->get($key, $default);

        $this->recordAccess($key, $value !== $default);

        return $value;
    }

    protected function recordAccess(string $key, bool $hit): void
    {
        if (class_exists(\Plugs\Debug\Profiler::class)) {
            \Plugs\Debug\Profiler::getInstance()->recordCache($key, $hit);
        }
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

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string   $key      Cache key
     * @param int|null $ttl      Time-to-live in seconds
     * @param callable $callback Called on cache miss — its return value is cached
     * @return mixed
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result forever.
     *
     * @param string   $key      Cache key
     * @param callable $callback Called on cache miss — its return value is cached
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
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
