<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AIOptimizeMiddleware implements MiddlewareInterface
{
    /**
     * Common AI Agent User-Agents
     */
    protected array $aiAgents = [
        'GPTBot',
        'ChatGPT-User',
        'Claude-Web',
        'ClaudeBot',
        'Google-CloudVertexBot',
        'Googlebot',
        'Bingbot',
        'CCBot',
        'PerplexityBot',
        'YouBot',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userAgent = $request->getHeaderLine('User-Agent');
        $isAi = false;

        foreach ($this->aiAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $isAi = true;
                break;
            }
        }

        // Also check for specialized headers
        if ($request->hasHeader('X-AI-Request') || $request->hasHeader('Accept-AI-Optimization')) {
            $isAi = true;
        }

        $response = $handler->handle($request);

        if ($isAi) {
            return $this->optimizeForAI($response);
        }

        return $response;
    }

    protected function optimizeForAI(ResponseInterface $response): ResponseInterface
    {
        // For now, let's just add a header to signify optimization.
        // In a more advanced implementation, we could strip HTML tags
        // or return a JSON representation of the content if the view supports it.
        return $response->withHeader('X-AI-Optimized', 'true')
            ->withHeader('X-Robots-Tag', 'index, follow');
    }
}
