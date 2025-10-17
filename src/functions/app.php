<?php

declare(strict_types=1);

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
    function config(string|null $key = null, $default = null)
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
        return BASE_PATH . '/' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage/' . ltrim($path, '/'));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public/' . ltrim($path, '/'));
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        $engine = new \Plugs\View\ViewEngine(
            base_path('views'),
            storage_path('views'),
            !env('APP_DEBUG', false)
        );

        return $engine->render($view, $data);
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

if (!function_exists('old')) {
    function old(string $key, $default = null)
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \Psr\Http\Message\ResponseInterface
    {
        return \Plugs\Http\ResponseFactory::redirect($url, $status);
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

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): void
    {
        throw new \RuntimeException($message ?: "HTTP {$code}", $code);
    }
}

if (!function_exists('dd')) {
    function dd(...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        die(1);
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

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim(env('APP_URL', 'http://localhost'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('now')) {
    function now(): int
    {
        return time();
    }
}

if (!function_exists('logger')) {
    function logger(string $message, string $level = 'info'): void
    {
        $logFile = storage_path('logs/app.log');
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('route')) {
    /**
     * Generate URL for named route
     */
    function route(string $name, array $parameters = []): string
    {
        $router = app(Plugs\Router\Router::class);
        return $router->route($name, $parameters);
    }
}
