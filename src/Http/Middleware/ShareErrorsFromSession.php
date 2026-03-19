<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Container\Container;
use Plugs\View\ErrorMessage;
use Plugs\View\ViewEngineInterface;
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
            $errors = new ErrorMessage($_SESSION['_errors'] ?? []);

            // Share 'errors' variable with all views
            if ($view instanceof ViewEngineInterface) {
                $view->share('errors', $errors);
            }

            // Clear errors from session after sharing
            if (isset($_SESSION['_errors'])) {
                unset($_SESSION['_errors']);
            }
        }

        return $handler->handle($request);
    }
}
