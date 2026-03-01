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
 * AuthenticateMiddleware
 *
 * Protects routes by verifying that the request is authenticated.
 * Supports multiple guards — tries each in order, first one that
 * authenticates the user wins.
 *
 * Usage:
 *     new AuthenticateMiddleware()         // Uses default guard
 *     new AuthenticateMiddleware('api')    // Uses 'api' guard
 *     new AuthenticateMiddleware('session', 'jwt')  // Tries session, then jwt
 */
#[Middleware(layer: MiddlewareLayer::BUSINESS, priority: 100)]
class AuthenticateMiddleware implements MiddlewareInterface
{
    /**
     * The guards to try, in order.
     *
     * @var string[]
     */
    protected array $guards;

    /**
     * The URL to redirect unauthenticated users to.
     */
    protected string $redirectTo;

    /**
     * @param array $guards Guard names to try. Empty = default guard.
     */
    public function __construct(array $guards = [])
    {
        $this->guards = $guards;
        $this->redirectTo = config('auth.login_route', '/login');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $guards = !empty($this->guards)
            ? $this->guards
            : [Auth::getDefaultGuard()];

        foreach ($guards as $guardName) {
            $guard = Auth::guard($guardName);

            if ($guard->check()) {
                // Store which guard authenticated the request
                $request = $request->withAttribute('auth.guard', $guardName);
                $request = $request->withAttribute('auth.user', $guard->user());

                // Update global request reference if available
                if (isset($GLOBALS['__current_request'])) {
                    $GLOBALS['__current_request'] = $request;
                }

                return $handler->handle($request);
            }
        }

        // No guard authenticated the user
        if ($this->shouldReturnJson($request)) {
            return ResponseFactory::json(['message' => 'Unauthenticated.'], 401);
        }

        // Store the intended URL so we can redirect back after login
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['url.intended'] = (string) $request->getUri();

        return ResponseFactory::redirect($this->redirectTo);
    }

    /**
     * Set the redirect URL for unauthenticated requests.
     */
    public function redirectTo(string $url): static
    {
        $this->redirectTo = $url;

        return $this;
    }

    protected function shouldReturnJson(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('Accept')
            && str_contains($request->getHeaderLine('Accept'), 'application/json');
    }
}
