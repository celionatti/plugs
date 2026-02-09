<?php

declare(strict_types=1);

namespace Plugs\Cache;

/**
 * Cache Warmer to pre-populate caches on deploy.
 */
class CacheWarmer
{
    private array $warmers = [];
    private CacheDriverInterface $cache;

    public function __construct(?CacheDriverInterface $cache = null)
    {
        $this->cache = $cache ?? new Drivers\FileCacheDriver();
    }

    /**
     * Register a warmer.
     *
     * @param string $name Warmer name
     * @param callable $callback Function that returns [key => value, ...]
     */
    public function register(string $name, callable $callback): self
    {
        $this->warmers[$name] = $callback;
        return $this;
    }

    /**
     * Run all warmers.
     */
    public function warmAll(): array
    {
        $results = [];

        foreach ($this->warmers as $name => $callback) {
            $results[$name] = $this->warm($name);
        }

        return $results;
    }

    /**
     * Run a specific warmer.
     */
    public function warm(string $name): array
    {
        if (!isset($this->warmers[$name])) {
            return ['error' => "Warmer '{$name}' not found"];
        }

        $start = microtime(true);
        $count = 0;

        try {
            $data = call_user_func($this->warmers[$name]);

            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    $ttl = $value['ttl'] ?? null;
                    $val = $value['value'] ?? $value;

                    $this->cache->set($key, $val, $ttl);
                    $count++;
                }
            }

            return [
                'name' => $name,
                'keys_warmed' => $count,
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
                'status' => 'success',
            ];

        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ];
        }
    }

    /**
     * Get registered warmer names.
     */
    public function getWarmerNames(): array
    {
        return array_keys($this->warmers);
    }

    /**
     * Create default warmers.
     */
    public static function withDefaults(?CacheDriverInterface $cache = null): self
    {
        $warmer = new self($cache);

        // Config warmer
        $warmer->register('config', function () {
            $configs = [];
            $configPath = base_path('config');

            if (is_dir($configPath)) {
                foreach (glob($configPath . '/*.php') as $file) {
                    $name = basename($file, '.php');
                    $configs["config:{$name}"] = [
                        'value' => require $file,
                        'ttl' => 3600,
                    ];
                }
            }

            return $configs;
        });

        // Routes warmer
        $warmer->register('routes', function () {
            // Cache compiled route patterns
            return [
                'routes:compiled' => [
                    'value' => 'routes_cached',
                    'ttl' => 3600,
                ],
            ];
        });

        return $warmer;
    }
}
