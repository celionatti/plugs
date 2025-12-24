<?php

declare(strict_types=1);

namespace Plugs\Inertia;

/*
|--------------------------------------------------------------------------
| InertiaMiddleware Class
|--------------------------------------------------------------------------
|
| PSR-15 middleware for handling Inertia.js requests. Detects Inertia
| requests, handles version conflicts, and shares common data with
| all Inertia responses.
|
| Register this middleware globally or on specific routes where you
| need Inertia functionality.
*/

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class InertiaMiddleware implements MiddlewareInterface
{
    /**
     * Process the request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Initialize Inertia from config
        Inertia::init();

        // Share common data with all Inertia responses
        $this->shareData($request);

        // Check for version conflict
        if ($this->hasVersionConflict($request)) {
            return $this->handleVersionConflict($request);
        }

        // Process the request
        $response = $handler->handle($request);

        // Handle redirect responses for Inertia requests
        $response = $this->handleRedirect($request, $response);

        // Convert InertiaResponse if returned from handler
        $response = $this->handleInertiaResponse($request, $response);

        // Add Vary header to all responses
        if (!$response->hasHeader('Vary')) {
            $response = $response->withHeader('Vary', 'X-Inertia');
        }

        return $response;
    }

    /**
     * Share data with all Inertia responses
     * 
     * Override this method to add your own shared data
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    protected function shareData(ServerRequestInterface $request): void
    {
        // Share flash messages from session
        $flash = Inertia::getFlashed();
        if (!empty($flash)) {
            Inertia::share('flash', $flash);
        }

        // Share errors from session (if any)
        if (session_status() === PHP_SESSION_ACTIVE) {
            $errors = $_SESSION['_errors'] ?? [];
            if (!empty($errors)) {
                Inertia::share('errors', $errors);
                unset($_SESSION['_errors']);
            }
        }

        // Override this method in your own middleware to add more shared data
        // Example:
        // Inertia::share('auth', [
        //     'user' => $this->getAuthenticatedUser($request),
        // ]);
    }

    /**
     * Check if there's a version conflict
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    private function hasVersionConflict(ServerRequestInterface $request): bool
    {
        if (!$this->isInertiaRequest($request)) {
            return false;
        }

        $clientVersion = $request->getHeaderLine('X-Inertia-Version');
        $serverVersion = Inertia::getVersion();

        // No conflict if no version is set
        if (empty($serverVersion) || empty($clientVersion)) {
            return false;
        }

        return $clientVersion !== $serverVersion;
    }

    /**
     * Handle version conflict by forcing a full page reload
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function handleVersionConflict(ServerRequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();

        return ResponseFactory::create('', 409, [
            'X-Inertia-Location' => $url,
        ]);
    }

    /**
     * Handle redirect responses for Inertia requests
     * 
     * Converts 302 redirects to 303 for PUT/PATCH/DELETE requests
     * to ensure proper browser handling
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function handleRedirect(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isInertiaRequest($request)) {
            return $response;
        }

        $statusCode = $response->getStatusCode();

        // Convert 302 to 303 for non-GET/HEAD requests
        if ($statusCode === 302 && !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $response->withStatus(303);
        }

        return $response;
    }

    /**
     * Handle InertiaResponse objects returned from handlers
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function handleInertiaResponse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // The router should already convert InertiaResponse to PSR-7 Response
        // This is a fallback in case it doesn't
        return $response;
    }

    /**
     * Check if the request is an Inertia request
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    private function isInertiaRequest(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('X-Inertia')
            && $request->getHeaderLine('X-Inertia') === 'true';
    }
}
