<?php

declare(strict_types=1);

namespace Plugs\Notification\Messages;

/**
 * Class SlackMessage
 *
 * A fluent builder for composing Slack notification payloads.
 */
class SlackMessage
{
    /**
     * The channel the message should be sent to.
     *
     * @var string|null
     */
    protected ?string $channel = null;

    /**
     * The username to send the message as.
     *
     * @var string|null
     */
    protected ?string $username = null;

    /**
     * The emoji icon for the message.
     *
     * @var string|null
     */
    protected ?string $icon = null;

    /**
     * The text content of the message.
     *
     * @var string
     */
    protected string $content = '';

    /**
     * The message attachments.
     *
     * @var array
     */
    protected array $attachments = [];

    /**
     * Create a new Slack message instance.
     *
     * @param string $content
     */
    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    /**
     * Create a new Slack message instance.
     *
     * @param string $content
     * @return static
     */
    public static function create(string $content = ''): static
    {
        return new static($content);
    }

    /**
     * Set the Slack channel to send the message to.
     *
     * @param string $channel
     * @return $this
     */
    public function to(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Set the text content of the message.
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
     * Set the bot username for the message.
     *
     * @param string $username
     * @return $this
     */
    public function from(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Set the emoji icon for the message.
     *
     * @param string $emoji
     * @return $this
     */
    public function emoji(string $emoji): self
    {
        $this->icon = $emoji;

        return $this;
    }

    /**
     * Add an attachment to the message.
     *
     * @param array $attachment
     * @return $this
     */
    public function attachment(array $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * Add a success-styled attachment.
     *
     * @param string $title
     * @param string $text
     * @return $this
     */
    public function success(string $title, string $text = ''): self
    {
        return $this->attachment([
            'color' => '#22c55e',
            'title' => $title,
            'text' => $text,
        ]);
    }

    /**
     * Add a warning-styled attachment.
     *
     * @param string $title
     * @param string $text
     * @return $this
     */
    public function warning(string $title, string $text = ''): self
    {
        return $this->attachment([
            'color' => '#f59e0b',
            'title' => $title,
            'text' => $text,
        ]);
    }

    /**
     * Add an error-styled attachment.
     *
     * @param string $title
     * @param string $text
     * @return $this
     */
    public function error(string $title, string $text = ''): self
    {
        return $this->attachment([
            'color' => '#ef4444',
            'title' => $title,
            'text' => $text,
        ]);
    }

    /**
     * Get the Slack channel.
     *
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->channel;
    }

    /**
     * Get the bot username.
     *
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Get the emoji icon.
     *
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Get the text content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the attachments.
     *
     * @return array
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * Convert the message to Slack-compatible payload array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $payload = [
            'text' => $this->content,
        ];

        if ($this->channel) {
            $payload['channel'] = $this->channel;
        }

        if ($this->username) {
            $payload['username'] = $this->username;
        }

        if ($this->icon) {
            $payload['icon_emoji'] = $this->icon;
        }

        if (!empty($this->attachments)) {
            $payload['attachments'] = $this->attachments;
        }

        return $payload;
    }
}
