<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Configuration Exception
|--------------------------------------------------------------------------
|
| Thrown when the application encounters an invalid or missing configuration.
| This covers missing database drivers, unconfigured connections, and invalid
| cast definitions.
*/

class ConfigurationException extends PlugsException
{
    /**
     * The configuration key that caused the error.
     *
     * @var string
     */
    protected string $key = '';

    /**
     * Create a new configuration exception.
     *
     * @param string $message
     * @param string $key
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'Invalid configuration.',
        string $key = '',
        ?\Throwable $previous = null
    ) {
        $this->key = $key;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the configuration key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Create an exception for a missing configuration key.
     *
     * @param string $key
     * @return static
     */
    public static function missingKey(string $key): static
    {
        return new static("Configuration key [{$key}] is not set.", $key);
    }

    /**
     * Create an exception for an unsupported driver.
     *
     * @param string $driver
     * @return static
     */
    public static function unsupportedDriver(string $driver): static
    {
        return new static("Unsupported driver: {$driver}", $driver);
    }

    /**
     * Create an exception for a missing connection.
     *
     * @param string $connection
     * @return static
     */
    public static function connectionNotConfigured(string $connection): static
    {
        return new static("Connection [{$connection}] not configured.", $connection);
    }
}
