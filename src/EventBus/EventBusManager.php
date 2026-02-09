<?php

declare(strict_types=1);

namespace Plugs\EventBus;

use InvalidArgumentException;
use Plugs\Container\Container;

/**
 * Factory for creating event bus instances.
 */
class EventBusManager
{
    private static ?EventBusInterface $instance = null;
    private static array $drivers = [];

    /**
     * Get the default event bus instance.
     */
    public static function bus(?string $driver = null): EventBusInterface
    {
        $driver = $driver ?? env('EVENT_BUS_DRIVER', 'sync');

        if (!isset(self::$drivers[$driver])) {
            self::$drivers[$driver] = self::createDriver($driver);
        }

        return self::$drivers[$driver];
    }

    /**
     * Create a driver instance.
     */
    private static function createDriver(string $driver): EventBusInterface
    {
        return match ($driver) {
            'sync' => new Drivers\SyncEventBus(),
            'redis' => self::createRedisDriver(),
            default => throw new InvalidArgumentException("Unsupported event bus driver: {$driver}"),
        };
    }

    /**
     * Create Redis driver with connection.
     */
    private static function createRedisDriver(): EventBusInterface
    {
        $redis = new \Redis();
        $redis->connect(
            env('REDIS_HOST', '127.0.0.1'),
            (int) env('REDIS_PORT', 6379)
        );

        $password = env('REDIS_PASSWORD');
        if ($password) {
            $redis->auth($password);
        }

        return new Drivers\RedisEventBus($redis);
    }

    /**
     * Publish a message to the default bus.
     */
    public static function publish(string $channel, array $payload, array $options = []): ?string
    {
        return self::bus()->publish($channel, $payload, $options);
    }

    /**
     * Subscribe to a channel on the default bus.
     */
    public static function subscribe(string $channel, callable $handler, ?string $group = null): void
    {
        self::bus()->subscribe($channel, $handler, $group);
    }

    /**
     * Reset all driver instances (for testing).
     */
    public static function reset(): void
    {
        self::$drivers = [];
        self::$instance = null;
    }
}
