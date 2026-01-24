<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| CsrfMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware handles Cross-Site Request Forgery (CSRF) protection.
| It validates CSRF tokens for state-changing HTTP requests and provides
| appropriate error responses when validation fails.
|
| It allows configuration of excluded routes, custom error handling,
| and logging of CSRF validation failures.
|--------------------------------------------------------------------------
|
| Protects against Cross-Site Request Forgery attacks by validating CSRF tokens
 * on state-changing HTTP methods (POST, PUT, PATCH, DELETE).
 *
 *  * Features:
 * - Automatic token validation
 * - Configurable excluded routes
 * - Support for AJAX/API requests
 * - Custom error responses
 * - Integration with SecurityShieldMiddleware
 * - Logging of CSRF validation failures
 * - Easy configuration and customization
 * - PSR-15 Middleware compliant
|--------------------------------------------------------------------------
| Usage:
 *
 * 1. Register the middleware in your application.
 * 2. Configure options as needed (e.g., excluded routes, error handling).
 * 3. Ensure CSRF tokens are included in forms and AJAX requests.
 * 4. Handle CSRF validation failures gracefully in your application.
 * 5. Monitor logs for CSRF validation failures to identify potential attacks.
|--------------------------------------------------------------------------
| @author Plugs Framework
* @package Plugs\Http\Middleware
*/

