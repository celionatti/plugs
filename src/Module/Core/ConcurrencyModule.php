<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class ConcurrencyModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Concurrency';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton(\Plugs\Concurrency\LoopManager::class, function () {
            return new \Plugs\Concurrency\LoopManager(config('concurrency', []));
        });
        $container->alias(\Plugs\Concurrency\LoopManager::class, 'loop');
    }

    public function boot(Plugs $app): void
    {
    }
}
