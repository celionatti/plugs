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
        'auth' => \Plugs\Http\Middleware\AuthenticateMiddleware::class,
        'guest' => \app\Http\Middleware\GuestMiddleware::class,
        'csrf' => \Plugs\Http\Middleware\CsrfMiddleware::class,
        'cors' => \Plugs\Http\Middleware\CorsMiddleware::class,
        'shield' => \Plugs\Http\Middleware\SecurityShieldMiddleware::class,
        'json' => \Plugs\Http\Middleware\ForceJsonMiddleware::class,
        'throttle' => \Plugs\Http\Middleware\RateLimitMiddleware::class,
        'spa' => \Plugs\Http\Middleware\SPAMiddleware::class,
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
        \Plugs\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Plugs\Http\Middleware\SecurityHeadersMiddleware::class,
        \Plugs\Http\Middleware\SPAMiddleware::class,
        \Plugs\Http\Middleware\FlashMiddleware::class,
        \Plugs\Http\Middleware\ShareErrorsFromSession::class,
        \Plugs\Http\Middleware\HandleValidationExceptions::class,
    ];

    /**
     * Explicit layer assignments for classes (overrides metadata in class).
     */
    protected array $layers = [
        \Plugs\Http\Middleware\PreventRequestsDuringMaintenance::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\SecurityHeadersMiddleware::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\SecurityShieldMiddleware::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\CsrfMiddleware::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\CorsMiddleware::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\RateLimitMiddleware::class => MiddlewareLayer::SECURITY,
        \Plugs\Http\Middleware\ProfilerMiddleware::class => MiddlewareLayer::PERFORMANCE,
        \Plugs\Http\Middleware\SPAMiddleware::class => MiddlewareLayer::BUSINESS,
    ];

    /**
     * Default priority for middleware execution.
     * Lower values run first.
     * This ensures security middlewares always execute before logic.
     */
    protected array $priority = [
        \Plugs\Http\Middleware\PreventRequestsDuringMaintenance::class => 5,
        \Plugs\Http\Middleware\SecurityHeadersMiddleware::class => 10,
        \Plugs\Http\Middleware\SecurityShieldMiddleware::class => 20,
        \Plugs\Http\Middleware\SPAMiddleware::class => 30,
        \Plugs\Http\Middleware\CorsMiddleware::class => 40,
        \Plugs\Http\Middleware\ForceJsonMiddleware::class => 45,
        \Plugs\Http\Middleware\CsrfMiddleware::class => 50,
        \Plugs\Http\Middleware\RateLimitMiddleware::class => 60,
        \Plugs\Http\Middleware\ShareErrorsFromSession::class => 70,
        \Plugs\Http\Middleware\HandleValidationExceptions::class => 80,
        \Plugs\Http\Middleware\AuthenticateMiddleware::class => 100,
    ];


    /**
     * Create a new MiddlewareRegistry instance.
     * 
     * @param array $config Configuration that can override defaults
     */
    public function __construct(array $config = [])
    {
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

}
