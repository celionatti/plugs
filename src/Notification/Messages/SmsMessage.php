<?php

declare(strict_types=1);

namespace Plugs\Notification\Messages;

/**
 * Class SmsMessage
 *
 * A fluent builder for composing SMS notification content.
 */
class SmsMessage
{
    /**
     * The phone number the message should be sent to.
     *
     * @var string
     */
    protected string $to = '';

    /**
     * The phone number the message should be sent from.
     *
     * @var string
     */
    protected string $from = '';

    /**
     * The message content.
     *
     * @var string
     */
    protected string $content = '';

    /**
     * Create a new SMS message instance.
     *
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * Create a new SMS message instance.
     *
     * @param string $content
     * @return static
     */
    public static function create(string $content = ''): static
    {
        return new static($content);
    }

    /**
     * Set the phone number the message should be sent to.
     *
     * @param string $to
     * @return $this
     */
    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Set the phone number the message should be sent from.
     *
     * @param string $from
     * @return $this
     */
    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Set the message content.
     *
     * @param string $content
     * @return $this
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the phone number the message should be sent to.
     *
     * @return string
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Get the phone number the message should be sent from.
     *
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Get the message content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Convert the message to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'to' => $this->to,
            'from' => $this->from,
            'content' => $this->content,
        ];
    }
}
