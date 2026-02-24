<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

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
    }

    /**
     * Resolve a middleware alias or group name into actual class strings.
     * 
     * @param string|array $middleware The alias, group name, or class string
     * @return array Array of resolved class names
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

        // Check if it's a group
        if (isset($this->groups[$middleware])) {
            return $this->resolve($this->groups[$middleware]);
        }

        // Check if it's an alias
        if (isset($this->aliases[$middleware])) {
            return [$this->aliases[$middleware]];
        }

        // Fallback: assume it's a class string
        return [$middleware];
    }

    /**
     * Get the global kernel middleware stack.
     * 
     * @return array
     */
    public function getKernel(): array
    {
        return $this->kernel;
    }

    /**
     * Get the priority of a middleware class.
     * Defaults to 500 if not explicitly defined.
     * 
     * @param string $class
     * @return int
     */
    public function getPriority(string $class): int
    {
        return $this->priority[$class] ?? 500;
    }

    /**
     * Sort an array of middleware by their defined priority.
     * 
     * @param array $middleware Array of middleware instances or class strings
     * @return array Sorted array
     */
    public function sort(array $middleware): array
    {
        usort($middleware, function ($a, $b) {
            $classA = is_string($a) ? $a : get_class($a);
            $classB = is_string($b) ? $b : get_class($b);

            $pA = $this->getPriority($classA);
            $pB = $this->getPriority($classB);

            if ($pA === $pB) {
                return 0;
            }

            return ($pA < $pB) ? -1 : 1;
        });

        return $middleware;
    }
}
