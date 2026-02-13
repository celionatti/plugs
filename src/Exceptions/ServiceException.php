<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Service Exception
|--------------------------------------------------------------------------
|
| Generic exception for service-layer errors such as notification channel
| failures, mail service misconfiguration, or missing service methods.
*/

/**
 * @phpstan-consistent-constructor
 */
class ServiceException extends PlugsException
{
    /**
     * The service that caused the error.
     *
     * @var string
     */
    protected string $service = '';

    /**
     * Create a new service exception.
     *
     * @param string $message
     * @param string $service
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = 'A service error occurred.',
        string $service = '',
        ?\Throwable $previous = null
    ) {
        $this->service = $service;
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the service name.
     *
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * Create an exception for a missing service method.
     *
     * @param string $method
     * @param string $service
     * @return static
     */
    public static function missingMethod(string $method, string $service = ''): static
    {
        return new static("Service is missing required method [{$method}].", $service);
    }

    /**
     * Create an exception for a missing dependency package.
     *
     * @param string $package
     * @param string $service
     * @return static
     */
    public static function missingPackage(string $package, string $service = ''): static
    {
        return new static(
            "Required package [{$package}] is not installed. Install it via: composer require {$package}",
            $service
        );
    }
}
