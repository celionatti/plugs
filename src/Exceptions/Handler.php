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
            $e instanceof MethodNotAllowedException => $this->renderHttpException($request, $e),
            $e instanceof RouteNotFoundException => $this->renderRouteNotFoundException($request, $e),
            $e instanceof HttpException => $this->renderHttpException($request, $e),
            default => $this->renderGenericException($request, $e),
        };
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
            return $this->renderDebugPage($e);
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
        // Try to render a view for the error
        try {
            $viewEngine = $this->container->make('view');
            $html = $viewEngine->render("errors.{$statusCode}", [
                'message' => $message,
                'statusCode' => $statusCode,
            ]);

            return ResponseFactory::html($html, $statusCode);
        } catch (Throwable $e) {
            // Fallback to basic error page
            return ResponseFactory::html(
                getProductionErrorHtml($statusCode, null, $message),
                $statusCode
            );
        }
    }

    /**
     * Render the debug page.
     *
     * @param Throwable $e
     * @return ResponseInterface
     */
    protected function renderDebugPage(Throwable $e): ResponseInterface
    {
        // Ensure error.php is loaded (may have been deferred in production)
        $errorFile = dirname(__DIR__) . '/functions/error.php';
        if (!function_exists('renderDebugErrorPage') && file_exists($errorFile)) {
            require_once $errorFile;
        }

        ob_start();
        renderDebugErrorPage($e);
        $html = ob_get_clean();

        $statusCode = $e instanceof PlugsException ? $e->getStatusCode() : 500;

        return ResponseFactory::html($html, $statusCode);
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
