<?php

declare(strict_types=1);

namespace Plugs\EventBus\Drivers;

use Plugs\EventBus\EventBusInterface;

/**
 * Synchronous in-memory event bus for local development.
 * Messages are processed immediately in the same process.
 */
class SyncEventBus implements EventBusInterface
{
    private array $subscribers = [];
    private array $messages = [];

    public function publish(string $channel, array $payload, array $options = []): ?string
    {
        $messageId = uniqid('msg_', true);
        $meta = [
            'id' => $messageId,
            'channel' => $channel,
            'timestamp' => time(),
            'options' => $options,
        ];

        // If there are subscribers, dispatch immediately
        if (isset($this->subscribers[$channel])) {
            foreach ($this->subscribers[$channel] as $subscriber) {
                call_user_func($subscriber['handler'], $payload, $meta);
            }
        } else {
            // Queue for later consumption
            $this->messages[$channel][] = [
                'payload' => $payload,
                'meta' => $meta,
            ];
        }

        return $messageId;
    }

    public function subscribe(string $channel, callable $handler, ?string $group = null): void
    {
        $this->subscribers[$channel][] = [
            'handler' => $handler,
            'group' => $group,
        ];
    }

    public function consume(int $timeout = 0, int $maxMessages = 100): void
    {
        $processed = 0;

        foreach ($this->messages as $channel => &$messages) {
            if (!isset($this->subscribers[$channel])) {
                continue;
            }

            while (!empty($messages) && $processed < $maxMessages) {
                $message = array_shift($messages);

                foreach ($this->subscribers[$channel] as $subscriber) {
                    call_user_func($subscriber['handler'], $message['payload'], $message['meta']);
                }

                $processed++;
            }
        }
    }

    public function acknowledge(string $messageId): void
    {
        // No-op for sync driver
    }

    public function pending(string $channel): int
    {
        return count($this->messages[$channel] ?? []);
    }
}
