<?php

declare(strict_types=1);

namespace Plugs\Cache;

/**
 * Cache Tag Manager for resource tagging and bulk invalidation.
 * Usage: cache()->tags(['users', 'posts'])->set('key', 'value');
 */
class CacheTagManager
{
    private CacheDriverInterface $driver;
    private array $tags = [];
    private string $tagPrefix = 'tag:';

    public function __construct(CacheDriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Set tags for the next operation.
     */
    public function tags(array $tags): self
    {
        $clone = clone $this;
        $clone->tags = $tags;
        return $clone;
    }

    /**
     * Set a cached value with tags.
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        // Store the value
        $result = $this->driver->set($key, $value, $ttl);

        // Register key with each tag
        foreach ($this->tags as $tag) {
            $this->addKeyToTag($tag, $key);
        }

        return $result;
    }

    /**
     * Get a cached value.
     */
    public function get(string $key, $default = null): mixed
    {
        return $this->driver->get($key, $default);
    }

    /**
     * Flush all keys associated with given tags.
     */
    public function flushTags(array $tags): int
    {
        $flushed = 0;

        foreach ($tags as $tag) {
            $keys = $this->getTagKeys($tag);

            foreach ($keys as $key) {
                if ($this->driver->delete($key)) {
                    $flushed++;
                }
            }

            // Clear the tag registry
            $this->driver->delete($this->getTagKey($tag));
        }

        return $flushed;
    }

    /**
     * Get all keys for a tag.
     */
    public function getTagKeys(string $tag): array
    {
        $keys = $this->driver->get($this->getTagKey($tag), []);
        return is_array($keys) ? $keys : [];
    }

    /**
     * Add a key to a tag's registry.
     */
    private function addKeyToTag(string $tag, string $key): void
    {
        $tagKey = $this->getTagKey($tag);
        $keys = $this->driver->get($tagKey, []);

        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->driver->set($tagKey, $keys);
        }
    }

    /**
     * Get the storage key for a tag.
     */
    private function getTagKey(string $tag): string
    {
        return $this->tagPrefix . $tag;
    }

    /**
     * Check if a key exists.
     */
    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    /**
     * Delete a specific key.
     */
    public function delete(string $key): bool
    {
        return $this->driver->delete($key);
    }

    /**
     * Pass through other methods to driver.
     */
    public function __call(string $method, array $args)
    {
        return $this->driver->$method(...$args);
    }
}
