<?php

declare(strict_types=1);

if (!function_exists('cache')) {
    /**
     * Simple file-based cache function for production
     */
    function cache($key = null, $value = null, int $ttl = 3600)
    {
        static $cacheDir = null;

        if ($cacheDir === null) {
            $cacheDir = sys_get_temp_dir() . '/plugs-cache';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
        }

        // No arguments - return cache instance info
        if ($key === null) {
            return ['cache_dir' => $cacheDir];
        }

        // Array of key-value pairs to set
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                _cache_set($cacheDir, $k, $v, $ttl);
            }
            return true;
        }

        // Get value if no value provided
        if ($value === null) {
            return _cache_get($cacheDir, $key);
        }

        // Set value with TTL
        return _cache_set($cacheDir, $key, $value, $ttl);
    }
}

if (!function_exists('_cache_get')) {
    function _cache_get(string $cacheDir, string $key)
    {
        $file = $cacheDir . '/' . md5($key) . '.cache';

        if (!file_exists($file)) {
            return null;
        }

        $data = unserialize(file_get_contents($file));

        if ($data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }
}

if (!function_exists('_cache_set')) {
    function _cache_set(string $cacheDir, string $key, $value, int $ttl): bool
    {
        $file = $cacheDir . '/' . md5($key) . '.cache';

        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }
}

if (!function_exists('cache_has')) {
    function cache_has(string $key): bool
    {
        return cache($key) !== null;
    }
}

if (!function_exists('cache_forget')) {
    function cache_forget(string $key): bool
    {
        $cacheDir = sys_get_temp_dir() . '/plugs-cache';
        $file = $cacheDir . '/' . md5($key) . '.cache';

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }
}

if (!function_exists('cache_flush')) {
    function cache_flush(): bool
    {
        $cacheDir = sys_get_temp_dir() . '/plugs-cache';
        $files = glob($cacheDir . '/*.cache');

        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }

        return $success;
    }
}

if (!function_exists('cache_remember')) {
    function cache_remember(string $key, callable $callback, int $ttl = 3600)
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
