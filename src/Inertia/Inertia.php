<?php

declare(strict_types=1);

namespace Plugs\Inertia;

/*
|--------------------------------------------------------------------------
| Inertia Class
|--------------------------------------------------------------------------
|
| Main facade class for Inertia.js-like SPA integration. Provides static
| methods for rendering Inertia pages, sharing global props, and managing
| asset versioning.
|
| Usage:
|   return Inertia::render('Users/Index', ['users' => $users]);
|   Inertia::share('auth', ['user' => $currentUser]);
*/

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

class Inertia
{
    /**
     * Root view template name
     */
    private static string $rootView = 'app';

    /**
     * Shared props available to all responses
     */
    private static array $sharedProps = [];

    /**
     * Asset version for cache busting
     * @var string|callable|null
     */
    private static $version = null;

    /**
     * Set the root view template
     *
     * @param string $view View template name
     * @return void
     */
    public static function setRootView(string $view): void
    {
        self::$rootView = $view;
    }

    /**
     * Get the root view template
     *
     * @return string
     */
    public static function getRootView(): string
    {
        return self::$rootView;
    }

    /**
     * Share data with all Inertia responses
     *
     * @param string|array $key Key or array of key-value pairs
     * @param mixed $value Value (if $key is string)
     * @return void
     */
    public static function share($key, $value = null): void
    {
        if (is_array($key)) {
            self::$sharedProps = array_merge(self::$sharedProps, $key);
        } else {
            self::$sharedProps[$key] = $value;
        }
    }

    /**
     * Get all shared props
     *
     * @return array
     */
    public static function getSharedProps(): array
    {
        $resolved = [];

        foreach (self::$sharedProps as $key => $value) {
            // Resolve callables
            if (is_callable($value) && !($value instanceof LazyProp)) {
                $resolved[$key] = $value();
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /**
     * Clear shared props (useful for testing)
     *
     * @return void
     */
    public static function clearShared(): void
    {
        self::$sharedProps = [];
    }

    /**
     * Render an Inertia page
     *
     * @param string $component Component name (e.g., 'Users/Index')
     * @param array $props Props to pass to the component
     * @return InertiaResponse
     */
    public static function render(string $component, array $props = []): InertiaResponse
    {
        return new InertiaResponse(
            $component,
            $props,
            self::$rootView,
            self::getVersion()
        );
    }

    /**
     * Create a lazy prop that's only evaluated when requested
     *
     * @param callable $callback Callback to evaluate lazily
     * @return LazyProp
     */
    public static function lazy(callable $callback): LazyProp
    {
        return new LazyProp($callback);
    }

    /**
     * Set the asset version for cache busting
     *
     * @param string|callable $version Version string or callback
     * @return void
     */
    public static function version($version): void
    {
        self::$version = $version;
    }

    /**
     * Get the current asset version
     *
     * @return string
     */
    public static function getVersion(): string
    {
        if (self::$version === null) {
            // Try to get from config
            if (function_exists('config')) {
                $configVersion = config('inertia.version');
                if ($configVersion !== null) {
                    self::$version = $configVersion;
                }
            }
        }

        if (self::$version === null) {
            return '';
        }

        if (is_callable(self::$version)) {
            return (string) (self::$version)();
        }

        return (string) self::$version;
    }

    /**
     * Create a location redirect response
     *
     * Used for external redirects or when you need to
     * force a full page reload
     *
     * @param string $url URL to redirect to
     * @return ResponseInterface
     */
    public static function location(string $url): ResponseInterface
    {
        // Check if this is an Inertia request
        $request = function_exists('request') ? request() : null;

        if ($request !== null && $request->hasHeader('X-Inertia')) {
            // Return 409 Conflict with X-Inertia-Location header
            return ResponseFactory::create('', 409, [
                'X-Inertia-Location' => $url,
            ]);
        }

        // Regular redirect for non-Inertia requests
        return ResponseFactory::redirect($url);
    }

    /**
     * Check if the current request is an Inertia request
     *
     * @return bool
     */
    public static function isInertiaRequest(): bool
    {
        $request = function_exists('request') ? request() : null;

        if ($request === null) {
            return false;
        }

        return $request->hasHeader('X-Inertia')
            && $request->getHeaderLine('X-Inertia') === 'true';
    }

    /**
     * Flash data to the session (will be shared as props on next request)
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function flash(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (headers_sent() === false) {
                session_start();
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_inertia_flash'][$key] = $value;
        }
    }

    /**
     * Get and clear flashed data
     *
     * @return array
     */
    public static function getFlashed(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return [];
        }

        $flashed = $_SESSION['_inertia_flash'] ?? [];
        unset($_SESSION['_inertia_flash']);

        return $flashed;
    }

    /**
     * Initialize Inertia from configuration
     *
     * @return void
     */
    public static function init(): void
    {
        if (!function_exists('config')) {
            return;
        }

        // Load configuration
        $rootView = config('inertia.root_view', 'app');
        if ($rootView !== null) {
            self::setRootView($rootView);
        }

        $version = config('inertia.version');
        if ($version !== null) {
            self::version($version);
        }
    }
}
