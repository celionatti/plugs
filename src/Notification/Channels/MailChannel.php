<?php

declare(strict_types=1);

namespace Plugs\Notification\Channels;

use Plugs\Container\Container;
use Plugs\Mail\MailService;

class MailChannel
{
    /**
     * The mail service instance.
     *
     * @var MailService
     */
    protected MailService $mailer;

    /**
     * Create a new mail channel instance.
     *
     * @param MailService|null $mailer
     */
    public function __construct(MailService $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return void
     */
    public function send($notifiable, $notification): void
    {
        $address = $notifiable->routeNotificationForMail();

        if (!$address) {
            return;
        }

        $message = $notification->toMail($notifiable);

        if (is_string($message)) {
            $this->mailer->send($address, 'Notification', $message);
        } else {
            // Assume $message is an EmailBuilder or similar if implemented
            // For now, support simple string or basic array
            $subject = $message['subject'] ?? 'Notification';
            $body = $message['body'] ?? $message;
            $this->mailer->send($address, $subject, $body);
        }
    }
}
