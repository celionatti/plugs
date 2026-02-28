<?php

declare(strict_types=1);

if (defined('PLUGS_APP_LOADED'))
    return;
define('PLUGS_APP_LOADED', true);

use Plugs\Utils\Str;
use Plugs\Security\Sanitizer;
use Plugs\Security\Csrf;
use Plugs\Container\Container;
use Plugs\Http\ResponseFactory;
use Plugs\Session\Session;
use Plugs\Utils\FlashMessage;
use Plugs\View\View;
use Plugs\View\ViewEngineInterface;
use Plugs\View\Escaper;
use Psr\Http\Message\ServerRequestInterface;

/*
|--------------------------------------------------------------------------
| Application Helper Functions - Refactored
|--------------------------------------------------------------------------
|
| Thin wrappers delegates to Plugs core classes and services.
*/

if (!function_exists('class_basename')) {
    /**
     * Get the class "basename" of the given object / class.
     */
    function class_basename($class): string
    {
        return Str::classBasename($class);
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable value.
     */
    function env(string $key, $default = null)
    {
        if (isset($GLOBALS['__env_overrides'][$key])) {
            return $GLOBALS['__env_overrides'][$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        switch (strtolower((string) $value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}

if (!function_exists('app')) {
    /**
     * Get the container instance or resolve a binding.
     */
    function app(string|null $abstract = null, array $parameters = [])
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve a class from the container.
     */
    function resolve(string $abstract, array $parameters = [])
    {
        return app($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set configuration value.
     */
    function config(string|array|null $key = null, $default = null)
    {
        if ($key === null) {
            return \Plugs\Config::all();
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                \Plugs\Config::set($k, $v);
            }
            return null;
        }

        return \Plugs\Config::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the base path of the installation.
     */
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : realpath(__DIR__ . '/../../');
        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the storage path of the installation.
     */
    function storage_path(string $path = ''): string
    {
        $storage = defined('STORAGE_PATH') ? STORAGE_PATH : base_path('storage');
        return rtrim($storage, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the public path of the installation.
     */
    function public_path(string $path = ''): string
    {
        $public = defined('PUBLIC_PATH') ? PUBLIC_PATH : base_path('public');
        return rtrim($public, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('resource_path')) {
    /**
     * Get the resource path of the installation.
     */
    function resource_path(string $path = ''): string
    {
        $resources = defined('RESOURCE_PATH') ? RESOURCE_PATH : base_path('resources');
        return rtrim($resources, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('database_path')) {
    /**
     * Get the database path of the installation.
     */
    function database_path(string $path = ''): string
    {
        $databases = defined('DATABASE_PATH') ? DATABASE_PATH : base_path('database');
        return rtrim($databases, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('view')) {
    /**
     * Render a view component.
     */
    function view(string $view, array $data = []): View
    {
        $engine = app(ViewEngineInterface::class);
        return new View($engine, $view, $data);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token.
     */
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Get the CSRF token HTML field.
     */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('request')) {
    /**
     * Get the current request instance.
     */
    function request()
    {
        try {
            $container = app();
            if ($container->bound(ServerRequestInterface::class)) {
                return $container->make(ServerRequestInterface::class);
            }
            if (isset($GLOBALS['__current_request']) && $GLOBALS['__current_request'] instanceof ServerRequestInterface) {
                return $GLOBALS['__current_request'];
            }
        } catch (\Exception $e) {
            if (env('APP_DEBUG', false)) {
                error_log('Request helper error: ' . $e->getMessage());
            }
        }
        return null;
    }
}

if (!function_exists('response')) {
    /**
     * Create a response.
     */
    function response($content = '', int $status = 200, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        if ($content instanceof View) {
            return ResponseFactory::html($content->render(), $status, $headers);
        }
        if (is_array($content) || is_object($content)) {
            return ResponseFactory::json($content, $status, $headers);
        }
        return ResponseFactory::html((string) $content, $status, $headers);
    }
}

if (!function_exists('session')) {
    /**
     * Get / set session value.
     */
    function session(string|array|null $key = null, $default = null)
    {
        try {
            $session = resolve(Session::class);

            if ($key === null)
                return $session;
            if (is_array($key)) {
                foreach ($key as $k => $v)
                    $session->set($k, $v);
                return null;
            }

            if (FlashMessage::has($key))
                return FlashMessage::get($key);

            return $session->get($key, $default);
        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            return $key === null ? $_SESSION : ($_SESSION[$key] ?? $default);
        }
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value.
     */
    function old(?string $key = null, mixed $default = null): mixed
    {
        if (session_status() === PHP_SESSION_NONE)
            session_start();
        $oldInput = $_SESSION['_old_input'] ?? $_SESSION['_flash']['_old_input'] ?? [];

        if ($key === null)
            return $oldInput;

        if (str_contains($key, '.')) {
            $value = $oldInput;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value))
                    return $default;
                $value = $value[$segment];
            }
            return $value;
        }

        return $oldInput[$key] ?? $default;
    }
}

// abort() is defined in abort.php (canonical source with full signature)

if (!function_exists('sanitize')) {
    /**
     * Sanitize values using the Sanitizer service.
     */
    function sanitize($value, string $type = 'string')
    {
        $method = [Sanitizer::class, $type];
        return is_callable($method) ? call_user_func($method, $value) : Sanitizer::string($value);
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML.
     */
    function e($value): mixed
    {
        return Escaper::html($value);
    }
}

if (!function_exists('mask')) {
    /**
     * Mask sensitive data using Str utility.
     */
    function mask(string $value, string $type = 'custom', string $maskChar = '*', int $visibleStart = 3, int $visibleEnd = 3): string
    {
        return Str::maskSensitive($value, $type, $maskChar, $visibleStart, $visibleEnd);
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message.
     */
    function logger(string|null $message = null, string $level = 'info', array $context = [])
    {
        $logger = app('log');
        if ($message === null)
            return $logger;
        $logger->log($level, $message, $context);
    }
}

/**
 * Utility functions for loading other functions.
 */
if (!function_exists('loadFunctions')) {
    function loadFunctions(string|array|null $source): void
    {
        if ($source === null)
            $source = __DIR__;

        if (is_string($source) && is_dir($source)) {
            requireFiles(glob(rtrim($source, '/\\') . DIRECTORY_SEPARATOR . '*.php'));
            return;
        }

        if (is_array($source)) {
            requireFiles(extractFilesFromArray($source));
            return;
        }

        if (file_exists($source))
            requireFiles([$source]);
    }
}

if (!function_exists('requireFiles')) {
    function requireFiles(array $files): void
    {
        foreach ($files as $file) {
            // Skip current file to avoid potential double-loading issues
            if (realpath($file) === realpath(__FILE__)) {
                continue;
            }

            if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                require_once $file;
            }
        }
    }
}

if (!function_exists('extractFilesFromArray')) {
    function extractFilesFromArray(array $fileArray): array
    {
        $files = [];
        foreach ($fileArray as $value) {
            if (is_string($value) && file_exists($value)) {
                $files[] = $value;
            } elseif (is_array($value)) {
                $files = array_merge($files, extractFilesFromArray($value));
            }
        }
        return array_unique($files);
    }
}

// Manual require for helper files not listed in composer.json
$extraHelpers = [
    'async.php',
    'cache.php',
    'form.php',
    'input.php',
    'skeleton_helper.php',
    'translation.php',
];

foreach ($extraHelpers as $helper) {
    $helperFile = __DIR__ . DIRECTORY_SEPARATOR . $helper;
    if (file_exists($helperFile)) {
        require_once $helperFile;
    }
}
