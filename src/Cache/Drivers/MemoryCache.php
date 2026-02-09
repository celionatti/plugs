<?php

declare(strict_types=1);

namespace Plugs\Cache\Drivers;

use Plugs\Cache\CacheDriverInterface;

/**
 * In-memory cache driver (per-request).
 * Fastest tier, not persistent across requests.
 */
class MemoryCache implements CacheDriverInterface
{
    private static array $cache = [];
    private static array $expiry = [];

    public function get(string $key, $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }
        return self::$cache[$key];
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        self::$cache[$key] = $value;
        if ($ttl !== null) {
            self::$expiry[$key] = time() + $ttl;
        } else {
            unset(self::$expiry[$key]);
        }
        return true;
    }

    public function delete(string $key): bool
    {
        unset(self::$cache[$key], self::$expiry[$key]);
        return true;
    }

    public function clear(): bool
    {
        self::$cache = [];
        self::$expiry = [];
        return true;
    }

    public function has(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        // Check expiry
        if (isset(self::$expiry[$key]) && time() > self::$expiry[$key]) {
            $this->delete($key);
            return false;
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

    public function setMultiple(iterable $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
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
}
