<?php

declare(strict_types=1);

namespace Plugs\Queue\Drivers;

use Redis;
use Plugs\Queue\QueueDriverInterface;

class RedisQueueDriver implements QueueDriverInterface
{
    protected Redis $redis;
    protected string $defaultQueue;
    protected string $prefix;

    public function __construct(Redis $redis, string $defaultQueue = 'default', string $prefix = 'plugs:queue:')
    {
        $this->redis = $redis;
        $this->defaultQueue = $defaultQueue;
        $this->prefix = $prefix;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $data), $queue ?: $this->defaultQueue);
    }

    public function later(int $delay, $job, $data = '', $queue = null)
    {
        $payload = $this->createPayload($job, $data);
        $queue = $queue ?: $this->defaultQueue;

        return $this->redis->zAdd(
            $this->getQueueKey($queue) . ':delayed',
            time() + $delay,
            $payload
        );
    }

    public function pop($queue = null)
    {
        $queue = $queue ?: $this->defaultQueue;
        $queueKey = $this->getQueueKey($queue);

        // First, move any delayed jobs that are ready to the main queue
        $this->migrateDelayedJobs($queue);

        // Pop from the list
        $job = $this->redis->lPop($queueKey);

        if ($job) {
            $payload = json_decode($job, true);
            $payload['attempts'] = ($payload['attempts'] ?? 0) + 1;

            // We return a stdClass to match the DatabaseQueueDriver behavior
            return (object) [
                'id' => $payload['id'] ?? uniqid(),
                'queue' => $queue,
                'payload' => serialize($payload), // Command expects serialized payload
                'attempts' => $payload['attempts'],
                'reserved_at' => time(),
            ];
        }

        return null;
    }

    public function size($queue = null): int
    {
        $queue = $queue ?: $this->defaultQueue;
        return $this->redis->lLen($this->getQueueKey($queue)) +
            $this->redis->zCard($this->getQueueKey($queue) . ':delayed');
    }

    public function delete(int $id): bool
    {
        // Redis pop already removes the item. 
        // If we implement a reserved set later, we'd delete from there.
        return true;
    }

    protected function pushRaw(string $payload, string $queue)
    {
        return $this->redis->rPush($this->getQueueKey($queue), $payload);
    }

    protected function migrateDelayedJobs(string $queue): void
    {
        $queueKey = $this->getQueueKey($queue);
        $delayedKey = $queueKey . ':delayed';

        $options = [
            'LIMIT' => [0, 10] // Migrate in batches
        ];

        $jobs = $this->redis->zRangeByScore($delayedKey, '-inf', (string) time(), $options);

        foreach ($jobs as $job) {
            if ($this->redis->zRem($delayedKey, $job) > 0) {
                $this->redis->rPush($queueKey, $job);
            }
        }
    }

    protected function createPayload($job, $data): string
    {
        return json_encode([
            'id' => bin2hex(random_bytes(16)),
            'job' => is_object($job) ? get_class($job) : $job,
            'data' => $data,
            'instance' => is_object($job) ? serialize($job) : null,
            'attempts' => 0,
            'created_at' => time(),
        ]);
    }

    protected function getQueueKey(string $queue): string
    {
        return $this->prefix . $queue;
    }
}
