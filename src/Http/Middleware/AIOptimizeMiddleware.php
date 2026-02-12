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
        $body = (string) $response->getBody();
        $contentType = $response->getHeaderLine('Content-Type');

        // Only optimize HTML content
        if (stripos($contentType, 'text/html') !== false) {
            // Remove scripts, styles, and comments to save tokens
            $optimizedBody = preg_replace('/<script\b[^>]*>([\s\S]*?)<\/script>/i', '', $body);
            $optimizedBody = preg_replace('/<style\b[^>]*>([\s\S]*?)<\/style>/i', '', $optimizedBody);
            $optimizedBody = preg_replace('/<!--([\s\S]*?)-->/', '', $optimizedBody);

            // If text-only is preferred (common for LLMs)
            if (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'text/plain') !== false) {
                $optimizedBody = strip_tags($optimizedBody);
                $optimizedBody = preg_replace('/\s+/', ' ', $optimizedBody);
                $response = $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            $newBody = new \Plugs\Http\Message\Stream(fopen('php://temp', 'r+'));
            $newBody->write(trim($optimizedBody));
            $response = $response->withBody($newBody);
        }

        return $response->withHeader('X-AI-Optimized', 'true')
            ->withHeader('X-Robots-Tag', 'index, follow');
    }
}
