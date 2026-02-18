<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Facades\Auth;
use Plugs\Exceptions\HttpException;

class Authorize implements MiddlewareInterface
{
    /**
     * The parameters for the authorization check.
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * Set the parameters for the middleware.
     *
     * @param array $parameters
     * @return void
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    /**
     * Process an incoming server request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws HttpException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Auth::user();

        if (!$user) {
            throw new HttpException(401, 'Unauthorized');
        }

        // The first parameter is usually the ability/permission
        $ability = $this->parameters[0] ?? $request->getAttribute('ability');
        $models = array_slice($this->parameters, 1);

        // If no models in parameters, check request attributes
        if (empty($models)) {
            $models = $request->getAttribute('models', []);
        }

        if ($ability && function_exists('gate') && !gate()->check($ability, $models)) {
            throw new HttpException(403, 'This action is unauthorized.');
        }

        return $handler->handle($request);
    }
}
