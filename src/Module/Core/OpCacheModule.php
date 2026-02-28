<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class OpCacheModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'OpCache';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('opcache', function () {
            return new \Plugs\Support\OpCacheManager();
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
