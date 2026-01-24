<?php

declare(strict_types=1);

namespace Plugs\Queue\Drivers;

use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Queue\QueueDriverInterface;

class DatabaseQueueDriver implements QueueDriverInterface
{
    protected Connection $connection;
    protected string $table;
    protected string $defaultQueue;

    public function __construct(Connection $connection, string $table = 'jobs', string $defaultQueue = 'default')
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->defaultQueue = $defaultQueue;
    }

    public function push($job, $data = '', $queue = null)
    {
        return $this->pushToDatabase($job, $data, $queue ?: $this->defaultQueue);
    }

    public function later(int $delay, $job, $data = '', $queue = null)
    {
        return $this->pushToDatabase($job, $data, $queue ?: $this->defaultQueue, time() + $delay);
    }

    public function pop($queue = null)
    {
        $queue = $queue ?: $this->defaultQueue;

        $job = (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('queue', '=', $queue)
            ->where('available_at', '<=', time())
            ->where('reserved_at', '=', null)
            ->orderBy('id', 'asc')
            ->first();

        if ($job) {
            $this->reserveJob($job['id']);

            return (object) $job;
        }

        return null;
    }

    public function size($queue = null): int
    {
        return (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('queue', '=', $queue ?: $this->defaultQueue)
            ->count();
    }

    public function delete(int $id): bool
    {
        return (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('id', '=', $id)
            ->delete();
    }

    protected function pushToDatabase($job, $data, string $queue, int $availableAt = null): int
    {
        $availableAt = $availableAt ?: time();

        $payload = serialize([
            'job' => is_object($job) ? get_class($job) : $job,
            'data' => $data,
            'instance' => is_object($job) ? serialize($job) : null,
        ]);

        (new QueryBuilder($this->connection))
            ->table($this->table)
            ->insert([
                'queue' => $queue,
                'payload' => $payload,
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $availableAt,
                'created_at' => time(),
            ]);

        return (int) $this->connection->lastInsertId();
    }

    protected function reserveJob(int $id): void
    {
        (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('id', '=', $id)
            ->update([
                'reserved_at' => time(),
                'attempts' => (new QueryBuilder($this->connection))
                    ->table($this->table)
                    ->where('id', '=', $id)
                    ->first()['attempts'] + 1,
            ]);
    }
}
