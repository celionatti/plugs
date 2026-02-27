<?php

declare(strict_types=1);

namespace Plugs\Cache\Drivers;

use Plugs\Cache\CacheDriverInterface;

/*
|--------------------------------------------------------------------------
| Redis Cache Driver
|--------------------------------------------------------------------------
|
| Production-grade cache driver using Redis. Supports tags via prefixed
| keys and atomic increment/decrement operations.
|
| Requires the phpredis extension.
*/

class RedisCacheDriver implements CacheDriverInterface
{
    private \Redis $redis;
    private string $prefix;

    public function __construct(?array $config = null)
    {
        $config = $config ?? [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => (int) env('REDIS_CACHE_DB', 1),
            'prefix' => env('CACHE_PREFIX', 'plugs_cache:'),
        ];

        $this->prefix = $config['prefix'] ?? 'plugs_cache:';
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select($config['database']);
        }
    }

    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, $value, int|null $ttl = null): bool
    {
        $serialized = is_string($value) ? $value : json_encode($value);

        if ($ttl !== null && $ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }

        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function clear(): bool
    {
        // Only flush keys with our prefix to avoid destroying other data
        $iterator = null;
        $pattern = $this->prefix . '*';

        while ($keys = $this->redis->scan($iterator, $pattern, 100)) {
            $this->redis->del(...$keys);
        }

        return true;
    }

    public function getMultiple(iterable $keys, $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }

        return $results;
    }

    public function setMultiple(iterable $values, int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Increment a cached value atomically.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->redis->incrBy($this->prefix . $key, $value);
    }

    /**
     * Decrement a cached value atomically.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->redis->decrBy($this->prefix . $key, $value);
    }

    /**
     * Get the underlying Redis connection.
     */
    public function getConnection(): \Redis
    {
        return $this->redis;
    }
}
