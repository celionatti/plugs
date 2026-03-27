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

    /**
     * Get the phone number for SMS notifications.
     *
     * @return string|null
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone ?? $this->phone_number ?? null;
    }

    /**
     * Get the Slack webhook URL for the notifiable.
     *
     * Override this method to return a Slack incoming webhook URL
     * for the notifiable entity.
     *
     * @return string|null
     */
    public function routeNotificationForSlack(): ?string
    {
        return null;
    }
}
