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

use Plugs\Image\Image;
use Plugs\View\ViewEngine;
use Plugs\Security\Validator;
use Plugs\Database\Connection;
use Plugs\Upload\FileUploader;
use Plugs\Upload\UploadedFile;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Plugs\Http\Message\ServerRequest;

abstract class Controller
{
    protected $view;
    protected $db;

    public function __construct(ViewEngine $view, ?Connection $db = null)
    {
        $this->view = $view;
        $this->db = $db;

        // Share common data with views
        $this->view->share('app_name', config('app.name', 'Plugs Framework'));

        $this->onConstruct();
    }

    protected function view(string $view, array $data = []): ResponseInterface
    {
        try {
            $html = $this->view->render($view, $data);
            return ResponseFactory::html($html);
        } catch (\Throwable $e) {
            if (config('app.debug', false)) {
                throw $e;
            }
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
    }

    protected function json($data, int $status = 200): ResponseInterface
    {
        return ResponseFactory::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return ResponseFactory::redirect($url, $status);
    }

    protected function validate(ServerRequestInterface $request, array $rules): array
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, $rules);

        if (!$validator->validate()) {
            throw new \RuntimeException(json_encode($validator->errors()), 422);
        }

        return $data;
    }

    protected function param(ServerRequestInterface $request, string $key, $default = null)
    {
        return $request->getAttribute($key, $default);
    }

    /**
     * Get uploaded file from request
     */
    protected function file(ServerRequest $request, string $key): ?UploadedFile
    {
        return $request->getUploadedFile($key);
    }

    /**
     * Upload file
     */
    protected function upload(UploadedFile $file, array $options = []): array
    {
        $uploader = new FileUploader();

        if (isset($options['path'])) {
            $uploader->setUploadPath($options['path']);
        }

        if (isset($options['allowed'])) {
            $uploader->setAllowedExtensions($options['allowed']);
        }

        if (isset($options['maxSize'])) {
            $uploader->setMaxSize($options['maxSize']);
        }

        return $uploader->upload($file, $options['name'] ?? null);
    }

    public function onConstruct()
    {}
}