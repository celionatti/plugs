<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class PdfModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Pdf';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('pdf', function () use ($container) {
            $pdf = new \Plugs\Pdf\PdfServiceProvider($container);
            $pdf->register();
            return $pdf;
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
