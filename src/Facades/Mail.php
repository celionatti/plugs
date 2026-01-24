<?php

declare(strict_types=1);

namespace Plugs\Facades;

/*
|--------------------------------------------------------------------------
| Mail Facade
|--------------------------------------------------------------------------
*/

use Plugs\Container\Container;
use Plugs\Mail\MailService;

class Mail
{
    /**
     * Get the mail service instance
     */
    private static function getMailService(): MailService
    {
        return Container::getInstance()->make('mail');
    }

    /**
     * Send a simple email
     */
    public static function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        return self::getMailService()->send($to, $subject, $body, $isHtml);
    }

    /**
     * Send email to multiple recipients
     */
    public static function sendToMultiple(array $recipients, string $subject, string $body, bool $isHtml = true): bool
    {
        return self::getMailService()->sendToMultiple($recipients, $subject, $body, $isHtml);
    }

    /**
     * Send email with CC and BCC
     */
    public static function sendWithCopies(
        string $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithCopies($to, $subject, $body, $cc, $bcc, $isHtml);
    }

    /**
     * Send email with attachments
     */
    public static function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithAttachment($to, $subject, $body, $attachments, $isHtml);
    }

    /**
     * Send multipart email
     */
    public static function sendMultipart(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        return self::getMailService()->sendMultipart($to, $subject, $htmlBody, $textBody);
    }

    /**
     * Send email with reply-to
     */
    public static function sendWithReplyTo(
        string $to,
        string $subject,
        string $body,
        string $replyTo,
        string $replyToName = '',
        bool $isHtml = true
    ): bool {
        return self::getMailService()->sendWithReplyTo($to, $subject, $body, $replyTo, $replyToName, $isHtml);
    }

    /**
     * Create a fluent email builder
     */
    public static function createEmail()
    {
        return self::getMailService()->createEmail();
    }
}
