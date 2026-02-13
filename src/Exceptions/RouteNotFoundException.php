<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Route Not Found Exception
|--------------------------------------------------------------------------
|
| Thrown when a route is not found.
*/

class RouteNotFoundException extends PlugsException
{
    /**
     * HTTP status code for not found errors.
     *
     * @var int
     */
    protected int $statusCode = 404;

    /**
     * The requested path.
     *
     * @var string
     */
    protected string $path = '';

    /**
     * The HTTP method.
     *
     * @var string
     */
    protected string $method = '';

    /**
     * Create a new route not found exception.
     *
     * @param string $path
     * @param string $method
     * @param string $message
     */
    public function __construct(string $path = '', string $method = '', string $message = '')
    {
        $this->path = $path;
        $this->method = $method;

        $message = $message ?: $this->formatMessage();
        parent::__construct($message);
    }

    /**
     * Get the requested path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the HTTP method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Format the exception message.
     *
     * @return string
     */
    protected function formatMessage(): string
    {
        if (empty($this->path) && empty($this->method)) {
            return 'The requested route could not be found.';
        }

        if (empty($this->method)) {
            return "Route [{$this->path}] not found.";
        }

        return "Route [{$this->method}] [{$this->path}] not found.";
    }
}
