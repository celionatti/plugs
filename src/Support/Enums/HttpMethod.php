<?php

declare(strict_types=1);

namespace Plugs\Support\Enums;

/**
 * HTTP Method Enum
 */
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
    case CONNECT = 'CONNECT';
    case TRACE = 'TRACE';

    /**
     * Check if method is safe (doesn't modify state)
     */
    public function isSafe(): bool
    {
        return in_array($this, [self::GET, self::HEAD, self::OPTIONS, self::TRACE]);
    }

    /**
     * Check if method is idempotent
     */
    public function isIdempotent(): bool
    {
        return $this->isSafe() || in_array($this, [self::PUT, self::DELETE]);
    }
}
