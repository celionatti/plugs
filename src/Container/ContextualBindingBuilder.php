<?php

declare(strict_types=1);

namespace Plugs\Container;

class ContextualBindingBuilder
{
    protected string $concrete;
    protected string $needs;
    protected Container $container;

    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    public function needs(string $abstract): self
    {
        $this->needs = $abstract;
        return $this;
    }

    public function give($implementation): void
    {
        $this->container->addContextualBinding(
            $this->concrete,
            $this->needs,
            $implementation
        );
    }
}
