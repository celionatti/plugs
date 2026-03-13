<?php

declare(strict_types = 1)
;

namespace Plugs\Exceptions;

/* |-------------------------------------------------------------------------- | Exception Handler |-------------------------------------------------------------------------- | | This class handles all exceptions thrown in the application. It provides | methods for reporting and rendering exceptions in a consistent manner. */

use Plugs\Container\Container;
use Plugs\Exceptions\AuthenticationException;
use Plugs\Exceptions\AuthorizationException;
use Plugs\Exceptions\DatabaseException;
use Plugs\Exceptions\EncryptionException;
use Plugs\Exceptions\HttpException;
use Plugs\Exceptions\MethodNotAllowedException;
use Plugs\Exceptions\MissingRouteParameterException;
use Plugs\Exceptions\ModelNotFoundException;
use Plugs\Exceptions\PlugsException;
use Plugs\Exceptions\RateLimitException;
use Plugs\Exceptions\RouteNotFoundException;
use Plugs\Exceptions\TokenMismatchException;
use Plugs\Exceptions\ValidationException;
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
        AuthenticationException::class ,
        AuthorizationException::class ,
        ValidationException::class ,
        HttpException::class ,
        ModelNotFoundException::class ,
        RouteNotFoundException::class ,
        RateLimitException::class ,
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
        $this->debug = (bool)config('app.debug', false);
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

        // Emit ExceptionThrown event
        if ($this->container->has('events')) {
            $this->container->make('events')->dispatch(
                new \Plugs\Event\Core\ExceptionThrown($e)
            );
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
        }
        catch (Throwable $ex) {
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
        if ($e instanceof ValidationException) {
            return $this->renderValidationException($request, $e);
        }

        if ($e instanceof AuthenticationException) {
            return $this->renderAuthenticationException($request, $e);
        }

        if ($e instanceof AuthorizationException) {
            return $this->renderAuthorizationException($request, $e);
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->renderModelNotFoundException($request, $e);
        }

        if ($e instanceof MethodNotAllowedException) {
            return $this->renderMethodNotAllowedException($request, $e);
        }

        if ($e instanceof RateLimitException) {
            return $this->renderRateLimitException($request, $e);
        }

        if ($e instanceof RouteNotFoundException) {
            return $this->renderRouteNotFoundException($request, $e);
        }

        if ($e instanceof TokenMismatchException) {
            return $this->renderTokenMismatchException($request, $e);
        }

        if ($e instanceof DatabaseException) {
            return $this->renderDatabaseException($request, $e);
        }

        if ($e instanceof EncryptionException) {
            return $this->renderEncryptionException($request, $e);
        }

        if ($e instanceof HttpException) {
            return $this->renderHttpException($request, $e);
        }

        if ($e instanceof MissingRouteParameterException) {
            return $this->renderMissingRouteParameterException($request, $e);
        }

        return $this->renderGenericException($request, $e);
    }

    /**
     * Build an RFC 7807 Problem Details JSON response.
     *
     * @param int $status The HTTP status code.
     * @param string $title A short, human-readable summary of the problem type.
     * @param string $detail A human-readable explanation specific to this occurrence of the problem.
     * @param string|null $type A URI reference that identifies the problem type (RFC 7807).
     * @param ServerRequestInterface|null $request The current request, to include the instance URI.
     * @param array $additional Additional data to merge into the response (like validation errors).
     * @return ResponseInterface
     */
    protected function buildJsonError(
        int $status,
        string $title,
        string $detail,
        ?string $type = null,
        ?ServerRequestInterface $request = null,
        array $additional = []
        ): ResponseInterface
    {
        $type = $type ?? "https://httpstatuses.com/{$status}";

        $payload = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($request !== null) {
            $payload['instance'] = $request->getUri()->getPath();
        }

        if (!empty($additional)) {
            $payload = array_merge($payload, $additional);
        }

        return ResponseFactory::json($payload, $status)
            ->withHeader('Content-Type', 'application/problem+json');
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                500,
                'Internal Server Error',
                $e->getMessage(),
                null,
                $request
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                422,
                'Unprocessable Entity',
                $e->getMessage() ?: 'The provided data was invalid.',
                'https://httpstatuses.com/422',
                $request,
            ['errors' => $e->errors()]
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                401,
                'Unauthenticated',
                $e->getMessage() ?: 'Authentication is required to access this resource.',
                'https://httpstatuses.com/401',
                $request
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                403,
                'Forbidden',
                $e->getMessage() ?: 'You do not have permission to access this resource.',
                'https://httpstatuses.com/403',
                $request
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                404,
                'Not Found',
                $e->getMessage() ?: 'The requested resource was not found.',
                'https://httpstatuses.com/404',
                $request
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                404,
                'Not Found',
                $e->getMessage() ?: 'The requested route was not found.',
                'https://httpstatuses.com/404',
                $request
            );
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
        ): ResponseInterface
    {
        $statusCode = 405;
        $allowedMethods = $e->getAllowedMethods();

        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                $statusCode,
                'Method Not Allowed',
                $e->getMessage() ?: 'The HTTP method is not allowed for this route.',
                "https://httpstatuses.com/{$statusCode}",
                $request,
            ['allowed_methods' => $allowedMethods]
            )->withHeader('Allow', implode(', ', $allowedMethods));
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
        ): ResponseInterface
    {
        $statusCode = 429;
        $retryAfter = $e->getRetryAfter();
        $headers = $retryAfter ? ['Retry-After' => (string)$retryAfter] : [];

        if ($this->expectsJson($request)) {
            $response = $this->buildJsonError(
                $statusCode,
                'Too Many Requests',
                $e->getMessage() ?: 'Rate limit exceeded.',
                "https://httpstatuses.com/{$statusCode}",
                $request,
            ['retry_after' => $retryAfter]
            );

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
        ): ResponseInterface
    {
        $message = $this->debug ? $e->getMessage() : 'A data security error occurred.';

        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                500,
                'Encryption Error',
                $message,
                'https://httpstatuses.com/500',
                $request
            );
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
        ): ResponseInterface
    {
        $statusCode = $e->getStatusCode();

        if ($this->expectsJson($request)) {
            $response = $this->buildJsonError(
                $statusCode,
                'HTTP Exception',
                $e->getMessage(),
                "https://httpstatuses.com/{$statusCode}",
                $request
            );

            // Add any custom headers from the exception
            foreach ($e->getHeaders() as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
            return $response;
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
        ): ResponseInterface
    {
        $statusCode = $e instanceof PlugsException ? $e->getStatusCode() : 500;

        if ($this->expectsJson($request)) {
            $detail = 'An unexpected server error occurred.';
            $additional = [];

            if ($this->debug) {
                $detail = $e->getMessage();
                $additional = [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString()),
                ];
            }

            return $this->buildJsonError(
                $statusCode,
                'Internal Server Error',
                $detail,
                "https://httpstatuses.com/{$statusCode}",
                $request,
                $additional
            );
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
        ): ResponseInterface
    {
        // Guard against recursive error page rendering
        if ($this->renderingErrorPage) {
            // Ensure error helpers are available for the fallback
            $errorFile = dirname(__DIR__) . '/functions/error.php';
            if (!function_exists('getProductionErrorHtml') && file_exists($errorFile)) {
                require_once $errorFile;
            }

            $nonce = null; // Error CSP explicitly relies on 'unsafe-inline' without nonces
            return ResponseFactory::html(
                function_exists('getProductionErrorHtml')
                ? getProductionErrorHtml($statusCode, null, $message, $nonce)
                : "<h1>Error {$statusCode}</h1><p>" . htmlspecialchars($message ?? 'An error occurred.') . '</p>',
                $statusCode
            )->withHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline';");
        }

        $this->renderingErrorPage = true;

        try {
            $viewEngine = $this->container->make('view');
            $html = $viewEngine->render("errors.{$statusCode}", [
                'message' => $message,
                'statusCode' => $statusCode,
            ]);

            return ResponseFactory::html($html, $statusCode)
                ->withHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline';");
        }
        catch (Throwable $e) {
            // Ensure error helpers are available for the fallback
            $errorFile = dirname(__DIR__) . '/functions/error.php';
            if (!function_exists('getProductionErrorHtml') && file_exists($errorFile)) {
                require_once $errorFile;
            }

            $nonce = null; // Error CSP explicitly relies on 'unsafe-inline' without nonces
            return ResponseFactory::html(
                function_exists('getProductionErrorHtml')
                ? getProductionErrorHtml($statusCode, null, $message, $nonce)
                : "<h1>Error {$statusCode}</h1><p>" . htmlspecialchars($message ?? 'An error occurred.') . '</p>',
                $statusCode
            )->withHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline';");
        }
        finally {
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

            // We explicitly do NOT pass a nonce to the error renderer.
            // The error response header relies on 'unsafe-inline'.
            // If the element has a nonce attribute but the header lacks that exact nonce string,
            // modern browsers will block the styles completely.
            $nonce = null;

            ob_start();
            renderDebugErrorPage($e, $nonce);
            $html = ob_get_clean();

            $statusCode = $e instanceof PlugsException ? $e->getStatusCode() : 500;

            $response = ResponseFactory::html($html, $statusCode);

            // Explicitly set a relaxed CSP so SecurityHeadersMiddleware doesn't overwrite it
            // with strict application rules that block the error page's inline highlighting.
            return $response->withHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline' 'unsafe-eval' data:;");
        }
        catch (Throwable $renderError) {
            // Emergency fallback — ensure user always sees something
            ob_end_clean(); // Clean any partial output

            $html = '<!DOCTYPE html><html><head><title>Error</title></head><body style="font-family:monospace;padding:2rem;background:#111;color:#eee;">';
            $html .= '<h1 style="color:#ef4444;">' . htmlspecialchars(get_class($e)) . '</h1>';
            $html .= '<p style="font-size:1.2rem;">' . htmlspecialchars($e->getMessage()) . '</p>';
            $html .= '<pre style="background:#1a1a2e;padding:1rem;border-radius:8px;overflow:auto;margin:1rem 0;">' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            $html .= '<hr style="border-color:#333;">';
            $html .= '<p style="color:#f59e0b;">⚠ Debug page rendering also failed: ' . htmlspecialchars($renderError->getMessage()) . '</p>';
            $html .= '</body></html>';

            $response = ResponseFactory::html($html, 500);
            return $response->withHeader('Content-Security-Policy', "default-src 'self' 'unsafe-inline';");
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            return $this->buildJsonError(
                419,
                'Token Mismatch',
                $e->getMessage() ?: 'CSRF token mismatch.',
                'https://httpstatuses.com/419',
                $request
            );
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
        ): ResponseInterface
    {
        if ($this->expectsJson($request)) {
            $detail = 'A database error occurred.';
            $additional = [];

            if ($this->debug) {
                $detail = $e->getMessage();
                $additional = [
                    'exception' => get_class($e),
                    'sql' => $e->getSql(),
                    'bindings' => $this->maskSensitiveData($e->getBindings()),
                ];
            }

            return $this->buildJsonError(
                500,
                'Database Error',
                $detail,
                'https://httpstatuses.com/500',
                $request,
                $additional
            );
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
     * Mask sensitive data in an array.
     *
     * @param array $data
     * @return array
     */
    protected function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
            elseif ($this->isSensitiveKey($key)) {
                $data[$key] = '********';
            }
        }

        return $data;
    }

    /**
     * Determine if a key should be considered sensitive.
     *
     * @param string|int $key
     * @return bool
     */
    protected function isSensitiveKey($key): bool
    {
        if (!is_string($key)) {
            return false;
        }

        $sensitivePatterns = [
            'password',
            'passwd',
            'secret',
            'token',
            'key',
            'auth',
            'api',
            'credential',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        $key = strtolower($key);

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains($key, $pattern)) {
                return true;
            }
        }

        return false;
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
