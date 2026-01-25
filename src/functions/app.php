<?php

declare(strict_types=1);


use Psr\Http\Message\ServerRequestInterface;

/*
|--------------------------------------------------------------------------
| Application Helper Functions
|--------------------------------------------------------------------------
|
| This file contains various helper functions to facilitate common tasks
| within the application, such as accessing the service container,
| retrieving configuration values, and generating URLs.
*/

if (!function_exists('class_basename')) {
    function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }
}

if (!function_exists('app')) {
    /**
     * Get the container instance or resolve a binding
     */
    function app(string|null $abstract = null, array $parameters = [])
    {
        $container = \Plugs\Container\Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('resolve')) {
    /**
     * Resolve a class from the container
     */
    function resolve(string $abstract, array $parameters = [])
    {
        return app($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set configuration value
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
    function base_path(string $path = ''): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : realpath(__DIR__ . '/../../');

        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $storage = defined('STORAGE_PATH') ? STORAGE_PATH : base_path('storage');

        return rtrim($storage, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public/' . ltrim($path, '/'));
    }
}

if (!function_exists('resource_path')) {
    function resource_path(string $path = ''): string
    {
        return base_path('resources/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        $engine = app(\Plugs\View\ViewEngine::class);

        return $engine->render($view, $data);
    }
}

if (!function_exists('inertia')) {
    /**
     * Render an Inertia response
     *
     * Returns an InertiaResponse that can render as JSON (for XHR requests)
     * or as a full HTML page with embedded page data.
     *
     * @param string $component Component name (e.g., 'Users/Index')
     * @param array $props Data to pass to the component
     * @return \Plugs\Inertia\InertiaResponse
     */
    function inertia(string $component, array $props = []): \Plugs\Inertia\InertiaResponse
    {
        return \Plugs\Inertia\Inertia::render($component, $props);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \Plugs\Security\Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \Plugs\Security\Csrf::field();
    }
}

if (!function_exists('db')) {
    /**
     * Get the database manager instance or a table query builder
     */
    function db(?string $table = null)
    {
        $db = app('db');

        if ($table === null) {
            return $db;
        }

        return $db->table($table);
    }
}

if (!function_exists('request')) {
    /**
     * Get the current request instance
     *
     * @return \Plugs\Http\Message\ServerRequest|\Psr\Http\Message\ServerRequestInterface|null
     */
    function request()
    {
        try {
            $container = app();

            // Try to get from container
            if ($container->bound(ServerRequestInterface::class)) {
                return $container->make(ServerRequestInterface::class);
            }

            // Try to get current request from global context
            if (isset($GLOBALS['__current_request']) && $GLOBALS['__current_request'] instanceof ServerRequestInterface) {
                return $GLOBALS['__current_request'];
            }
        } catch (\Exception $e) {
            // Silent fail in production, log in development
            if (env('APP_DEBUG', false)) {
                error_log('Request helper error: ' . $e->getMessage());
            }
        }

        return null;
    }
}

if (!function_exists('response')) {
    function response($content = '', int $status = 200, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        if (is_array($content) || is_object($content)) {
            return \Plugs\Http\ResponseFactory::json($content, $status, $headers);
        }

        return \Plugs\Http\ResponseFactory::html((string) $content, $status, $headers);
    }
}

if (!function_exists('session')) {
    /**
     * Get / set session value or return the session manager
     */
    function session(string|array|null $key = null, $default = null)
    {
        try {
            $session = resolve(\Plugs\Session\Session::class);

            if ($key === null) {
                return $session;
            }

            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $session->set($k, $v);
                }

                return null;
            }

            // Check flash through FlashMessage first
            if (\Plugs\Utils\FlashMessage::has($key)) {
                return \Plugs\Utils\FlashMessage::get($key);
            }

            return $session->get($key, $default);
        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_NONE)
                session_start();
            if ($key === null)
                return $_SESSION;
            return $_SESSION[$key] ?? $default;
        }
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value from previous request
     */
    function old(string $key, $default = null)
    {
        if (isset($_SESSION['_old_input'][$key])) {
            return $_SESSION['_old_input'][$key];
        }

        return $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Get or set flash message
     */
    function flash(string $key, $value = null, ?string $title = null)
    {
        if ($value === null) {
            return \Plugs\Utils\FlashMessage::get($key);
        }

        \Plugs\Utils\FlashMessage::set($key, $value, $title);
    }
}

if (!function_exists('auth')) {
    /**
     * Get the auth manager or a specific guard
     */
    function auth(?string $guard = null)
    {
        if ($guard === null) {
            return app('auth');
        }

        return app('auth')->guard($guard);
    }
}

if (!function_exists('user')) {
    /**
     * Get the currently authenticated user
     */
    function user(?string $guard = null)
    {
        return auth($guard)->user();
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a job to the queue
     */
    function dispatch($job, $data = '', $queue = null)
    {
        return app('queue')->push($job, $data, $queue);
    }
}

if (!function_exists('storage')) {
    /**
     * Get the storage manager or a specific disk
     */
    function storage(?string $disk = null)
    {
        if (is_null($disk)) {
            return app('storage');
        }

        return app('storage')->disk($disk);
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): void
    {
        throw new \RuntimeException($message ?: "HTTP {$code}", $code);
    }
}

if (!function_exists('sanitize')) {
    function sanitize($value, string $type = 'string')
    {
        $method = [\Plugs\Security\Sanitizer::class, $type];

        if (is_callable($method)) {
            return call_user_func($method, $value);
        }

        return \Plugs\Security\Sanitizer::string($value);
    }
}

if (!function_exists('escape')) {
    function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('mask')) {
    /**
     * Mask sensitive data with asterisks or custom character
     *
     * @param string $value The value to mask
     * @param string $type The type of masking (email, phone, card, custom, full)
     * @param string $maskChar The character to use for masking (default: *)
     * @param int $visibleStart Number of visible characters at the start (for custom type)
     * @param int $visibleEnd Number of visible characters at the end (for custom type)
     * @return string The masked value
     */
    function mask(string $value, string $type = 'custom', string $maskChar = '*', int $visibleStart = 3, int $visibleEnd = 3): string
    {
        if (empty($value)) {
            return $value;
        }

        $length = strlen($value);

        switch ($type) {
            case 'email':
                // Mask email: j***@e******.com
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value; // Not a valid email, return as is
                }

                [$username, $domain] = explode('@', $value);
                $usernameLength = strlen($username);
                $showChars = min(1, $usernameLength - 1);

                $maskedUsername = $usernameLength > 1
                    ? substr($username, 0, $showChars) . str_repeat($maskChar, $usernameLength - $showChars)
                    : $username;

                // Mask domain part (before the dot)
                $domainParts = explode('.', $domain);
                $domainName = $domainParts[0];
                $domainExt = implode('.', array_slice($domainParts, 1));

                $domainLength = strlen($domainName);
                $showDomainChars = min(1, $domainLength - 1);

                $maskedDomain = $domainLength > 1
                    ? substr($domainName, 0, $showDomainChars) . str_repeat($maskChar, $domainLength - $showDomainChars)
                    : $domainName;

                return $maskedUsername . '@' . $maskedDomain . '.' . $domainExt;

            case 'phone':
                // Mask phone: +234***9876 or 080***1234
                $cleaned = preg_replace('/[\s\-\(\)]/', '', $value);
                $cleanedLength = strlen($cleaned);

                if ($cleanedLength < 4) {
                    return str_repeat($maskChar, $cleanedLength);
                }

                $visiblePrefix = substr($cleaned, 0, min(3, $cleanedLength - 4));
                $visibleSuffix = substr($cleaned, -4);
                $maskedMiddle = str_repeat($maskChar, $cleanedLength - strlen($visiblePrefix) - 4);

                return $visiblePrefix . $maskedMiddle . $visibleSuffix;

            case 'card':
                // Mask card number: **** **** **** 1234
                $cleaned = preg_replace('/\s/', '', $value);
                $cleanedLength = strlen($cleaned);

                if ($cleanedLength < 4) {
                    return str_repeat($maskChar, $cleanedLength);
                }

                $visibleDigits = substr($cleaned, -4);
                $maskedPart = str_repeat($maskChar, $cleanedLength - 4);

                // Format in groups of 4
                $masked = $maskedPart . $visibleDigits;

                return implode(' ', str_split($masked, 4));

            case 'full':
                // Mask entire string
                return str_repeat($maskChar, $length);

            case 'custom':
            default:
                // Custom masking with configurable visible characters
                if ($length <= ($visibleStart + $visibleEnd)) {
                    // String too short, mask the middle part only
                    if ($length <= 2) {
                        return str_repeat($maskChar, $length);
                    }

                    $start = substr($value, 0, 1);
                    $end = substr($value, -1);
                    $middle = str_repeat($maskChar, $length - 2);

                    return $start . $middle . $end;
                }

                $start = substr($value, 0, $visibleStart);
                $end = substr($value, -$visibleEnd);
                $middle = str_repeat($maskChar, $length - $visibleStart - $visibleEnd);

                return $start . $middle . $end;
        }
    }
}


if (!function_exists('now')) {
    function now(): int
    {
        return time();
    }
}

if (!function_exists('logger')) {
    /**
     * Log a message or get the logger instance
     */
    function logger(string|null $message = null, string $level = 'info', array $context = [])
    {
        $logger = app('log');

        if ($message === null) {
            return $logger;
        }

        $logger->log($level, $message, $context);
    }
}

function loadFunctions(string|array|null $source): void
{
    // If no source provided, use default directory behavior
    if ($source === null) {
        $source = __DIR__ . '/functions/';
    }

    // Handle directory source
    if (is_string($source) && is_dir($source)) {
        $files = glob($source . '*.php');
        requireFiles($files);

        return;
    }

    // Handle array source (like your example)
    if (is_array($source)) {
        $files = extractFilesFromArray($source);
        requireFiles($files);

        return;
    }

    // Handle single file path
    if (file_exists($source)) {
        requireFiles([$source]);

        return;
    }
}

function requireFiles(array $files): void
{
    foreach ($files as $file) {
        if (file_exists($file) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            require_once $file;
        }
    }
}

function extractFilesFromArray(array $fileArray): array
{
    $files = [];

    foreach ($fileArray as $key => $value) {
        // Extract file paths from array values
        if (is_string($value) && file_exists($value) && pathinfo($value, PATHINFO_EXTENSION) === 'php') {
            $files[] = $value;
        }
        // Handle nested arrays recursively
        elseif (is_array($value)) {
            $files = array_merge($files, extractFilesFromArray($value));
        }
    }

    return array_unique($files); // Remove duplicates
}

/**
 * Quick pagination helper
 */
function paginate($data, $perPage = 15, $page = null)
{
    $page = $page ?? $_GET['page'] ?? 1;

    if (is_array($data)) {
        return \Plugs\Paginator\Pagination::fromArray([
            'data' => array_slice($data, ($page - 1) * $perPage, $perPage),
            'per_page' => $perPage,
            'current_page' => $page,
            'total' => count($data),
        ]);
    }

    // Assume it's PlugModel pagination array
    return \Plugs\Paginator\Pagination::fromArray((array) $data);
}
