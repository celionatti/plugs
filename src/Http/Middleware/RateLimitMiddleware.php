<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| RateLimitMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles rate limiting for incoming requests.
| Now includes behavior-based throttling using ThreatDetector.
*/

use Plugs\Security\ThreatDetector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $perMinutes;
    private static array $storage = [];

    public function __construct(int $maxRequests = 60, int $perMinutes = 1)
    {
        $this->maxRequests = $maxRequests;
        $this->perMinutes = $perMinutes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveRequestIdentifier($request);
        $currentTime = time();

        // Dynamic limit based on threat score
        $effectiveLimit = $this->calculateEffectiveLimit($request);

        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = [
                'count' => 0,
                'reset_at' => $currentTime + ($this->perMinutes * 60),
            ];
        }

        $data = &self::$storage[$key];

        if ($currentTime > $data['reset_at']) {
            $data['count'] = 0;
            $data['reset_at'] = $currentTime + ($this->perMinutes * 60);
        }

        $data['count']++;

        if ($data['count'] > $effectiveLimit) {
            throw new \RuntimeException('Too many requests', 429);
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
}
