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
use Plugs\Exceptions\BindingResolutionException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;

class Container implements ContainerInterface
{
    private static ?self $instance = null;

    private array $bindings = [];
    private array $instances = [];
    private array $scopedInstances = [];
    private array $aliases = [];
    private array $contextual = [];
    private array $reflectionCache = [];

    private ?Inspector $inspector = null;

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
     * Set the container instance.
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function setInspector(Inspector $inspector): void
    {
        $this->inspector = $inspector;
    }

    public function getInspector(): ?Inspector
    {
        return $this->inspector;
    }

    /**
     * Bind a class or interface to an implementation
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false, bool $scoped = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
            'scoped' => $scoped,
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
     * Bind a scoped instance (per-request/context)
     */
    public function scoped(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, false, true);
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
     * PSR-11: Finds an entry of the container by its identifier and returns it.
     */
    public function get(string $id): mixed
    {
        try {
            return $this->make($id);
        } catch (\RuntimeException $e) {
            throw new ContainerNotFoundException("No entry was found for '{$id}' in the container.", 0, $e);
        }
    }

    /**
     * PSR-11: Returns true if the container can return an entry for the given identifier.
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Resolve a class from the container
     */
    public function make(string $abstract, array $parameters = [])
    {
        $this->inspector?->start($abstract, $parameters);

        try {
            // Check for alias
            if (isset($this->aliases[$abstract])) {
                $abstract = $this->aliases[$abstract];
            }

            // Check if singleton instance exists
            if (isset($this->instances[$abstract])) {
                $this->inspector?->end($abstract, $this->instances[$abstract]);
                return $this->instances[$abstract];
            }

            // Check if scoped instance exists
            if (isset($this->scopedInstances[$abstract])) {
                $this->inspector?->end($abstract, $this->scopedInstances[$abstract]);
                return $this->scopedInstances[$abstract];
            }

            // Get concrete implementation
            $concrete = $this->getConcrete($abstract);

            // Build the object
            $object = $this->build($concrete, $parameters);

            // Store as singleton if needed
            if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']) {
                $this->instances[$abstract] = $object;
            }

            // Store as scoped if needed
            if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['scoped']) {
                $this->scopedInstances[$abstract] = $object;
            }

            $this->inspector?->end($abstract, $object);
            return $object;

        } catch (\Throwable $e) {
            $this->inspector?->end($abstract, null); // Log failure?
            throw $e;
        }
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

        if (isset($this->reflectionCache[$concrete])) {
            [$reflector, $constructor, $constructorParams] = $this->reflectionCache[$concrete];
        } else {
            try {
                $reflector = new ReflectionClass($concrete);
            } catch (ReflectionException $e) {
                throw BindingResolutionException::targetNotFound($concrete, $e);
            }

            // Check if class is instantiable
            if (!$reflector->isInstantiable()) {
                throw BindingResolutionException::notInstantiable($concrete);
            }

            $constructor = $reflector->getConstructor();
            $constructorParams = $constructor ? $constructor->getParameters() : null;

            $this->reflectionCache[$concrete] = [$reflector, $constructor, $constructorParams];
        }

        // If no constructor, just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveDependencies(
            $constructorParams,
            $parameters
        );

        return $reflector->newInstanceArgs($dependencies);
    }


    /**
     * Cache for resolved dependencies.
     *
     * @var array
     */
    protected array $resolvedDependenciesCache = [];

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

            // Check for attributes (Contextual Binding)
            $attribute = $parameter->getAttributes(\Plugs\Container\Attributes\Inject::class)[0] ?? null;
            if ($attribute) {
                $inject = $attribute->newInstance();
                $dependencies[] = $this->make($inject->service);
                continue;
            }

            // Get parameter type
            $type = $parameter->getType();

            // Union/intersection types (e.g. int|string) don't have isBuiltin()
            // Treat them as primitives — use default value if available
            $isBuiltin = $type === null
                || $type instanceof \ReflectionUnionType
                || $type instanceof \ReflectionIntersectionType
                || $type->isBuiltin();

            if ($isBuiltin) {
                // Handle primitive types
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw BindingResolutionException::unresolvedPrimitive($name);
                }
            } else {
                // Resolve class dependency
                $typeName = $type->getName();

                // OPTIMIZATION: Resolve only once
                $dependencies[] = $this->make($typeName);
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
            throw new BindingResolutionException('Invalid callback provided.');
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
            $this->aliases[$abstract],
            $this->scopedInstances[$abstract]
        );
    }

    /**
     * Remove a shared instance from the container.
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all shared instances from the container.
     */
    public function forgetInstances(): void
    {
        $this->instances = [];
        $this->scopedInstances = [];
    }

    /**
     * Flush the container
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->scopedInstances = [];
        $this->contextual = [];
    }
}
