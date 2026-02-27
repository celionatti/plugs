<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| RateLimitMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles rate limiting for incoming requests.
| Supports named limiters configured via RateLimiter::for() in
| a service provider, as well as simple numeric throttling.
|
| Usage in routes:
|   ->middleware('throttle:login')      // Named limiter
|   ->middleware('throttle:60,1')       // 60 requests per 1 minute
|   ->middleware('throttle')            // Default: 60 requests per minute
*/

use Plugs\Exceptions\RateLimitException;
use Plugs\Security\RateLimitConfig;
use Plugs\Security\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

#[Middleware(layer: MiddlewareLayer::SECURITY, priority: 60)]
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $perMinutes;
    private ?string $limiterName = null;

    public function __construct(int|string $maxRequestsOrName = 60, int $perMinutes = 1)
    {
        // If the first param is non-numeric, it's a named limiter
        if (is_string($maxRequestsOrName) && !is_numeric($maxRequestsOrName)) {
            $this->limiterName = $maxRequestsOrName;
            $this->maxRequests = 60; // Fallback defaults
            $this->perMinutes = 1;
        } else {
            $this->maxRequests = (int) $maxRequestsOrName;
            $this->perMinutes = $perMinutes;
        }
    }

    /**
     * Set parameters from route middleware definition.
     * Called by the Router when resolving middleware like 'throttle:login' or 'throttle:60,1'.
     *
     * @param array $params
     * @return void
     */
    public function setParameters(array $params): void
    {
        if (empty($params)) {
            return;
        }

        $first = $params[0];

        if (is_numeric($first)) {
            // Numeric: throttle:60,1
            $this->maxRequests = (int) $first;
            $this->perMinutes = isset($params[1]) ? (int) $params[1] : 1;
            $this->limiterName = null;
        } else {
            // Named: throttle:login
            $this->limiterName = $first;
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // If a named limiter is configured, use it
        if ($this->limiterName !== null) {
            return $this->handleNamedLimiter($request, $handler);
        }

        // Otherwise fall back to simple IP-based throttling
        return $this->handleSimpleLimiter($request, $handler);
    }

    /**
     * Handle rate limiting using a named limiter from RateLimiter::for().
     */
    private function handleNamedLimiter(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $callback = RateLimiter::limiter($this->limiterName);

        if ($callback === null) {
            // No limiter registered with this name — pass through
            return $handler->handle($request);
        }

        $result = $callback($request);

        // Normalize to array of RateLimitConfig
        $configs = $result instanceof RateLimitConfig ? [$result] : (array) $result;

        $rateLimiter = $this->getRateLimiterInstance();

        foreach ($configs as $config) {
            if (!$config instanceof RateLimitConfig) {
                continue;
            }

            $key = 'throttle:' . ($config->key ?: $this->limiterName);

            if ($rateLimiter->tooManyAttempts($key, $config->maxAttempts)) {
                $retryAfter = $rateLimiter->availableIn($key);
                throw new RateLimitException(
                    'Too many requests. Please try again in ' . $retryAfter . ' seconds.',
                    $retryAfter
                );
            }

            // Increment the counter immediately
            $rateLimiter->hit($key, $config->decaySeconds);
        }

        return $handler->handle($request);
    }

    /**
     * Handle simple IP-based rate limiting (legacy / default behavior).
     */
    private function handleSimpleLimiter(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $rateLimiter = $this->getRateLimiterInstance();
        $key = 'throttle:' . $this->resolveRequestIdentifier($request);
        $windowSeconds = $this->perMinutes * 60;

        if ($rateLimiter->tooManyAttempts($key, $this->maxRequests)) {
            $retryAfter = $rateLimiter->availableIn($key);
            throw new RateLimitException('Too many requests', $retryAfter);
        }

        $response = $handler->handle($request);

        $rateLimiter->hit($key, $windowSeconds);

        $remaining = max(0, $this->maxRequests - $rateLimiter->attempts($key));

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    private function resolveRequestIdentifier(ServerRequestInterface $request): string
    {
        if (method_exists($request, 'ip')) {
            return $request->ip();
        }

        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get the RateLimiter service instance from the container.
     */
    private function getRateLimiterInstance(): RateLimiter
    {
        return \Plugs\Container\Container::getInstance()->make('ratelimiter');
    }
}
