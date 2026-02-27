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
 * - Integration with \Plugs\Http\Middleware\SecurityShieldMiddleware
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

use Plugs\Security\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

#[Middleware(layer: MiddlewareLayer::SECURITY, priority: 50)]
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
            $response = $this->handleSuccess($request, $handler);

            // Add XSRF-TOKEN cookie for GET/HEAD requests
            Csrf::setXsrftokenCookie();

            return $response;
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
        $response = $this->handleSuccess($request, $handler);

        // Add XSRF-TOKEN cookie for all successful requests
        Csrf::setXsrftokenCookie();

        return $response;
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

        throw new \Plugs\Exceptions\TokenMismatchException('CSRF token mismatch. Please refresh the page.');
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
            'reason' => Csrf::getLastError(),
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
