<?php

declare(strict_types=1);

namespace Plugs\Session\Drivers;

use Plugs\Database\Connection;
use Plugs\Session\SessionDriverInterface;

/*
|--------------------------------------------------------------------------
| Database Session Driver
|--------------------------------------------------------------------------
|
| Stores sessions in a database table. Requires a `sessions` table with
| columns: id (VARCHAR), payload (TEXT), last_activity (INT), user_id (INT|NULL).
|
| Migration example:
|   CREATE TABLE sessions (
|       id VARCHAR(255) PRIMARY KEY,
|       payload TEXT NOT NULL,
|       last_activity INT UNSIGNED NOT NULL,
|       user_id INT UNSIGNED NULL,
|       ip_address VARCHAR(45) NULL,
|       user_agent TEXT NULL,
|       INDEX sessions_last_activity_index (last_activity)
|   );
*/

class DatabaseSessionDriver implements SessionDriverInterface
{
    private Connection $connection;
    private string $table;

    public function __construct(?Connection $connection = null, string $table = 'sessions')
    {
        $this->connection = $connection ?? Connection::getInstance();
        $this->table = $table;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $result = $this->connection->fetch(
            "SELECT payload FROM {$this->table} WHERE id = ?",
            [$id]
        );

        return $result['payload'] ?? '';
    }

    public function write(string $id, string $data): bool
    {
        $existing = $this->connection->fetch(
            "SELECT id FROM {$this->table} WHERE id = ?",
            [$id]
        );

        $attributes = [
            'payload' => $data,
            'last_activity' => time(),
        ];

        if ($existing) {
            $this->connection->execute(
                "UPDATE {$this->table} SET payload = ?, last_activity = ? WHERE id = ?",
                [$attributes['payload'], $attributes['last_activity'], $id]
            );
        } else {
            $this->connection->execute(
                "INSERT INTO {$this->table} (id, payload, last_activity) VALUES (?, ?, ?)",
                [$id, $attributes['payload'], $attributes['last_activity']]
            );
        }

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->connection->execute(
            "DELETE FROM {$this->table} WHERE id = ?",
            [$id]
        );

        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        $cutoff = time() - $maxLifetime;

        return $this->connection->execute(
            "DELETE FROM {$this->table} WHERE last_activity < ?",
            [$cutoff]
        );
    }
}
