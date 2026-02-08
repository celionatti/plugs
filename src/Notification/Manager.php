<?php

declare(strict_types=1);

namespace Plugs\Notification;

use Plugs\Container\Container;

class Manager
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The registered channels.
     *
     * @var array
     */
    protected array $channels = [];

    /**
     * Create a new notification manager instance.
     *
     * @param Container|null $container
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container ?: Container::getInstance();
    }

    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param mixed $notifiables
     * @param mixed $notification
     * @return void
     */
    public function send($notifiables, $notification): void
    {
        $notifiables = $this->formatNotifiables($notifiables);

        foreach ($notifiables as $notifiable) {
            $this->sendToNotifiable($notifiable, $notification);
        }
    }

    /**
     * Send the notification to a single notifiable entity.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return void
     */
    protected function sendToNotifiable($notifiable, $notification): void
    {
        $payload = clone $notification;
        if (!$payload->id) {
            $payload->id = \Plugs\Utils\Uuid::v4();
        }

        $viaChannels = $notification->via($notifiable);

        foreach ($viaChannels as $channel) {
            $this->driver($channel)->send($notifiable, $payload);
        }
    }

    /**
     * Get a channel driver instance.
     *
     * @param string $channel
     * @return mixed
     */
    public function driver(string $channel)
    {
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        return $this->channels[$channel] = $this->resolveDriver($channel);
    }

    /**
     * Resolve the given channel driver.
     *
     * @param string $channel
     * @return mixed
     */
    protected function resolveDriver(string $channel)
    {
        $method = 'create' . ucfirst($channel) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        // Try to resolve from container if it's a class
        if (class_exists($channel)) {
            return $this->container->make($channel);
        }

        // Standard framework channels
        $frameworkChannels = [
            'mail' => \Plugs\Notification\Channels\MailChannel::class,
            'database' => \Plugs\Notification\Channels\DatabaseChannel::class,
        ];

        if (isset($frameworkChannels[$channel])) {
            return $this->container->make($frameworkChannels[$channel]);
        }

        throw new \InvalidArgumentException("Notification channel [{$channel}] not supported.");
    }

    /**
     * Create an instance of the mail driver.
     *
     * @return \Plugs\Notification\Channels\MailChannel
     */
    protected function createMailDriver()
    {
        return $this->container->make(\Plugs\Notification\Channels\MailChannel::class);
    }

    /**
     * Format the notifiables into a collection/array.
     *
     * @param mixed $notifiables
     * @return array
     */
    protected function formatNotifiables($notifiables): array
    {
        return is_array($notifiables) ? $notifiables : [$notifiables];
    }
}
