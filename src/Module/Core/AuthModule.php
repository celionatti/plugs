<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class AuthModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Auth';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('auth', function () {
            return new \Plugs\Security\Auth\AuthManager();
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
