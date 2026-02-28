<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class AiModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Ai';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->scoped(\Plugs\AI\Metadata\EventTimelineRegistry::class);
        $container->scoped(\Plugs\AI\Metadata\MetadataRegistry::class);

        $container->singleton('ai', function () use ($container) {
            return new \Plugs\AI\AIManager(config('ai'), $container->make('cache'));
        });
        $container->alias('ai', \Plugs\AI\AIManager::class);
    }

    public function boot(Plugs $app): void
    {
        $container = $app->getContainer();
        $events = $container->make('events');
        $timeline = $container->make(\Plugs\AI\Metadata\EventTimelineRegistry::class);

        // Listen to all core events to build the timeline for AI analysis
        $coreEvents = [
            \Plugs\Event\Core\ApplicationBootstrapped::class,
            \Plugs\Event\Core\RequestReceived::class,
            \Plugs\Event\Core\RouteMatched::class,
            \Plugs\Event\Core\ActionExecuting::class,
            \Plugs\Event\Core\ActionExecuted::class,
            \Plugs\Event\Core\ResponseSending::class,
            \Plugs\Event\Core\ResponseSent::class,
            \Plugs\Event\Core\ExceptionThrown::class,
            \Plugs\Event\Core\QueryExecuted::class,
        ];

        foreach ($coreEvents as $eventClass) {
            $events->listen($eventClass, function ($event) use ($eventClass, $timeline) {
                $metadata = [];
                if (method_exists($event, 'toArray')) {
                    $metadata = $event->toArray();
                } elseif (isset($event->sql)) {
                    $metadata = ['sql' => $event->sql, 'time' => $event->time];
                }

                $timeline->record($eventClass, $metadata);
            });
        }
    }
}
