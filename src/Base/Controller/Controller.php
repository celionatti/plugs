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

        // Share common data with views
        $this->view->share('app_name', config('app.name', 'Plugs Framework'));

        $this->onConstruct();
    }

    /**
     * Render a view
     */
    protected function view(string $view, array $data = []): ResponseInterface
    {
        try {
            $html = $this->view->render($view, $data);
            return ResponseFactory::html($html);
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                throw $e;
            }
            return $this->renderErrorPage();
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
     * Validate request data
     */
    protected function validate(ServerRequestInterface $request, array $rules): array
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
     * 
     * @param ServerRequestInterface $request
     * @return array
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
     * Hook method called after constructor
     */
    public function onConstruct()
    {
        // Override in child controllers if needed
    }
}