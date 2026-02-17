<?php

declare(strict_types=1);

namespace Plugs\Http\Server;

use Plugs\Concurrency\LoopManager;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Message\Response;
use Closure;

class HttpServer
{
    protected string $host;
    protected int $port;
    protected LoopManager $loop;

    public function __construct(string $host = '0.0.0.0', int $port = 8080)
    {
        $this->host = $host;
        $this->port = $port;
        $this->loop = app(LoopManager::class);
    }

    public function start(Closure $handler): void
    {
        $socket = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);

        if (!$socket) {
            throw new \RuntimeException("Could not bind to {$this->host}:{$this->port}: {$errstr}");
        }

        stream_set_blocking($socket, false);

        $this->loop->addReadStream($socket, function ($socket) use ($handler) {
            $client = stream_socket_accept($socket);
            if ($client) {
                $this->handleClient($client, $handler);
            }
        });

        echo "HTTP Server started on http://{$this->host}:{$this->port}\n";
        $this->loop->run();
    }

    protected function handleClient($client, Closure $handler): void
    {
        stream_set_blocking($client, false);

        $this->loop->addReadStream($client, function ($client) use ($handler) {
            $data = fread($client, 8192);
            if ($data === false || $data === '') {
                $this->loop->removeReadStream($client);
                fclose($client);
                return;
            }

            // Simple request parsing (for demonstration, real framework would use a robust parser)
            $request = ServerRequest::fromGlobals(); // Placeholder: in real server mode, parse from $data

            $response = $handler($request);

            fwrite($client, (string) $response);
            $this->loop->removeReadStream($client);
            fclose($client);
        });
    }
}
