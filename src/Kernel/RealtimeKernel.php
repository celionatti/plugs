<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Http\Middleware\CorsMiddleware;
use Plugs\Http\Middleware\ForceJsonMiddleware;
use Plugs\Http\Middleware\RateLimitMiddleware;
use Plugs\Http\Middleware\SecurityHeadersMiddleware;

/**
 * Realtime Kernel — WebSocket / persistent connection pipeline.
 *
 * No session per-request (persistent connections), no CSRF.
 * Optimized for long-lived connections with CORS, security headers,
 * and rate limiting.
 */
class RealtimeKernel extends AbstractKernel
{
    protected array $middlewareLayers = [
        'security' => [
            SecurityHeadersMiddleware::class,
            CorsMiddleware::class,
            RateLimitMiddleware::class,
        ],
        'performance' => [],
        'business' => [
            ForceJsonMiddleware::class,
        ],
    ];

    protected function bootServices(): void
    {
        $this->configureDatabase();
        $this->setupRequest();

        // Realtime kernel boots event system for pub/sub
        // The event dispatcher is already available as a deferred service
    }

    /**
     * Cleanup on connection close.
     */
    public function terminate(): void
    {
        // Allow memory cleanup for long-running processes
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
