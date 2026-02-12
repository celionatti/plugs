<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| Database Load Balancer
|--------------------------------------------------------------------------
| Handles host selection with pluggable strategies (random, round-robin,
| weighted), automatic failover, and host health tracking.
|
| Usage:
|   $lb = new LoadBalancer(['db1.local', 'db2.local'], 'round-robin');
|   $host = $lb->select();
|
|   // With weighted hosts:
|   $lb = new LoadBalancer([
|       ['host' => 'db1.local', 'weight' => 10],
|       ['host' => 'db2.local', 'weight' => 5],
|   ], 'weighted');
|--------------------------------------------------------------------------
*/

class LoadBalancer
{
    /**
     * Normalized host entries: [['host' => ..., 'port' => ..., 'weight' => ...], ...]
     */
    private array $hosts = [];

    /**
     * Load balancing strategy: 'random', 'round-robin', 'weighted'
     */
    private string $strategy;

    /**
     * Current index for round-robin cycling.
     */
    private int $roundRobinIndex = 0;

    /**
     * Host health states: host_key => ['down_at' => float|null, 'failures' => int]
     */
    private array $healthStates = [];

    /**
     * Seconds to wait before retrying a downed host.
     */
    private float $healthCheckCooldown;

    /**
     * Maximum consecutive failures before marking a host as down.
     */
    private int $maxFailures;

