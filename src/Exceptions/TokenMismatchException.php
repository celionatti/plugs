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

class TokenMismatchException extends HttpException
{
    /**
     * Create a new token mismatch exception.
     *
     * @param string $message
     */
    public function __construct(string $message = 'CSRF token mismatch.')
    {
        parent::__construct(419, $message);
    }
}
