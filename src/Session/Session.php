<?php

declare(strict_types=1);

namespace Plugs\Session;

/*
|--------------------------------------------------------------------------
| Session Class
|--------------------------------------------------------------------------
|
| This class manages user sessions, providing methods to start,
| retrieve, and destroy sessions.
*/

class Session
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        return $_SESSION;
    }

    public function clear(): void
    {
        $_SESSION = [];
    }

    public function destroy(): void
    {
        session_destroy();
    }

    public function regenerate(bool $deleteOld = true): bool
    {
        return session_regenerate_id($deleteOld);
    }

    public function flash(string $key, $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, $default = null)
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    public function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    public function getId(): string
    {
        return session_id();
    }

    public function setId(string $id): void
    {
        session_id($id);
    }
}
