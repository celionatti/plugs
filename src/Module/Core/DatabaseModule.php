<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class DatabaseModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Database';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true; // DB can be used in any context
    }

    public function register(Container $container): void
    {
        $container->bind(\Plugs\Database\Connection::class, function () {
            return \Plugs\Database\Connection::getInstance();
        }, true);

        $container->singleton('database', function () use ($container) {
            return new \Plugs\Database\DatabaseManager($container->make(\Plugs\Database\Connection::class));
        });

        $container->alias('database', 'db');
    }

    public function boot(Plugs $app): void
    {
        $databaseConfig = config('database');
        if ($databaseConfig && isset($databaseConfig['connections'][$databaseConfig['default']])) {
            \Plugs\Base\Model\PlugModel::setConnection(
                $databaseConfig['connections'][$databaseConfig['default']]
            );
        }
    }
}
