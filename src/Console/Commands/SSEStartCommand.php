<?php

namespace Plugs\Console\Commands;

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
    protected string $description = 'Start the ReactPHP based SSE multiplexing daemon';

    /**
     * Active SSE Connections indexed by a unique ID
     * @var array
     */
    protected array $connections = [];

    public function handle(): int
    {
        $port = $this->option('port') ?? 8080;
        
        $redisHost = env('REDIS_HOST', '127.0.0.1');
        $redisPort = env('REDIS_PORT', 6379);
        $redisPass = env('REDIS_PASSWORD', null);

        $this->info("Initializing Plugs SSE Daemon on port $port...");
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

        // 2. Setup the HTTP Server to accept SSE connections
        $server = new HttpServer($loop, function (ServerRequestInterface $request) use ($loop) {
            
            // Only accept /api/stream endpoints
            $path = $request->getUri()->getPath();
            
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
                'stream' => $stream,
                'topics' => $topics
            ];

            $this->info("New SSE Connection: {$connId} listening to topics: [" . implode(', ', $topics) . "]");

            // Handle client disconnects gracefully!
            $stream->on('close', function () use ($connId) {
                if (isset($this->connections[$connId])) {
                    $this->warn("Client Disconnected: {$connId}");
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

        $this->success("SSE React Daemon successfully started and listening to NGINX standard loop.");

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
}
