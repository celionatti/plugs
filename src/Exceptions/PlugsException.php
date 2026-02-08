<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Base Plugs Exception
|--------------------------------------------------------------------------
|
| This is the base exception class for the Plugs framework. All custom
| exceptions should extend this class for consistent error handling.
*/

use Exception;
use Throwable;

class PlugsException extends Exception
{
    /**
     * Additional context for the exception.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * HTTP status code for the exception.
     *
     * @var int
     */
    protected int $statusCode = 500;

    /**
     * Create a new Plugs exception.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get the context for the exception.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set the context for the exception.
     *
     * @param array $context
     * @return static
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode
     * @return static
     */
    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report(): ?bool
    {
        return null; // Return false to prevent default logging
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @return mixed
     */
    public function render(): mixed
    {
        return null; // Return null to use default rendering
    }

    /**
     * Convert the exception to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'status' => $this->statusCode,
            'context' => $this->context,
        ];
    }
}
