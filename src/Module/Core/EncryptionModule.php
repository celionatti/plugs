<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class EncryptionModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Encryption';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('encrypter', function () {
            $key = config('app.key');

            if (str_starts_with($key, 'base64:')) {
                $key = base64_decode(substr($key, 7));
            }

            return new \Plugs\Security\Encrypter($key);
        });

        $container->alias('encrypter', \Plugs\Security\Encrypter::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
