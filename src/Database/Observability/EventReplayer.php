<?php

declare(strict_types=1);

namespace Plugs\Database\Observability;

class EventReplayer
{
    /**
     * Replay events on a model instance.
     * Expects model to have 'apply{EventName}' methods.
     */
    public static function replay(object $model, array $events): void
    {
        foreach ($events as $event) {
            $eventName = (new \ReflectionClass($event))->getShortName();
            $method = "apply{$eventName}";

            if (method_exists($model, $method)) {
                $model->$method($event);
            }
        }
    }
}
