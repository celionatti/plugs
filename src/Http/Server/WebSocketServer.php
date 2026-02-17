<?php

declare(strict_types=1);

namespace Plugs\Http\Server;

use Plugs\Concurrency\LoopManager;

class WebSocketServer extends HttpServer
{
    protected array $clients = [];

    public function start(\Closure $handler): void
    {
        parent::start(function ($request) use ($handler) {
            // Check for upgrade header
            if (str_contains(strtolower($request->getHeaderLine('Upgrade')), 'websocket')) {
                // Return switching protocols response
                // This logic would be more complex in a real implementation
                return new \Plugs\Http\Message\Response(101, null, [
                    'Upgrade' => 'websocket',
                    'Connection' => 'Upgrade',
                    'Sec-WebSocket-Accept' => $this->generateAcceptKey($request->getHeaderLine('Sec-WebSocket-Key'))
                ]);
            }

            return new \Plugs\Http\Message\Response(400, null, [], '1.1', "Expected WebSocket Upgrade");
        });
    }

    protected function generateAcceptKey(string $key): string
    {
        return base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }
}
