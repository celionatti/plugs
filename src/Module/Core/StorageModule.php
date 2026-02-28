<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class StorageModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Storage';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('storage', function () {
            return new \Plugs\Filesystem\StorageManager(config('filesystems'));
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
