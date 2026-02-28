<?php

declare(strict_types=1);

namespace Plugs\Container;

/**
 * LazyProxy for deferred service instantiation.
 */
class LazyProxy
{
    protected ?object $instance = null;
    protected \Closure $resolver;

    public function __construct(\Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getInstance()->$name(...$arguments);
    }

    public function __get(string $name)
    {
        return $this->getInstance()->$name;
    }

    public function __set(string $name, $value)
    {
        $this->getInstance()->$name = $value;
    }

    protected function getInstance(): object
    {
        if ($this->instance === null) {
            $this->instance = ($this->resolver)();
        }

        return $this->instance;
    }
}
