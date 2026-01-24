<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| RateLimitMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles rate limiting for incoming requests.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private $maxRequests;
    private $perMinutes;
    private $storage = [];

    public function __construct(int $maxRequests = 60, int $perMinutes = 1)
    {
        $this->maxRequests = $maxRequests;
        $this->perMinutes = $perMinutes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveRequestIdentifier($request);
        $currentTime = time();

        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [
                'count' => 0,
                'reset_at' => $currentTime + ($this->perMinutes * 60),
            ];
        }

        $data = &$this->storage[$key];

        if ($currentTime > $data['reset_at']) {
            $data['count'] = 0;
            $data['reset_at'] = $currentTime + ($this->perMinutes * 60);
        }

        $data['count']++;

        if ($data['count'] > $this->maxRequests) {
            throw new \RuntimeException('Too many requests', 429);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - $data['count']))
            ->withHeader('X-RateLimit-Reset', (string) $data['reset_at']);
    }

    private function resolveRequestIdentifier(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
