<?php

declare(strict_types=1);

namespace Plugs\Console\Scheduling;

/**
 * Manages the collection of scheduled events.
 */
class Schedule
{
    /**
     * All of the events on the schedule.
     *
     * @var Event[]
     */
    protected array $events = [];

    /**
     * Create a new schedule instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param string $command The command name (e.g., 'inspire')
     * @param array  $parameters Optional command parameters
     * @return Event
     */
    public function command(string $command, array $parameters = []): Event
    {
        $event = new Event($command, $parameters);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Add a new callback event to the schedule.
     *
     * @param callable $callback The callback to execute
     * @param array    $parameters Parameters for the callback
     * @return CallbackEvent
     */
    public function call(callable $callback, array $parameters = []): CallbackEvent
    {
        $event = new CallbackEvent($callback, $parameters);
        $this->events[] = $event;

        return $event;
    }

    /**
     * Get all events on the schedule.
     *
     * @return Event[]
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Get all due events.
     *
     * @return Event[]
     */
    public function dueEvents(): array
    {
        return array_filter($this->events, function ($event) {
            return $event->isDue() && $event->filtersPass();
        });
    }
}
