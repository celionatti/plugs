<?php

declare(strict_types=1);

namespace Plugs\Mail;

/*
|--------------------------------------------------------------------------
| MailService Class
|--------------------------------------------------------------------------
*/

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class MailService
{
    private Mailer $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $config)
    {
        // Create DSN (Data Source Name) for transport
        // Format: smtp://user:pass@smtp.example.com:port
        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $config['driver'] ?? 'smtp',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['host'] ?? 'localhost',
            $config['port'] ?? 587
        );

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
        
        $this->fromEmail = $config['from']['address'] ?? 'noreply@example.com';
        $this->fromName = $config['from']['name'] ?? 'My Application';
    }

    /**
     * Send a simple email
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): void
    {
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
    ): void {
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
    }

    /**
     * Send email with attachments
     */
    public function sendWithAttachment(
        string $to,
        string $subject,
        string $body,
        array $attachments = []
    ): void {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->html($body);

        foreach ($attachments as $attachment) {
            $email->attachFromPath($attachment);
        }

        $this->mailer->send($email);
    }

    /**
     * Send email with both HTML and plain text versions
     */
    public function sendMultipart(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): void {
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->html($htmlBody)
            ->text($textBody);

        $this->mailer->send($email);
    }
}