<?php

namespace Plugs\Console\Commands;

use Plugs\Broadcasting\BroadcastToken;
use Plugs\Console\Command;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use React\EventLoop\Loop;
use Psr\Http\Message\ServerRequestInterface;
use Clue\React\Redis\Factory as RedisFactory;

class SSEStartCommand extends Command
{
    protected string $signature = 'sse:start {--port=8080 : The port to run the SSE daemon on}';
    protected string $description = 'Start the ReactPHP based SSE multiplexing daemon with broadcast channel support';

    /**
     * Active SSE Connections indexed by a unique ID.
     * Each entry: ['stream' => ThroughStream, 'topics' => [...], 'user_id' => int|null, 'user_info' => array|null]
     * @var array
     */
    protected array $connections = [];

    /**
     * Presence channel member tracking.
     * Key: channel name, Value: [connId => user_info, ...]
     * @var array<string, array>
     */
    protected array $presenceMembers = [];

    /**
     * Redis client for presence state persistence (non-subscriber connection).
     * @var \Clue\React\Redis\Client|null
     */
    protected $redisState = null;

    public function handle(): int
    {
        $port = $this->option('port') ?? 8080;
        
        $redisHost = env('REDIS_HOST', '127.0.0.1');
        $redisPort = env('REDIS_PORT', 6379);
        $redisPass = env('REDIS_PASSWORD', null);

        $this->info("Initializing Plugs SSE Daemon (v2.0 — Broadcasting) on port $port...");
        $this->info("Connecting to Redis on $redisHost:$redisPort");

        $loop = Loop::get();

        // 1. Connect to Redis via clue/redis-react
        // (the pub/sub subscriber)
        $redisFactory = new RedisFactory($loop);
        $redisUri = $redisPass ? "redis://:{$redisPass}@{$redisHost}:{$redisPort}" : "redis://{$redisHost}:{$redisPort}";
        
        $redisFactory->createClient($redisUri)->then(function (\Clue\React\Redis\Client $redis) use ($loop) {
            
            $this->info("Successfully connected to Redis stream.");

            $redis->on('message', function ($channel, $message) {
                // $message format: {"topic":"chat","payload":"...","time":123}
                $this->broadcast(json_decode($message, true));
            });
            
            // The channel matches our FPM Publisher default
            $redis->subscribe('plugs_sse_stream');

        }, function (\Exception $e) {
            $this->error("Redis Connection Failed: " . $e->getMessage());
            exit(1);
        });

        // 1b. Create a second Redis connection for presence state (non-blocking, non-subscriber)
        $redisFactory->createClient($redisUri)->then(function (\Clue\React\Redis\Client $redis) {
            $this->redisState = $redis;
            $this->info("Redis state connection established for presence tracking.");
        }, function (\Exception $e) {
            $this->warn("Redis state connection failed (presence tracking disabled): " . $e->getMessage());
        });

        // 2. Setup the HTTP Server to accept SSE connections
        $server = new HttpServer($loop, function (ServerRequestInterface $request) use ($loop) {
            
            $path = $request->getUri()->getPath();

            // ── Presence Members API ──
            // GET /api/stream/members?channel=presence-lobby.1
            if (str_contains($path, '/members')) {
                return $this->handleMembersRequest($request);
            }
            
            // Only accept /api/stream endpoints
            if (!str_contains($path, '/stream')) {
                return new Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
            }

            // Parse frontend requested topics: e.g. ?topics=chat,crash_game
            $queryParams = $request->getQueryParams();
            $topicsStr = $queryParams['topics'] ?? '';
            $topics = array_filter(explode(',', $topicsStr));

            if (empty($topics)) {
                return new Response(400, ['Content-Type' => 'text/plain'], 'No topics requested');
            }

            // ── Channel Authentication ──
            // Check if any requested topic requires auth (private-* or presence-*)
            $requiresAuth = false;
            foreach ($topics as $topic) {
                if (str_starts_with($topic, 'private-') || str_starts_with($topic, 'presence-')) {
                    $requiresAuth = true;
                    break;
                }
            }

            $userId = null;
            $userInfo = null;

            if ($requiresAuth) {
                $token = $queryParams['token'] ?? '';

                if (empty($token)) {
                    return new Response(403, ['Content-Type' => 'text/plain'], 'Authentication required for private/presence channels');
                }

                // Verify the token against EACH private/presence topic
                foreach ($topics as $topic) {
                    if (str_starts_with($topic, 'private-') || str_starts_with($topic, 'presence-')) {
                        $tokenData = BroadcastToken::verify($token, $topic);

                        if ($tokenData === null) {
                            return new Response(403, ['Content-Type' => 'text/plain'], "Token verification failed for channel: {$topic}");
                        }

                        $userId = $tokenData['user_id'];
                    }
                }

                // Parse user info for presence channels
                if (isset($queryParams['user_info'])) {
                    $userInfo = json_decode(base64_decode($queryParams['user_info']), true);
                }

                if (!$userInfo && $userId) {
                    $userInfo = ['id' => $userId];
                }
            }

            $connId = uniqid('sse_');

            // Construct SSE Response headers
            $headers = [
                'Content-Type'  => 'text/event-stream',
                'Cache-Control' => 'no-cache, no-transform',
                'Connection'    => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disables NGINX buffering
                'Access-Control-Allow-Origin' => '*' // Adjust as needed
            ];

            // Create a ReactPHP stream interface using an intermediate stream
            $stream = new \React\Stream\ThroughStream();

            $this->connections[$connId] = [
                'stream'    => $stream,
                'topics'    => $topics,
                'user_id'   => $userId,
                'user_info' => $userInfo,
            ];

            $this->info("New SSE Connection: {$connId} listening to topics: [" . implode(', ', $topics) . "]" . ($userId ? " (user: {$userId})" : " (public)"));

            // ── Presence: Join ──
            foreach ($topics as $topic) {
                if (str_starts_with($topic, 'presence-') && $userInfo) {
                    $this->presenceJoin($connId, $topic, $userInfo);
                }
            }

            // Handle client disconnects gracefully!
            $stream->on('close', function () use ($connId, $topics) {
                if (isset($this->connections[$connId])) {
                    $userInfo = $this->connections[$connId]['user_info'];
                    $this->warn("Client Disconnected: {$connId}");

                    // ── Presence: Leave ──
                    foreach ($topics as $topic) {
                        if (str_starts_with($topic, 'presence-') && $userInfo) {
                            $this->presenceLeave($connId, $topic, $userInfo);
                        }
                    }

                    unset($this->connections[$connId]);
                }
            });

            // Return the streaming response back to the client natively
            return new Response(200, $headers, $stream);
        });

        // 3. Heartbeat (Every 15 Seconds)
        // Sends standard SSE comment to prevent firewalls/load balancers from closing idle TCP conns.
        $loop->addPeriodicTimer(15, function () {
            foreach ($this->connections as $conn) {
                $conn['stream']->write(":\n\n");
            }
        });

        // Start listening
        $socket = new SocketServer("0.0.0.0:$port", [], $loop);
        $server->listen($socket);

        $this->success("SSE Broadcast Daemon successfully started on port {$port}.");
        $this->info("  → Public channels: no auth required");
        $this->info("  → Private channels (private-*): token auth enforced");
        $this->info("  → Presence channels (presence-*): token auth + member tracking");

        // Start loop blockingly
        $loop->run();

        return self::SUCCESS;
    }

