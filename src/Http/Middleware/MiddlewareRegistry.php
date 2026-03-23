<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Bootstrap\ContextType;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\MiddlewareMetadata;

/**
 * Centralized registry for middleware aliases, groups, and priority.
 */
class MiddlewareRegistry
{
    /**
     * Middleware aliases for easy reference.
     * These can be used in routes or groups.
     */
    protected array $aliases = [
        'auth' => AuthenticateMiddleware::class,
        'guest' => \App\Http\Middleware\GuestMiddleware::class,
        'csrf' => CsrfMiddleware::class,
        'cors' => CorsMiddleware::class,
        'shield' => SecurityShieldMiddleware::class,
        'json' => ForceJsonMiddleware::class,
        'throttle' => RateLimitMiddleware::class,
        'spa' => SPAMiddleware::class,
        'ai.moderate' => AIContentModerationMiddleware::class,
        'verified' => EnsureEmailIsVerified::class,
    ];

    /**
     * Middleware groups.
     * Useful for grouping common middleware for different route sections.
     */
    protected array $groups = [
        'web' => [
            'shield',
            'spa',
            'csrf',
            'cors',
            'verified',
        ],
        'api' => [
            'json',
            'throttle',
        ],
    ];

    /**
     * Global middleware that runs on EVERY request before any other middleware.
     * This is the "hardwired" security layer.
     */
    protected array $kernel = [
        PreventRequestsDuringMaintenance::class,
        SecurityHeadersMiddleware::class,
        SPAMiddleware::class,
        FlashMiddleware::class,
        ShareErrorsFromSession::class,
        HandleValidationExceptions::class,
        EnsureEmailIsVerified::class,
    ];

    /**
     * Explicit layer assignments for classes (overrides metadata in class).
     */
    protected array $layers = [
        PreventRequestsDuringMaintenance::class => MiddlewareLayer::SECURITY,
        SecurityHeadersMiddleware::class => MiddlewareLayer::SECURITY,
        SecurityShieldMiddleware::class => MiddlewareLayer::SECURITY,
        CsrfMiddleware::class => MiddlewareLayer::SECURITY,
        CorsMiddleware::class => MiddlewareLayer::SECURITY,
        RateLimitMiddleware::class => MiddlewareLayer::SECURITY,
        ProfilerMiddleware::class => MiddlewareLayer::PERFORMANCE,
        SPAMiddleware::class => MiddlewareLayer::BUSINESS,
    ];

    /**
     * Default priority for middleware execution.
     * Lower values run first.
     * This ensures security middlewares always execute before logic.
     */
    protected array $priority = [
        PreventRequestsDuringMaintenance::class => 5,
        SecurityHeadersMiddleware::class => 10,
        SecurityShieldMiddleware::class => 20,
        SPAMiddleware::class => 30,
        CorsMiddleware::class => 40,
        ForceJsonMiddleware::class => 45,
        CsrfMiddleware::class => 50,
        RateLimitMiddleware::class => 60,
        ShareErrorsFromSession::class => 70,
        HandleValidationExceptions::class => 80,
        AuthenticateMiddleware::class => 100,
        EnsureEmailIsVerified::class => 110,
    ];


    /**
     * Create a new MiddlewareRegistry instance.
     * 
     * @param array $config Configuration that can override defaults
     */
    public function __construct(?array $config = [])
    {
        $config = $config ?? [];
        if (isset($config['aliases'])) {
            $this->aliases = array_merge($this->aliases, $config['aliases']);
        }
        if (isset($config['groups'])) {
            $this->groups = array_merge($this->groups, $config['groups']);
        }
        if (isset($config['priority'])) {
            $this->priority = array_merge($this->priority, $config['priority']);
        }
        if (isset($config['layers'])) {
            $this->layers = array_merge($this->layers, $config['layers']);
        }
    }

