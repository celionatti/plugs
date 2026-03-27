<?php

declare(strict_types=1);

namespace Plugs\Notification\Channels;

use Plugs\Exceptions\ServiceException;
use Plugs\Notification\Messages\SmsMessage;

/**
 * Class SmsChannel
 *
 * Sends SMS notifications via a Twilio-compatible REST API.
 *
 * Required configuration keys:
 *   notifications.sms.sid   — Account SID / API key
 *   notifications.sms.token — Auth token / API secret
 *   notifications.sms.from  — Default sender phone number (E.164 format)
 *   notifications.sms.url   — (Optional) API endpoint, defaults to Twilio
 */
class SmsChannel
{
    /**
     * The Account SID.
     *
     * @var string
     */
    protected string $sid;

    /**
     * The auth token.
     *
     * @var string
     */
    protected string $token;

    /**
     * The default "from" phone number.
     *
     * @var string
     */
    protected string $from;

    /**
     * The API endpoint URL.
     *
     * @var string
     */
    protected string $url;

    /**
     * Create a new SMS channel instance.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->sid   = $config['sid']   ?? $this->resolveConfig('notifications.sms.sid', '');
        $this->token = $config['token'] ?? $this->resolveConfig('notifications.sms.token', '');
        $this->from  = $config['from']  ?? $this->resolveConfig('notifications.sms.from', '');
        $this->url   = $config['url']   ?? $this->resolveConfig(
            'notifications.sms.url',
            "https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json"
        );
    }

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
        $message = $notification->toSms($notifiable);

        if (is_string($message)) {
            $message = SmsMessage::create($message);
        }

        if (!$message instanceof SmsMessage) {
            throw new ServiceException(
                'The toSms() method must return an SmsMessage instance or a string.',
                'SmsChannel'
            );
        }

        $to = $message->getTo() ?: $this->resolveRecipient($notifiable);

        if (!$to) {
            return;
        }

        $from = $message->getFrom() ?: $this->from;

        $this->sendSms($to, $from, $message->getContent());
    }

    /**
     * Resolve the SMS recipient from the notifiable entity.
     *
     * @param mixed $notifiable
     * @return string|null
     */
    protected function resolveRecipient($notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            return $notifiable->routeNotificationForSms();
        }

        return $notifiable->phone ?? $notifiable->phone_number ?? null;
    }

    /**
     * Send the SMS via the configured API.
     *
     * @param string $to
     * @param string $from
     * @param string $body
     * @return void
     *
     * @throws ServiceException
     */
    protected function sendSms(string $to, string $from, string $body): void
    {
        if (!$this->sid || !$this->token) {
            throw new ServiceException(
                'SMS credentials (sid, token) are not configured. Set notifications.sms.sid and notifications.sms.token.',
                'SmsChannel'
            );
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->url,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$this->sid}:{$this->token}",
            CURLOPT_POSTFIELDS     => http_build_query([
                'To'   => $to,
                'From' => $from,
                'Body' => $body,
            ]),
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new ServiceException("SMS delivery failed: {$error}", 'SmsChannel');
        }

        if ($httpCode >= 400) {
            $decoded = json_decode((string) $response, true);
            $apiMsg  = $decoded['message'] ?? $response;

            throw new ServiceException(
                "SMS delivery failed (HTTP {$httpCode}): {$apiMsg}",
                'SmsChannel'
            );
        }
    }

    /**
     * Resolve a configuration value using the container if available.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    protected function resolveConfig(string $key, mixed $default = null): mixed
    {
        try {
            $config = \Plugs\Container\Container::getInstance()->make('config');

            return $config->get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
