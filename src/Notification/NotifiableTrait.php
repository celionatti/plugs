<?php

declare(strict_types=1);

namespace Plugs\Notification;

use Plugs\Container\Container;

/** @phpstan-ignore trait.unused */
trait NotifiableTrait
{
    /**
     * Send the given notification.
     *
     * @param mixed $notification
     * @return void
     */
    public function notify($notification): void
    {
        Container::getInstance()->make('notifications')->send($this, $notification);
    }

    /**
     * Get the email address for the notification.
     *
     * @return string|null
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->email ?? null;
    }
}
