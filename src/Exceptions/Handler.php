<?php

declare(strict_types=1);

namespace Plugs\Exceptions;

/*
|--------------------------------------------------------------------------
| Exception Handler
|--------------------------------------------------------------------------
|
| This class handles all exceptions thrown in the application. It provides
| methods for reporting and rendering exceptions in a consistent manner.
*/

use Plugs\Container\Container;
use Plugs\Exceptions\DatabaseException;
use Plugs\Exceptions\TokenMismatchException;
use Plugs\Exceptions\MissingRouteParameterException;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class Handler
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * A list of exception types that should not be reported.
     *
     * @var array<class-string<Throwable>>
     */
    protected array $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        RouteNotFoundException::class,
        RateLimitException::class,
    ];

    /**
     * A list of exception types with their corresponding custom handlers.
     *
     * @var array<class-string<Throwable>, callable>
     */
    protected array $customHandlers = [];

    /**
     * Indicates if the application is in debug mode.
     *
     * @var bool
     */
    protected bool $debug = false;

    /**
     * Guard against recursive error page rendering.
     *
     * @var bool
     */
    private bool $renderingErrorPage = false;

    /**
     * Create a new exception handler instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->debug = (bool) config('app.debug', false);
    }

    /**
     * Report or log an exception.
     *
     * @param Throwable $e
     * @return void
     */
    public function report(Throwable $e): void
    {
        // Check if exception has custom reporting
        if ($e instanceof PlugsException && $e->report() === false) {
            return;
        }

        // Don't report certain exceptions
        if ($this->shouldntReport($e)) {
            return;
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
            $logger->error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'context' => $e instanceof PlugsException ? $e->getContext() : [],
            ]);
        } catch (Throwable $ex) {
            // If logging fails, write to error log
            error_log($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        }
    }

    /**
     * Determine if the exception should not be reported.
     *
     * @param Throwable $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add exception types that should not be reported.
     *
     * @param array<class-string<Throwable>> $types
     * @return static
     */
    public function dontReport(array $types): static
    {
        $this->dontReport = array_merge($this->dontReport, $types);

        return $this;
    }

    /**
     * Register a custom handler for an exception type.
     *
     * @param string $exceptionClass
     * @param callable $handler
     * @return static
     */
    public function renderable(string $exceptionClass, callable $handler): static
    {
        $this->customHandlers[$exceptionClass] = $handler;

        return $this;
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param ServerRequestInterface $request
     * @param Throwable $e
     * @return ResponseInterface
     */
    public function render(ServerRequestInterface $request, Throwable $e): ResponseInterface
    {
        // Check if exception has custom rendering
        if ($e instanceof PlugsException) {
            $response = $e->render();
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        // Check for custom handlers
        foreach ($this->customHandlers as $exceptionClass => $handler) {
            if ($e instanceof $exceptionClass) {
                $response = $handler($e, $request);
                if ($response instanceof ResponseInterface) {
                    return $response;
                }
            }
        }

        // Handle specific exception types
        return match (true) {
            $e instanceof ValidationException => $this->renderValidationException($request, $e),
            $e instanceof AuthenticationException => $this->renderAuthenticationException($request, $e),
            $e instanceof AuthorizationException => $this->renderAuthorizationException($request, $e),
            $e instanceof ModelNotFoundException => $this->renderModelNotFoundException($request, $e),
            $e instanceof MethodNotAllowedException => $this->renderMethodNotAllowedException($request, $e),
            $e instanceof RateLimitException => $this->renderRateLimitException($request, $e),
            $e instanceof RouteNotFoundException => $this->renderRouteNotFoundException($request, $e),
            $e instanceof TokenMismatchException => $this->renderTokenMismatchException($request, $e),
            $e instanceof DatabaseException => $this->renderDatabaseException($request, $e),
            $e instanceof EncryptionException => $this->renderEncryptionException($request, $e),
            $e instanceof HttpException => $this->renderHttpException($request, $e),
            $e instanceof MissingRouteParameterException => $this->renderMissingRouteParameterException($request, $e),
            default => $this->renderGenericException($request, $e),
        };
    }

    /**
     * Render a missing route parameter exception.
     *
     * @param ServerRequestInterface $request
     * @param MissingRouteParameterException $e
     * @return ResponseInterface
     */
    protected function renderMissingRouteParameterException(
        ServerRequestInterface $request,
        MissingRouteParameterException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json($e->toArray(), 500);
        }

        if ($this->debug) {
            throw $e;
        }

        return $this->renderErrorPage($request, 500, $e->getMessage());
    }

    /**
     * Render a validation exception.
     *
     * @param ServerRequestInterface $request
     * @param ValidationException $e
     * @return ResponseInterface
     */
    protected function renderValidationException(
        ServerRequestInterface $request,
        ValidationException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json($e->toArray(), 422);
        }

        // Redirect back with errors
        $redirectTo = $e->getRedirectTo() ?? $this->getPreviousUrl($request);

        return ResponseFactory::redirect($redirectTo)
            ->withErrors($e->errors())
            ->withInput($request->getParsedBody() ?? []);
    }

    /**
     * Render an authentication exception.
     *
     * @param ServerRequestInterface $request
     * @param AuthenticationException $e
     * @return ResponseInterface
     */
    protected function renderAuthenticationException(
        ServerRequestInterface $request,
        AuthenticationException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $e->getMessage()], 401);
        }

        $redirectTo = $e->redirectTo() ?? '/login';

        return ResponseFactory::redirect($redirectTo);
    }

    /**
     * Render an authorization exception.
     *
     * @param ServerRequestInterface $request
     * @param AuthorizationException $e
     * @return ResponseInterface
     */
    protected function renderAuthorizationException(
        ServerRequestInterface $request,
        AuthorizationException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $e->getMessage()], 403);
        }

        return $this->renderErrorPage($request, 403, $e->getMessage());
    }

    /**
     * Render a model not found exception.
     *
     * @param ServerRequestInterface $request
     * @param ModelNotFoundException $e
     * @return ResponseInterface
     */
    protected function renderModelNotFoundException(
        ServerRequestInterface $request,
        ModelNotFoundException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $e->getMessage()], 404);
        }

        return $this->renderErrorPage($request, 404);
    }

    /**
     * Render a route not found exception.
     *
     * @param ServerRequestInterface $request
     * @param RouteNotFoundException $e
     * @return ResponseInterface
     */
    protected function renderRouteNotFoundException(
        ServerRequestInterface $request,
        RouteNotFoundException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => 'Not Found'], 404);
        }

        return $this->renderErrorPage($request, 404);
    }

    /**
     * Render a method not allowed exception.
     *
     * @param ServerRequestInterface $request
     * @param MethodNotAllowedException $e
     * @return ResponseInterface
     */
    protected function renderMethodNotAllowedException(
        ServerRequestInterface $request,
        MethodNotAllowedException $e
    ): ResponseInterface {
        $statusCode = 405;
        $allowedMethods = $e->getAllowedMethods();

        if ($this->expectsJson($request)) {
            return ResponseFactory::json([
                'message' => $e->getMessage(),
                'allowed_methods' => $allowedMethods,
            ], $statusCode)->withHeader('Allow', implode(', ', $allowedMethods));
        }

        $response = $this->renderErrorPage($request, $statusCode, $e->getMessage());

        return $response->withHeader('Allow', implode(', ', $allowedMethods));
    }

    /**
     * Render a rate limit exception.
     *
     * @param ServerRequestInterface $request
     * @param RateLimitException $e
     * @return ResponseInterface
     */
    protected function renderRateLimitException(
        ServerRequestInterface $request,
        RateLimitException $e
    ): ResponseInterface {
        $statusCode = 429;
        $retryAfter = $e->getRetryAfter();
        $headers = $retryAfter ? ['Retry-After' => (string) $retryAfter] : [];

        if ($this->expectsJson($request)) {
            $response = ResponseFactory::json([
                'message' => $e->getMessage(),
                'retry_after' => $retryAfter,
            ], $statusCode);

            foreach ($headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        }

        $response = $this->renderErrorPage($request, $statusCode, $e->getMessage());

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Render an encryption exception.
     *
     * Avoids leaking security-sensitive details in production.
     *
     * @param ServerRequestInterface $request
     * @param EncryptionException $e
     * @return ResponseInterface
     */
    protected function renderEncryptionException(
        ServerRequestInterface $request,
        EncryptionException $e
    ): ResponseInterface {
        $message = $this->debug ? $e->getMessage() : 'A data security error occurred.';

        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $message], 500);
        }

        if ($this->debug) {
            return $this->renderDebugPage($e, $request);
        }

        return $this->renderErrorPage($request, 500, $message);
    }

    /**
     * Render an HTTP exception.
     *
     * @param ServerRequestInterface $request
     * @param HttpException $e
     * @return ResponseInterface
     */
    protected function renderHttpException(
        ServerRequestInterface $request,
        HttpException $e
    ): ResponseInterface {
        $statusCode = $e->getStatusCode();

        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $e->getMessage()], $statusCode);
        }

        $response = $this->renderErrorPage($request, $statusCode, $e->getMessage());

        // Add any custom headers
        foreach ($e->getHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * Render a generic exception.
     *
     * @param ServerRequestInterface $request
     * @param Throwable $e
     * @return ResponseInterface
     */
    protected function renderGenericException(
        ServerRequestInterface $request,
        Throwable $e
    ): ResponseInterface {
        $statusCode = $e instanceof PlugsException ? $e->getStatusCode() : 500;

        if ($this->expectsJson($request)) {
            $data = ['message' => 'Server Error'];

            if ($this->debug) {
                $data = [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ];
            }

            return ResponseFactory::json($data, $statusCode);
        }

        if ($this->debug) {
            return $this->renderDebugPage($e, $request);
        }

        return $this->renderErrorPage($request, $statusCode);
    }

    /**
     * Render an error page.
     *
     * @param ServerRequestInterface $request
     * @param int $statusCode
     * @param string|null $message
     * @return ResponseInterface
     */
    protected function renderErrorPage(
        ServerRequestInterface $request,
        int $statusCode,
        ?string $message = null
    ): ResponseInterface {
        // Guard against recursive error page rendering
        if ($this->renderingErrorPage) {
            // Ensure error helpers are available for the fallback
            $errorFile = dirname(__DIR__) . '/functions/error.php';
            if (!function_exists('getProductionErrorHtml') && file_exists($errorFile)) {
                require_once $errorFile;
            }

            $nonce = $request->getAttribute('csp_nonce');
            return ResponseFactory::html(
                function_exists('getProductionErrorHtml')
                ? getProductionErrorHtml($statusCode, null, $message, $nonce)
                : "<h1>Error {$statusCode}</h1><p>" . htmlspecialchars($message ?? 'An error occurred.') . '</p>',
                $statusCode
            );
        }

        $this->renderingErrorPage = true;

        try {
            $viewEngine = $this->container->make('view');
            $html = $viewEngine->render("errors.{$statusCode}", [
                'message' => $message,
                'statusCode' => $statusCode,
            ]);

            return ResponseFactory::html($html, $statusCode);
        } catch (Throwable $e) {
            // Ensure error helpers are available for the fallback
            $errorFile = dirname(__DIR__) . '/functions/error.php';
            if (!function_exists('getProductionErrorHtml') && file_exists($errorFile)) {
                require_once $errorFile;
            }

            $nonce = $request->getAttribute('csp_nonce');
            return ResponseFactory::html(
                function_exists('getProductionErrorHtml')
                ? getProductionErrorHtml($statusCode, null, $message, $nonce)
                : "<h1>Error {$statusCode}</h1><p>" . htmlspecialchars($message ?? 'An error occurred.') . '</p>',
                $statusCode
            );
        } finally {
            $this->renderingErrorPage = false;
        }
    }

    /**
     * Render the debug page.
     *
     * @param Throwable $e
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    protected function renderDebugPage(Throwable $e, ?ServerRequestInterface $request = null): ResponseInterface
    {
        try {
            // Ensure error.php is loaded (may have been deferred in production)
            $errorFile = dirname(__DIR__) . '/functions/error.php';
            if (!function_exists('renderDebugErrorPage') && file_exists($errorFile)) {
                require_once $errorFile;
            }

            $nonce = $request ? $request->getAttribute('csp_nonce') : null;

            ob_start();
            renderDebugErrorPage($e, $nonce);
            $html = ob_get_clean();

            $statusCode = $e instanceof PlugsException ? $e->getStatusCode() : 500;

            return ResponseFactory::html($html, $statusCode);
        } catch (Throwable $renderError) {
            // Emergency fallback — ensure user always sees something
            ob_end_clean(); // Clean any partial output

            $html = '<!DOCTYPE html><html><head><title>Error</title></head><body style="font-family:monospace;padding:2rem;background:#111;color:#eee;">';
            $html .= '<h1 style="color:#ef4444;">' . htmlspecialchars(get_class($e)) . '</h1>';
            $html .= '<p style="font-size:1.2rem;">' . htmlspecialchars($e->getMessage()) . '</p>';
            $html .= '<pre style="background:#1a1a2e;padding:1rem;border-radius:8px;overflow:auto;margin:1rem 0;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            $html .= '<hr style="border-color:#333;">';
            $html .= '<p style="color:#f59e0b;">⚠ Debug page rendering also failed: ' . htmlspecialchars($renderError->getMessage()) . '</p>';
            $html .= '</body></html>';

            return ResponseFactory::html($html, 500);
        }
    }

    /**
     * Render a token mismatch (CSRF) exception.
     *
     * @param ServerRequestInterface $request
     * @param TokenMismatchException $e
     * @return ResponseInterface
     */
    protected function renderTokenMismatchException(
        ServerRequestInterface $request,
        TokenMismatchException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            return ResponseFactory::json(['message' => $e->getMessage()], 419);
        }

        if ($this->debug) {
            return $this->renderDebugPage($e, $request);
        }

        return $this->renderErrorPage($request, 419, $e->getMessage());
    }

    /**
     * Render a database exception.
     *
     * @param ServerRequestInterface $request
     * @param DatabaseException $e
     * @return ResponseInterface
     */
    protected function renderDatabaseException(
        ServerRequestInterface $request,
        DatabaseException $e
    ): ResponseInterface {
        if ($this->expectsJson($request)) {
            $data = ['message' => 'A database error occurred.'];

            if ($this->debug) {
                $data = [
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ];
            }

            return ResponseFactory::json($data, 500);
        }

        if ($this->debug) {
            return $this->renderDebugPage($e, $request);
        }

        // In production, never expose database details
        return $this->renderErrorPage($request, 500);
    }

    /**
     * Determine if the request expects a JSON response.
     *
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function expectsJson(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');
        $isXhr = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || $isXhr;
    }

    /**
     * Get the previous URL from the request.
     *
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function getPreviousUrl(ServerRequestInterface $request): string
    {
        return $request->getHeaderLine('Referer') ?: '/';
    }

    /**
     * Handle the exception from beginning to end.
     *
     * @param Throwable $e
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    public function handle(Throwable $e, ?ServerRequestInterface $request = null): ResponseInterface
    {
        $this->report($e);

        if ($request === null) {
            // Create a minimal request for rendering
            $request = $this->container->make(ServerRequestInterface::class);
        }

        return $this->render($request, $e);
    }
}