use Plugs\Http\ResponseFactory;
use Plugs\Security\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * HTTP methods that require CSRF validation
     */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes/patterns to exclude from CSRF validation
     *
     * @var array
     */
    private array $except;

    /**
     * Whether to add CSRF token to request attributes
     *
     * @var bool
     */
    private bool $addTokenToRequest;

    /**
     * Custom error response handler
     *
     * @var callable|null
     */
    private $errorHandler;

    /**
     * Whether to consume per-request tokens
     *
     * @var bool
     */
    private bool $consumeRequestTokens;

    /**
     * Whether to log CSRF failures
     *
     * @var bool
     */
    private bool $logFailures;

    /**
     * Initialize CSRF middleware
     *
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->except = $options['except'] ?? [];
        $this->addTokenToRequest = $options['add_token_to_request'] ?? true;
        $this->errorHandler = $options['error_handler'] ?? null;
        $this->consumeRequestTokens = $options['consume_request_tokens'] ?? true;
        $this->logFailures = $options['log_failures'] ?? true;

        // Configure CSRF class if options provided
        if (isset($options['csrf_config'])) {
            Csrf::configure($options['csrf_config']);
        }
    }

    /**
     * Process the middleware
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     * @return ResponseInterface The response
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = strtoupper($request->getMethod());

        // Only validate state-changing methods
        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            return $this->handleSuccess($request, $handler);
        }

        // Check if route is excluded
        if ($this->isExcluded($request)) {
            return $this->handleSuccess($request, $handler);
        }

        // Check if CSRF is properly initialized
        if (!Csrf::isConfigured()) {
            Csrf::generate(); // Initialize if needed
        }

        // Verify CSRF token
        if (!Csrf::verifyRequest($request, $this->consumeRequestTokens)) {
            return $this->handleFailure($request);
        }

        // Token is valid - proceed
        return $this->handleSuccess($request, $handler);
    }

    /**
     * Handle successful CSRF validation
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     * @return ResponseInterface The response
     */
    private function handleSuccess(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Add CSRF token to request attributes for downstream use
        if ($this->addTokenToRequest) {
            $request = $request->withAttribute('csrf_token', Csrf::token());
        }

        // Continue to next middleware
        return $handler->handle($request);
    }

    /**
     * Handle CSRF validation failure
     *
     * @param ServerRequestInterface $request The request
     * @return ResponseInterface The error response
     */
    private function handleFailure(ServerRequestInterface $request): ResponseInterface
    {
        // Log the failure if enabled
        if ($this->logFailures) {
            $this->logCsrfFailure($request);
        }

        // Use custom error handler if provided
        if (is_callable($this->errorHandler)) {
            return call_user_func($this->errorHandler, $request);
        }

        // Default error response
        return $this->createErrorResponse($request);
    }

    /**
     * Check if the request should be excluded from CSRF validation
     *
     * @param ServerRequestInterface $request The request
     * @return bool True if excluded
     */
    private function isExcluded(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        return Csrf::shouldExclude($path, $this->except);
    }

    /**
     * Create error response for CSRF failure
     *
     * @param ServerRequestInterface $request The request
     * @return ResponseInterface The error response
     */
    private function createErrorResponse(ServerRequestInterface $request): ResponseInterface
    {
        $isAjax = $this->isAjaxRequest($request);
        $isJson = $this->isJsonRequest($request);

        // For AJAX/JSON requests, return JSON error
        if ($isAjax || $isJson) {
            return ResponseFactory::json([
                'error' => 'CSRF token mismatch',
                'message' => 'The CSRF token is invalid or has expired. Please refresh the page and try again.',
                'code' => 419,
            ], 419, [
                'X-CSRF-Failure' => 'true',
            ]);
        }

        // For regular requests, return HTML error
        return $this->createHtmlErrorResponse();
    }

    /**
     * Create HTML error response
     *
     * @return ResponseInterface The HTML error response
     */
    private function createHtmlErrorResponse(): ResponseInterface
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>419 - CSRF Token Mismatch</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        padding: 20px;
                    }
                    .container {
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                        max-width: 500px;
                        padding: 40px;
                        text-align: center;
                    }
                    .error-code {
                        font-size: 72px;
                        font-weight: bold;
                        color: #667eea;
                        margin: 0;
                    }
                    h1 {
                        font-size: 24px;
                        color: #333;
                        margin: 20px 0 10px;
                    }
                    p {
                        color: #666;
                        line-height: 1.6;
                        margin: 0 0 30px;
                    }
                    .btn {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 12px 30px;
                        border-radius: 6px;
                        text-decoration: none;
                        display: inline-block;
                        transition: transform 0.2s;
                    }
                    .btn:hover {
                        transform: translateY(-2px);
                    }
                    .icon {
                        font-size: 64px;
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="icon">🛡️</div>
                    <div class="error-code">419</div>
                    <h1>CSRF Token Mismatch</h1>
                    <p>The security token for this request is invalid or has expired. This can happen if you've had the page open for too long.</p>
                    <a href="javascript:history.back()" class="btn">Go Back</a>
                    <a href="javascript:location.reload()" class="btn" style="margin-left: 10px;">Refresh Page</a>
                </div>
            </body>
            </html>
            HTML;

        return ResponseFactory::html($html, 419, [
            'X-CSRF-Failure' => 'true',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Check if request is an AJAX request
     *
     * @param ServerRequestInterface $request The request
     * @return bool True if AJAX request
     */
    private function isAjaxRequest(ServerRequestInterface $request): bool
    {
        $header = $request->getHeaderLine('X-Requested-With');

        return strtolower($header) === 'xmlhttprequest';
    }

    /**
     * Check if request expects JSON response
     *
     * @param ServerRequestInterface $request The request
     * @return bool True if JSON request
     */
    private function isJsonRequest(ServerRequestInterface $request): bool
    {
        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');

        return stripos($accept, 'application/json') !== false ||
            stripos($contentType, 'application/json') !== false;
    }

    /**
     * Log CSRF validation failure
     *
     * @param ServerRequestInterface $request The request
     * @return void
     */
    private function logCsrfFailure(ServerRequestInterface $request): void
    {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $this->getClientIp($request),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'user_agent' => $request->getHeaderLine('User-Agent'),
            'referer' => $request->getHeaderLine('Referer'),
        ];

        // Use error_log if available
        if (function_exists('error_log')) {
            error_log(sprintf(
                'CSRF Validation Failed: %s',
                json_encode($data, JSON_UNESCAPED_SLASHES)
            ));
        }

        // If you have a custom logger, use it here
        // Example: Logger::warning('CSRF validation failed', $data);
    }

    /**
     * Get client IP address
     *
     * @param ServerRequestInterface $request The request
     * @return string The client IP
     */
    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Add route pattern to exclusion list
     *
     * @param string $pattern Regex pattern to exclude
     * @return self
     */
    public function except(string $pattern): self
    {
        $this->except[] = $pattern;

        return $this;
    }

    /**
     * Set custom error handler
     *
     * @param callable $handler Custom error handler
     * @return self
     */
    public function setErrorHandler(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Get excluded patterns
     *
     * @return array The excluded patterns
     */
    public function getExceptions(): array
    {
        return $this->except;
    }
}
