<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class AiModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Ai';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('ai', function () use ($container) {
            return new \Plugs\AI\AIManager(config('ai'), $container->make('cache'));
        });
        $container->alias('ai', \Plugs\AI\AIManager::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
