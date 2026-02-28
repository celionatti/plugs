<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Security\ThreatDetector;
use Plugs\Security\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\ResponseFactory;

/**
 * Built-in Security Engine Middleware
 * Non-optional, strict security enforcement.
 */
class SecurityEngineMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. Request Anomaly Detection (SQLi, XSS, Path Traversal, Invalid Shapes)
        $isLocal = in_array($this->getClientIp($request), ['127.0.0.1', '::1', 'localhost', 'plugs.local']);
        if (!$isLocal && ThreatDetector::isSuspicious($request)) {
            $nonce = base64_encode(random_bytes(16));
            return $this->blockRequest($request, 'Suspicious activity detected. Request blocked by Security Engine.', 403, $nonce);
        }

        // 2. Strict Rate Limiting (by IP)
        // Hardcoded global limit: e.g. 100 requests per minute
        $ip = $this->getClientIp($request);
        $container = class_exists(\Plugs\Container\Container::class) ? \Plugs\Container\Container::getInstance() : null;
        $rateLimiter = $container && $container->bound('ratelimiter') ? $container->make('ratelimiter') : new RateLimiter();
        if (!$isLocal && $rateLimiter->tooManyAttempts($ip, 100)) {
            return $this->blockRequest($request, 'Too many requests. Please try again later.', 429);
        }
        if (!$isLocal) {
            $rateLimiter->hit($ip, 60);
        }

        // 3. Share the nonce from SecurityHeadersMiddleware with the View Engine if it exists
        // (SecurityHeadersMiddleware should have already set it, but we ensure consistency if needed
        // though SecurityHeadersMiddleware actually already injects to ViewEngine, we just don't overwrite it here)

        // Continue the request
        $response = $handler->handle($request);

        // Additional strict security headers (if not already applied by SecurityHeadersMiddleware)
        if (!$response->hasHeader('X-Content-Type-Options')) {
            $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }

    private function blockRequest(ServerRequestInterface $request, string $message, int $status = 403, ?string $nonce = null): ResponseInterface
    {
        $acceptHeader = $request->getHeaderLine('Accept');

        $cspPolicy = $nonce ? sprintf(
            "default-src 'self'; script-src 'self' 'nonce-%s'; style-src 'self' 'nonce-%s' 'unsafe-inline';",
            $nonce,
            $nonce
        ) : "default-src 'self'; style-src 'self' 'unsafe-inline';";

        if (strpos($acceptHeader, 'application/json') !== false) {
            $response = ResponseFactory::json([
                'error' => 'Security Violation',
                'message' => $message,
                'status' => $status
            ], $status);
            return $response->withHeader('Content-Security-Policy', $cspPolicy);
        }

        // Basic HTML layout for security block
        $html = sprintf(
            '<!DOCTYPE html><html><head><title>Access Denied</title><style%s>body{font-family:sans-serif;text-align:center;padding:50px;background:#080b12;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;} .container{max-width:600px;padding:40px;background:rgba(255,255,255,0.05);border-radius:20px;border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(10px);} h1{font-size:3em;margin-bottom:1rem;color:#ef4444;} p{font-size:1.2em;opacity:0.8;}</style></head><body><div class="container"><h1>🛡️ Access Denied</h1><p>%s</p></div></body></html>',
            $nonce ? ' nonce="' . $nonce . '"' : '',
            htmlspecialchars($message)
        );

        $response = ResponseFactory::html($html, $status);
        return $response->withHeader('Content-Security-Policy', $cspPolicy);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR']
            ?? $serverParams['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}
