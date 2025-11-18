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
use RuntimeException;
use InvalidArgumentException;


abstract class Controller
{
    protected ViewEngine $view;
    protected ?Connection $db;
    protected ?FileUploader $uploader = null;
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
     * Get uploaded file from request
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @return UploadedFile|null
     */
    protected function file(ServerRequestInterface $request, string $key): ?UploadedFile
    {
        // Store current request for rate limiting
        $this->currentRequest = $request;

        // Use ServerRequest's getUploadedFile method
        return $request->getUploadedFile($key);
    }

    /**
     * Check if request has uploaded file
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @return bool
     */
    protected function hasFile(ServerRequestInterface $request, string $key): bool
    {
        // Check if request has hasFile method (custom method)
        if (method_exists($request, 'hasFile')) {
            return $request->hasFile($key);
        }

        $file = $this->file($request, $key);
        
        if ($file === null) {
            return false;
        }

        return $file->isValid();
    }

    /**
     * Get multiple uploaded files
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name (e.g., 'photos')
     * @return UploadedFile[]
     */
    protected function files(ServerRequestInterface $request, string $key): array
    {
        $this->currentRequest = $request;
        $uploadedFiles = $request->getUploadedFiles();
        
        if (!isset($uploadedFiles[$key])) {
            return [];
        }

        $files = $uploadedFiles[$key];

        // If it's already an array of UploadedFile instances
        if (is_array($files)) {
            $result = [];
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $result[] = $file;
                } elseif (is_array($file) && isset($file['tmp_name'])) {
                    $result[] = new UploadedFile($file);
                }
            }
            return $result;
        }

        // Single file
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return [];
    }

    /**
     * Get or create FileUploader instance
     */
    protected function uploader(): FileUploader
    {
        if ($this->uploader === null) {
            $uploadPath = config('upload.path', null);
            $this->uploader = new FileUploader($uploadPath);
        }

        return $this->uploader;
    }

    /**
     * Upload single file with options
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @param array $options Upload configuration
     * @return array Upload result
     * @throws RuntimeException If upload fails
     */
    protected function upload(ServerRequestInterface $request, string $key, array $options = []): array
    {
        $file = $this->file($request, $key);

        if ($file === null) {
            throw new RuntimeException("No file found with key: {$key}");
        }

        if (!$file->isValid()) {
            throw new RuntimeException("Invalid file upload: " . $file->getErrorMessage());
        }

        return $this->uploadFile($file, $options);
    }

    /**
     * Upload file with configuration
     * 
     * @param UploadedFile $file
     * @param array $options Configuration options
     * @return array Upload result
     */
    protected function uploadFile(UploadedFile $file, array $options = []): array
    {
        $uploader = $this->uploader();

        // Configure uploader based on options
        $this->configureUploader($uploader, $options);

        // Get user identifier for rate limiting
        $userIdentifier = $this->getUserIdentifier();

        // Custom filename
        $customName = $options['name'] ?? null;

        // Upload the file
        return $uploader->upload($file, $customName, $userIdentifier);
    }

    /**
     * Upload multiple files
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @param array $options Upload configuration
     * @return array Upload results
     */
    protected function uploadMultiple(ServerRequestInterface $request, string $key, array $options = []): array
    {
        $files = $this->files($request, $key);

        if (empty($files)) {
            throw new RuntimeException("No files found with key: {$key}");
        }

        $uploader = $this->uploader();
        $this->configureUploader($uploader, $options);

        $userIdentifier = $this->getUserIdentifier();

        return $uploader->uploadMultiple($files, $userIdentifier);
    }

    /**
     * Configure uploader with options
     */
    private function configureUploader(FileUploader $uploader, array $options): void
    {
        // Upload path
        if (isset($options['path'])) {
            $uploader->setUploadPath($options['path']);
        }

        // Allowed extensions
        if (isset($options['allowed'])) {
            $uploader->setAllowedExtensions($options['allowed']);
        }

        // Allowed MIME types
        if (isset($options['mimes'])) {
            $uploader->setAllowedMimeTypes($options['mimes']);
        }

        // File size limits
        if (isset($options['maxSize'])) {
            $uploader->setMaxSize($options['maxSize']);
        }

        if (isset($options['minSize'])) {
            $uploader->setMinSize($options['minSize']);
        }

        // Image dimensions
        if (isset($options['maxWidth']) || isset($options['maxHeight']) || 
            isset($options['minWidth']) || isset($options['minHeight'])) {
            $uploader->setImageDimensions(
                $options['maxWidth'] ?? null,
                $options['maxHeight'] ?? null,
                $options['minWidth'] ?? null,
                $options['minHeight'] ?? null
            );
        }

        // Rate limiting
        if (isset($options['rateLimit'])) {
            $uploader->setRateLimit($options['rateLimit']);
        }

        // Other options
        if (isset($options['uniqueName'])) {
            $uploader->generateUniqueName($options['uniqueName']);
        }

        if (isset($options['organizeByDate'])) {
            $uploader->organizeByDate($options['organizeByDate']);
        }

        if (isset($options['preventDuplicates'])) {
            $uploader->preventDuplicates($options['preventDuplicates']);
        }

        if (isset($options['allowSvg'])) {
            $uploader->allowSvg($options['allowSvg']);
        }

        // Preset configurations
        if (isset($options['preset'])) {
            switch ($options['preset']) {
                case 'images':
                    $maxSize = $options['maxSize'] ?? 5242880;
                    $uploader->imagesOnly($maxSize);
                    break;
                case 'documents':
                    $maxSize = $options['maxSize'] ?? 10485760;
                    $uploader->documentsOnly($maxSize);
                    break;
            }
        }
    }

    /**
     * Quick image upload helper
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @param int $maxSize Maximum file size in bytes (default 5MB)
     * @param array $dimensions Image dimension constraints
     * @return array Upload result
     */
    protected function uploadImage(
        ServerRequestInterface $request, 
        string $key, 
        int $maxSize = 5242880,
        array $dimensions = []
    ): array {
        $options = [
            'preset' => 'images',
            'maxSize' => $maxSize,
        ];

        if (!empty($dimensions)) {
            $options = array_merge($options, $dimensions);
        }

        return $this->upload($request, $key, $options);
    }

    /**
     * Quick document upload helper
     * 
     * @param ServerRequestInterface $request
     * @param string $key File input name
     * @param int $maxSize Maximum file size in bytes (default 20MB)
     * @return array Upload result
     */
    protected function uploadDocument(
        ServerRequestInterface $request, 
        string $key, 
        int $maxSize = 20971520
    ): array {
        return $this->upload($request, $key, [
            'preset' => 'documents',
            'maxSize' => $maxSize,
        ]);
    }

    /**
     * Delete uploaded file
     * 
     * @param string $path File path to delete
     * @return bool Success status
     */
    protected function deleteFile(string $path): bool
    {
        return $this->uploader()->delete($path);
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
     * Get input value from request (query or body)
     * 
     * @param ServerRequestInterface $request
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function input(ServerRequestInterface $request, string $key, $default = null)
    {
        // Check if request has input method
        if (method_exists($request, 'input')) {
            return $request->input($key, $default);
        }

        // Fallback
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        if (isset($queryParams[$key])) {
            return $queryParams[$key];
        }

        if (is_array($parsedBody) && isset($parsedBody[$key])) {
            return $parsedBody[$key];
        }

        return $default;
    }

    /**
     * Check if input exists
     * 
     * @param ServerRequestInterface $request
     * @param string $key
     * @return bool
     */
    protected function has(ServerRequestInterface $request, string $key): bool
    {
        // Check if request has has method
        if (method_exists($request, 'has')) {
            return $request->has($key);
        }

        // Fallback
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        return isset($queryParams[$key]) || 
               (is_array($parsedBody) && isset($parsedBody[$key]));
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
    public function onConstruct(): void
    {
        // Override in child controllers if needed
    }
}