<?php

namespace Plugs\SSE;

use Predis\Client;

class Publisher
{
    /**
     * @var Client
     */
    protected Client $redis;

    /**
     * The Redis channel used for SSE internal pub/sub.
     *
     * @var string
     */
    protected string $channel;

    /**
     * SSE Publisher Constructor.
     * 
     * @param array $config Redis configuration
     * @param string $channel Default channel name
     */
    public function __construct(array $config = [], string $channel = 'plugs_sse_stream')
    {
        $this->redis = new Client($config);
        $this->channel = $channel;
    }

    /**
     * Publish an event to the internal SSE Daemon stream.
     * 
     * @param string $topic The topic clients subscribe to (e.g. 'chat', 'telemetry', 'crash')
     * @param array|string $payload The data payload for the client
     * @return int Number of subscribers that received the message
     */
    public function publish(string $topic, array|string $payload): int
    {
        $data = [
            'topic'   => $topic,
            'payload' => $payload,
            'time'    => microtime(true)
        ];

        return $this->redis->publish($this->channel, json_encode($data));
    }

    /**
     * Helper to statically publish an event utilizing connection configuration 
     * from the user's framework (you can inject your exact Config facade logic here).
     */
    public static function emit(string $topic, array|string $payload)
    {
        $publisher = new self([
            'scheme' => 'tcp',
            'host'   => env('REDIS_HOST', '127.0.0.1'),
            'port'   => (int) env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', null),
        ]);

        return $publisher->publish($topic, $payload);
    }
}
