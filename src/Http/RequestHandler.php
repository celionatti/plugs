<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| RequestHandler Class
|--------------------------------------------------------------------------
|
| This class is responsible for handling HTTP requests and generating
| appropriate responses. It serves as the main entry point for processing
| HTTP requests in the application.
*/

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface
{
    private $middleware = [];
    private $fallbackHandler;

    public function __construct(callable $fallbackHandler)
    {
        $this->fallbackHandler = $fallbackHandler;
    }

    public function pipe($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // If no middleware left, use fallback
        if (empty($this->middleware)) {
            return ($this->fallbackHandler)($request);
        }

        $middleware = array_shift($this->middleware);
        
        return $middleware->process($request, $this);
    }
}