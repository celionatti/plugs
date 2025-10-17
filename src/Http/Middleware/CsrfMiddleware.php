<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| CsrfMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles Cross-Site Request Forgery (CSRF) protection.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    private $except = [];
    
    public function __construct(array $except = [])
    {
        $this->except = $except;
    }
    
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = $request->getMethod();
        
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $path = $request->getUri()->getPath();
            
            foreach ($this->except as $pattern) {
                if (preg_match($pattern, $path)) {
                    return $handler->handle($request);
                }
            }
            
            if (!$this->tokensMatch($request)) {
                throw new \RuntimeException('CSRF token mismatch', 419);
            }
        }
        
        return $handler->handle($request);
    }
    
    private function tokensMatch(ServerRequestInterface $request): bool
    {
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        
        return $token !== null && hash_equals($sessionToken, $token);
    }
    
    private function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        $post = $request->getParsedBody();
        if (is_array($post) && isset($post['_token'])) {
            return $post['_token'];
        }
        
        $header = $request->getHeaderLine('X-CSRF-TOKEN');
        if ($header) {
            return $header;
        }
        
        return null;
    }
}