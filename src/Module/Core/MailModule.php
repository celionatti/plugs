<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class MailModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Mail';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('mail', function () use ($container) {
            $mail = new \Plugs\Mail\MailServiceProvider($container);
            $mail->register();
            return $mail;
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
