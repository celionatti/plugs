<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Container\Container;
use Plugs\View\ErrorMessage;
use Plugs\View\ViewEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ShareErrorsFromSession implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $container = Container::getInstance();

        // Share errors with view
        if ($container->bound('view')) {
            $view = $container->make('view');
            $errors = new ErrorMessage($_SESSION['errors'] ?? []);

            // Share 'errors' variable with all views
            if ($view instanceof ViewEngine) {
                $view->share('errors', $errors);
            }

            // Clear errors from session after sharing
            // We use 'flash' mechanism usually, but simple unset works for now
            // typically errors are flashed, so they persist for one redirect.
            // If they were already in session, it means they were flashed.
            if (isset($_SESSION['errors'])) {
                unset($_SESSION['errors']);
            }
        }

        return $handler->handle($request);
    }
}
