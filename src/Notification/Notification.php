<?php

declare(strict_types=1);

namespace Plugs\Notification;

/**
 * Class Notification
 * 
 * Base class for all application notifications.
 */
abstract class Notification
{
    /**
     * The unique ID for the notification.
     *
     * @var string|null
     */
    public ?string $id = null;

    /**
     * Get the channels the notification should be sent on.
     *
     * @param mixed $notifiable
     * @return array
     */
    abstract public function via($notifiable): array;

    /**
     * Set the unique ID for the notification.
     *
     * @param string $id
     * @return $this
     */
    public function withId(string $id): self
    {
        $this->id = $id;
        return $this;
    }
}
