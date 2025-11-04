<?php

declare(strict_types=1);

namespace Plugs\Mail;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailBuilder
{
    private Mailer $mailer;
    private Email $email;

    public function __construct(Mailer $mailer, string $fromEmail, string $fromName)
    {
        $this->mailer = $mailer;
        $this->email = (new Email())->from(new Address($fromEmail, $fromName));
    }

    public function to(string $email, string $name = ''): self
    {
        $this->email->to(new Address($email, $name));
        return $this;
    }

    public function cc(string $email, string $name = ''): self
    {
        $this->email->cc(new Address($email, $name));
        return $this;
    }

    public function bcc(string $email, string $name = ''): self
    {
        $this->email->bcc(new Address($email, $name));
        return $this;
    }

    public function replyTo(string $email, string $name = ''): self
    {
        $this->email->replyTo(new Address($email, $name));
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->email->subject($subject);
        return $this;
    }

    public function html(string $body): self
    {
        $this->email->html($body);
        return $this;
    }

    public function text(string $body): self
    {
        $this->email->text($body);
        return $this;
    }

    public function attach(string $path, ?string $name = null): self
    {
        if (file_exists($path)) {
            $this->email->attachFromPath($path, $name);
        }
        return $this;
    }

    public function embed(string $path, string $cid): self
    {
        if (file_exists($path)) {
            $this->email->embedFromPath($path, $cid);
        }
        return $this;
    }

    public function priority(int $priority): self
    {
        $this->email->priority($priority);
        return $this;
    }

    public function send(): bool
    {
        try {
            $this->mailer->send($this->email);
            return true;
        } catch (TransportExceptionInterface $e) {
            error_log("Mail sending failed: " . $e->getMessage());
            return false;
        }
    }
}
