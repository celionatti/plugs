<?php

declare(strict_types = 1)
;

namespace Plugs\Cache;

use InvalidArgumentException;
use Plugs\Cache\TieredCache;
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

        $driver = $this->drivers[$name];

        // Ensure we handle nested callables until we get an instance or reach an uncallable type.
        while (is_callable($driver) && !$driver instanceof CacheDriverInterface) {
            $driver = $driver();
        }

        // Cache the resolved instantiated driver back
        $this->drivers[$name] = $driver;

        if (!$driver instanceof CacheDriverInterface) {
            $type = is_object($driver) ? get_class($driver) : gettype($driver);
            throw new InvalidArgumentException("Cache driver [{$name}] must resolve to an instance of CacheDriverInterface. {$type} returned.");
        }

        return $driver;
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
        $ttl = $this->applyJitter($ttl);
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
     * Apply a small random jitter to the TTL to prevent thundering herds.
     */
    protected function applyJitter(int|null $ttl): int|null
    {
        if ($ttl === null || $ttl <= 0) {
            return $ttl;
        }

        $jitterFactor = config('cache.jitter', 0.1); // Default 10%
        $jitter = (int)($ttl * $jitterFactor);

        if ($jitter < 1) {
            return $ttl;
        }

        return $ttl + rand(-$jitter, $jitter);
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     * Uses atomic locking to prevent cache stampedes.
     *
     * @param string   $key      Cache key
     * @param int|null $ttl      Time-to-live in seconds
     * @param callable $callback Called on cache miss — its return value is cached
     * @return mixed
     */
    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        // Cache miss: attempt to acquire a lock
        $lockKey = "lock:{$key}";
        $lockTtl = 10; // 10 seconds should be enough for most computations

        if ($this->acquireLock($lockKey, $lockTtl)) {
            try {
                // Re-check cache after acquiring lock (double-checked locking)
                $value = $this->get($key);
                if ($value !== null) {
                    return $value;
                }

                $value = $callback();
                $this->set($key, $value, $ttl);

                return $value;
            }
            finally {
                $this->releaseLock($lockKey);
            }
        }

        // Failed to acquire lock: another process is already calculating it.
        // Wait a bit and try again, or return stale if we had one (not implemented here)
        $attempts = 0;
        while ($attempts < 5) {
            usleep(250000); // Wait 250ms
            $value = $this->get($key);
            if ($value !== null) {
                return $value;
            }
            $attempts++;
        }

        // Last resort: just run the callback if the lock held too long
        return $callback();
    }

    /**
     * Simple atomic lock using the cache driver's set() method.
     */
    protected function acquireLock(string $key, int $ttl): bool
    {
        $driver = $this->driver();

        // If driver supports explicit locking (like Redis), use it
        if (method_exists($driver, 'set')) {
            // For general drivers, we might need a way to check if 'NX' (not exists) is possible.
            // Since we don't have a clean way currently, we'll check if 'has' then 'set'.
            // WARNING: This is NOT fully atomic without NX support, but better than nothing.
            // If it's Redis, we should ideally use NX.
            if ($driver instanceof \Plugs\Cache\Drivers\RedisCacheDriver) {
                return $driver->getConnection()->set($key, '1', ['nx', 'ex' => $ttl]);
            }
        }

        if ($this->has($key)) {
            return false;
        }

        return $this->set($key, '1', $ttl);
    }

    protected function releaseLock(string $key): void
    {
        $this->delete($key);
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