    /**
     * @param array  $hosts    Array of host strings, or arrays with 'host', 'port', 'weight' keys
     * @param string $strategy One of 'random', 'round-robin', 'weighted'
     * @param array  $options  Optional: 'health_check_cooldown' (seconds), 'max_failures'
     */
    public function __construct(array $hosts, string $strategy = 'random', array $options = [])
    {
        $this->strategy = $this->validateStrategy($strategy);
        $this->healthCheckCooldown = (float) ($options['health_check_cooldown'] ?? 30);
        $this->maxFailures = (int) ($options['max_failures'] ?? 3);
        $this->hosts = $this->normalizeHosts($hosts);

        if (empty($this->hosts)) {
            throw new \InvalidArgumentException('LoadBalancer requires at least one host.');
        }

        // Initialize health states
        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $this->healthStates[$key] = ['down_at' => null, 'failures' => 0];
        }
    }

    // ==================== HOST SELECTION ====================

    /**
     * Select a host using the configured strategy.
     *
     * Skips hosts that are currently marked as down (unless cooldown has expired).
     *
     * @return array The selected host entry ['host' => ..., 'port' => ..., 'weight' => ...]
     * @throws \RuntimeException If all hosts are down
     */
    public function select(): array
    {
        $available = $this->getAvailableHosts();

        if (empty($available)) {
            // Check if any hosts can be recovered via cooldown
            $this->recoverCooledDownHosts();
            $available = $this->getAvailableHosts();

            if (empty($available)) {
                throw new \RuntimeException(
                    'All database hosts are down. Hosts: ' . $this->formatDownHosts()
                );
            }
        }

        return match ($this->strategy) {
            'round-robin' => $this->selectRoundRobin($available),
            'weighted' => $this->selectWeighted($available),
            default => $this->selectRandom($available),
        };
    }

    /**
     * Select a host with automatic failover.
     *
     * Tries hosts in order until one succeeds with the given $testCallback.
     * On failure, marks the host as failed and tries the next.
     *
     * @param callable $testCallback Receives host entry array, must return true on success or throw/return false on failure
     * @return array ['host_entry' => [...], 'result' => mixed] The successful host and callback result
     * @throws \RuntimeException If all hosts fail
     */
    public function selectWithFailover(callable $testCallback): array
    {
        $tried = [];
        $lastException = null;
        $maxAttempts = count($this->hosts);

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $hostEntry = $this->select();
                $key = $this->hostKey($hostEntry);

                // Don't retry the same host
                if (in_array($key, $tried, true)) {
                    continue;
                }
                $tried[] = $key;

                $result = $testCallback($hostEntry);

                if ($result === false) {
                    $this->recordFailure($key);

                    continue;
                }

                // Success — reset failure counter
                $this->recordSuccess($key);

                return ['host_entry' => $hostEntry, 'result' => $result];
            } catch (\Throwable $e) {
                $key = $this->hostKey($hostEntry ?? $this->hosts[0]);
                $this->recordFailure($key);
                $lastException = $e;

                $this->logFailover($hostEntry ?? null, $e);
            }
        }

        throw new \RuntimeException(
            'All database hosts failed during failover. Tried: ' . implode(', ', $tried) .
            ($lastException ? '. Last error: ' . $lastException->getMessage() : ''),
            0,
            $lastException
        );
    }

    // ==================== STRATEGY IMPLEMENTATIONS ====================

    /**
     * Random selection from available hosts.
     */
    private function selectRandom(array $available): array
    {
        return $available[array_rand($available)];
    }

    /**
     * Round-robin: cycles through hosts sequentially.
     */
    private function selectRoundRobin(array $available): array
    {
        // Reset index to available range
        $keys = array_keys($available);
        $index = $this->roundRobinIndex % count($keys);
        $this->roundRobinIndex++;

        return $available[$keys[$index]];
    }

    /**
     * Weighted selection: hosts with higher weight are chosen proportionally more often.
     *
     * Uses cumulative weight distribution for O(n) selection.
     */
    private function selectWeighted(array $available): array
    {
        $totalWeight = 0;
        foreach ($available as $entry) {
            $totalWeight += $entry['weight'];
        }

        if ($totalWeight <= 0) {
            return $this->selectRandom($available);
        }

        $random = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($available as $entry) {
            $cumulative += $entry['weight'];
            if ($random <= $cumulative) {
                return $entry;
            }
        }

        // Fallback (should never reach here)
        return end($available);
    }

    // ==================== HEALTH TRACKING ====================

    /**
     * Record a connection failure for a host.
     */
    public function recordFailure(string $hostKey): void
    {
        if (!isset($this->healthStates[$hostKey])) {
            return;
        }

        $this->healthStates[$hostKey]['failures']++;

        if ($this->healthStates[$hostKey]['failures'] >= $this->maxFailures) {
            $this->healthStates[$hostKey]['down_at'] = microtime(true);
        }
    }

    /**
     * Record a successful connection to a host (resets failure counter).
     */
    public function recordSuccess(string $hostKey): void
    {
        if (!isset($this->healthStates[$hostKey])) {
            return;
        }

        $this->healthStates[$hostKey] = ['down_at' => null, 'failures' => 0];
    }

    /**
     * Manually mark a host as down.
     */
    public function markDown(string $hostKey): void
    {
        if (isset($this->healthStates[$hostKey])) {
            $this->healthStates[$hostKey]['down_at'] = microtime(true);
            $this->healthStates[$hostKey]['failures'] = $this->maxFailures;
        }
    }

    /**
     * Manually mark a host as up.
     */
    public function markUp(string $hostKey): void
    {
        $this->recordSuccess($hostKey);
    }

    /**
     * Check if a specific host is currently marked as down.
     */
    public function isDown(string $hostKey): bool
    {
        if (!isset($this->healthStates[$hostKey])) {
            return false;
        }

        $state = $this->healthStates[$hostKey];

        if ($state['down_at'] === null) {
            return false;
        }

        // Check if cooldown has expired
        if ((microtime(true) - $state['down_at']) >= $this->healthCheckCooldown) {
            return false; // Ready to be retried
        }

        return true;
    }

    /**
     * Get available (not-down) hosts.
     */
    private function getAvailableHosts(): array
    {
        $available = [];

        foreach ($this->hosts as $index => $entry) {
            $key = $this->hostKey($entry);

            if (!$this->isDown($key)) {
                $available[$index] = $entry;
            }
        }

        return $available;
    }

    /**
     * Recover hosts whose cooldown period has expired.
     */
    private function recoverCooledDownHosts(): void
    {
        $now = microtime(true);

        foreach ($this->healthStates as $key => &$state) {
            if ($state['down_at'] !== null && ($now - $state['down_at']) >= $this->healthCheckCooldown) {
                // Reset to allow retry, but keep failure count at threshold - 1
                // so one more failure immediately marks it down again
                $state['down_at'] = null;
                $state['failures'] = max(0, $this->maxFailures - 1);
            }
        }
    }

    // ==================== REPORTING ====================

    /**
     * Get the health status of all hosts.
     *
     * @return array List of host statuses
     */
    public function getHealthReport(): array
    {
        $report = [];

        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $state = $this->healthStates[$key];

            $report[] = [
                'host' => $entry['host'],
                'port' => $entry['port'],
                'weight' => $entry['weight'],
                'key' => $key,
                'is_down' => $this->isDown($key),
                'failures' => $state['failures'],
                'down_since' => $state['down_at'] ? date('Y-m-d H:i:s', (int) $state['down_at']) : null,
                'cooldown_remaining' => $state['down_at']
                    ? max(0, $this->healthCheckCooldown - (microtime(true) - $state['down_at']))
                    : 0,
            ];
        }

        return $report;
    }

    /**
     * Get the current strategy.
     */
    public function getStrategy(): string
    {
        return $this->strategy;
    }

    /**
     * Get all registered hosts.
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * Get the host key for a given entry.
     */
    public function hostKey(array $entry): string
    {
        return $entry['host'] . ':' . $entry['port'];
    }

    // ==================== INTERNAL HELPERS ====================

    /**
     * Normalize mixed host formats into consistent entries.
     *
     * Accepts:
     *   - ['host1', 'host2']                         → simple string list
     *   - [['host' => 'h1', 'port' => 3307, 'weight' => 5], ...]  → full config
     *   - 'single-host'                               → single string
     */
    private function normalizeHosts(array $hosts): array
    {
        $normalized = [];

        foreach ($hosts as $entry) {
            if (is_string($entry)) {
                $normalized[] = [
                    'host' => $entry,
                    'port' => null, // Will inherit from connection config
                    'weight' => 1,
                ];
            } elseif (is_array($entry) && isset($entry['host'])) {
                $normalized[] = [
                    'host' => $entry['host'],
                    'port' => $entry['port'] ?? null,
                    'weight' => (int) ($entry['weight'] ?? 1),
                ];
            }
        }

        return $normalized;
    }

    /**
     * Validate strategy name.
     */
    private function validateStrategy(string $strategy): string
    {
        $valid = ['random', 'round-robin', 'weighted'];

        if (!in_array($strategy, $valid, true)) {
            throw new \InvalidArgumentException(
                "Invalid load balancing strategy '{$strategy}'. Valid: " . implode(', ', $valid)
            );
        }

        return $strategy;
    }

    /**
     * Format down hosts list for error messages.
     */
    private function formatDownHosts(): string
    {
        $parts = [];

        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $state = $this->healthStates[$key];
            $parts[] = "{$key} (failures: {$state['failures']})";
        }

        return implode(', ', $parts);
    }

    /**
     * Log a failover event.
     */
    private function logFailover(?array $hostEntry, \Throwable $e): void
    {
        $host = $hostEntry ? $this->hostKey($hostEntry) : 'unknown';

        error_log(sprintf(
            '[DB LoadBalancer] Failover from %s: %s',
            $host,
            $e->getMessage()
        ));
    }
}
