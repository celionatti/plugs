<?php

declare(strict_types=1);

namespace Plugs\Log;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/*
|--------------------------------------------------------------------------
| LogManager
|--------------------------------------------------------------------------
|
| Multi-channel logging manager. Allows routing log messages to different
| channels (file, daily, stderr, stack).
|
| Usage:
|   $logManager = new LogManager();
|   $logManager->channel('daily')->info('Rotated log entry');
|   $logManager->channel('stderr')->error('Critical failure');
|   $logManager->info('Uses default channel');
*/

class LogManager extends Logger
{
    /** @var array<string, LoggerInterface> Resolved channel instances */
    private array $channels = [];

    /** @var array<string, callable> Channel factory closures */
    private array $customCreators = [];

    private string $defaultChannel;
    private array $config;

    public function __construct(?string $logPath = null)
    {
        $this->config = config('logging', []);
        $this->defaultChannel = $this->config['default'] ?? 'file';

        // Initialize the default channel's logger as the parent
        $channelConfig = $this->config['channels'][$this->defaultChannel] ?? [];
        $driver = $channelConfig['driver'] ?? 'single';
        $path = $channelConfig['path'] ?? $logPath ?? storage_path('logs/plugs.log');

        if ($driver === 'daily') {
            $maxFiles = $channelConfig['max_files'] ?? 14;
            $defaultLogger = new RotatingFileLogger($path, $maxFiles);
        } else {
            $defaultLogger = new Logger($path);
        }

        // Store the default channel
        $this->channels[$this->defaultChannel] = $defaultLogger;

        // Initialize parent with the resolved path
        parent::__construct($path);
    }

    /**
     * Get a log channel instance by name.
     *
     * @param string|null $name Channel name (from config/logging.php)
     * @return LoggerInterface
     */
    public function channel(?string $name = null): LoggerInterface
    {
        $name = $name ?? $this->defaultChannel;

        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        return $this->channels[$name] = $this->resolveChannel($name);
    }

    /**
     * Create a stack channel that writes to multiple channels.
     *
     * @param array $channels List of channel names
     * @return LoggerInterface
     */
    public function stack(array $channels): LoggerInterface
    {
        $loggers = array_map(fn(string $ch) => $this->channel($ch), $channels);

        return new StackLogger($loggers);
    }

    /**
     * Register a custom channel creator.
     *
     * @param string   $driver  Driver name
     * @param callable $creator Factory: fn(array $config): LoggerInterface
     */
    public function extend(string $driver, callable $creator): void
    {
        $this->customCreators[$driver] = $creator;
    }

    /**
     * @inheritdoc — Delegate to the default channel
     */
    public function log($level, $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    /**
     * Resolve a channel from configuration.
     */
    private function resolveChannel(string $name): LoggerInterface
    {
        $channelConfig = $this->config['channels'][$name] ?? null;

        if ($channelConfig === null) {
            throw new InvalidArgumentException("Log channel [{$name}] is not configured.");
        }

        $driver = $channelConfig['driver'] ?? 'single';

        // Check custom creators first
        if (isset($this->customCreators[$driver])) {
            return ($this->customCreators[$driver])($channelConfig);
        }

        return match ($driver) {
            'single' => $this->createSingleDriver($channelConfig),
            'daily' => $this->createDailyDriver($channelConfig),
            'stderr' => $this->createStderrDriver($channelConfig),
            'stack' => $this->createStackDriver($channelConfig),
            default => throw new InvalidArgumentException("Log driver [{$driver}] is not supported."),
        };
    }

    private function createSingleDriver(array $config): LoggerInterface
    {
        $path = $config['path'] ?? storage_path('logs/plugs.log');
        return new Logger($path);
    }

    private function createDailyDriver(array $config): LoggerInterface
    {
        $path = $config['path'] ?? storage_path('logs/plugs.log');
        $maxFiles = $config['max_files'] ?? 14;
        return new RotatingFileLogger($path, $maxFiles);
    }

    private function createStderrDriver(array $config): LoggerInterface
    {
        // Write to php://stderr — useful for Docker / serverless
        return new Logger('php://stderr');
    }

    private function createStackDriver(array $config): LoggerInterface
    {
        $channels = $config['channels'] ?? [];
        return $this->stack($channels);
    }
}
