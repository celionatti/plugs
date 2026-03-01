<?php

declare(strict_types=1);

namespace Plugs\Security\Identity;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Event\DispatcherInterface;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;
use Plugs\Security\Auth\AuthManager;

/**
 * IdentityModule
 *
 * Optional framework module that bootstraps the key-based identity system.
 * Registers KeyDerivationService, NonceService, and IdentityManager,
 * and extends the AuthManager with the 'key' guard driver.
 *
 * Install independently: composer require framework/identity (conceptual)
 * Disable via ModuleManager::disableModule('Identity').
 */
class IdentityModule implements ModuleInterface
{
    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'Identity';
    }

    /**
     * {@inheritDoc}
     */
    public function shouldBoot(ContextType $context): bool
    {
        // Only boot if identity is enabled in config
        return (bool) config('auth.identity.enabled', true);
    }

    /**
     * {@inheritDoc}
     */
    public function register(Container $container): void
    {
        // Bind KeyDerivationService as singleton
        $container->singleton(KeyDerivationService::class, function () {
            return new KeyDerivationService(
                config('auth.identity.kdf.memory', null),
                config('auth.identity.kdf.time', null),
            );
        });

        // Bind NonceService as singleton
        $container->singleton(NonceService::class, function () {
            return new NonceService(
                config('app.key', null),
                (int) config('auth.identity.nonce_ttl', 300),
            );
        });

        // Bind IdentityManager as singleton
        $container->singleton(IdentityManager::class, function () use ($container) {
            $events = $container->has(DispatcherInterface::class)
                ? $container->make(DispatcherInterface::class)
                : null;

            return new IdentityManager(
                $container->make(KeyDerivationService::class),
                $container->make(NonceService::class),
                $events,
                config('auth.identity.model', 'App\\Models\\User'),
            );
        });

        // Alias for convenience
        $container->alias(IdentityManager::class, 'identity');
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Plugs $app): void
    {
        // Register the 'key' guard driver with the auth manager
        $container = $app->getContainer();

        if ($container->bound('auth')) {
            /** @var AuthManager $auth */
            $auth = $container->make('auth');

            $auth->extend('key', function (Container $c, string $name, array $config) {
                return $c->make(AuthManager::class)->createUserProvider($config['provider'] ?? 'key_users');
            });
        }
    }
}
