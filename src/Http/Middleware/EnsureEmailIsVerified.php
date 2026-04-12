<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Facades\Auth;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

/**
 * EnsureEmailIsVerified Middleware
 * 
 * Redirects users to the email verification notice if their email
 * address has not been verified.
 */
#[Middleware(layer: MiddlewareLayer::BUSINESS, priority: 110)]
class EnsureEmailIsVerified implements MiddlewareInterface
{
    /**
     * Handle an incoming request.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = Auth::user();

        // Check if email verification is enabled via DB settings or config
        $enabled = (bool) \setting('auth_verification', config('auth.email_verification.enabled', true));

        if ($enabled && $user && method_exists($user, 'hasVerifiedEmail') && !$user->hasVerifiedEmail()) {
            $path = $request->getUri()->getPath();
            
            // Allow access to verification routes, logout and Admin Panel
            $excludedPaths = [
                '/verify-email', 
                '/logout', 
                '/email/verification-notification',
                '/email/verify',
                '/admin'
            ];

            // Simple prefix/exact check
            $isExcluded = false;
            foreach ($excludedPaths as $excludedPath) {
                if ($path === $excludedPath || str_starts_with($path, $excludedPath . '/')) {
                    $isExcluded = true;
                    break;
                }
            }

            if (!$isExcluded) {
                if ($this->shouldReturnJson($request)) {
                    return ResponseFactory::json(['message' => 'Your email address is not verified.'], 403);
                }

                return ResponseFactory::redirect('/verify-email');
            }
        }

        return $handler->handle($request);
    }

    /**
     * Determine if the request expects a JSON response.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function shouldReturnJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        return str_contains($accept, 'application/json');
    }
}
