<?php

declare(strict_types=1);

namespace Plugs\Event;

/**
 * Class Event
 *
 * Base class for all application events.
 */
abstract class Event
{
    /**
     * Whether the event propagation is stopped.
     *
     * @var bool
     */
    protected bool $propagationStopped = false;

    /**
     * Stop the propagation of the event to other listeners.
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Determine if the event propagation is stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
