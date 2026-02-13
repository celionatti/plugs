<?php

declare(strict_types=1);

namespace Plugs\Support;

class OpCacheManager
{
    /**
     * Check if OPcache is enabled.
     */
    public function isEnabled(): bool
    {
        return function_exists('opcache_get_status') &&
            is_array(opcache_get_status(false)) &&
            (config('opcache.enabled', true) === true);
    }

    /**
     * Clear the OPcache.
     */
    public function clear(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return opcache_reset();
    }

    /**
     * Invalidate a specific script from OPcache.
     */
    public function invalidate(string $script, bool $force = true): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return opcache_invalidate($script, $force);
    }

    /**
     * Compile a script into OPcache.
     */
    public function compile(string $script): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!file_exists($script)) {
            return false;
        }

        try {
            return opcache_compile_file($script);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get OPcache status.
     */
    public function getStatus(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return opcache_get_status(false) ?: [];
    }

    /**
     * Get OPcache configuration.
     */
    public function getConfig(): array
    {
        if (!function_exists('opcache_get_configuration')) {
            return [];
        }

        return opcache_get_configuration() ?: [];
    }
}
