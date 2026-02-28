<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class EventsModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Events';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('events', function ($container) {
            return new \Plugs\Event\Dispatcher($container);
        });

        $container->alias('events', \Plugs\Event\DispatcherInterface::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
