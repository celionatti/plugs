<?php

declare(strict_types=1);

namespace Plugs\Module;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Plugs;

interface ModuleInterface
{
    /**
     * Get the name of the module (e.g., 'Session', 'Database').
     */
    public function getName(): string;

    /**
     * Determine if this module should boot in the given context.
     */
    public function shouldBoot(ContextType $context): bool;

    /**
     * Register any bindings in the container before booting.
     */
    public function register(Container $container): void;

    /**
     * Boot the module.
     */
    public function boot(Plugs $app): void;
}
