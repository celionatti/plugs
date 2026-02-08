<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

use Plugs\Http\Exceptions\ValidationException;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HandleValidationExceptions implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            // Check if request expects JSON
            $acceptHeader = $request->getHeaderLine('Accept');
            if (strpos($acceptHeader, 'application/json') !== false || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest') {
                return ResponseFactory::json([
                    'message' => 'The given data was invalid.',
                    'errors' => $this->formatErrors($errors),
                ], 422);
            }

            // Standard web request - redirect back with errors
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['errors'] = $this->formatErrors($errors);

            // Redirect back - using Referer header or root as fallback
            $referer = $request->getHeaderLine('Referer') ?: '/';

            return ResponseFactory::redirect($referer);
        }
    }

    private function formatErrors($errors): array
    {
        return $errors->toArray();
    }
}
