<?php

declare(strict_types=1);

namespace Plugs\AI\Middleware;

use Closure;
use Plugs\Http\Message\ServerRequest;
use Plugs\Http\Message\Response;
use Plugs\Facades\AI;

class AIMiddleware
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
        // Example: Auto-classify contact form messages if they exist
        if ($request->has('message') && $request->getMethod() === 'POST') {
            $classification = AI::classify($request->input('message'), ['spam', 'inquiry', 'support', 'feedback']);
            $request = $request->withAttribute('ai_classification', $classification);
        }

        return $next($request);
    }
}
