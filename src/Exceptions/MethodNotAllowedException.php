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
    protected array $allowedMethods = [];

    /**
     * Create a new method not allowed exception.
     *
     * @param array $allowedMethods
     * @param string $message
     */
    public function __construct(array $allowedMethods = [], string $message = 'Method Not Allowed')
    {
        $this->allowedMethods = $allowedMethods;
        parent::__construct(405, $message);
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
