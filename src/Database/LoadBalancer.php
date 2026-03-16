<?php

declare(strict_types = 1)
;

namespace Plugs\Database;

use Plugs\Support\LoadBalancer as BaseLoadBalancer;
use Throwable;

/**
 * Database Load Balancer
 * Extends the generic LoadBalancer with database-specific logging and failover handling.
 */
class LoadBalancer extends BaseLoadBalancer
{
    /**
     * @param array  $hosts    Array of host strings, or arrays with 'host', 'port', 'weight' keys
     * @param string $strategy One of 'random', 'round-robin', 'weighted', 'least-connections'
     * @param array  $options  Optional: 'health_check_cooldown', 'max_failures'
     */
    public function __construct(array $hosts, string $strategy = 'random', array $options = [])
    {
        $options['cache_prefix'] = $options['cache_prefix'] ?? 'db:lb:health:';
        parent::__construct($hosts, $strategy, $options);
    }

    public function select(): array
    {
        try {
            return parent::select();
        }
        catch (Throwable $e) {
            throw new \RuntimeException(
                'All database hosts are down. Hosts: ' . $this->formatDownHosts(),
                0,
                $e
                );
        }
    }

    /**
     * Select a host with automatic failover and database-specific logging.
     */
    public function selectWithFailover(callable $testCallback): array
    {
        try {
            return parent::selectWithFailover($testCallback);
        }
        catch (Throwable $e) {
            $this->logFailover(null, $e);
            throw $e;
        }
    }

    /**
     * Record a success and log if it was a recovery.
     */
    public function recordSuccess(string $hostKey): void
    {
        $state = $this->getHealthState($hostKey);
        if ($state['down_at'] !== null) {
            error_log("[DB LoadBalancer] Host {$hostKey} recovered.");
        }
        parent::recordSuccess($hostKey);
    }

    /**
     * Record a failure with database-specific logging.
     */
    public function recordFailure(string $hostKey): void
    {
        parent::recordFailure($hostKey);
        $state = $this->getHealthState($hostKey);

        if ($state['failures'] >= $this->maxFailures) {
            error_log("[DB LoadBalancer] Host {$hostKey} marked as DOWN after {$state['failures']} failures.");
        }
    }

    /**
     * Get the health status of all hosts (for reporting/monitoring).
     */
    public function getHealthReport(): array
    {
        $report = [];
        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $state = $this->getHealthState($key);

            $report[] = [
                'host' => $entry['host'],
                'port' => $entry['port'],
                'weight' => $entry['weight'],
                'key' => $key,
                'is_down' => $this->isDown($key),
                'failures' => $state['failures'],
                'down_since' => $state['down_at'] ? date('Y-m-d H:i:s', (int)$state['down_at']) : null,
                'cooldown_remaining' => $state['down_at']
                ? max(0, $this->healthCheckCooldown - (microtime(true) - $state['down_at']))
                : 0,
            ];
        }
        return $report;
    }

    /**
     * Log a failover event.
     */
    protected function logFailover(?array $hostEntry, Throwable $e): void
    {
        $host = $hostEntry ? $this->hostKey($hostEntry) : 'unknown';
        error_log(sprintf('[DB LoadBalancer] Failover event: %s. Error: %s', $host, $e->getMessage()));
    }
}
