<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

/**
 * @phpstan-ignore trait.unused
 */
trait HasEvents
{
    protected static $booted = [];
    protected static $bootTraits = [];
    protected static $globalScopes = [];
    protected static $observers = [];
    protected static $eventListeners = [];

    protected function bootIfNotBooted(): void
    {
        $class = static::class;
        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;
            static::boot();
        }
    }

    protected static function boot(): void
    {
        // Override in child classes
    }

    public static function observe($observer): void
    {
        static::$observers[static::class][] = $observer;
    }

    protected function fireObserverEvent(string $event, array $context = []): bool
    {
        $class = static::class;
        if (!isset(static::$observers[$class])) {
            return true;
        }

        foreach (static::$observers[$class] as $observer) {
            if (method_exists($observer, $event)) {
                if ($observer->$event($this, $context) === false) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function fireModelEvent(string $event, array $context = [])
    {
        // Record event in Profiler if available
        if (class_exists(\Plugs\Debug\Profiler::class)) {
            \Plugs\Debug\Profiler::getInstance()->recordModelEvent(static::class, $event);
        }

        // Fire static closures
        $class = static::class;
        if (isset(static::$eventListeners[$class][$event])) {
            foreach (static::$eventListeners[$class][$event] as $callback) {
                if ($callback($this, $context) === false) {
                    return false;
                }
            }
        }

        // Fire observer events
        if ($this->fireObserverEvent($event, $context) === false) {
            return false;
        }

        $method = 'on' . ucfirst($event);
        if (method_exists($this, $method)) {
            return $this->$method($context);
        }

        return true;
    }

    /**
     * Register a model event listener.
     */
    public static function on(string $event, \Closure $callback): void
    {
        static::registerModelEvent($event, $callback);
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure  $callback
     * @return void
     */
    protected static function registerModelEvent(string $event, \Closure $callback): void
    {
        static::$eventListeners[static::class][$event][] = $callback;
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function creating(\Closure $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function created(\Closure $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function updating(\Closure $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function updated(\Closure $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    /**
     * Register a saving model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function saving(\Closure $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    /**
     * Register a saved model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function saved(\Closure $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function deleting(\Closure $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function deleted(\Closure $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    /**
     * Register a retrieved model event with the dispatcher.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function retrieved(\Closure $callback): void
    {
        static::registerModelEvent('retrieved', $callback);
    }

    protected function onRetrieving()
    {
    }

    protected function onRetrieved()
    {
    }

    protected function onCreating()
    {
    }

    protected function onCreated()
    {
    }

    protected function onUpdating()
    {
    }

    protected function onUpdated()
    {
    }

    protected function onSaving()
    {
    }

    protected function onSaved()
    {
    }

    protected function onDeleting()
    {
    }

    protected function onDeleted()
    {
    }

    protected function onRestoring()
    {
    }

    protected function onRestored()
    {
    }

    /**
     * Add global scope
     */
    public static function addGlobalScope(string $name, callable $callback)
    {
        static::$globalScopes[static::class][$name] = $callback;
    }

    /**
     * Remove global scope
     */
    public static function removeGlobalScope(string $name)
    {
        unset(static::$globalScopes[static::class][$name]);
    }

    /**
     * Apply global scopes to query
     */
    protected function applyGlobalScopes(\Plugs\Database\QueryBuilder $builder): \Plugs\Database\QueryBuilder
    {
        $class = static::class;
        if (!isset(static::$globalScopes[$class])) {
            return $builder;
        }

        foreach (static::$globalScopes[$class] as $scope) {
            $builder = $scope($builder);
        }

        return $builder;
    }
}
