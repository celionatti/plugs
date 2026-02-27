<?php

declare(strict_types=1);

namespace Plugs\Cache\Drivers;

use Plugs\Cache\CacheDriverInterface;

/*
|--------------------------------------------------------------------------
| Array Cache Driver (Null/Testing Driver)
|--------------------------------------------------------------------------
|
| In-memory cache that persists only for the current request lifetime.
| Ideal for testing environments where you don't want cache side effects
| but still need the cache API to work correctly.
*/

class ArrayCacheDriver implements CacheDriverInterface
{
    private array $store = [];
    private array $expirations = [];

    public function get(string $key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->store[$key] ?? $default;
    }

    public function set(string $key, $value, int|null $ttl = null): bool
    {
        $this->store[$key] = $value;

        if ($ttl !== null && $ttl > 0) {
            $this->expirations[$key] = time() + $ttl;
        } else {
            unset($this->expirations[$key]);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->store[$key], $this->expirations[$key]);

        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->store)) {
            return false;
        }

        // Check expiration
        if (isset($this->expirations[$key]) && time() > $this->expirations[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->store = [];
        $this->expirations = [];

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
}
