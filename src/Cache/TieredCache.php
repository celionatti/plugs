<?php

declare(strict_types=1);

namespace Plugs\Cache;

/**
 * Multi-tier caching with automatic promotion/demotion.
 * Tiers: Memory (fastest) → File → Redis (persistent)
 */
class TieredCache implements CacheDriverInterface
{
    private array $tiers = [];
    private array $tierNames = [];

    public function __construct(array $drivers = [])
    {
        if (empty($drivers)) {
            // Default tiers: memory → file
            $this->addTier('memory', new Drivers\MemoryCache());
            $this->addTier('file', new Drivers\FileCacheDriver());
        } else {
            foreach ($drivers as $name => $driver) {
                $this->addTier($name, $driver);
            }
        }
    }

    /**
     * Add a cache tier.
     */
    public function addTier(string $name, CacheDriverInterface $driver): self
    {
        $this->tiers[] = $driver;
        $this->tierNames[] = $name;
        return $this;
    }

    /**
     * Get from cache, checking tiers in order.
     * Promotes value to faster tiers on hit.
     */
    public function get(string $key, $default = null): mixed
    {
        $hitTierIndex = null;
        $value = null;

        // Check each tier
        foreach ($this->tiers as $index => $tier) {
            if ($tier->has($key)) {
                $value = $tier->get($key);
                $hitTierIndex = $index;
                break;
            }
        }

        if ($hitTierIndex === null) {
            return $default;
        }

        // Promote to faster tiers
        if ($hitTierIndex > 0) {
            for ($i = 0; $i < $hitTierIndex; $i++) {
                $this->tiers[$i]->set($key, $value);
            }
        }

        return $value;
    }

    /**
     * Set value in all tiers.
     */
    public function set(string $key, $value, ?int $ttl = null): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Delete from all tiers.
     */
    public function delete(string $key): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Clear all tiers.
     */
    public function clear(): bool
    {
        $success = true;
        foreach ($this->tiers as $tier) {
            if (!$tier->clear()) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Check if key exists in any tier.
     */
    public function has(string $key): bool
    {
        foreach ($this->tiers as $tier) {
            if ($tier->has($key)) {
                return true;
            }
        }
        return false;
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
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * Get tier statistics.
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->tierNames as $index => $name) {
            $stats[$name] = [
                'index' => $index,
                'driver' => get_class($this->tiers[$index]),
            ];
        }
        return $stats;
    }
}
