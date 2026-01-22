<?php

declare(strict_types=1);

if (!function_exists('cache')) {
    /**
     * Get / set cache value or get the cache manager
     */
    function cache($key = null, $value = null, int|null $ttl = null)
    {
        $cache = app('cache');

        if ($key === null) {
            return $cache;
        }

        if (is_array($key)) {
            return $cache->setMultiple($key, $value); // value here acts as optional ttl for multiples
        }

        if ($value === null) {
            return $cache->get($key);
        }

        return $cache->set($key, $value, $ttl);
    }
}

if (!function_exists('cache_has')) {
    function cache_has(string $key): bool
    {
        return app('cache')->has($key);
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): bool
    {
        return app('cache')->delete($key);
    }
}

if (!function_exists('cache_flush')) {
    function cache_flush(): bool
    {
        return app('cache')->clear();
    }
}

if (!function_exists('cache_remember')) {
    function cache_remember(string $key, callable $callback, int|null $ttl = null)
    {
        $value = cache($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        cache($key, $value, $ttl);

        return $value;
    }
}
