<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| CorsMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles Cross-Origin Resource Sharing (CORS) settings.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Message\Response;
use Plugs\Http\Message\Stream;

class CorsMiddleware implements MiddlewareInterface
{
    private $allowedOrigins;
    private $allowedMethods;
    private $allowedHeaders;
    private $maxAge;
    private $allowCredentials;
    
    public function __construct(
        array $allowedOrigins = ['*'],
        array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN'],
        int $maxAge = 86400,
        bool $allowCredentials = true
    ) {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedMethods = $allowedMethods;
        $this->allowedHeaders = $allowedHeaders;
        $this->maxAge = $maxAge;
        $this->allowCredentials = $allowCredentials;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Handle preflight requests
        if ($request->getMethod() === 'OPTIONS') {
            $body = new Stream(fopen('php://temp', 'r+'));
            $response = new Response(204, $body);
        } else {
            $response = $handler->handle($request);
        }
        
        $origin = $request->getHeaderLine('Origin');
        
        if ($this->isOriginAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin ?: '*')
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
            
            if ($this->allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }
        
        return $response;
    }
    
    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }
        
        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }
        
        return in_array($origin, $this->allowedOrigins);
    }
}