    /**
     * Broadcast an event only to clients listening to that specific topic.
     */
    protected function broadcast(?array $messagePayload)
    {
        if (!$messagePayload || !isset($messagePayload['topic'])) {
            return;
        }

        $topic = $messagePayload['topic'];
        $payload = json_encode($messagePayload['payload']);
        $time = $messagePayload['time'] ?? microtime(true);
        $totalPushes = 0;

        $sseString = "event: {$topic}\ndata: {$payload}\nid: {$time}\n\n";

        foreach ($this->connections as $connId => $conn) {
            if (in_array($topic, $conn['topics'])) {
                $conn['stream']->write($sseString);
                $totalPushes++;
            }
        }

        if ($totalPushes > 0) {
            $this->info("Broadcasted topic '{$topic}' to {$totalPushes} clients.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Presence Channel Management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle a user joining a presence channel.
     */
    protected function presenceJoin(string $connId, string $channel, array $userInfo): void
    {
        if (!isset($this->presenceMembers[$channel])) {
            $this->presenceMembers[$channel] = [];
        }

        $this->presenceMembers[$channel][$connId] = $userInfo;

        // Persist to Redis for cross-process visibility
        $this->persistPresence($channel);

        // Broadcast join event to all members of this presence channel
        $joinPayload = json_encode([
            'event' => 'joining',
            'data'  => $userInfo,
        ]);

        $sseString = "event: {$channel}\ndata: {$joinPayload}\nid: " . microtime(true) . "\n\n";

        foreach ($this->connections as $id => $conn) {
            if ($id !== $connId && in_array($channel, $conn['topics'])) {
                $conn['stream']->write($sseString);
            }
        }

        // Send the current member list to the newly joined client
        $members = array_values($this->presenceMembers[$channel]);
        $herePayload = json_encode([
            'event'   => 'here',
            'data'    => $members,
        ]);

        $hereSse = "event: {$channel}\ndata: {$herePayload}\nid: " . microtime(true) . "\n\n";

        if (isset($this->connections[$connId])) {
            $this->connections[$connId]['stream']->write($hereSse);
        }

        $this->info("Presence JOIN: {$channel} — user " . json_encode($userInfo) . " (total: " . count($this->presenceMembers[$channel]) . ")");
    }

    /**
     * Handle a user leaving a presence channel.
     */
    protected function presenceLeave(string $connId, string $channel, array $userInfo): void
    {
        if (isset($this->presenceMembers[$channel][$connId])) {
            unset($this->presenceMembers[$channel][$connId]);
        }

        // Clean up empty channels
        if (empty($this->presenceMembers[$channel])) {
            unset($this->presenceMembers[$channel]);
        }

        // Persist to Redis
        $this->persistPresence($channel);

        // Broadcast leave event to remaining members
        $leavePayload = json_encode([
            'event' => 'leaving',
            'data'  => $userInfo,
        ]);

        $sseString = "event: {$channel}\ndata: {$leavePayload}\nid: " . microtime(true) . "\n\n";

        foreach ($this->connections as $id => $conn) {
            if (in_array($channel, $conn['topics'])) {
                $conn['stream']->write($sseString);
            }
        }

        $this->info("Presence LEAVE: {$channel} — user " . json_encode($userInfo));
    }

    /**
     * Persist presence members to Redis for cross-process visibility.
     */
    protected function persistPresence(string $channel): void
    {
        if (!$this->redisState) {
            return;
        }

        $key = 'plugs:presence:' . $channel;
        $members = $this->presenceMembers[$channel] ?? [];

        if (empty($members)) {
            $this->redisState->del($key);
        } else {
            $this->redisState->set($key, json_encode(array_values($members)));
            $this->redisState->expire($key, 86400); // 24h TTL safety net
        }
    }

    /**
     * Handle GET /api/stream/members?channel=presence-lobby.1
     * Returns current members of a presence channel.
     */
    protected function handleMembersRequest(ServerRequestInterface $request): Response
    {
        $queryParams = $request->getQueryParams();
        $channel = $queryParams['channel'] ?? '';

        if (empty($channel) || !str_starts_with($channel, 'presence-')) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode([
                'error' => 'A valid presence channel name is required',
            ]));
        }

        $members = array_values($this->presenceMembers[$channel] ?? []);

        return new Response(200, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode([
            'channel' => $channel,
            'members' => $members,
            'count'   => count($members),
        ]));
    }
}
