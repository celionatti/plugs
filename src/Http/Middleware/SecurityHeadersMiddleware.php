<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| SecurityHeadersMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware adds security headers to the HTTP response.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

#[Middleware(layer: MiddlewareLayer::SECURITY, priority: 10)]
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $config = [];

    private array $defaultHeaders = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ];

    private array $defaultCsp = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'"],
        'style-src' => ["'self'", "'unsafe-inline'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'frame-ancestors' => ["'self'"],
    ];

    public function __construct(array $config = [])
    {
        // Merge user config with defaults
        $this->config = array_merge($this->defaultHeaders, config('security.headers', []), $config);

        // Build CSP: merge user config with sensible defaults
        $cspEnabled = config('security.csp.enabled', true); // Enabled by default now
        if ($cspEnabled) {
            $userCsp = config('security.csp', []);
            $mergedCsp = array_merge($this->defaultCsp, $userCsp);
            $this->config['Content-Security-Policy'] = $this->buildCspHeader(null, $mergedCsp);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Generate and inject nonce if CSP is enabled
        if (config('security.csp.enabled', false)) {
            $nonce = base64_encode(random_bytes(16));

            // Inject into ViewEngine
            $container = \Plugs\Container\Container::getInstance();
            if ($container->has(\Plugs\View\ViewEngineInterface::class)) {
                $viewEngine = $container->make(\Plugs\View\ViewEngineInterface::class);
                $viewEngine->setCspNonce($nonce);
            }

            // Inject into AssetManager (via helper if available or container)
            // We use the helper function to ensure we get the initialized instance
            if (function_exists('asset_manager')) {
                asset_manager()->setNonce($nonce);
            }

            // Update CSP header config with nonce
            $this->config['Content-Security-Policy'] = $this->buildCspHeader($nonce);
        }

        $response = $handler->handle($request);

        foreach ($this->config as $header => $value) {
            // Only add header if it doesn't already exist and value is not null
            if ($value !== null && !$response->hasHeader($header)) {
                $response = $response->withHeader($header, $value);
            }
        }

        return $response;
    }

    private function buildCspHeader(?string $nonce = null, ?array $cspConfig = null): string
    {
        $cspConfig = $cspConfig ?? config('security.csp', $this->defaultCsp);

        // Get allowed external domains from config
        $allowedDomains = config('security.csp.allowed_domains', []);

        $directives = [];

        foreach ($cspConfig as $key => $values) {
            if ($key === 'enabled' || $key === 'allowed_domains' || !is_array($values)) {
                continue;
            }

            // Inject nonce into script-src only. 
            // Do NOT inject into style-src because it invalidates 'unsafe-inline' and breaks style="..." attributes.
            if ($nonce && $key === 'script-src') {
                $values[] = "'nonce-{$nonce}'";
            }

            // Add allowed domains to relevant directives
            if (!empty($allowedDomains) && in_array($key, ['script-src', 'style-src', 'font-src', 'img-src', 'connect-src'])) {
                $values = array_merge($values, $allowedDomains);
            }

            $directives[] = $key . ' ' . implode(' ', array_unique($values));
        }

        return implode('; ', $directives);
    }
}
