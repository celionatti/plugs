<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Flash Middleware
 *
 * Manages flash data lifecycle:
 * - Starts session if needed
 * - Marks old flash data for deletion
 * - Cleans up flash data after response
 */
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

        // Mark previous flash data for deletion
        $this->ageFlashData();

        // Process the request
        $response = $handler->handle($request);

        // Note: Actual cleanup happens on next request
        // This allows flash data to be available for one full request cycle

        return $response;
    }

    /**
     * Age the flash data for the session
     */
    protected function ageFlashData(): void
    {
        if (!isset($_SESSION['_flash'])) {
            return;
        }

        // If flash data was marked for deletion, delete it now
        if (isset($_SESSION['_flash']['_delete_next']) && $_SESSION['_flash']['_delete_next'] === true) {
            unset($_SESSION['_flash']);
        } else {
            // Mark current flash data for deletion on next request
            $_SESSION['_flash']['_delete_next'] = true;
        }
    }
}
