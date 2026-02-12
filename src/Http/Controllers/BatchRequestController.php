<?php

declare(strict_types=1);

namespace Plugs\Http\Controllers;

use Plugs\Http\Message\ServerRequest;
use Plugs\Http\ResponseFactory;
use Plugs\Router\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BatchRequestController
{
    public function handle(ServerRequestInterface $request, Router $router): ResponseInterface
    {
        $payload = $request->getParsedBody();

        if (!isset($payload['requests']) || !is_array($payload['requests'])) {
            return ResponseFactory::json([
                'error' => 'Invalid Request',
                'message' => 'The "requests" array is missing or invalid.',
            ], 400);
        }

        $responses = [];
        $maxPerBatch = 50; // Safety limit
        $count = 0;

        foreach ($payload['requests'] as $subRequest) {
            if ($count++ >= $maxPerBatch) {
                $responses[] = [
                    'id' => $subRequest['id'] ?? null,
                    'status' => 429, // Too Many Requests
                    'body' => ['error' => 'Batch limit exceeded'],
                ];
                continue;
            }

            // 1. Validate sub-request
            if (empty($subRequest['method']) || empty($subRequest['uri'])) {
                $responses[] = [
                    'id' => $subRequest['id'] ?? null,
                    'status' => 400,
                    'body' => ['error' => 'Missing method or uri'],
                ];
                continue;
            }

            // 2. Prepare Headers (Inherit Parent + Override)
            $headers = $request->getHeaders();

            // Remove headers that shouldn't be inherited indiscriminately if they are payload-specific
            // But usually we want Auth/Cookies. 
            // Content-Type/Length should be reset for the sub-request based on its body.
            unset($headers['Content-Type'], $headers['Content-Length'], $headers['Host']);

            if (isset($subRequest['headers']) && is_array($subRequest['headers'])) {
                foreach ($subRequest['headers'] as $name => $value) {
                    $headers[$name] = (array) $value;
                }
            }

            // 3. Create Request Object
            // We use ServerRequest directly. 
            // Note: Protocol version and server params are inherited from parent.
            $subReq = new ServerRequest(
                $subRequest['method'],
                $subRequest['uri'],
                $headers,
                null, // Body handled below
                $request->getProtocolVersion(),
                $request->getServerParams()
            );

            // 4. Set Body
            if (isset($subRequest['body'])) {
                $subReq = $subReq->withParsedBody($subRequest['body']);
            }

            // 5. Dispatch
            try {
                // Dispatch logic:
                // We depend on Router::dispatch to handle the request lifecycle. 
                // However, Router::dispatch expects a request and returns a response.
                // It does modify global state (currentRequest), which is acceptable for sequential batch processing.

                $subResponse = $router->dispatch($subReq);

                // If dispatch returns null (no route match), it usually throws an exception or 404.
                // If it returns null here, we simulate 404.
                if (!$subResponse) {
                    $responses[] = [
                        'id' => $subRequest['id'] ?? null,
                        'status' => 404,
                        'body' => ['error' => 'Route not found'],
                    ];
                    continue;
                }

                // 6. Format Response
                $bodyStr = (string) $subResponse->getBody();
                $bodyData = json_decode($bodyStr, true);

                // If valid JSON, return as object, otherwise string
                $body = (json_last_error() === JSON_ERROR_NONE) ? $bodyData : $bodyStr;

                $responses[] = [
                    'id' => $subRequest['id'] ?? null,
                    'status' => $subResponse->getStatusCode(),
                    'headers' => $subResponse->getHeaders(),
                    'body' => $body,
                ];

            } catch (\Throwable $e) {
                // Catch any error during dispatch (e.g. 500, 404 exception)
                $status = ($e instanceof \Plugs\Exceptions\HttpException) ? $e->getStatusCode() : 500;

                $responses[] = [
                    'id' => $subRequest['id'] ?? null,
                    'status' => $status,
                    'body' => [
                        'error' => 'Internal Error',
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return ResponseFactory::json(['responses' => $responses]);
    }
}
