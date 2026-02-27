<?php

declare(strict_types=1);

namespace Plugs\Session;

use Plugs\Session\Drivers\DatabaseSessionDriver;
use Plugs\Session\Drivers\FileSessionDriver;

/*
|--------------------------------------------------------------------------
| SessionManager Class
|--------------------------------------------------------------------------
|
| Manages session configuration, initialization, and driver selection.
| Supports multiple session storage backends via SessionDriverInterface.
|
| Supported drivers: 'file' (default), 'database'
*/

class SessionManager
{
    private array $config;
    private ?SessionDriverInterface $driver = null;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'file',           // 'file' or 'database'
            'lifetime' => 120,
            'path' => '/',
            'domain' => '',
            'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
            'save_path' => null,
            'table' => 'sessions',        // For database driver
            'connection' => null,         // For database driver (null = default)
        ], $config);
    }

    /**
     * Start the session with the configured driver.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Set session cookie parameters
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'] * 60,
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);

        // Register custom session handler if using a non-default driver
        $driver = $this->resolveDriver();

        if ($driver !== null) {
            $handler = new SessionHandler($driver);
            session_set_save_handler($handler, true);
        } elseif ($this->config['save_path'] !== null) {
            // Fallback: use file driver with custom save path
            if (!is_dir($this->config['save_path'] ?? '')) {
                mkdir($this->config['save_path'] ?? '', 0755, true);
            }
            session_save_path($this->config['save_path']);
        }

        session_start();

        // Regenerate session ID periodically for security
        $this->regenerateIfNeeded();
    }

    /**
     * Resolve the session driver based on configuration.
     */
    private function resolveDriver(): ?SessionDriverInterface
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $this->driver = match ($this->config['driver']) {
            'database' => new DatabaseSessionDriver(
                $this->config['connection'],
                $this->config['table']
            ),
            'file' => $this->config['save_path']
            ? new FileSessionDriver($this->config['save_path'])
            : null, // Use PHP's native handler
            default => null,
        };

        return $this->driver;
    }

    /**
     * Set a custom session driver.
     */
    public function setDriver(SessionDriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * Get the current driver name.
     */
    public function getDriverName(): string
    {
        return $this->config['driver'];
    }

    private function regenerateIfNeeded(): void
    {
        if (!isset($_SESSION['_last_regenerated'])) {
            $_SESSION['_last_regenerated'] = time();
        }

        // Regenerate every 30 minutes
        if (time() - $_SESSION['_last_regenerated'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        }
    }

    public function getSession(): Session
    {
        return new Session();
    }
}
