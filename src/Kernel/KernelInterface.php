<?php

declare(strict_types=1);

namespace Plugs\Kernel;

use Plugs\Bootstrap\ContextType;

/**
 * Contract that all application kernels must implement.
 *
 * Each kernel represents a specific execution context (Web, API, CLI, etc.)
 * and defines its own optimized bootstrap and middleware pipeline.
 */
interface KernelInterface
{
    /**
     * Get the context type this kernel handles.
     */
    public function getContext(): ContextType;

    /**
     * Boot kernel-specific services.
     *
     * This is where context-specific bootstrapping happens:
     * - WebKernel boots session, CSRF, views
     * - ApiKernel boots CORS, rate limiting
     * - CliKernel boots console services
     * - QueueKernel boots queue drivers
     */
    public function boot(): void;

    /**
     * Get the middleware layers for this kernel.
     *
     * Returns a categorized array:
     * [
     *     'security'    => [...],  // Security middleware (runs first)
     *     'performance' => [...],  // Performance/monitoring middleware
     *     'business'    => [...],  // Business logic middleware (runs last)
     * ]
     *
     * @return array<string, array<string>>
     */
    public function getMiddlewareLayers(): array;

    /**
     * Get the flattened, ordered middleware stack.
     *
     * @return array<string>
     */
    public function getMiddleware(): array;

    /**
     * Perform cleanup after the request/command has been handled.
     */
    public function terminate(): void;

    /**
     * Whether this kernel has been booted.
     */
    public function isBooted(): bool;
}
