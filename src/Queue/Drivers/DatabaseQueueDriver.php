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

        $this->ensureTableExists();
    }

    /**
     * Ensure the jobs table exists.
     */
    protected function ensureTableExists(): void
    {
        if (!\Plugs\Database\Schema::hasTable($this->table)) {
            \Plugs\Database\Schema::create($this->table, function (\Plugs\Database\Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->tinyInteger('attempts')->unsigned();
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');

                $table->index(['queue', 'reserved_at', 'available_at']);
            });
        }
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
        $currentTime = time();

        try {
            $this->connection->beginTransaction();

            // Select and lock the next available job
            // Using raw query because QueryBuilder doesn't support FOR UPDATE and LIMIT on UPDATE
            $sql = "SELECT id FROM {$this->table} 
                    WHERE queue = ? 
                    AND available_at <= ? 
                    AND reserved_at IS NULL 
                    ORDER BY id ASC 
                    LIMIT 1 
                    FOR UPDATE";

            $stmt = $this->connection->query($sql, [$queue, $currentTime]);
            $jobId = $stmt->fetchColumn();

            if (!$jobId) {
                $this->connection->rollBack();
                return null;
            }

            // Reserve the job and increment attempts
            $updateSql = "UPDATE {$this->table} 
                         SET reserved_at = ?, attempts = attempts + 1 
                         WHERE id = ?";

            $this->connection->execute($updateSql, [$currentTime, $jobId]);

            // Fetch the full job data
            $jobData = (new QueryBuilder($this->connection))
                ->table($this->table)
                ->where('id', '=', $jobId)
                ->first();

            $this->connection->commit();

            return $jobData ? (object) $jobData : null;
        } catch (\Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $e;
        }
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
        return (bool) (new QueryBuilder($this->connection))
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
}
