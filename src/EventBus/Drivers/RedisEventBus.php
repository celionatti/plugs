<?php

declare(strict_types=1);

namespace Plugs\EventBus\Drivers;

use Plugs\EventBus\EventBusInterface;
use Redis;

/**
 * Redis Streams event bus adapter.
 * Uses Redis Streams for reliable message delivery with consumer groups.
 */
class RedisEventBus implements EventBusInterface
{
    private Redis $redis;
    private array $subscribers = [];
    private string $consumerName;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
        $this->consumerName = gethostname() . ':' . getmypid();
    }

    public function publish(string $channel, array $payload, array $options = []): ?string
    {
        $streamKey = $this->getStreamKey($channel);

        $data = [
            'payload' => json_encode($payload),
            'timestamp' => (string) time(),
        ];

        // Add delay support
        if (isset($options['delay'])) {
            $data['deliver_at'] = (string) (time() + $options['delay']);
        }

        $messageId = $this->redis->xAdd($streamKey, '*', $data);

        return $messageId ?: null;
    }

    public function subscribe(string $channel, callable $handler, ?string $group = null): void
    {
        $group = $group ?? 'default';
        $streamKey = $this->getStreamKey($channel);

        // Create consumer group if it doesn't exist
        try {
            $this->redis->xGroup('CREATE', $streamKey, $group, '0', true);
        } catch (\RedisException $e) {
            // Group already exists - ignore
        }

        $this->subscribers[$channel] = [
            'handler' => $handler,
            'group' => $group,
            'stream' => $streamKey,
        ];
    }

    public function consume(int $timeout = 0, int $maxMessages = 100): void
    {
        if (empty($this->subscribers)) {
            return;
        }

        $streams = [];
        foreach ($this->subscribers as $channel => $config) {
            $streams[$config['stream']] = '>';
        }

        $blockMs = $timeout > 0 ? $timeout * 1000 : 0;

        while (true) {
            foreach ($this->subscribers as $channel => $config) {
                $messages = $this->redis->xReadGroup(
                    $config['group'],
                    $this->consumerName,
                    [$config['stream'] => '>'],
                    $maxMessages,
                    $blockMs
                );

                if (!$messages) {
                    continue;
                }

                foreach ($messages[$config['stream']] ?? [] as $messageId => $data) {
                    $payload = json_decode($data['payload'] ?? '{}', true);
                    $meta = [
                        'id' => $messageId,
                        'channel' => $channel,
                        'timestamp' => (int) ($data['timestamp'] ?? time()),
                    ];

                    try {
                        call_user_func($config['handler'], $payload, $meta);
                        $this->acknowledge($messageId);
                    } catch (\Throwable $e) {
                        // Log error but don't ack - message will be retried
                        error_log("[EventBus] Failed processing {$messageId}: " . $e->getMessage());
                    }
                }
            }

            if ($timeout === 0) {
                break;
            }
        }
    }

    public function acknowledge(string $messageId): void
    {
        foreach ($this->subscribers as $config) {
            $this->redis->xAck($config['stream'], $config['group'], [$messageId]);
        }
    }

    public function pending(string $channel): int
    {
        if (!isset($this->subscribers[$channel])) {
            return 0;
        }

        $config = $this->subscribers[$channel];
        $info = $this->redis->xPending($config['stream'], $config['group']);

        return (int) ($info[0] ?? 0);
    }

    private function getStreamKey(string $channel): string
    {
        $prefix = env('REDIS_PREFIX', 'plugs:');
        return $prefix . 'stream:' . $channel;
    }
}
