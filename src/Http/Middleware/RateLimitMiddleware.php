<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| RateLimitMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles rate limiting for incoming requests.
| Uses the Cache system for persistent storage across requests.
| Includes behavior-based throttling using ThreatDetector.
*/

use Plugs\Exceptions\RateLimitException;
use Plugs\Security\ThreatDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $perMinutes;

    /**
     * In-memory fallback when the cache system is unavailable.
     */
    private static array $memoryStorage = [];

    public function __construct(int $maxRequests = 60, int $perMinutes = 1)
    {
        $this->maxRequests = $maxRequests;
        $this->perMinutes = $perMinutes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = 'rate_limit:' . $this->resolveRequestIdentifier($request);
        $windowSeconds = $this->perMinutes * 60;
        $currentTime = time();

        // Dynamic limit based on threat score
        $effectiveLimit = $this->calculateEffectiveLimit($request);

        // Try to get existing data from cache
        $data = $this->getData($key);

        if ($data === null || $currentTime > $data['reset_at']) {
            $data = [
                'count' => 0,
                'reset_at' => $currentTime + $windowSeconds,
            ];
        }

        $data['count']++;

        // Persist to cache for the remaining window duration
        $ttl = max(1, $data['reset_at'] - $currentTime);
        $this->setData($key, $data, $ttl);

        if ($data['count'] > $effectiveLimit) {
            throw new RateLimitException('Too many requests', $data['reset_at'] - $currentTime);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $effectiveLimit)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $effectiveLimit - $data['count']))
            ->withHeader('X-RateLimit-Reset', (string) $data['reset_at']);
    }

    private function resolveRequestIdentifier(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Calculate effective rate limit based on threat score.
     * Suspicious clients get lower limits.
     */
    private function calculateEffectiveLimit(ServerRequestInterface $request): int
    {
        $threatScore = ThreatDetector::analyze($request);

        if ($threatScore >= 15) {
            return max(5, (int) ($this->maxRequests * 0.1)); // 10% of normal
        }
        if ($threatScore >= 10) {
            return max(10, (int) ($this->maxRequests * 0.25)); // 25% of normal
        }
        if ($threatScore >= 5) {
            return max(20, (int) ($this->maxRequests * 0.5)); // 50% of normal
        }

        return $this->maxRequests;
    }

    /**
     * Get rate limit data from cache, falling back to in-memory storage.
     */
    private function getData(string $key): ?array
    {
        // Try the cache system first
        if (function_exists('cache')) {
            try {
                $cache = cache();
                if ($cache !== null) {
                    $data = $cache->get($key);
                    return is_array($data) ? $data : null;
                }
            } catch (\Throwable) {
                // Cache unavailable, fall through to memory
            }
        }

        return self::$memoryStorage[$key] ?? null;
    }

    /**
     * Persist rate limit data to cache, falling back to in-memory storage.
     */
    private function setData(string $key, array $data, int $ttl): void
    {
        if (function_exists('cache')) {
            try {
                $cache = cache();
                if ($cache !== null) {
                    $cache->set($key, $data, $ttl);
                    return;
                }
            } catch (\Throwable) {
                // Cache unavailable, fall through to memory
            }
        }

        self::$memoryStorage[$key] = $data;
    }
}
