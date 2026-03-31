<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;
use Plugs\Http\Request;
use Psr\Http\Message\ServerRequestInterface;

/**
 * RequestModule — Foundation Web Request.
 *
 * Core module responsible for initializing the globally shared request instance
 * early in the bootstrap process, enabling other modules to access the request context.
 */
class RequestModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Request';
    }

    public function shouldBoot(ContextType $context): bool
    {
        // For CLI, we might not need a full request, but we still bind it
        // for uniformity in modules that expect a request instance.
        return true;
    }

    public function register(Container $container): void
    {
        $request = Request::fromGlobals();

        // Bind under the PSR-7 interface
        $container->singleton(
            ServerRequestInterface::class,
            fn() => $request
        );

        // Bind under the concrete Request class (preferred usage)
        $container->singleton(
            Request::class,
            fn() => $request
        );

        // Simple string alias
        $container->singleton('request', fn() => $request);
    }

    public function boot(Plugs $app): void
    {
    }
}
