<?php

declare(strict_types=1);

namespace Plugs\Support;

/**
 * Generic Load Balancer
 * Handles host selection with pluggable strategies, automatic failover, 
 * and host health tracking using a shared cache.
 */
class LoadBalancer
{
    protected array $hosts = [];
    protected string $strategy;
    protected int $roundRobinIndex = 0;
    protected array $healthStates = [];
    protected float $healthCheckCooldown;
    protected int $maxFailures;
    protected string $cachePrefix;

    /**
     * @param array  $hosts       Array of host strings or config arrays
     * @param string $strategy    'random', 'round-robin', 'weighted', 'least-connections'
     * @param array  $options     Optional configuration
     */
    public function __construct(array $hosts, string $strategy = 'random', array $options = [])
    {
        $this->strategy = $this->validateStrategy($strategy);
        $this->healthCheckCooldown = (float) ($options['health_check_cooldown'] ?? 30);
        $this->maxFailures = (int) ($options['max_failures'] ?? 3);
        $this->cachePrefix = $options['cache_prefix'] ?? 'lb:health:';
        $this->hosts = $this->normalizeHosts($hosts);

        if (empty($this->hosts)) {
            throw new \InvalidArgumentException('LoadBalancer requires at least one host.');
        }
    }

    public function select(): array
    {
        $available = $this->getAvailableHosts();

        if (empty($available)) {
            $this->recoverCooledDownHosts();
            $available = $this->getAvailableHosts();

            if (empty($available)) {
                throw new \RuntimeException('All hosts are down: ' . $this->formatDownHosts());
            }
        }

        return match ($this->strategy) {
            'round-robin' => $this->selectRoundRobin($available),
            'weighted' => $this->selectWeighted($available),
            'least-connections' => $this->selectLeastConnections($available),
            default => $this->selectRandom($available),
        };
    }

    public function selectWithFailover(callable $testCallback): array
    {
        $tried = [];
        $lastException = null;
        $maxAttempts = count($this->hosts);

        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $hostEntry = $this->select();
                $key = $this->hostKey($hostEntry);

                if (in_array($key, $tried, true))
                    continue;
                $tried[] = $key;

                $result = $testCallback($hostEntry);

                if ($result === false) {
                    $this->recordFailure($key);
                    continue;
                }

                $this->recordSuccess($key);
                return ['host_entry' => $hostEntry, 'result' => $result];
            } catch (\Throwable $e) {
                $key = $this->hostKey($hostEntry ?? $this->hosts[0]);
                $this->recordFailure($key);
                $lastException = $e;
            }
        }

        throw new \RuntimeException(
            'All hosts failed during failover. Last error: ' . ($lastException ? $lastException->getMessage() : 'unknown'),
            0,
            $lastException
        );
    }

    protected function selectRandom(array $available): array
    {
        return $available[array_rand($available)];
    }

    protected function selectRoundRobin(array $available): array
    {
        $keys = array_keys($available);
        $index = $this->roundRobinIndex % count($keys);
        $this->roundRobinIndex++;
        return $available[$keys[$index]];
    }

    protected function selectWeighted(array $available): array
    {
        $totalWeight = array_reduce($available, fn($carry, $item) => $carry + $item['weight'], 0);
        if ($totalWeight <= 0)
            return $this->selectRandom($available);

        $random = random_int(1, $totalWeight);
        $cumulative = 0;

        foreach ($available as $entry) {
            $cumulative += $entry['weight'];
            if ($random <= $cumulative)
                return $entry;
        }

        return end($available);
    }

    protected function selectLeastConnections(array $available): array
    {
        $best = null;
        $minConnections = PHP_INT_MAX;

        foreach ($available as $entry) {
            $key = $this->hostKey($entry);
            $conns = \Plugs\Facades\Cache::get($this->cachePrefix . 'conns:' . md5($key), 0);

            if ($conns < $minConnections) {
                $minConnections = $conns;
                $best = $entry;
            }
        }

        return $best ?? $this->selectRandom($available);
    }

    public function recordFailure(string $hostKey): void
    {
        $state = $this->getHealthState($hostKey);
        $state['failures']++;

        if ($state['failures'] >= $this->maxFailures) {
            $state['down_at'] = microtime(true);
        }

        $this->saveHealthState($hostKey, $state);
    }

    public function recordSuccess(string $hostKey): void
    {
        $this->saveHealthState($hostKey, ['down_at' => null, 'failures' => 0]);
    }

    public function markDown(string $hostKey): void
    {
        $this->saveHealthState($hostKey, [
            'down_at' => microtime(true),
            'failures' => $this->maxFailures
        ]);
    }

    public function markUp(string $hostKey): void
    {
        $this->recordSuccess($hostKey);
    }

    public function isDown(string $hostKey): bool
    {
        $state = $this->getHealthState($hostKey);
        if ($state['down_at'] === null)
            return false;
        if ((microtime(true) - $state['down_at']) >= $this->healthCheckCooldown)
            return false;
        return true;
    }

    protected function getAvailableHosts(): array
    {
        $available = [];
        foreach ($this->hosts as $index => $entry) {
            if (!$this->isDown($this->hostKey($entry))) {
                $available[$index] = $entry;
            }
        }
        return $available;
    }

    protected function recoverCooledDownHosts(): void
    {
        $now = microtime(true);
        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $state = $this->getHealthState($key);

            if ($state['down_at'] !== null && ($now - $state['down_at']) >= $this->healthCheckCooldown) {
                $state['down_at'] = null;
                $state['failures'] = max(0, $this->maxFailures - 1);
                $this->saveHealthState($key, $state);
            }
        }
    }

    protected function getHealthState(string $hostKey): array
    {
        if (isset($this->healthStates[$hostKey]))
            return $this->healthStates[$hostKey];

        $cacheKey = $this->cachePrefix . md5($hostKey);
        $state = \Plugs\Facades\Cache::get($cacheKey, ['down_at' => null, 'failures' => 0]);

        $this->healthStates[$hostKey] = $state;
        return $state;
    }

    protected function saveHealthState(string $hostKey, array $state): void
    {
        $this->healthStates[$hostKey] = $state;
        $cacheKey = $this->cachePrefix . md5($hostKey);
        \Plugs\Facades\Cache::set($cacheKey, $state, 3600);
    }

    public function hostKey(array $entry): string
    {
        return $entry['host'] . (isset($entry['port']) ? ':' . $entry['port'] : '');
    }

    protected function normalizeHosts(array $hosts): array
    {
        $normalized = [];
        foreach ($hosts as $entry) {
            if (is_string($entry)) {
                $normalized[] = ['host' => $entry, 'port' => null, 'weight' => 1];
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

    protected function validateStrategy(string $strategy): string
    {
        $valid = ['random', 'round-robin', 'weighted', 'least-connections'];
        if (!in_array($strategy, $valid, true)) {
            throw new \InvalidArgumentException("Invalid strategy '{$strategy}'.");
        }
        return $strategy;
    }

    protected function formatDownHosts(): string
    {
        $parts = [];
        foreach ($this->hosts as $entry) {
            $key = $this->hostKey($entry);
            $state = $this->getHealthState($key);
            $parts[] = "{$key} (f: {$state['failures']})";
        }
        return implode(', ', $parts);
    }
}
