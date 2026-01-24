<?php

declare(strict_types=1);

namespace Plugs\Router;

/*
|--------------------------------------------------------------------------
| PageRouter Class
|--------------------------------------------------------------------------
|
| Handles file-based routing (Next.js-style pages routing).
| Automatically discovers routes from the pages directory and
| registers them with the main router.
|
| Features:
| - Automatic route discovery from file structure
| - Dynamic parameters using [param] syntax
| - Catch-all routes using [...params] syntax
| - Route caching for performance
| - HTTP method-specific handling
*/

use Plugs\Base\Page;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PageRouter
{
    private Router $router;
    private string $pagesDirectory;
    private array $options;
    private array $discoveredRoutes = [];
    private bool $cacheEnabled = true;
    private ?string $cacheFile = null;

    public function __construct(Router $router, string $pagesDirectory, array $options = [])
    {
        $this->router = $router;
        $this->pagesDirectory = rtrim($pagesDirectory, '/\\');
        $this->options = array_merge([
            'cache' => true,
            'cache_file' => null,
            'middleware' => [],
            'prefix' => '',
            'namespace' => 'App\\Pages',
        ], $options);

        $this->cacheEnabled = $this->options['cache'];
        $this->cacheFile = $this->options['cache_file'];

        if (!is_dir($this->pagesDirectory)) {
            // Directory doesn't exist - we'll just handle this gracefully in registerRoutes
            // by doing nothing if the directory is missing.
            return;
        }
    }

    /**
     * Discover and register all page routes
     */
    public function registerRoutes(): void
    {
        // Try to load from cache first
        if ($this->loadFromCache()) {
            return;
        }

        // Discover routes from filesystem
        $this->discoverRoutes();

        // Register discovered routes with the router
        foreach ($this->discoveredRoutes as $routeInfo) {
            $this->registerRoute($routeInfo);
        }

        // Save to cache
        $this->saveToCache();
    }

    /**
     * Discover routes from the pages directory
     */
    private function discoverRoutes(): void
    {
        if (!is_dir($this->pagesDirectory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->pagesDirectory,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $this->processPageFile($file->getPathname());
        }

        // Sort routes by specificity (specific routes before dynamic ones)
        usort($this->discoveredRoutes, [$this, 'compareRouteSpecificity']);
    }

    /**
     * Process a single page file
     */
    private function processPageFile(string $filePath): void
    {
        // Get relative path from pages directory
        $relativePath = str_replace(
            [$this->pagesDirectory, '\\'],
            ['', '/'],
            $filePath
        );
        $relativePath = ltrim($relativePath, '/');

        // Remove .php extension
        $relativePath = substr($relativePath, 0, -4);

        // Convert to route pattern
        $routePattern = $this->convertToRoutePattern($relativePath);

        // Get namespace and class name
        $className = $this->getPageClassName($relativePath);

        // Extract dynamic parameters
        $parameters = $this->extractParameters($relativePath);

        // Determine if file defines a class
        $isClass = $this->hasDefinedClass($filePath);

        $this->discoveredRoutes[] = [
            'pattern' => $routePattern,
            'file' => $filePath,
            'class' => $className,
            'is_class' => $isClass,
            'parameters' => $parameters,
            'is_catch_all' => $this->isCatchAllRoute($relativePath),
        ];
    }

    /**
     * Check if a file defines a class
     */
    private function hasDefinedClass(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        $tokens = token_get_all($content);

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CLASS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert file path to route pattern
     */
    private function convertToRoutePattern(string $path): string
    {
        // Replace index with empty string
        $path = preg_replace('/\/index$/', '', $path);
        $path = preg_replace('/^index$/', '', $path);

        // Convert [param] to {param}
        $path = preg_replace('/\[([^\]]+)\]/', '{$1}', $path);

        // Convert [...params] to {params} (catch-all)
        $path = preg_replace('/\{\.\.\.([^}]+)\}/', '{$1}', $path);

        // Apply prefix if set
        $prefix = trim($this->options['prefix'], '/');
        if ($prefix) {
            $path = $prefix . '/' . $path;
        }

        // Ensure path starts with /
        $path = '/' . ltrim($path, '/');

        // Handle root path
        if ($path === '/') {
            return '/';
        }

        // Remove trailing slash
        return rtrim($path, '/');
    }

    /**
     * Get the fully qualified class name for a page
     */
    private function getPageClassName(string $relativePath): string
    {
        // Convert path to namespace
        $parts = explode('/', $relativePath);

        // Convert each part to PascalCase and remove special characters
        $namespaceParts = array_map(function ($part) {
            // Remove dynamic parameter brackets
            $part = preg_replace('/\[\.\.\.([^\]]+)\]/', '$1', $part);
            $part = preg_replace('/\[([^\]]+)\]/', '$1', $part);

            // Convert to PascalCase
            return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
        }, $parts);

        // Last part is the class name
        $className = array_pop($namespaceParts) ?: 'Index';
        $className .= 'Page';

        // Build full namespace
        $namespace = rtrim($this->options['namespace'], '\\');
        if (!empty($namespaceParts)) {
            $namespace .= '\\' . implode('\\', $namespaceParts);
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Extract parameter names from path
     */
    private function extractParameters(string $path): array
    {
        $parameters = [];

        // Match [param] and [...param] patterns
        if (preg_match_all('/\[\.\.\.?([^\]]+)\]/', $path, $matches)) {
            $parameters = $matches[1];
        }

        return $parameters;
    }

    /**
     * Check if route is a catch-all route
     */
    private function isCatchAllRoute(string $path): bool
    {
        return str_contains($path, '[...');
    }

    /**
     * Compare route specificity for sorting
     * More specific routes (fewer parameters) come first
     */
    private function compareRouteSpecificity(array $a, array $b): int
    {
        // Catch-all routes always come last
        if ($a['is_catch_all'] && !$b['is_catch_all']) {
            return 1;
        }
        if (!$a['is_catch_all'] && $b['is_catch_all']) {
            return -1;
        }

        // Count parameters (fewer = more specific)
        $aParams = count($a['parameters']);
        $bParams = count($b['parameters']);

        if ($aParams !== $bParams) {
            return $aParams <=> $bParams;
        }

        // Count path segments (more = more specific)
        $aSegments = substr_count($a['pattern'], '/');
        $bSegments = substr_count($b['pattern'], '/');

        return $bSegments <=> $aSegments;
    }

    /**
     * Register a single route with the router
     */
    private function registerRoute(array $routeInfo): void
    {
        // Handle Class-based Pages
        if ($routeInfo['is_class']) {
            $this->registerClassRoute($routeInfo);

            return;
        }

        // Handle Simple Pages (View/Script)
        $this->registerSimpleRoute($routeInfo);
    }

    /**
     * Register a class-based page route
     */
    private function registerClassRoute(array $routeInfo): void
    {
        $className = $routeInfo['class'];

        // Require the file to ensure class is loaded
        if (file_exists($routeInfo['file'])) {
            require_once $routeInfo['file'];
        }

        // Check if class exists
        if (!class_exists($className)) {
            return;
        }

        // Verify it extends Page
        if (!is_subclass_of($className, Page::class)) {
            // If it's a class but doesn't extend Page, we treat it as a class but skip validation?
            // Or maybe user defined a helper class?
            // For now, strict check: must extend Page
            throw new RuntimeException(
                "Page class {$className} must extend " . Page::class
            );
        }

        // Get middleware from the page class
        $pageInstance = new $className();
        $middleware = array_merge(
            $this->options['middleware'],
            method_exists($pageInstance, 'middleware') ? $pageInstance->middleware() : []
        );

        // Register route
        $route = $this->router->any(
            $routeInfo['pattern'],
            [$className, '__invoke'],
            $middleware
        );

        $route->meta('page_file', $routeInfo['file']);
        $route->meta('page_class', $className);
    }

    /**
     * Register a simple page route (view/script)
     */
    private function registerSimpleRoute(array $routeInfo): void
    {
        $file = $routeInfo['file'];

        // Define the handler closure
        $handler = function (\Psr\Http\Message\ServerRequestInterface $request) use ($file) {
            // Start output buffering
            ob_start();

            // Execute file
            // We isolate scope but $request is available if they use func_get_args or use global?
            // Ideally we pass $request to the file scope.
            // Using require inside a closure with variables makes them available? No.
            // But we can extract parameters?

            $includeFile = function ($__file__, $request) {
                return require $__file__;
            };

            $result = $includeFile($file, $request);
            $output = ob_get_clean();

            // Handle return value
            if ($result instanceof \Psr\Http\Message\ResponseInterface) {
                return $result;
            }

            // If string returned, use it
            if (is_string($result)) {
                // Check if it looks like a view (if View system exists)
                // Actually, if they return view('name'), that returns a string or object?
                // In this framework, view() likely returns string or we need ResponseFactory.
                return \Plugs\Http\ResponseFactory::html($result);
            }

            // Should also handle objects that are "View" instances if they exist
            if (is_object($result) && method_exists($result, '__toString')) {
                return \Plugs\Http\ResponseFactory::html((string) $result);
            }

            // Use captured output if return was empty/true/1 (typical require success)
            if ($output !== '') {
                return \Plugs\Http\ResponseFactory::html($output);
            }

            // Default response if nothing
            return \Plugs\Http\ResponseFactory::html('');
        };

        $route = $this->router->any(
            $routeInfo['pattern'],
            $handler,
            $this->options['middleware']
        );

        $route->meta('page_file', $file);
        $route->meta('page_type', 'simple');
    }

    /**
     * Load routes from cache
     */
    private function loadFromCache(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile || !file_exists($this->cacheFile)) {
            return false;
        }

        $cached = include $this->cacheFile;

        if (!is_array($cached) || !isset($cached['routes'])) {
            return false;
        }

        $this->discoveredRoutes = $cached['routes'];

        // Register cached routes
        foreach ($this->discoveredRoutes as $routeInfo) {
            $this->registerRoute($routeInfo);
        }

        return true;
    }

    /**
     * Save routes to cache
     */
    private function saveToCache(): void
    {
        if (!$this->cacheEnabled || !$this->cacheFile) {
            return;
        }

        $cacheDir = dirname($this->cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $content = "<?php\n\nreturn " . var_export([
            'routes' => $this->discoveredRoutes,
            'generated_at' => date('Y-m-d H:i:s'),
        ], true) . ";\n";

        file_put_contents($this->cacheFile, $content);
    }

    /**
     * Clear the route cache
     */
    public function clearCache(): void
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    /**
     * Get all discovered routes
     */
    public function getDiscoveredRoutes(): array
    {
        return $this->discoveredRoutes;
    }

    /**
     * Get pages directory
     */
    public function getPagesDirectory(): string
    {
        return $this->pagesDirectory;
    }
}
