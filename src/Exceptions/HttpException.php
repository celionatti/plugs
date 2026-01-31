<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| HTTP Exception
|--------------------------------------------------------------------------
|
| Generic HTTP exception for returning specific HTTP status codes.
*/

class HttpException extends PlugsException
{
    /**
     * The headers to be sent with the response.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * Create a new HTTP exception.
     *
     * @param int $statusCode
     * @param string $message
     * @param \Throwable|null $previous
     * @param array $headers
     */
    public function __construct(
        int $statusCode = 500,
        string $message = '',
        ?\Throwable $previous = null,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        if (empty($message)) {
            $message = $this->getDefaultMessage($statusCode);
        }

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get the HTTP headers.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set the HTTP headers.
     *
     * @param array $headers
     * @return static
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Get the default message for a status code.
     *
     * @param int $statusCode
     * @return string
     */
    protected function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'An error occurred.',
        };
    }

    /**
     * Create a 400 Bad Request exception.
     *
     * @param string $message
     * @return static
     */
    public static function badRequest(string $message = ''): static
    {
        return new static(400, $message);
    }

    /**
     * Create a 404 Not Found exception.
     *
     * @param string $message
     * @return static
     */
    public static function notFound(string $message = ''): static
    {
        return new static(404, $message);
    }

    /**
     * Create a 405 Method Not Allowed exception.
     *
     * @param array $allowed
     * @param string $message
     * @return static
     */
    public static function methodNotAllowed(array $allowed = [], string $message = ''): static
    {
        $headers = !empty($allowed) ? ['Allow' => implode(', ', $allowed)] : [];
        return new static(405, $message, null, $headers);
    }

    /**
     * Create a 429 Too Many Requests exception.
     *
     * @param int|null $retryAfter
     * @param string $message
     * @return static
     */
    public static function tooManyRequests(?int $retryAfter = null, string $message = ''): static
    {
        $headers = $retryAfter ? ['Retry-After' => (string) $retryAfter] : [];
        return new static(429, $message, null, $headers);
    }

    /**
     * Create a 500 Internal Server Error exception.
     *
     * @param string $message
     * @return static
     */
    public static function serverError(string $message = ''): static
    {
        return new static(500, $message);
    }

    /**
     * Create a 503 Service Unavailable exception.
     *
     * @param int|null $retryAfter
     * @param string $message
     * @return static
     */
    public static function serviceUnavailable(?int $retryAfter = null, string $message = ''): static
    {
        $headers = $retryAfter ? ['Retry-After' => (string) $retryAfter] : [];
        return new static(503, $message, null, $headers);
    }
}
