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


use Plugs\Http\ResponseFactory;
use Plugs\Security\Validator;
use Plugs\View\ErrorMessage;
use Plugs\View\ViewEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

abstract class Controller
{
    protected ViewEngine $view;
    protected $db;
    protected ?ServerRequestInterface $currentRequest = null;

    public function __construct()
    {
        // Constructor is now empty to allow child controllers to define their own
        // without always needing to call parent::__construct() if they don't want to.
        // Initialization is now handled by the initialize() method called by the Router.
    }

    /**
     * Initialize the controller with framework dependencies.
     * This is called by the Router after instantiation.
     */
    public function initialize(ViewEngine $view, $db = null): void
    {
        $this->view = $view;
        $this->db = $db;

        // Initialize session if not started
        $this->ensureSessionStarted();

        // Share common data with views
        $this->view->share('app_name', \config('app.name', 'Plugs Framework'));

        // Share global errors (always available, empty if no errors)
        $this->shareGlobalErrors();

        // Make old() helper available globally
        $this->shareOldInputHelper();
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
     * Render an Inertia page
     *
     * Use this for SPA-style rendering with React, Vue, or other frontend frameworks.
     * Returns JSON for XHR requests (navigation) or full HTML for initial page loads.
     *
     * @param string $component Component name (e.g., 'Users/Index')
     * @param array $props Data to pass to the component
     * @return \Plugs\Inertia\InertiaResponse
     */
    protected function inertia(string $component, array $props = []): \Plugs\Inertia\InertiaResponse
    {
        return \Plugs\Inertia\Inertia::render($component, $props);
    }

    /**
     * Redirect back with errors and old input (chainable)
     */
    protected function back(string $fallback = '/', int $status = 302): \Plugs\Http\RedirectResponse
    {
        return (\Plugs\Http\RedirectResponse::class)::fromGlobal($fallback, $status);
    }

    /**
     * Create a redirect response (chainable)
     */
    protected function redirect(string $url, int $status = 302): \Plugs\Http\RedirectResponse
    {
        return new \Plugs\Http\RedirectResponse($url, $status);
    }

    /**
     * Redirect with success message
     */
    protected function redirectWithSuccess(string $url, string $message, ?string $title = null): ResponseInterface
    {
        return $this->redirect($url)->withSuccess($message, $title);
    }

    /**
     * Redirect with error message
     */
    protected function redirectWithError(string $url, string $message, ?string $title = null): ResponseInterface
    {
        return $this->redirect($url)->withError($message, $title);
    }

    /**
     * Flash a message to the session
     */
    protected function flash(string $key, $value, ?string $title = null): void
    {
        /** @phpstan-ignore arguments.count */
        flash($key, $value, $title);
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
     * Return file response
     */
    protected function file(string $path, ?string $name = null, array $headers = []): ResponseInterface
    {
        return ResponseFactory::file($path, $name, $headers);
    }

    /**
     * Return download response
     */
    protected function download(string $path, ?string $name = null, array $headers = []): ResponseInterface
    {
        return ResponseFactory::download($path, $name, $headers);
    }

    /**
     * Validate request data
     * Supports both array rules and FormRequest objects.
     */
    protected function validate(ServerRequestInterface|string $request, array $rules = []): array
    {
        // Handle FormRequest class string
        if (is_string($request) && is_subclass_of($request, \Plugs\Http\Requests\FormRequest::class)) {
            $formRequest = new $request($this->currentRequest?->getParsedBody() ?? $_POST);
            if (!$formRequest->validate()) {
                $this->back()->withErrors($formRequest->errors())->withInput()->send();
            }

            return $formRequest->sanitized();
        }

        // Handle regular array rules
        $data = $request instanceof ServerRequestInterface ? ($request->getParsedBody() ?? []) : $_POST;
        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            $this->back()->withErrors($validator->errors())->withInput()->send();
        }

        return $validator->validated();
    }

    /**
     * Authorize a given action
     */
    protected function authorize(string $ability, $arguments = []): void
    {
        // Implementation for simple authorization
        // This could integrate with a Gate or similar system later
        if (method_exists($this, 'can') && !$this->can($ability, $arguments)) {
            throw new RuntimeException("This action is unauthorized.", 403);
        }
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
     * @phpstan-ignore method.unused
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
     * Get the current request instance
     */
    protected function request(): ServerRequestInterface
    {
        if ($this->currentRequest) {
            return $this->currentRequest;
        }

        return request() ?? \Plugs\Http\Message\ServerRequest::fromGlobals();
    }

    /**
     * Get all input data
     */
    protected function all(?ServerRequestInterface $request = null): array
    {
        $request = $request ?? $this->request();
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        return array_merge(
            $queryParams,
            is_array($parsedBody) ? $parsedBody : []
        );
    }

    /**
     * Get a specific input value
     */
    protected function input(string $key, $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    /**
     * Get the session instance
     */
    protected function session(): \Plugs\Session\Session
    {
        return session();
    }

    /**
     * Ensure session is started
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Logic to clear old input after it's been available for one GET request
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['_old_input'])) {
            // We keep it for the current execution but mark for deletion
            register_shutdown_function(function () {
                unset($_SESSION['_old_input']);
            });
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