    /**
     * Add a middleware alias dynamically.
     */
    public function addAlias(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    /**
     * Add a middleware group dynamically.
     */
    public function addGroup(string $name, array $middleware): void
    {
        $this->groups[$name] = $middleware;
    }

    /**
     * Resolve a middleware alias or group name into actual class strings.
     * 
     * @param string|array $middleware The alias, group name, or class string
     * @return array Array of resolved class names
     */
    /**
     * Resolve a middleware alias or group name into actual class strings.
     * Supports parameters: "alias:param1,param2"
     * 
     * @param string|array $middleware The alias, group name, or class string
     * @return array Array of resolved class names (optionally with parameters)
     */
    public function resolve(string|array $middleware): array
    {
        if (is_array($middleware)) {
            $resolved = [];
            foreach ($middleware as $m) {
                $resolved = array_merge($resolved, $this->resolve($m));
            }
            return array_unique($resolved);
        }

        $params = '';
        if (strpos($middleware, ':') !== false) {
            [$middleware, $params] = explode(':', $middleware, 2);
            $params = ':' . $params;
        }

        // Check if it's a group (groups don't support direct params on the group name itself usually, 
        // but we apply them to members if it ever makes sense. For now, just expand.)
        if (isset($this->groups[$middleware])) {
            return $this->resolve($this->groups[$middleware]);
        }

        // Check if it's an alias
        if (isset($this->aliases[$middleware])) {
            $resolvedClass = $this->aliases[$middleware];
            return [$resolvedClass . $params];
        }

        // Fallback: assume it's a class string
        return [$middleware . $params];
    }


    /**
     * Get the global kernel middleware stack.
     * If a context is provided, returns the appropriate stack for that context.
     */
    public function getKernel(?ContextType $context = null): array
    {
        if ($context !== null) {
            return $this->getKernelForContext($context);
        }

        return $this->kernel;
    }

    /**
     * Get the kernel middleware stack for a specific context.
     *
     * Web gets the full kernel stack (session-aware middleware).
     * API gets a reduced stack (no session, no flash, no SPA).
     * CLI/Queue get no kernel middleware (no HTTP at all).
     *
     * @return array<string>
     */
    public function getKernelForContext(ContextType $context): array
    {
        return match ($context) {
            ContextType::Web => $this->kernel,
            ContextType::Api => array_filter($this->kernel, function (string $mw) {
                    // API doesn't need session-dependent middleware
                    $excluded = [
                    SPAMiddleware::class,
                    FlashMiddleware::class,
                    ShareErrorsFromSession::class,
                    ];
                    return !in_array($mw, $excluded, true);
                }),
            ContextType::Realtime => [
                PreventRequestsDuringMaintenance::class,
                SecurityHeadersMiddleware::class,
            ],
            // CLI, Queue, Cron — no HTTP middleware
            default => [],
        };
    }

    /**
     * Set the global kernel middleware stack.
     */
    public function setKernel(array $kernel): void
    {
        $this->kernel = $kernel;
    }


    /**
     * Get the priority of a middleware class.
     * Defaults to metadata defined in the class or 500.
     */
    public function getPriority(string $class): int
    {
        if (isset($this->priority[$class])) {
            return $this->priority[$class];
        }

        return MiddlewareMetadata::extract($class)['priority'];
    }

    /**
     * Get the layer of a middleware class.
     */
    public function getLayer(string $class): MiddlewareLayer
    {
        if (isset($this->layers[$class])) {
            $layer = $this->layers[$class];
            return $layer instanceof MiddlewareLayer ? $layer : MiddlewareLayer::tryFrom($layer) ?? MiddlewareLayer::BUSINESS;
        }

        return MiddlewareMetadata::extract($class)['layer'];
    }

    /**
     * Get full metadata for a middleware class.
     */
    public function getMetadata(string $class): array
    {
        return [
            'layer' => $this->getLayer($class),
            'priority' => $this->getPriority($class),
        ];
    }

    /**
     * Sort an array of middleware by their defined priority.
     */
    public function sort(array $middleware): array
    {
        usort($middleware, function ($a, $b) {
            $classA = is_string($a) ? $a : get_class($a);
            $classB = is_string($b) ? $b : get_class($b);

            // Strip parameters for priority lookup
            if (strpos($classA, ':') !== false) {
                $classA = explode(':', $classA, 2)[0];
            }
            if (strpos($classB, ':') !== false) {
                $classB = explode(':', $classB, 2)[0];
            }

            $metaA = $this->getMetadata($classA);
            $metaB = $this->getMetadata($classB);

            // First compare layers
            $order = MiddlewareLayer::getOrder();
            $layerA = array_search($metaA['layer'], $order, true);
            $layerB = array_search($metaB['layer'], $order, true);

            if ($layerA !== $layerB) {
                return $layerA <=> $layerB;
            }

            // Then compare priorities within the same layer
            if ($metaA['priority'] === $metaB['priority']) {
                return 0;
            }

            return ($metaA['priority'] < $metaB['priority']) ? -1 : 1;
        });

        return $middleware;
    }

    /**
     * Group middleware by their respective layers.
     * 
     * @param array $middleware
     * @return array<string, array<string>>
     */
    public function orchestrate(array $middleware): array
    {
        $orchestrated = [];
        foreach (MiddlewareLayer::cases() as $layer) {
            $orchestrated[$layer->value] = [];
        }

        foreach ($middleware as $mw) {
            $class = is_string($mw) ? $mw : get_class($mw);
            $actualClass = $class;
            if (strpos($class, ':') !== false) {
                $actualClass = explode(':', $class, 2)[0];
            }

            $layer = $this->getLayer($actualClass);
            $orchestrated[$layer->value][] = $mw;
        }

        // Sort each layer individually
        foreach ($orchestrated as $layer => &$mws) {
            $mws = $this->sort($mws);
        }

        return $orchestrated;
    }
    /**
     * Get a unique cache key for a given middleware stack and context.
     * 
     * @param array $middleware
     * @param \Plugs\Bootstrap\ContextType|null $context
     * @return string
     */
    public function getCacheKey(array $middleware, ?ContextType $context = null): string
    {
        $parts = array_map(function ($mw) {
            return is_string($mw) ? $mw : get_class($mw);
        }, $middleware);

        sort($parts);

        return 'mw_pipeline:' . md5(implode('|', $parts) . ($context ? $context->value : 'none'));
    }

}
