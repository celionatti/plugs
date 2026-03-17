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
        return $context === ContextType::Web || 
               $context === ContextType::Cli || 
               $context === ContextType::Api;
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
        $container = Container::getInstance();

        // Register ThemeManager singleton
        $container->singleton(\Plugs\View\ThemeManager::class, function () {
            return new \Plugs\View\ThemeManager(config('app.paths.views'));
        });

        // Override config theme with DB-stored active theme
        try {
            $themeManager = $container->make(\Plugs\View\ThemeManager::class);
            $activeTheme = $themeManager->getActiveTheme();

            /** @var \Plugs\View\ViewEngineInterface $engine */
            $engine = $container->make('view');
            if ($engine instanceof \Plugs\View\PlugViewEngine) {
                $engine->setTheme($activeTheme);
            }
        } catch (\Throwable) {
            // Silently ignore — DB may not be ready during migrations
        }
    }
}
