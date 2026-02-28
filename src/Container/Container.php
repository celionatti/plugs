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
    protected array $contextual = [];
    protected array $reflectionCache = [];
    protected array $buildStack = [];

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
     * Bind a lazy proxy for the given abstract.
     */
    public function lazy(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, function ($container) use ($abstract, $concrete) {
            return new LazyProxy(function () use ($container, $abstract, $concrete) {
                return $container->build($concrete ?? $abstract);
            });
        });
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
     * Define a contextual binding.
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $this->getAlias($concrete));
    }

    /**
     * Add a contextual binding to the container.
     */
    public function addContextualBinding(string $concrete, string $abstract, $implementation): void
    {
        $this->contextual[$concrete][$abstract] = $implementation;
    }

    /**
     * Resolve an alias to its abstract name.
     */
    protected function getAlias(string $abstract): string
    {
        return isset($this->aliases[$abstract]) ? $this->getAlias($this->aliases[$abstract]) : $abstract;
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

            $this->buildStack[] = $abstract;

            // Build the object
            $object = $this->build($concrete, $parameters);

            array_pop($this->buildStack);

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
            $metadata = $this->reflectionCache[$concrete];
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
            $constructorParams = $constructor ? $constructor->getParameters() : [];

            $metadata = [
                'hasConstructor' => $constructor !== null,
                'parameters' => $this->extractParameterMetadata($constructorParams),
            ];

            $this->reflectionCache[$concrete] = $metadata;
        }

        // If no constructor, just instantiate
        if (!$metadata['hasConstructor']) {
            return new $concrete();
        }

        // Resolve constructor dependencies
        $dependencies = $this->resolveDependencies(
            $metadata['parameters'],
            $parameters
        );

        return new $concrete(...$dependencies);
    }

    /**
     * Extract serializable metadata from reflection parameters.
     */
    private function extractParameterMetadata(array $parameters): array
    {
        $metadata = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            $metadata[] = [
                'name' => $parameter->getName(),
                'type' => ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) ? $type->getName() : null,
                'isBuiltin' => !($type instanceof \ReflectionNamedType && !$type->isBuiltin()),
                'isOptional' => $parameter->isOptional(),
                'hasDefaultValue' => $parameter->isDefaultValueAvailable(),
                'defaultValue' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                'inject' => ($attr = $parameter->getAttributes(\Plugs\Container\Attributes\Inject::class)[0] ?? null)
                    ? $attr->newInstance()->service : null,
            ];
        }
        return $metadata;
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
            $name = $parameter['name'];

            // Check if primitive value provided
            if (isset($primitives[$name])) {
                $dependencies[] = $primitives[$name];
                continue;
            }

            // Check for attributes (Contextual Binding)
            if ($parameter['inject']) {
                $dependencies[] = $this->make($parameter['inject']);
                continue;
            }

            if ($parameter['isBuiltin']) {
                // Check for contextual primitive binding
                $concrete = end($this->buildStack);
                if (isset($this->contextual[$concrete][$name])) {
                    $dependencies[] = $this->getContextualConcrete($this->contextual[$concrete][$name]);
                } elseif ($parameter['hasDefaultValue']) {
                    $dependencies[] = $parameter['defaultValue'];
                } else {
                    throw BindingResolutionException::unresolvedPrimitive($name);
                }
            } else {
                // Resolve class dependency
                $typeName = $parameter['type'];

                // Check for contextual class binding
                $concrete = end($this->buildStack);
                if (isset($this->contextual[$concrete][$typeName])) {
                    $dependencies[] = $this->getContextualConcrete($this->contextual[$concrete][$typeName]);
                } else {
                    $dependencies[] = $this->make($typeName);
                }
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
     * Get the contextual concrete search for a given abstract.
     */
    protected function getContextualConcrete($implementation)
    {
        if ($implementation instanceof Closure) {
            return $implementation($this);
        }

        return is_string($implementation) ? $this->make($implementation) : $implementation;
    }

    /**
     * Clear all scoped instances from the container.
     */
    public function forgetScoped(): void
    {
        $this->scopedInstances = [];
    }

    /**
     * Get a structured graph of all bindings and instances for AI introspection.
     *
     * @return array
     */
    public function getGraph(): array
    {
        return [
            'bindings' => $this->bindings,
            'instances' => array_keys($this->instances),
            'scoped' => array_keys($this->scopedInstances),
            'aliases' => $this->aliases,
            'contextual' => $this->contextual,
        ];
    }

    /**
     * Cache the container's reflection data and bindings.
     */
    public function cache(string $path): bool
    {
        $cacheData = [
            'reflectionCache' => $this->reflectionCache,
            'aliases' => $this->aliases,
        ];

        $content = '<?php return ' . var_export($cacheData, true) . ';';
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $content) !== false;
    }

    /**
     * Load the container's cached data.
     */
    public function loadFromCache(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        $cacheData = require $path;

        if (is_array($cacheData)) {
            $this->reflectionCache = array_merge($this->reflectionCache, $cacheData['reflectionCache'] ?? []);
            $this->aliases = array_merge($this->aliases, $cacheData['aliases'] ?? []);
            return true;
        }

        return false;
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
