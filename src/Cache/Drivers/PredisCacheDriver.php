<?php

declare(strict_types=1);

namespace Plugs\Cache\Drivers;

use Plugs\Cache\CacheDriverInterface;
use Predis\Client;

/*
|--------------------------------------------------------------------------
| Predis Cache Driver
|--------------------------------------------------------------------------
|
| A pure PHP implementation of the Redis driver. This is useful when
| the phpredis extension is not available.
|
| Requires: composer require predis/predis
*/

class PredisCacheDriver implements CacheDriverInterface
{
    private Client $redis;
    private string $prefix;

    public function __construct(?array $config = null)
    {
        if (!class_exists(\Predis\Client::class)) {
            throw new \RuntimeException("Predis is not installed. Please run 'composer require predis/predis'.");
        }

        $config = $config ?? [
            'scheme' => 'tcp',
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
            'database' => (int) env('REDIS_CACHE_DB', 1),
            'prefix' => env('CACHE_PREFIX', 'plugs_cache:'),
        ];

        $this->prefix = $config['prefix'] ?? 'plugs_cache:';

        $this->redis = new Client([
            'scheme' => $config['scheme'] ?? 'tcp',
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
            'database' => $config['database'],
        ]);
    }

    public function get(string $key, $default = null)
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === null) {
            return $default;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, $value, int|null $ttl = null): bool
    {
        $serialized = is_string($value) ? $value : json_encode($value);

        if ($ttl !== null && $ttl > 0) {
            $response = $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        } else {
            $response = $this->redis->set($this->prefix . $key, $serialized);
        }

        return $response->getPayload() === 'OK';
    }

    public function delete(string $key): bool
    {
        return $this->redis->del([$this->prefix . $key]) > 0;
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis->exists($this->prefix . $key);
    }

    public function clear(): bool
    {
        $iterator = null;
        $pattern = $this->prefix . '*';

        // Predis uses a different approach for scan compared to phpredis
        $keys = $this->redis->keys($pattern);

        if (!empty($keys)) {
            $this->redis->del($keys);
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
        $prefixedKeys = [];
        foreach ($keys as $key) {
            $prefixedKeys[] = $this->prefix . $key;
        }

        if (!empty($prefixedKeys)) {
            $this->redis->del($prefixedKeys);
        }

        return true;
    }

    /**
     * Increment a cached value atomically.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        return $this->redis->incrby($this->prefix . $key, $value);
    }

    /**
     * Decrement a cached value atomically.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        return $this->redis->decrby($this->prefix . $key, $value);
    }

    /**
     * Get the underlying Predis client.
     */
    public function getConnection(): Client
    {
        return $this->redis;
    }
}
