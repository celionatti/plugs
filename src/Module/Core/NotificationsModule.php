<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class NotificationsModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Notifications';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('notifications', function () use ($container) {
            return new \Plugs\Notification\Manager($container);
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
