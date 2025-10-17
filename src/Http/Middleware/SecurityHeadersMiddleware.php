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
    private $config = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ];
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        
        foreach ($this->config as $header => $value) {
            if ($value !== null && !$response->hasHeader($header)) {
                $response = $response->withHeader($header, $value);
            }
        }
        
        return $response;
    }
}