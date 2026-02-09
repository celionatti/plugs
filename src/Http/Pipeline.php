<?php

declare(strict_types=1);

namespace Plugs\Http;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Pipeline
{
    protected ServerRequestInterface $passable;
    protected array $pipes = [];
    protected string $method = 'process';

    public function send(ServerRequestInterface $passable): self
    {
        $this->passable = $passable;
        return $this;
    }

    public function through(array $pipes): self
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function then(Closure $destination): ResponseInterface
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    protected function prepareDestination(Closure $destination): Closure
    {
        return function ($passable) use ($destination) {
            return $destination($passable);
        };
    }

    protected function carry(): Closure
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                if (is_callable($pipe)) {
                    // If pipe is a callable (Closure), call it directly
                    // It should accept ($request, $next)
                    return $pipe($passable, $stack);
                } elseif (is_string($pipe) && class_exists($pipe)) {
                    // If pipe is a class string, instantiate it
                    $pipeInstance = new $pipe();
                } else {
                    $pipeInstance = $pipe;
                }

                if ($pipeInstance instanceof MiddlewareInterface) {
                    // Adapt PSR-15 middleware to callable stack
                    // PSR-15 Process: process(Request, Handler)
                    // Our stack is a callable that acts as a Handler
                    $handler = new class ($stack) implements RequestHandlerInterface {
                        private $next;
                        public function __construct($next)
                        {
                            $this->next = $next; }
                        public function handle(ServerRequestInterface $request): ResponseInterface
                        {
                            return ($this->next)($request);
                        }
                    };
                    return $pipeInstance->process($passable, $handler);
                }

                // Fallback for non-PSR-15 classes with custom method
                if (method_exists($pipeInstance, $this->method)) {
                    return $pipeInstance->{$this->method}($passable, $stack);
                }

                throw new \InvalidArgumentException("Invalid pipe type.");
            };
        };
    }
}
