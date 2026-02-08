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

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('security.headers', []), $config);

        // Add CSP if enabled in config
        if (config('security.csp.enabled', false)) {
            $this->config['Content-Security-Policy'] = $this->buildCspHeader();
        }

        // Add HSTS if not already present
        if (!isset($this->config['Strict-Transport-Security'])) {
            $this->config['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Generate and inject nonce if CSP is enabled
        if (config('security.csp.enabled', false)) {
            $nonce = base64_encode(random_bytes(16));

            // Inject into ViewEngine
            $container = \Plugs\Container\Container::getInstance();
            if ($container->has(\Plugs\View\ViewEngine::class)) {
                $viewEngine = $container->make(\Plugs\View\ViewEngine::class);
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

    private function buildCspHeader(?string $nonce = null): string
    {
        $cspConfig = config('security.csp', []);
        $directives = [];

        foreach ($cspConfig as $key => $values) {
            if ($key === 'enabled' || !is_array($values)) {
                continue;
            }

            // Inject nonce into script-src if available
            if ($nonce && $key === 'script-src') {
                $values[] = "'nonce-{$nonce}'";
            }

            $directives[] = $key . ' ' . implode(' ', $values);
        }

        return implode('; ', $directives);
    }
}
