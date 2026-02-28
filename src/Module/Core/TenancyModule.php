<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class TenancyModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Tenancy';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('tenancy', function () {
            return new \Plugs\Tenancy\TenantManager();
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
