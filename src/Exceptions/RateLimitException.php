<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Rate Limit Exception
|--------------------------------------------------------------------------
|
| Thrown when a client exceeds the configured rate limit.
| Extends HttpException with status 429 and Retry-After header support.
*/

class RateLimitException extends HttpException
{
    /**
     * Seconds until the client can retry.
     *
     * @var int|null
     */
    protected ?int $retryAfter = null;

    /**
     * Create a new rate limit exception.
     *
     * @param string $message
     * @param int|null $retryAfter
     */
    public function __construct(string $message = 'Too Many Requests', ?int $retryAfter = null)
    {
        $this->retryAfter = $retryAfter;

        $headers = $retryAfter ? ['Retry-After' => (string) $retryAfter] : [];

        parent::__construct(429, $message, null, $headers);
    }

    /**
     * Get the retry-after value.
     *
     * @return int|null
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
