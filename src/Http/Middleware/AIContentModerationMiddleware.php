<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Closure;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Message\Response;
use Plugs\Facades\AI;
use Plugs\Http\Middleware\Middleware;
use Plugs\Http\Middleware\MiddlewareLayer;

#[Middleware(layer: MiddlewareLayer::SECURITY, priority: 45)]
class AIContentModerationMiddleware
{
    /**
     * Handle the incoming request.
     *
     * @param ServerRequest $request
     * @param Closure $next
     * @return Response
     */
    public function handle(ServerRequest $request, Closure $next): Response
    {
        // Only moderate mutating requests (POST, PUT, PATCH)
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        $input = $request->all();

        // Filter out sensitive fields like passwords or tokens if they exist
        $sensitive = ['password', 'token', 'secret', 'api_key', 'cc', 'credit_card'];
        $filteredInput = array_diff_key($input, array_flip($sensitive));

        if (empty($filteredInput)) {
            return $next($request);
        }

        $contentToScan = json_encode($filteredInput);

        try {
            // Use AI to classify the content
            $classification = AI::classify($contentToScan, ['safe', 'toxic', 'spam', 'inappropriate'], [
                'cache' => 3600, // Cache results for repeat content
            ]);

            if ($classification !== 'safe') {
                return $this->blockRequest($classification);
            }
        } catch (\Throwable $e) {
            // If AI fails, we allow the request but log it? 
            // For now, fail-safe (allow) to prevent blocking users on API outages.
            // In a real app, this should be configurable.
        }

        return $next($request);
    }

    /**
     * Return a blocked response.
     */
    protected function blockRequest(string $reason): Response
    {
        $message = match ($reason) {
            'toxic' => 'Request blocked: Content detected as toxic or harmful.',
            'spam' => 'Request blocked: Content detected as spam.',
            'inappropriate' => 'Request blocked: Content detected as inappropriate.',
            default => 'Request blocked by AI moderation.',
        };

        $response = new Response();
        $response->getBody()->write(json_encode([
            'error' => 'AI Moderation Block',
            'reason' => $reason,
            'message' => $message
        ]));

        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }
}
