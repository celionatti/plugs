<?php

declare(strict_types=1);

namespace Plugs\EventBus;

/**
 * Contract for event bus adapters.
 * Enables publish-subscribe messaging for microservices.
 */
interface EventBusInterface
{
    /**
     * Publish an event to a channel/topic.
     *
     * @param string $channel The channel/topic name
     * @param array $payload The event payload
     * @param array $options Additional options (e.g., delay, priority)
     * @return string|null Message ID if applicable
     */
    public function publish(string $channel, array $payload, array $options = []): ?string;

    /**
     * Subscribe to a channel/topic.
     *
     * @param string $channel The channel/topic name
     * @param callable $handler Handler function: fn(array $payload, array $meta): void
     * @param string|null $group Consumer group name for competing consumers
     */
    public function subscribe(string $channel, callable $handler, ?string $group = null): void;

    /**
     * Consume messages from subscribed channels (blocking).
     *
     * @param int $timeout Timeout in seconds (0 = indefinite)
     * @param int $maxMessages Max messages to process before returning
     */
    public function consume(int $timeout = 0, int $maxMessages = 100): void;

    /**
     * Acknowledge a message was processed.
     *
     * @param string $messageId The message ID to acknowledge
     */
    public function acknowledge(string $messageId): void;

    /**
     * Get pending message count for a channel.
     *
     * @param string $channel The channel name
     * @return int Number of pending messages
     */
    public function pending(string $channel): int;
}
