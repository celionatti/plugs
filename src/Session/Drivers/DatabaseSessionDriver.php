<?php

declare(strict_types = 1);

namespace Plugs\Session\Drivers;

use Plugs\Database\Connection;
use Plugs\Session\SessionDriverInterface;
use Plugs\Database\Schema;
use Plugs\Database\Blueprint;

/* |-------------------------------------------------------------------------- | Database Session Driver |-------------------------------------------------------------------------- | | Stores sessions in a database table. Requires a `sessions` table with | columns: id (VARCHAR), payload (TEXT), last_activity (INT), user_id (INT|NULL). | | Migration example: |   CREATE TABLE sessions ( |       id VARCHAR(255) PRIMARY KEY, |       payload TEXT NOT NULL, |       last_activity INT UNSIGNED NOT NULL, |       user_id INT UNSIGNED NULL, |       ip_address VARCHAR(45) NULL, |       user_agent TEXT NULL, |       INDEX sessions_last_activity_index (last_activity) |   ); */

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
        try {
            $result = $this->connection->fetch(
                "SELECT payload FROM {$this->table} WHERE id = ?",
                [$id]
            );
        } catch (\PDOException $e) {
            if ($this->isTableMissingError($e)) {
                $this->createTable();
                return '';
            }
            throw $e;
        }

        if ($result) {
            return $result['payload'] ?? '';
        }

        // 2. Try Cookie Fallback (Guest/Anonymous)
        $cookieKey = 'guest_sess_' . $id;
        $cookie = $this->getCookieJar();
        return $cookie ? (string)$cookie->get($cookieKey, '') : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = $this->getUserIdFromData($data);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $existing = $this->connection->fetch(
                "SELECT id FROM {$this->table} WHERE id = ?",
                [$id]
            );
        } catch (\PDOException $e) {
            if ($this->isTableMissingError($e)) {
                $this->createTable();
                $existing = null;
            } else {
                return false;
            }
        }

        $cookieKey = 'guest_sess_' . $id;
        $cookie = $this->getCookieJar();

        if ($userId === null && !$existing) {
            if ($cookie) {
                $cookie->set($cookieKey, $data, 120); // 2 hours
            }
            return true;
        }

        if ($userId !== null && $cookie && $cookie->has($cookieKey)) {
            $cookie->forget($cookieKey);
        }

        if ($userId !== null) {
            try {
                $this->connection->execute(
                    "DELETE FROM {$this->table} WHERE user_id = ? AND id != ?",
                    [$userId, $id]
                );
            } catch (\Exception $e) {}
        }

        try {
            if ($existing) {
                $this->connection->execute(
                    "UPDATE {$this->table} SET payload = ?, last_activity = ?, user_id = ?, ip_address = ?, user_agent = ? WHERE id = ?",
                    [$data, time(), $userId, $ipAddress, $userAgent, $id]
                );
            } else {
                $this->connection->execute(
                    "INSERT INTO {$this->table} (id, payload, last_activity, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
                    [$id, $data, time(), $userId, $ipAddress, $userAgent]
                );
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    protected function getCookieJar()
    {
        $container = \Plugs\Container\Container::getInstance();
        if ($container->bound('cookie')) {
            return $container->make('cookie');
        }
        return null;
    }

    protected function getUserIdFromData(string $data): ?int
    {
        if (preg_match('/login_[a-zA-Z0-9_]+\|(?:i:(\d+)|s:\d+:"(\d+)")/', $data, $matches)) {
            return isset($matches[2]) ? (int)$matches[2] : (int)$matches[1];
        }
        return null;
    }

    public function destroy(string $id): bool
    {
        try {
            $this->connection->execute(
                "DELETE FROM {$this->table} WHERE id = ?",
                [$id]
            );
        } catch (\Exception $e) {}

        $cookie = $this->getCookieJar();
        if ($cookie) {
            $cookie->forget('guest_sess_' . $id);
        }

        return true;
    }

    public function gc(int $maxLifetime): int|bool
    {
        $cutoff = time() - $maxLifetime;

        try {
            return $this->connection->execute(
                "DELETE FROM {$this->table} WHERE last_activity < ?",
                [$cutoff]
            );
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function isTableMissingError(\PDOException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'no such table') || 
               str_contains($message, "Base table or view not found");
    }

    protected function createTable(): void
    {
        Schema::create($this->table, function (Blueprint $table) {
            $table->string('id', 255)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
}
