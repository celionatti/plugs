<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class SeoModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Seo';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('seo', function () {
            return new \Plugs\Support\SEO(config('seo'));
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
