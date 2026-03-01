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
        // 1. Try Database (Authenticated)
        $result = $this->connection->fetch(
            "SELECT payload FROM {$this->table} WHERE id = ?",
            [$id]
        );

        if ($result) {
            return $result['payload'] ?? '';
        }

        // 2. Try Cookie Fallback (Guest/Anonymous)
        // This ensures CSRF tokens work without cluttering the database with guest rows.
        $cookieKey = 'guest_sess_' . $id;
        $cookie = $this->getCookieJar();
        return $cookie ? (string) $cookie->get($cookieKey, '') : '';
    }

    public function write(string $id, string $data): bool
    {
        $userId = $this->getUserIdFromData($data);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $existing = $this->connection->fetch(
            "SELECT id FROM {$this->table} WHERE id = ?",
            [$id]
        );

        $cookieKey = 'guest_sess_' . $id;
        $cookie = $this->getCookieJar();

        // Hybrid Strategy:
        // Guest sessions (no user_id) are stored in an encrypted cookie.
        // Authenticated sessions are stored in the database.
        if ($userId === null && !$existing) {
            // Store in cookie for guest
            if ($cookie) {
                $cookie->set($cookieKey, $data, 120); // 2 hours
            }
            return true;
        }

        // If it's becoming authenticated, clear the guest cookie
        if ($userId !== null && $cookie && $cookie->has($cookieKey)) {
            $cookie->forget($cookieKey);
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
        // PHP sessions are serialized in a special format: key|serialized_value
        // We look for keys starting with 'login_' (e.g., login_web_..., login_session_...)

        // Match both integer (i:123;) and string (s:3:"123";) IDs
        if (preg_match('/login_[a-zA-Z0-9_]+\|(?:i:(\d+)|s:\d+:"(\d+)")/', $data, $matches)) {
            // Return whichever group matched
            return isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : (int) $matches[1];
        }

        return null;
    }

    public function destroy(string $id): bool
    {
        // Clear both database and guest cookie
        $this->connection->execute(
            "DELETE FROM sessions WHERE id = ?",
            [$id]
        );

        $cookie = $this->getCookieJar();
        if ($cookie) {
            $cookie->forget('guest_sess_' . $id);
        }

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
