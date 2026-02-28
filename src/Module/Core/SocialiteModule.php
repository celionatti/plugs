<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class SocialiteModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Socialite';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('socialite', function () use ($container) {
            return new \Plugs\Security\OAuth\SocialiteManager($container);
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
