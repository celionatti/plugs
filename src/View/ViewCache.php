<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| ViewCache Class
|--------------------------------------------------------------------------
|
| Manages view block caching for improved rendering performance.
| Supports PSR-16 compatible cache implementations and file-based caching.
|
| @package Plugs\View
*/

class ViewCache
{
    /**
     * Cache instance (PSR-16 compatible or any cache with get/set/delete/has/clear methods)
     * @var object|null
     */
    private ?object $cache = null;

    /**
     * File-based cache directory
     */
    private string $cacheDir;

    /**
     * Default cache TTL in seconds
     */
    private int $defaultTtl = 3600;

    /**
     * In-memory cache for current request
     */
    private array $memoryCache = [];

    /**
     * Cache statistics
     */
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
    ];

    /**
     * Create a new ViewCache instance
     *
     * @param string $cacheDir Directory for file-based caching
     * @param object|null $cache Optional PSR-16 cache instance (any object with get/set/delete/has/clear methods)
     */
    public function __construct(string $cacheDir, ?object $cache = null)
    {
        $this->cacheDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'blocks';
        $this->cache = $cache;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Set default TTL for cached blocks
     *
     * @param int $seconds
     * @return self
     */
    public function setDefaultTtl(int $seconds): self
    {
        $this->defaultTtl = $seconds;

        return $this;
    }

    /**
     * Get cached block content
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->normalizeKey($key);

        // Check memory cache first
        if (isset($this->memoryCache[$cacheKey])) {
            $this->stats['hits']++;

            return $this->memoryCache[$cacheKey];
        }

        // Try PSR-16 cache
        if ($this->cache !== null) {
            $value = $this->cache->get($cacheKey);
            if ($value !== null) {
                $this->stats['hits']++;
                $this->memoryCache[$cacheKey] = $value;

                return $value;
            }
        }

        // Try file cache
        $value = $this->getFromFile($cacheKey);
        if ($value !== null) {
            $this->stats['hits']++;
            $this->memoryCache[$cacheKey] = $value;

            return $value;
        }

        $this->stats['misses']++;

        return $default;
    }

    /**
     * Check if cache key exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $cacheKey = $this->normalizeKey($key);

        if (isset($this->memoryCache[$cacheKey])) {
            return true;
        }

        if ($this->cache !== null && $this->cache->has($cacheKey)) {
            return true;
        }

        return $this->fileExists($cacheKey);
    }

    /**
     * Store content in cache
     *
     * @param string $key Cache key
     * @param string $content Content to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool
     */
    public function put(string $key, string $content, ?int $ttl = null): bool
    {
        $cacheKey = $this->normalizeKey($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $this->memoryCache[$cacheKey] = $content;
        $this->stats['writes']++;

        // Store in PSR-16 cache
        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $content, $ttl);
        }

        // Store in file cache
        return $this->putToFile($cacheKey, $content, $ttl);
    }

    /**
     * Remove item from cache
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool
    {
        $cacheKey = $this->normalizeKey($key);

        unset($this->memoryCache[$cacheKey]);

        if ($this->cache !== null) {
            $this->cache->delete($cacheKey);
        }

        return $this->deleteFile($cacheKey);
    }

    /**
     * Clear all cached content
     *
     * @return bool
     */
    public function flush(): bool
    {
        $this->memoryCache = [];

        if ($this->cache !== null) {
            $this->cache->clear();
        }

        return $this->clearFiles();
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Get or set cached content using callback
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate content
     * @param int|null $ttl Time to live
     * @return string
     */
    public function remember(string $key, callable $callback, ?int $ttl = null): string
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $content = $callback();
        $this->put($key, $content, $ttl);

        return $content;
    }

    /**
     * Cache content forever
     *
     * @param string $key
     * @param string $content
     * @return bool
     */
    public function forever(string $key, string $content): bool
    {
        return $this->put($key, $content, 31536000); // 1 year
    }

    /**
     * Tag-based cache operations
     *
     * @param string|array $tags
     * @return TaggedCache
     */
    public function tags(string|array $tags): TaggedCache
    {
        return new TaggedCache($this, (array) $tags);
    }

    // ============================================
    // PRIVATE METHODS
    // ============================================

    /**
     * Normalize cache key
     */
    private function normalizeKey(string $key): string
    {
        return 'view_block_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }

    /**
     * Get file path for cache key
     */
    private function getFilePath(string $key): string
    {
        return $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    /**
     * Get content from file cache
     */
    private function getFromFile(string $key): ?string
    {
        $path = $this->getFilePath($key);

        if (!file_exists($path)) {
            return null;
        }

        $data = @unserialize(file_get_contents($path));

        if (!is_array($data) || !isset($data['content'], $data['expires'])) {
            @unlink($path);

            return null;
        }

        // Check expiration
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($path);

            return null;
        }

        return $data['content'];
    }

    /**
     * Store content to file cache
     */
    private function putToFile(string $key, string $content, int $ttl): bool
    {
        $path = $this->getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;

        $data = serialize([
            'content' => $content,
            'expires' => $expires,
            'created' => time(),
        ]);

        return file_put_contents($path, $data, LOCK_EX) !== false;
    }

    /**
     * Check if cache file exists
     */
    private function fileExists(string $key): bool
    {
        return file_exists($this->getFilePath($key));
    }

    /**
     * Delete cache file
     */
    private function deleteFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    /**
     * Clear all cache files
     */
    private function clearFiles(): bool
    {
        $files = glob($this->cacheDir . DIRECTORY_SEPARATOR . '*.cache');

        foreach ($files as $file) {
            @unlink($file);
        }

        return true;
    }
}

/**
 * Tagged Cache Support
 */
class TaggedCache
{
    private ViewCache $cache;
    private array $tags;

    public function __construct(ViewCache $cache, array $tags)
    {
        $this->cache = $cache;
        $this->tags = $tags;
    }

    /**
     * Get tagged cache key
     */
    private function taggedKey(string $key): string
    {
        return implode(':', $this->tags) . ':' . $key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->taggedKey($key), $default);
    }

    public function put(string $key, string $content, ?int $ttl = null): bool
    {
        return $this->cache->put($this->taggedKey($key), $content, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->taggedKey($key));
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): string
    {
        return $this->cache->remember($this->taggedKey($key), $callback, $ttl);
    }

    /**
     * Flush all items with these tags
     */
    public function flush(): bool
    {
        // Note: This is a simplified implementation
        // A full implementation would track tagged keys
        return false;
    }
}
