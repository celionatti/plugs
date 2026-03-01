<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class ViewModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'View';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return $context === ContextType::Web || $context === ContextType::Cli;
    }

    public function register(Container $container): void
    {
        $container->singleton(\Plugs\View\ViewManager::class, function () use ($container) {
            return new \Plugs\View\ViewManager($container);
        });

        $container->singleton(\Plugs\View\ViewEngineInterface::class, function () use ($container) {
            return $container->make(\Plugs\View\ViewManager::class)->driver();
        });

        $container->alias(\Plugs\View\ViewManager::class, 'view.manager');
        $container->alias(\Plugs\View\ViewEngineInterface::class, 'view');
    }

    public function boot(Plugs $app): void
    {
    }
}
