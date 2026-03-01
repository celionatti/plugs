<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;
use Plugs\Http\CookieJar;

class CookieModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Cookie';
    }

    public function shouldBoot(ContextType $context): bool
    {
        // Cookies are relevant in both Web and Api contexts
        return $context->isHttp();
    }

    public function register(Container $container): void
    {
        $container->singleton('cookie', function (Container $container) {
            $encrypter = $container->has('encrypter') ? $container->make('encrypter') : null;
            return new CookieJar($encrypter);
        });

        $container->alias('cookie', CookieJar::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
