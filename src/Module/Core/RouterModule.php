<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;
use Plugs\Router\Router;
use Plugs\Http\Redirector;
use Plugs\Facades\Route;
use Plugs\Facades\Redirect;

/**
 * RouterModule — Foundation Routing Service.
 *
 * Core module responsible for initializing the router and redirector services
 * early in the bootstrap process, enabling other modules to register routes.
 */
class RouterModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Router';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true; // We want router even in CLI for URL generation
    }

    public function register(Container $container): void
    {
        $router = new Router();
        $container->singleton('router', fn() => $router);
        $container->singleton(Router::class, fn() => $router);
        Route::setFacadeInstance('router', $router);

        $redirector = new Redirector();
        $container->singleton('redirect', fn() => $redirector);
        Redirect::setFacadeInstance('redirect', $redirector);

        // Load groups from config if available
        $middlewareConfig = config('middleware');
        if (isset($middlewareConfig['groups'])) {
            foreach ($middlewareConfig['groups'] as $group => $middlewares) {
                $router->middlewareGroup($group, $middlewares);
            }
        }
    }

    public function boot(Plugs $app): void
    {
        // ... (middleware groups already loaded in register)
    }
}
