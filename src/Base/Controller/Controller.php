<?php

declare(strict_types=1);

namespace Plugs\Base\Controller;

/*
|--------------------------------------------------------------------------
| Controller Class
|--------------------------------------------------------------------------
|
| This is the base controller class that other controllers can extend. It
| provides common functionalities such as rendering views, handling
| JSON responses, redirects, validation, and file uploads.
*/


use Plugs\View\ViewEngine;
use Plugs\Security\Validator;
use Plugs\Database\Connection;
use Plugs\Http\ResponseFactory;
use Plugs\View\ErrorMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;


abstract class Controller
{
    protected ViewEngine $view;
    protected ?Connection $db;
    protected ?ServerRequestInterface $currentRequest = null;

    public function __construct(ViewEngine $view, ?Connection $db = null)
    {
        $this->view = $view;
        $this->db = $db;

        // Initialize session if not started
        $this->ensureSessionStarted();

        // Share common data with views
        $this->view->share('app_name', config('app.name', 'Plugs Framework'));
        
        // Share global errors (always available, empty if no errors)
        $this->shareGlobalErrors();
        
        // Make old() helper available globally
        $this->shareOldInputHelper();

        $this->onConstruct();
    }

    /**
     * Share global errors with all views
     */
    private function shareGlobalErrors(): void
    {
        // Check if there are flashed errors in session
        if (isset($_SESSION['_errors'])) {
            $errors = $_SESSION['_errors'];
            unset($_SESSION['_errors']); // Clear after retrieving
        } else {
            $errors = new ErrorMessage();
        }

        $this->view->share('errors', $errors);
    }

    /**
     * Share old input helper function with views
     */
    private function shareOldInputHelper(): void
    {
        // Share the old() function result as a callable
        $this->view->share('old', function (string $key, $default = null) {
            return $this->getOldInput($key, $default);
        });
    }

    /**
     * Get old input value
     */
    private function getOldInput(string $key, $default = null)
    {
        // Check session for old input (flash data)
        if (isset($_SESSION['_old_input'][$key])) {
            return $_SESSION['_old_input'][$key];
        }

        return $default;
    }

    /**
     * Render a view
     */
    protected function view(string $view, array $data = []): ResponseInterface
    {
        try {
            $html = $this->view->render($view, $data);
            
            // Clear old input after successful render (only if no errors)
            if (!isset($_SESSION['_errors'])) {
                $this->clearOldInput();
            }
            
            return ResponseFactory::html($html);
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                throw $e;
            }
            return $this->renderErrorPage();
        }
    }

    /**
     * Redirect back with errors and old input
     */
    protected function back(?ErrorMessage $errors = null, array $data = []): ResponseInterface
    {
        // Flash errors to session if provided
        if ($errors && $errors->any()) {
            $_SESSION['_errors'] = $errors;
        }

        // Flash old input to session
        if ($this->currentRequest) {
            $parsedBody = $this->currentRequest->getParsedBody();
            if (is_array($parsedBody)) {
                $_SESSION['_old_input'] = $parsedBody;
            }
        }

        // Flash additional data if provided
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $_SESSION['_flash_' . $key] = $value;
            }
        }

        // Get referer or fallback to home
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        
        return $this->redirect($referer);
    }

    /**
     * Redirect with success message
     */
    protected function redirectWithSuccess(string $url, string $message): ResponseInterface
    {
        $_SESSION['_success'] = $message;
        return $this->redirect($url);
    }

    /**
     * Redirect with error message
     */
    protected function redirectWithError(string $url, string $message): ResponseInterface
    {
        $errors = new ErrorMessage();
        $errors->add('general', $message);
        $_SESSION['_errors'] = $errors;
        
        return $this->redirect($url);
    }

    /**
     * Clear old input from session
     */
    private function clearOldInput(): void
    {
        if (isset($_SESSION['_old_input'])) {
            unset($_SESSION['_old_input']);
        }
    }

    /**
     * Render error page
     */
    private function renderErrorPage(): ResponseInterface
    {
        return ResponseFactory::html(
            '<html>
                <head>
                    <style>
                        body {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                            margin: 0;
                            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
                            background-color: #000000;
                            color: #6b7280;
                        }
                        .container {
                            text-align: center;
                            max-width: 400px;
                            padding: 2rem;
                        }
                        .message {
                            font-size: 1.125rem;
                            line-height: 1.6;
                            margin: 1.5rem 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>500 | View Error</h1>
                        <div class="message">
                            Unable to render view. Please try again later.
                        </div>
                        <a href="/" style="color: #3b82f6; text-decoration: none;">Go to Homepage</a>
                    </div>
                </body>
            </html>',
            500
        );
    }

    /**
     * Return JSON response
     */
    protected function json($data, int $status = 200): ResponseInterface
    {
        return ResponseFactory::json($data, $status);
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return ResponseFactory::redirect($url, $status);
    }

    /**
     * Validate request data - now with automatic error handling
     */
    protected function validate(ServerRequestInterface $request, array $rules): array|object
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            // Convert validator errors to ErrorBag
            $errorBag = new ErrorMessage($validator->errors());
            
            // Flash errors and old input, then redirect back
            return $this->back($errorBag);
        }

        return $data;
    }

    /**
     * Validate request data - throws exception instead of redirecting
     */
    protected function validateOrFail(ServerRequestInterface $request, array $rules): array
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            throw new RuntimeException(json_encode($validator->errors()), 422);
        }

        return $data;
    }

    /**
     * Get route parameter
     */
    protected function param(ServerRequestInterface $request, string $key, $default = null)
    {
        return $request->getAttribute($key, $default);
    }

    /**
     * Get user identifier for rate limiting
     */
    private function getUserIdentifier(): ?string
    {
        // Try to get from session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }

        // Fall back to IP address
        if ($this->currentRequest) {
            $serverParams = $this->currentRequest->getServerParams();
            
            // Check for proxy headers
            if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
                return 'ip_' . trim($ips[0]);
            }
            
            if (!empty($serverParams['REMOTE_ADDR'])) {
                return 'ip_' . $serverParams['REMOTE_ADDR'];
            }
        }

        // Last resort: use from $_SERVER
        return isset($_SERVER['REMOTE_ADDR']) ? 'ip_' . $_SERVER['REMOTE_ADDR'] : null;
    }

    /**
     * Get all input data
     */
    protected function all(ServerRequestInterface $request): array
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        return array_merge(
            $queryParams,
            is_array($parsedBody) ? $parsedBody : []
        );
    }

    /**
     * Ensure session is started
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Hook method called after constructor
     */
    public function onConstruct()
    {
        // Override in child controllers if needed
    }
}