<?php

declare(strict_types=1);

namespace Plugs\Database;

/*
|--------------------------------------------------------------------------
| EloquentBootstrap Class
|--------------------------------------------------------------------------
|
| This class is responsible for bootstrapping the Eloquent ORM. It provides
| methods to initialize Eloquent with single or multiple database
| connections and manage its configuration.
*/

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;

/**
 * @phpstan-ignore-next-line
 */
class EloquentBootstrap
{
    private static $capsule = null;
    private static $booted = false;

    /**
     * Boot Eloquent ORM with given database configuration
     *
     * @param array $config Database configuration array
     * @return Capsule
     */
    public static function boot(array $config): Capsule
    {
        if (self::$booted) {
            return self::$capsule;
        }

        self::$capsule = new Capsule();

        // Add the connection
        self::$capsule->addConnection([
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'port' => $config['port'] ?? 3306,
            'database' => $config['database'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8mb4',
            'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
            'strict' => $config['strict'] ?? true,
            'engine' => $config['engine'] ?? null,
        ]);

        // Set the event dispatcher (for model events)
        self::$capsule->setEventDispatcher(new Dispatcher(new IlluminateContainer()));

        // Make this Capsule instance available globally via static methods
        self::$capsule->setAsGlobal();

        // Setup the Eloquent ORM
        self::$capsule->bootEloquent();

        self::$booted = true;

        return self::$capsule;
    }

    /**
     * Boot Eloquent with multiple database connections
     *
     * @param array $connections Array of connection configurations
     * @param string $default Default connection name
     * @return Capsule
     */
    public static function bootMultiple(array $connections, string $default = 'mysql'): Capsule
    {
        if (self::$booted) {
            return self::$capsule;
        }

        self::$capsule = new Capsule();

        // Add each connection
        foreach ($connections as $name => $config) {
            self::$capsule->addConnection([
                'driver' => $config['driver'] ?? 'mysql',
                'host' => $config['host'] ?? 'localhost',
                'port' => $config['port'] ?? 3306,
                'database' => $config['database'] ?? '',
                'username' => $config['username'] ?? '',
                'password' => $config['password'] ?? '',
                'charset' => $config['charset'] ?? 'utf8mb4',
                'collation' => $config['collation'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $config['prefix'] ?? '',
                'strict' => $config['strict'] ?? true,
                'engine' => $config['engine'] ?? null,
            ], $name);
        }

        // Set default connection
        self::$capsule->getDatabaseManager()->setDefaultConnection($default);

        // Set the event dispatcher
        self::$capsule->setEventDispatcher(new Dispatcher(new IlluminateContainer()));

        // Make this Capsule instance available globally
        self::$capsule->setAsGlobal();

        // Setup the Eloquent ORM
        self::$capsule->bootEloquent();

        self::$booted = true;

        return self::$capsule;
    }

    /**
     * Get the Capsule instance
     *
     * @return Capsule|null
     */
    public static function getCapsule(): ?Capsule
    {
        return self::$capsule;
    }

    /**
     * Check if Eloquent has been booted
     *
     * @return bool
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }

    /**
     * Get the current connection name
     *
     * @return string
     */
    public static function getConnectionName(): string
    {
        if (!self::$booted) {
            throw new \RuntimeException('Eloquent has not been booted yet.');
        }

        return self::$capsule->getDatabaseManager()->getDefaultConnection();
    }

    /**
     * Switch the default connection
     *
     * @param string $name Connection name
     * @return void
     */
    public static function setDefaultConnection(string $name): void
    {
        if (!self::$booted) {
            throw new \RuntimeException('Eloquent has not been booted yet.');
        }

        self::$capsule->getDatabaseManager()->setDefaultConnection($name);
    }

    /**
     * Get all connection names
     *
     * @return array
     */
    public static function getConnections(): array
    {
        if (!self::$booted) {
            throw new \RuntimeException('Eloquent has not been booted yet.');
        }

        return array_keys(self::$capsule->getDatabaseManager()->getConnections());
    }
}
