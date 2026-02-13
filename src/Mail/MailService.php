<?php

declare(strict_types=1);

namespace Plugs\Mail;

/*
|--------------------------------------------------------------------------
| MailService Class
|--------------------------------------------------------------------------
|
| This class provides email functionality using Symfony Mailer.
| It supports simple emails, CC/BCC, attachments, and multipart messages.
*/

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailService
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $config)
    {
        if (!class_exists(Mailer::class)) {
            throw new \RuntimeException(
                'The "symfony/mailer" package is required to use the Mail service. ' .
                'Please install it via composer: composer require symfony/mailer'
            );
        }

        // Create DSN (Data Source Name) for transport
        $dsn = $this->buildDsn($config);

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);

        $this->fromEmail = $config['from']['address'] ?? 'noreply@example.com';
        $this->fromName = $config['from']['name'] ?? 'My Application';
    }

    /**
     * Build DSN string from config
     */
    private function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'smtp';
        $encryption = $config['encryption'] ?? 'tls';

        // Handle encryption in DSN
        if ($encryption === 'ssl') {
            $driver = $driver . 's';
        }

        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $driver,
            urlencode($config['username'] ?? ''),
            urlencode($config['password'] ?? ''),
            $config['host'] ?? 'localhost',
            $config['port'] ?? 587
        );

        // Add TLS option if needed
        if ($encryption === 'tls') {
            $dsn .= '?encryption=tls';
        }

        return $dsn;
    }

    /**
     * Send a simple email
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email to multiple recipients
     */
    public function sendToMultiple(array $recipients, string $subject, string $body, bool $isHtml = true): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->subject($subject);

            foreach ($recipients as $recipient) {
                $email->addTo($recipient);
            }

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email with CC and BCC
     */
    public function sendWithCopies(
        string $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            foreach ($cc as $ccEmail) {
                $email->cc($ccEmail);
            }

            foreach ($bcc as $bccEmail) {
                $email->bcc($bccEmail);
            }

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email with attachments
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        array $attachments = [],
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $email->attachFromPath($attachment);
                }
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email with inline attachments (embedded images)
     */
    public function sendWithEmbeddedImage(
        string $to,
        string $subject,
        string $body,
        array $embedImages = []
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($body);

            foreach ($embedImages as $cid => $path) {
                if (file_exists($path)) {
                    $email->embedFromPath($path, $cid);
                }
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email with both HTML and plain text versions
     */
    public function sendMultipart(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->subject($subject)
                ->html($htmlBody)
                ->text($textBody);

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Send email with custom reply-to address
     */
    public function sendWithReplyTo(
        string $to,
        string $subject,
        string $body,
        string $replyTo,
        string $replyToName = '',
        bool $isHtml = true
    ): bool {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($to)
                ->replyTo(new Address($replyTo, $replyToName))
                ->subject($subject);

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());

            return false;
        }
    }

    /**
     * Create a fluent email builder
     */
    public function createEmail(): EmailBuilder
    {
        return new EmailBuilder($this->mailer, $this->fromEmail, $this->fromName);
    }
}
