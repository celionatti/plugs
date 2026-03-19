<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

/**
 * Flash Middleware
 *
 * Manages flash data lifecycle:
 * - Starts session if needed
 * - Marks old flash data for deletion
 * - Cleans up flash data after response
 */
#[Middleware(layer: MiddlewareLayer::BUSINESS, priority: 200)]
class FlashMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Only age flash data if it's NOT an AJAX/SPA request
        // This prevents background SPA/AJAX requests from clearing the main page's flash
        if (!$this->isAjaxRequest($request)) {
            $this->ageFlashData();
        }

        // Process the request
        $response = $handler->handle($request);

        // If response is a RedirectResponse, store its flash data now
        if ($response instanceof \Plugs\Http\RedirectResponse) {
            $response->storeFlashData();
        }

        // For AJAX/SPA requests, inject the X-Plugs-Flash header
        if ($this->isAjaxRequest($request)) {
            $response = $this->processAjaxFlash($request, $response);
        }

        return $response;
    }

    /**
     * Process flash messages for AJAX/SPA requests.
     * Injects them as a JSON header for the SPA script to handle.
     */
    protected function processAjaxFlash(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $messages = \Plugs\Utils\FlashMessage::get(clear: true);
        
        if (!empty($messages)) {
            // Encode the first message or all? plugs-spa.js seems to handle it.
            // Documentation says: header('X-Plugs-Flash: ' . json_encode(['type' => 'success', 'message' => 'Saved!']));
            // But if there are multiple, we should probably send them all if the JS supports it.
            // Looking at plugs-spa.js, it does JSON.parse(flashHeader).
            
            // If we send the first one as requested by docs:
            $flash = $messages[0];
            return $response->withHeader('X-Plugs-Flash', json_encode($flash));
        }

        return $response;
    }

    /**
     * Check if the request is an AJAX/SPA request that should not age flash data
     */
    protected function isAjaxRequest(ServerRequestInterface $request): bool
    {
        // Standard AJAX check
        if (strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        // SPA-specific checks
        if ($request->hasHeader('X-Plugs-SPA') || $request->hasHeader('X-Plugs-Section')) {
            return true;
        }

        return false;
    }

    /**
     * Age the flash data for the session
     */
    protected function ageFlashData(): void
    {
        if (!isset($_SESSION['_flash'])) {
            return;
        }

        // We no longer unset the entire $_SESSION['_flash'] here.
        // Instead, FlashMessage::get() is responsible for clearing messages 
        // after they are read, avoiding issues with concurrent requests or 
        // the middleware clearing data before the layout renders.
        // 
        // We still mark it so older messages could theoretically be cleaned 
        // up, but in practice get() clears them.

        if (!isset($_SESSION['_flash']['_delete_next']) || $_SESSION['_flash']['_delete_next'] !== true) {
            // Mark current flash data for deletion on next request
            // (Used as a general age indicator, though actual clearing happens in get())
            $_SESSION['_flash']['_delete_next'] = true;
        }
    }
}
