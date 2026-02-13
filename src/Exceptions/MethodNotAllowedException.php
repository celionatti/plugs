<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Method Not Allowed Exception
|--------------------------------------------------------------------------
|
| Thrown when a route exists for a path but not for the requested method.
*/

class MethodNotAllowedException extends HttpException
{
    /**
     * The allowed methods for the requested route.
     *
     * @var array
     */
    protected $allowedMethods;

    /**
     * Create a new method not allowed exception.
     *
     * @param int $statusCode
     * @param string $message
     * @param \Throwable|null $previous
     * @param array $headers
     * @param array $allowedMethods
     */
    public function __construct(int $statusCode = 405, string $message = 'Method Not Allowed', ?\Throwable $previous = null, array $headers = [], array $allowedMethods = [])
    {
        $this->allowedMethods = $allowedMethods;
        parent::__construct($statusCode, $message, $previous, $headers);
    }

    /**
     * Get the allowed methods.
     *
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * Get the headers for the exception.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return [
            'Allow' => strtoupper(implode(', ', $this->allowedMethods)),
        ];
    }
}
