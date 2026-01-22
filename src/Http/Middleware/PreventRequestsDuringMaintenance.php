<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Message\Response;
use Plugs\Http\Message\Stream;

class PreventRequestsDuringMaintenance implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $maintenanceFile = storage_path('framework/maintenance.json');

        if (file_exists($maintenanceFile)) {
            $data = json_decode(file_get_contents($maintenanceFile), true);

            // Bypass check
            if ($this->hasBypassCookie($request, $data)) {
                return $handler->handle($request);
            }

            if (isset($data['secret']) && $request->getUri()->getPath() === '/' . $data['secret']) {
                return $this->bypassResponse($data['secret']);
            }

            // Return 503
            return $this->maintenanceResponse($data);
        }

        return $handler->handle($request);
    }

    private function hasBypassCookie(ServerRequestInterface $request, array $data): bool
    {
        if (!isset($data['secret'])) {
            return false;
        }

        $cookies = $request->getCookieParams();
        return isset($cookies['plugs_maintenance']) && $cookies['plugs_maintenance'] === $data['secret'];
    }

    private function bypassResponse(string $secret): ResponseInterface
    {
        $response = new Response();
        return $response
            ->withHeader('Set-Cookie', "plugs_maintenance={$secret}; Path=/; HttpOnly")
            ->withHeader('Location', '/')
            ->withStatus(302);
    }

    private function maintenanceResponse(array $data): ResponseInterface
    {
        $body = new Stream(fopen('php://temp', 'w+'));

        $message = $data['message'] ?? 'Service Unavailable';
        $template = $this->getMaintenanceTemplate($message);

        $body->write($template);
        $body->rewind();

        return new Response(503, $body, [
            'Content-Type' => 'text/html',
            'Retry-After' => $data['retry'] ?? 60
        ]);
    }

    private function getMaintenanceTemplate(string $message): string
    {
        $file = resource_path('views/maintenance.html'); // Optional custom view

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Unavailable</title>
    <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f3f4f6; color: #374151; }
        .container { text-align: center; }
        h1 { font-size: 2.5rem; margin-bottom: 1rem; color: #111827; }
        p { font-size: 1.25rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>503</h1>
        <p>{$message}</p>
    </div>
</body>
</html>
HTML;
    }
}
