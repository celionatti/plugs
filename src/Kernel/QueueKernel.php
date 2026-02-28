<?php

declare(strict_types=1);

namespace Plugs\Kernel;

/**
 * Queue Kernel — worker process pipeline.
 *
 * Minimal bootstrap with database and queue drivers only.
 * No HTTP middleware, no session, no routing, no view engine.
 */
class QueueKernel extends AbstractKernel
{
    /**
     * Queue workers have no HTTP middleware.
     */
    protected array $middlewareLayers = [
        'security' => [],
        'performance' => [],
        'business' => [],
    ];

    protected function bootServices(): void
    {
        // Queue manager is a deferred service in the container,
        // it will be resolved on first access via container->make('queue')
    }

    /**
     * Cleanup after queue processing.
     */
    public function terminate(): void
    {
        // Allow garbage collection between jobs
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
}
