<?php

declare(strict_types=1);

namespace Plugs\Security\Auth;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Event\DispatcherInterface;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

/**
 * AuthModule
 *
 * Framework module that bootstraps the core authentication system.
 * Registers the AuthManager singleton, binds the GuardInterface,
 * and configures default guards from the 'auth' config.
 *
 * This module can be disabled via ModuleManager::disableModule('Auth').
 */
class AuthModule implements ModuleInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Auth';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBoot(ContextType $context): bool
    {
        // Auth is needed in all contexts (web, console, etc.)
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function register(Container $container): void
    {
        // Bind the AuthManager as a singleton
        $container->singleton('auth', function () use ($container) {
            $events = $container->has(DispatcherInterface::class)
                ? $container->make(DispatcherInterface::class)
                : ($container->has('events') ? $container->make('events') : null);

            return new AuthManager($container, $events);
        });

        // Alias: resolve GuardInterface to the default guard
        $container->bind(GuardInterface::class, function () use ($container) {
            return $container->make('auth')->guard();
        });

        // Alias: resolve AuthManager class
        $container->alias('auth', AuthManager::class);
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Plugs $app): void
    {
        // Auth module is now available via Auth facade and container
    }
}
