<?php

declare(strict_types=1);

namespace Modules\Admin\Middleware;

use Plugs\Facades\Auth;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminMiddleware implements MiddlewareInterface
{
    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Auth::user();

        // Basic check for admin role.
        // Assuming the User model has an isAdmin() method or a role property.
        if (!$user || (method_exists($user, 'isAdmin') && !$user->isAdmin()) && ($user->role ?? '') !== 'admin') {
            if ($request->hasHeader('Accept') && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
                return ResponseFactory::json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            return ResponseFactory::redirect('/');
        }

        return $handler->handle($request);
    }
}
