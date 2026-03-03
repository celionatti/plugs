<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Token Mismatch Exception
|--------------------------------------------------------------------------
|
| Thrown when the CSRF token is invalid or missing.
*/

/**
 * @phpstan-consistent-constructor
 */
class TokenMismatchException extends HttpException
{
    /**
     * Create a new token mismatch exception.
     *
     * @param string $message
     */
    public function __construct(
        int $statusCode = 419,
        string $message = 'CSRF token mismatch.',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        parent::__construct($statusCode, $message, $previous, $headers);
    }
}
