<?php

declare(strict_types=1);

namespace Plugs\Notification\Channels;

use Plugs\Exceptions\ServiceException;
use Plugs\Notification\Messages\SlackMessage;

/**
 * Class SlackChannel
 *
 * Sends notifications to Slack via Incoming Webhooks.
 *
 * The notifiable entity must provide a webhook URL via
 * `routeNotificationForSlack()`.
 */
class SlackChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param mixed $notification
     * @return void
     *
     * @throws ServiceException
     */
    public function send($notifiable, $notification): void
    {
        $webhookUrl = $this->resolveWebhookUrl($notifiable);

        if (!$webhookUrl) {
            return;
        }

        $message = $notification->toSlack($notifiable);

        if (is_string($message)) {
            $message = SlackMessage::create($message);
        }

        if (!$message instanceof SlackMessage) {
            throw new ServiceException(
                'The toSlack() method must return a SlackMessage instance or a string.',
                'SlackChannel'
            );
        }

        $this->postToWebhook($webhookUrl, $message->toArray());
    }

    /**
     * Resolve the Slack webhook URL from the notifiable entity.
     *
     * @param mixed $notifiable
     * @return string|null
     */
    protected function resolveWebhookUrl($notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSlack')) {
            return $notifiable->routeNotificationForSlack();
        }

        // Fallback: try a global config value
        try {
            $config = \Plugs\Container\Container::getInstance()->make('config');

            return $config->get('notifications.slack.webhook_url');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Post the payload to the Slack incoming webhook.
     *
     * @param string $url
     * @param array  $payload
     * @return void
     *
     * @throws ServiceException
     */
    protected function postToWebhook(string $url, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json),
            ],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new ServiceException("Slack notification failed: {$error}", 'SlackChannel');
        }

        if ($httpCode >= 400) {
            throw new ServiceException(
                "Slack notification failed (HTTP {$httpCode}): {$response}",
                'SlackChannel'
            );
        }
    }
}
