<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class LogModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Log';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('log', function ($container) {
            $config = config('logging');
            $channel = $config['default'] ?? 'file';
            $channelConfig = $config['channels'][$channel] ?? [];
            $driver = $channelConfig['driver'] ?? 'single';
            $path = $channelConfig['path'] ?? storage_path('logs/plugs.log');

            $logger = ($driver === 'daily')
                ? new \Plugs\Log\RotatingFileLogger($path, $channelConfig['max_files'] ?? 14)
                : new \Plugs\Log\Logger($path);

            return $logger;
        });

        $container->alias('log', \Psr\Log\LoggerInterface::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
