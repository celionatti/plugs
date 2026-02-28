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
        $container->singleton('log', function () {
            $config = config('logging');
            $channel = $config['default'] ?? 'file';
            $channelConfig = $config['channels'][$channel] ?? [];
            $driver = $channelConfig['driver'] ?? 'single';
            $path = $channelConfig['path'] ?? storage_path('logs/plugs.log');

            if ($driver === 'daily') {
                $maxFiles = $channelConfig['max_files'] ?? 14;
                return new \Plugs\Log\RotatingFileLogger($path, $maxFiles);
            }

            return new \Plugs\Log\Logger($path);
        });
        $container->alias('log', \Psr\Log\LoggerInterface::class);
    }

    public function boot(Plugs $app): void
    {
    }
}
