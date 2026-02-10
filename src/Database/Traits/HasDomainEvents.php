<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\RecordsEvents;
use ReflectionClass;

trait HasDomainEvents
{
    /**
     * The recorded domain events.
     */
    protected array $recordedEvents = [];

    /**
     * Config for event recording.
     */
    protected static ?RecordsEvents $eventConfig = null;

    /**
     * Boot the trait and register post-save dispatcher.
     */
    public static function bootHasDomainEvents(): void
    {
        $class = static::class;
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(RecordsEvents::class);

        if (empty($attributes)) {
            return;
        }

        static::$eventConfig = $attributes[0]->newInstance();

        static::saved(function ($model) {
            $model->dispatchEvents();
        });
    }

    /**
     * Record a domain event.
     */
    public function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Release all recorded events.
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }

    /**
     * Dispatch all recorded events.
     */
    public function dispatchEvents(): void
    {
        $events = $this->releaseEvents();

        foreach ($events as $event) {
            $this->dispatchEvent($event);
        }
    }

    /**
     * Dispatch a single event.
     * This can be extended to use a global event dispatcher or queue.
     */
    protected function dispatchEvent(object $event): void
    {
        // Integration with Framework Dispatcher
        // For now, we fire model-level events for each domain event
        $eventName = (new ReflectionClass($event))->getShortName();

        if (method_exists($this, 'fireModelEvent')) {
            $this->fireModelEvent("domain.{$eventName}", ['event' => $event]);
        }

        // Logic for persistence if enabled
        if (static::$eventConfig?->persist) {
            // \Plugs\Database\Observability\DomainEventStore::record(static::class, $event);
        }
    }
}
