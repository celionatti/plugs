<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class CacheModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Cache';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('cache', function () {
            return new \Plugs\Cache\CacheManager();
        });

        $container->singleton('ratelimiter', function ($container) {
            return new \Plugs\Security\RateLimiter();
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
