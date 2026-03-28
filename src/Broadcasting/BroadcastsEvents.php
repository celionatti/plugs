<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

use Plugs\SSE\Publisher;

/**
 * BroadcastsEvents Trait
 *
 * When applied to a model that uses the HasEvents trait, this trait
 * hooks into the model lifecycle to automatically broadcast created,
 * updated, and deleted events through the SSE system.
 *
 * Usage:
 *   class Game extends Model {
 *       use BroadcastsEvents;
 *       // Events auto-broadcast to 'private-game.{id}' by default
 *   }
 *
 * Customization:
 *   Override broadcastChannel() to change the channel name.
 *   Override broadcastWith() to customize the payload.
 *   Override shouldBroadcastEvent() to filter which events are broadcast.
 */
trait BroadcastsEvents
{
    /**
     * Boot the BroadcastsEvents trait.
     * Hooks into the model lifecycle events defined in HasEvents.
     */
    public static function bootBroadcastsEvents(): void
    {
        // After model is created
        static::created(function ($model) {
            if ($model->shouldBroadcastEvent('created')) {
                $model->broadcastModelEvent('created');
            }
        });

        // After model is updated
        static::updated(function ($model) {
            if ($model->shouldBroadcastEvent('updated')) {
                $model->broadcastModelEvent('updated');
            }
        });

        // After model is deleted
        static::deleted(function ($model) {
            if ($model->shouldBroadcastEvent('deleted')) {
                $model->broadcastModelEvent('deleted');
            }
        });
    }

    /**
     * Get the channel name for this model's broadcasts.
     *
     * Default: 'private-{modelname}.{primaryKey}'
     *   e.g. 'private-game.42', 'private-user.7'
     *
     * Override this to use a custom channel or make it public.
     *
     * @return string
     */
    public function broadcastChannel(): string
    {
        $baseName = strtolower($this->getModelBasename());
        $key = $this->getKey();

        return "private-{$baseName}.{$key}";
    }

    /**
     * Get the data payload to broadcast for a model event.
     *
     * Override to customize what data is sent to clients.
     *
     * @param string $event The event name (created, updated, deleted)
     * @return array
     */
    public function broadcastWith(string $event): array
    {
        // For deletions, only send the identifier
        if ($event === 'deleted') {
            return [
                'id' => $this->getKey(),
            ];
        }

        // For creates and updates, send all model attributes
        return method_exists($this, 'toArray')
            ? $this->toArray()
            : (array) $this;
    }

    /**
     * Determine if a given model event should be broadcast.
     *
     * Override to conditionally broadcast. For example, you might
     * only broadcast 'updated' when specific fields change.
     *
     * @param string $event The event name (created, updated, deleted)
     * @return bool
     */
    public function shouldBroadcastEvent(string $event): bool
    {
        return true;
    }

    /**
     * Get the SSE topic/event name for this model event.
     *
     * @param string $event The lifecycle event name
     * @return string
     */
    public function broadcastEventName(string $event): string
    {
        return $this->getModelBasename() . ucfirst($event);
    }

    /**
     * Publish the model event to the SSE stream.
     *
     * @param string $event The lifecycle event name
     * @return void
     */
    protected function broadcastModelEvent(string $event): void
    {
        $channel = $this->broadcastChannel();

        try {
            Publisher::emit($channel, [
                'event' => $this->broadcastEventName($event),
                'model' => $this->getModelBasename(),
                'data'  => $this->broadcastWith($event),
            ]);
        } catch (\Throwable $e) {
            error_log(
                "[BroadcastsEvents] Failed to broadcast {$event} for " .
                $this->getModelBasename() . "#{$this->getKey()}: " .
                $e->getMessage()
            );
        }
    }

    /**
     * Get the unqualified class name of the model.
     *
     * @return string
     */
    protected function getModelBasename(): string
    {
        $class = static::class;
        return substr($class, strrpos($class, '\\') + 1);
    }

    /**
     * Get the model's primary key value.
     * Most models already have getKey() via HasAttributes, but we
     * provide a fallback for safety.
     *
     * @return mixed
     */
    protected function getKey(): mixed
    {
        if (method_exists($this, 'getKeyValue')) {
            return $this->getKeyValue();
        }

        $keyName = property_exists($this, 'primaryKey') ? $this->primaryKey : 'id';

        return $this->$keyName ?? null;
    }
}
