<?php

declare(strict_types=1);

namespace Plugs\Notification\Channels;

use Plugs\Container\Container;

class DatabaseChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return \Plugs\Base\Model\PlugModel
     */
    public function send($notifiable, $notification)
    {
        return $notifiable->routeNotificationForDatabase($notification)->create([
            'id' => $notification->id,
            'type' => get_class($notification),
            'data' => $this->getData($notifiable, $notification),
            'read_at' => null,
        ]);
    }

    /**
     * Get the data for the notification.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return array
     */
    protected function getData($notifiable, $notification): array
    {
        if (method_exists($notification, 'toDatabase')) {
            return $notification->toDatabase($notifiable);
        }

        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($notifiable);
        }

        throw new \RuntimeException('Notification is missing toDatabase or toArray method.');
    }
}
