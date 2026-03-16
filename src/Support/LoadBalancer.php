<?php

declare(strict_types=1);

namespace Plugs\Support;

use Plugs\Facade;
use RuntimeException;
use Throwable;

/**
 * Generic Load Balancer
 * Handles host selection using various strategies and health management via cache.
 */
class LoadBalancer
{
    /** @var array Normalized host entries */
    protected array $hosts = [];

    /** @var string Distribution strategy */
    protected string $strategy;

    /** @var array Configuration options */
    protected array $options;

    /** @var float Cooldown period in seconds before retrying a down host */
    protected float $healthCheckCooldown;

    /** @var int Number of failures before marking a host as down */
    protected int $maxFailures;

    /** @var string Cache prefix for health state */
    protected string $cachePrefix;

    /**
     * @param array  $hosts    Array of host strings, or arrays with 'host', 'port', 'weight' keys
     * @param string $strategy One of 'random', 'round-robin', 'weighted', 'least-connections'
     * @param array  $options  Optional: 'health_check_cooldown', 'max_failures', 'cache_prefix'
     */
    public function __construct(array $hosts, string $strategy = 'random', array $options = [])
    {
        $this->hosts = $this->normalizeHosts($hosts);
        $this->strategy = $strategy;
        $this->options = $options;

        $this->healthCheckCooldown = (float) ($options['health_check_cooldown'] ?? 30);
        $this->maxFailures = (int) ($options['max_failures'] ?? 3);
        $this->cachePrefix = $options['cache_prefix'] ?? 'lb:health:';
    }

    /**
     * Select a host based on the configured strategy.
     *
     * @return array The selected host entry
     * @throws RuntimeException If all hosts are down
     */
    public function select(): array
    {
        $availableHosts = $this->getAvailableHosts();

        if (empty($availableHosts)) {
            $msg = "All hosts are down. Hosts: " . $this->formatDownHosts();
            throw new RuntimeException($msg);
        }

        switch ($this->strategy) {
            case 'round-robin':
                return $this->selectRoundRobin($availableHosts);
            case 'weighted':
                return $this->selectWeighted($availableHosts);
            case 'least-connections':
                return $this->selectLeastConnections($availableHosts);
            case 'random':
            default:
                return $availableHosts[array_rand($availableHosts)];
        }
    }

    /**
     * Select a host with automatic failover.
     *
     * @param callable $testCallback Returns mixed result on success, or false/throws on failure
     * @return array ['host_entry' => array, 'result' => mixed]
     * @throws RuntimeException If all hosts fail
     */
    public function selectWithFailover(callable $testCallback): array
    {
        $availableHosts = $this->getAvailableHosts();
        
        // If we have multiple hosts, we shuffle them to try in different order for failover
        // except for round-robin where we want the next one in sequence first.
        if ($this->strategy !== 'round-robin') {
            shuffle($availableHosts);
        }

        foreach ($availableHosts as $entry) {
            $key = $this->hostKey($entry);
            try {
                $result = $testCallback($entry);
                if ($result !== false) {
                    $this->recordSuccess($key);
                    return ['host_entry' => $entry, 'result' => $result];
                }
            } catch (Throwable $e) {
                // Log/handle error internally if needed
            }

            $this->recordFailure($key);
        }

        throw new RuntimeException("All hosts failed during selection.");
    }

    /**
     * Generate a unique key for a host entry.
     */
    public function hostKey(array $entry): string
    {
        $host = $entry['host'];
        $port = $entry['port'] ?? null;
        return $port ? "{$host}:{$port}" : $host;
    }

    /**
     * Record a success for a host (potentially recovering it).
     */
    public function recordSuccess(string $hostKey): void
    {
        $cache = Facade::getFacadeInstance('cache');
        if (!$cache) return;

        $stateKey = $this->cachePrefix . $hostKey;
        $cache->delete($stateKey);
    }

    /**
     * Record a failure for a host.
     */
    public function recordFailure(string $hostKey): void
    {
        $cache = Facade::getFacadeInstance('cache');
        if (!$cache) return;

        $stateKey = $this->cachePrefix . $hostKey;
        $state = $cache->get($stateKey, ['failures' => 0, 'down_at' => null]);

        $state['failures']++;
        if ($state['failures'] >= $this->maxFailures && $state['down_at'] === null) {
            $state['down_at'] = microtime(true);
        }

        $cache->set($stateKey, $state, 3600);
    }

