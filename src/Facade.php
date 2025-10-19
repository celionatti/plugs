<?php

declare(strict_types=1);

namespace Plugs;

/*
|--------------------------------------------------------------------------
| Facade Base Class
|--------------------------------------------------------------------------
|
| Base class for creating static facades that proxy calls to underlying
| service instances.
*/

use RuntimeException;

abstract class Facade
{
    /**
     * The resolved object instances.
     *
     * @var array
     */
    protected static $resolvedInstance = [];

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Get the root object behind the facade.
     *
     * @return mixed
     */
    public static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    /**
     * Resolve the facade root instance from the container.
     *
     * @param string $name
     * @return mixed
     */
    protected static function resolveFacadeInstance(string $name)
    {
        // Return cached instance if exists
        if (isset(static::$resolvedInstance[$name])) {
            return static::$resolvedInstance[$name];
        }

        // Try to get from container using make() method
        try {
            $container = \Plugs\Container\Container::getInstance();
            
            // Check if bound in container
            if ($container->bound($name)) {
                return static::$resolvedInstance[$name] = $container->make($name);
            }
            
            throw new RuntimeException(
                "Facade accessor [{$name}] is not bound in the container. " .
                "Make sure to bind it using \$container->singleton('{$name}', \$instance); or " .
                "use Facade::setFacadeInstance('{$name}', \$instance);"
            );
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to resolve facade [{$name}]: " . $e->getMessage()
            );
        }
    }

    /**
     * Clear a resolved facade instance.
     *
     * @param string $name
     * @return void
     */
    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$resolvedInstance[$name]);
    }

    /**
     * Clear all resolved instances.
     *
     * @return void
     */
    public static function clearResolvedInstances(): void
    {
        static::$resolvedInstance = [];
    }

    /**
     * Set the resolved instance manually (useful for testing or direct binding).
     *
     * @param string $name
     * @param mixed $instance
     * @return void
     */
    public static function setFacadeInstance(string $name, $instance): void
    {
        static::$resolvedInstance[$name] = $instance;
    }

    /**
     * Get a manually set facade instance (bypasses container).
     *
     * @param string $name
     * @return mixed|null
     */
    public static function getFacadeInstance(string $name)
    {
        return static::$resolvedInstance[$name] ?? null;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            throw new RuntimeException('A facade root has not been set.');
        }

        if (!method_exists($instance, $method)) {
            $class = get_class($instance);
            throw new RuntimeException("Method [{$method}] does not exist on [{$class}].");
        }

        return $instance->$method(...$args);
    }
}