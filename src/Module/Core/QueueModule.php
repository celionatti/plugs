<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class QueueModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Queue';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('queue', function () {
            $queue = new \Plugs\Queue\QueueManager();
            $queue->setDefaultDriver(config('queue.default', 'sync'));
            return $queue;
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
