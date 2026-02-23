<?php

declare(strict_types=1);

namespace Plugs\Queue;

use Plugs\Database\Connection;
use Plugs\Database\QueryBuilder;
use Plugs\Console\Support\Str;

class DatabaseFailedJobProvider
{
    protected Connection $connection;
    protected string $table;

    public function __construct(Connection $connection, string $table = 'failed_jobs')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function log(string $connection, string $queue, string $payload, \Throwable $exception): void
    {
        (new QueryBuilder($this->connection))
            ->table($this->table)
            ->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => $connection,
                'queue' => $queue,
                'payload' => $payload,
                'exception' => (string) $exception,
                'failed_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function all(): array
    {
        return (new QueryBuilder($this->connection))
            ->table($this->table)
            ->orderBy('id', 'desc')
            ->get();
    }

    public function find($id)
    {
        return (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('id', '=', $id)
            ->first();
    }

    public function forget($id): bool
    {
        return (bool) (new QueryBuilder($this->connection))
            ->table($this->table)
            ->where('id', '=', $id)
            ->delete();
    }

    public function flush(): bool
    {
        return (bool) (new QueryBuilder($this->connection))
            ->table($this->table)
            ->delete();
    }
}
