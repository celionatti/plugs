<?php

declare(strict_types=1);

namespace Plugs\Container;

/*
|--------------------------------------------------------------------------
| Container Class
|--------------------------------------------------------------------------
|
| This class is responsible for managing dependencies and services
| within the application. It provides methods to register and retrieve.
*/

use Closure;
use ReflectionClass;
use ReflectionException;

class Container
{
    private static $instance = null;

    private $bindings = [];
    private $instances = [];
    private $aliases = [];

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bind a class or interface to an implementation
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * Bind a singleton (shared instance)
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind an existing instance
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Resolve a class from the container
     */
    public function make(string $abstract, array $parameters = [])
    {
        // Check for alias
        if (isset($this->aliases[$abstract])) {
            $abstract = $this->aliases[$abstract];
        }

        // Check if instance exists
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get concrete implementation
        $concrete = $this->getConcrete($abstract);

        // Build the object
        $object = $this->build($concrete, $parameters);

        // Store as singleton if needed
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the concrete implementation
     */
    private function getConcrete(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Build an instance of the given class
     */
    private function build($concrete, array $parameters = [])
    {
        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            return $concrete($this, $parameters);
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new \RuntimeException("Target class [{$concrete}] does not exist.", 0, $e);
        }

        // Check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Target [{$concrete}] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        // If no constructor, just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     */
    private function resolveDependencies(array $parameters, array $primitives = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            // Check if primitive value provided
            if (isset($primitives[$name])) {
                $dependencies[] = $primitives[$name];

                continue;
            }

            // Get parameter type
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                // Handle primitive types
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \RuntimeException(
                        "Cannot resolve primitive parameter [{$name}]"
                    );
                }
            } else {
                // Resolve class dependency
                $dependencies[] = $this->make($type->getName());
            }
        }

        return $dependencies;
    }

    /**
     * Call a method with dependency injection
     */
    public function call($callback, array $parameters = [])
    {
        if (is_string($callback) && strpos($callback, '@') !== false) {
            $callback = explode('@', $callback);
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;

            if (is_string($class)) {
                $class = $this->make($class);
            }

            $callback = [$class, $method];
        }

        if (!is_callable($callback)) {
            throw new \RuntimeException('Invalid callback provided');
        }

        $dependencies = $this->resolveCallbackDependencies($callback, $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Resolve callback dependencies
     */
    private function resolveCallbackDependencies($callback, array $primitives = []): array
    {
        $reflector = $this->getCallbackReflector($callback);

        if ($reflector === null) {
            return $primitives;
        }

        return $this->resolveDependencies(
            $reflector->getParameters(),
            $primitives
        );
    }

    /**
     * Get callback reflector
     */
    private function getCallbackReflector($callback)
    {
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }

        if (is_object($callback) && !$callback instanceof Closure) {
            return new \ReflectionMethod($callback, '__invoke');
        }

        if ($callback instanceof Closure) {
            return new \ReflectionFunction($callback);
        }

        return null;
    }

    /**
     * Check if binding exists
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
            isset($this->instances[$abstract]) ||
            isset($this->aliases[$abstract]);
    }

    /**
     * Remove a binding
     */
    public function forget(string $abstract): void
    {
        unset(
            $this->bindings[$abstract],
            $this->instances[$abstract],
            $this->aliases[$abstract]
        );
    }

    /**
     * Remove a shared instance from the container.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all shared instances from the container.
     *
     * @return void
     */
    public function forgetInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Flush the container
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }
}
