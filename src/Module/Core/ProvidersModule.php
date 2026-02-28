<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class ProvidersModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Providers';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
    }

    public function boot(Plugs $app): void
    {
        $container = $app->getContainer();
        $providers = config('app.providers', []);
        foreach ($providers as $provider) {
            $instance = new $provider($container);
            $instance->register();
            $instance->boot();
        }
    }
}
