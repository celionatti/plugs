<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

/**
 * @phpstan-ignore trait.unused
 */
trait HasEvents
{
    protected static $booted = [];
    protected static $globalScopes = [];
    protected static $observers = [];

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

    protected function fireObserverEvent(string $event, ...$args): void
    {
        $class = static::class;
        if (!isset(static::$observers[$class])) {
            return;
        }

        foreach (static::$observers[$class] as $observer) {
            if (method_exists($observer, $event)) {
                $observer->$event($this, ...$args);
            }
        }
    }

    protected function fireModelEvent($event)
    {
        // Record event in Profiler if available
        if (class_exists(\Plugs\Debug\Profiler::class)) {
            \Plugs\Debug\Profiler::getInstance()->recordModelEvent(static::class, $event);
        }

        $method = $event;
        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return true;
    }

    protected function retrieving()
    {
    }

    protected function retrieved()
    {
    }

    protected function creating()
    {
    }

    protected function created()
    {
    }

    protected function updating()
    {
    }

    protected function updated()
    {
    }

    protected function saving()
    {
    }

    protected function saved()
    {
    }

    protected function deleting()
    {
    }

    protected function deleted()
    {
    }

    protected function restoring()
    {
    }

    protected function restored()
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
    protected function applyGlobalScopes()
    {
        $class = static::class;
        if (!isset(static::$globalScopes[$class])) {
            return $this;
        }

        $clone = $this;
        foreach (static::$globalScopes[$class] as $scope) {
            $clone = $scope($clone);
        }

        return $clone;
    }
}