    /**
     * Explicitly mark a host as down.
     */
    public function markDown(string $hostKey): void
    {
        $cache = Facade::getFacadeInstance('cache');
        if (!$cache) return;

        $stateKey = $this->cachePrefix . $hostKey;
        $cache->set($stateKey, [
            'failures' => $this->maxFailures,
            'down_at' => microtime(true)
        ], 3600);
    }

    /**
     * Check if a host is currently marked as down.
     */
    public function isDown(string $hostKey): bool
    {
        $state = $this->getHealthState($hostKey);
        
        if ($state['down_at'] === null) {
            return false;
        }

        // Check if cooldown has expired
        if ((microtime(true) - $state['down_at']) > $this->healthCheckCooldown) {
            return false;
        }

        return true;
    }

    /**
     * Get the current health state of a host from cache.
     */
    protected function getHealthState(string $hostKey): array
    {
        $cache = Facade::getFacadeInstance('cache');
        if (!$cache) {
            return ['failures' => 0, 'down_at' => null];
        }

        return $cache->get($this->cachePrefix . $hostKey, ['failures' => 0, 'down_at' => null]);
    }

    /**
     * Get the max failures before marking a host as down.
     */
    public function getMaxFailures(): int
    {
        return $this->maxFailures;
    }

    /**
     * Get the health check cooldown period.
     */
    public function getHealthCheckCooldown(): int
    {
        return $this->healthCheckCooldown;
    }

    /**
     * Get all hosts that are not currently down.
     */
    protected function getAvailableHosts(): array
    {
        return array_filter($this->hosts, function ($entry) {
            return !$this->isDown($this->hostKey($entry));
        });
    }

    /**
     * Strategy: Round-Robin
     */
    protected function selectRoundRobin(array $availableHosts): array
    {
        $cache = Facade::getFacadeInstance('cache');
        $indexKey = $this->cachePrefix . 'index:' . md5(serialize($this->hosts));
        $currentIndex = $cache ? (int) $cache->get($indexKey, 0) : 0;

        $count = count($this->hosts);
        for ($i = 0; $i < $count; $i++) {
            $idx = ($currentIndex + $i) % $count;
            $entry = $this->hosts[$idx];
            $key = $this->hostKey($entry);

            if (!$this->isDown($key)) {
                if ($cache) {
                    $cache->set($indexKey, ($idx + 1) % $count, 3600);
                }
                return $entry;
            }
        }

        return $availableHosts[array_rand($availableHosts)];
    }

    /**
     * Strategy: Weighted Random
     */
    protected function selectWeighted(array $availableHosts): array
    {
        $totalWeight = array_sum(array_column($availableHosts, 'weight'));
        $rand = mt_rand(1, (int) $totalWeight);
        $current = 0;

        foreach ($availableHosts as $entry) {
            $current += $entry['weight'];
            if ($rand <= $current) {
                return $entry;
            }
        }

        return $availableHosts[array_rand($availableHosts)];
    }

    /**
     * Strategy: Least Connections
     */
    protected function selectLeastConnections(array $availableHosts): array
    {
        $cache = Facade::getFacadeInstance('cache');
        if (!$cache) return $availableHosts[array_rand($availableHosts)];

        $minConns = PHP_INT_MAX;
        $selected = null;

        foreach ($availableHosts as $entry) {
            $key = $this->hostKey($entry);
            // Connection counts are tracked using a standard key format
            $connKey = $this->cachePrefix . 'conns:' . md5($key);
            $conns = (int) $cache->get($connKey, 0);

            if ($conns < $minConns) {
                $minConns = $conns;
                $selected = $entry;
            }
        }

        return $selected ?: $availableHosts[array_rand($availableHosts)];
    }

    /**
     * Normalize hosts into standard array format.
     */
    protected function normalizeHosts(array $hosts): array
    {
        $normalized = [];
        foreach ($hosts as $host) {
            if (is_string($host)) {
                $normalized[] = [
                    'host' => $host,
                    'port' => null,
                    'weight' => 1
                ];
            } else {
                $normalized[] = [
                    'host' => $host['host'] ?? 'localhost',
                    'port' => $host['port'] ?? null,
                    'weight' => $host['weight'] ?? 1
                ];
            }
        }
        return $normalized;
    }

    /**
     * Format down hosts for error message.
     */
    protected function formatDownHosts(): string
    {
        return implode(', ', array_map(function ($h) {
            return $this->hostKey($h);
        }, $this->hosts));
    }
}
