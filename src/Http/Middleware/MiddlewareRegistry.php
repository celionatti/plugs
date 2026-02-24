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
     */
    public function getKernel(): array
    {
        return $this->kernel;
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

            // Strip parameters for priority lookup
            if (strpos($classA, ':') !== false) {
                $classA = explode(':', $classA, 2)[0];
            }
            if (strpos($classB, ':') !== false) {
                $classB = explode(':', $classB, 2)[0];
            }

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
