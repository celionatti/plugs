<?php

declare(strict_types=1);

namespace Plugs\Router;

use Plugs\Exceptions\RouteNotFoundException;
use Plugs\Exceptions\MissingRouteParameterException;

class RouteUrlGenerator
{
    private ?Router $router;
    private array $namedRoutes = [];

    public function __construct(?Router $router = null)
    {
        $this->router = $router;
    }

    /**
     * Set the named routes array if not relying on the router instance.
     */
    public function setNamedRoutes(array $namedRoutes): self
    {
        $this->namedRoutes = $namedRoutes;
        return $this;
    }

    /**
     * Get a named route either from local array or the attached Router.
     */
    protected function getRouteByName(string $name): ?Route
    {
        if ($this->router) {
            return $this->router->getRouteByName($name);
        }

        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name
     * @param array $parameters
     * @param bool $absolute
     * @return string
     * @throws RouteNotFoundException
     */
    public function route(string $name, array $parameters = [], bool $absolute = false): string
    {
        $route = $this->getRouteByName($name);

        if ($route === null) {
            $message = "Route [{$name}] not found.";

            if ($suggestion = $this->getRouteSuggestion($name)) {
                $message .= " Did you mean [{$suggestion}]?";
            }

            throw new RouteNotFoundException($name, '', $message);
        }

        return $this->generate($route, $parameters, $absolute);
    }

    /**
     * Generate a URL for a specific Route instance.
     *
     * @param Route $route
     * @param array $parameters
     * @param bool $absolute
     * @return string
     * @throws MissingRouteParameterException
     */
    public function generate(Route $route, array $parameters = [], bool $absolute = false): string
    {
        $path = $route->getPath();

        // 1. Substitute path parameters '{param}' and '{param?}'
        foreach ($parameters as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
            $path = str_replace('{' . $key . '?}', (string) $value, $path);
        }

        // 2. Remove remaining optional parameters '{param?}'
        $path = preg_replace('/\/?\{[^}]+\?\}/', '', $path);

        // 3. Check for any unresolved required parameters
        preg_match_all('/\{([^}?]+)\}/', $path, $matches);
        if (!empty($matches[1])) {
            throw new MissingRouteParameterException(
                $route->getName() ?? 'unnamed',
                $route->getPath(),
                $matches[1],
                $parameters
            );
        }

        // 4. Build absolute URL if requested
        if ($absolute) {
            $scheme = $route->getScheme() ?? (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'
            );
            $host = $route->getDomain() ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');

            // Substitute domain parameters (e.g., {tenant} in {tenant}.example.com)
            foreach ($parameters as $key => $value) {
                if (str_contains($host, '{' . $key . '}')) {
                    $host = str_replace('{' . $key . '}', (string) $value, $host);
                    unset($parameters[$key]);
                }
            }

            $basePath = function_exists('get_base_path') ? get_base_path() : '/';
            $url = $scheme . '://' . $host . rtrim($basePath, '/') . '/' . ltrim($path, '/');
        } else {
            $basePath = function_exists('get_base_path') ? get_base_path() : '/';
            $url = rtrim($basePath, '/') . '/' . ltrim($path, '/');
        }

        // 5. Append unused parameters as query string
        $usedParams = [];
        preg_match_all('/\{([^}?]+)\??\}/', $route->getPath(), $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $m) {
                $usedParams[$m] = true;
            }
        }

        $domain = $route->getDomain();
        if ($domain) {
            preg_match_all('/\{([^}?]+)\??\}/', $domain, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $m) {
                    $usedParams[$m] = true;
                }
            }
        }

        $extraParams = array_diff_key($parameters, $usedParams);
        if (!empty($extraParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($extraParams);
        }

        return $url;
    }

    /**
     * Get a suggestion for a missing route name.
     */
    public function getRouteSuggestion(string $name): ?string
    {
        $namedRoutes = $this->router ? $this->router->getNamedRoutes() : $this->namedRoutes;
        $bestMatch = null;
        $shortestDistance = -1;

        foreach (array_keys($namedRoutes) as $routeName) {
            $distance = levenshtein($name, $routeName);

            if ($distance === 0) {
                return $routeName;
            }

            if ($distance <= $shortestDistance || $shortestDistance < 0) {
                $bestMatch = $routeName;
                $shortestDistance = $distance;
            }
        }

        // Only return if the distance is reasonably small (e.g., < 3)
        return ($shortestDistance >= 0 && $shortestDistance < 3) ? $bestMatch : null;
    }
}
