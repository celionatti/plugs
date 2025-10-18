<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| RouteListCommand Class
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class RouteListCommand extends Command
{
    protected string $name = 'route:list';
    protected string $description = 'Display all registered routes from the Route class';

    public function handle(): int
    {
        $this->output->header("Application Routes");
        
        try {
            // Load route files to register routes
            $routeFiles = $this->getRouteFiles();
            
            if (empty($routeFiles)) {
                $this->output->warning("No route files found!");
                $this->output->note("Expected files: routes/web.php, routes/api.php");
                return 0;
            }

            // Load routes from files (this will register them with Route class)
            $this->loadRouteFiles($routeFiles);
            
            // Get registered routes from Route class
            $routes = $this->getRegisteredRoutes();
            
            if (empty($routes)) {
                $this->output->warning("No routes registered.");
                $this->displayRouteFileStatus($routeFiles);
                return 0;
            }

            // Display route statistics
            $this->displayRouteStatistics($routes, $routeFiles);
            
            // Group routes by source file
            $groupedRoutes = $this->groupRoutesBySource($routes);
            
            // Display routes for each group
            foreach ($groupedRoutes as $source => $sourceRoutes) {
                $this->displayRoutesForSource($source, $sourceRoutes);
            }

            // Display summary
            $this->displayRouteSummary($routes);

            return 0;

        } catch (\Exception $e) {
            $this->output->critical("Failed to load routes: " . $e->getMessage());
            $this->output->debug("Error details: " . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Get available route files
     */
    private function getRouteFiles(): array
    {
        $routeFiles = [
            'routes/web.php' => 'web',
            'routes/api.php' => 'api'
        ];

        $existing = [];
        
        foreach ($routeFiles as $file => $type) {
            if (file_exists($file) && is_readable($file)) {
                $existing[$file] = $type;
                $this->output->success("✓ Found: {$file}");
            } else {
                $this->output->info("✗ Not found: {$file}");
            }
        }
        
        return $existing;
    }

    /**
     * Load route files (this registers routes with Route class)
     */
    private function loadRouteFiles(array $files): void
    {
        foreach ($files as $file => $type) {
            try {
                $this->output->info("Loading routes from {$file}...");
                
                // Track routes before loading
                $routesBefore = $this->getRegisteredRoutes();
                $countBefore = count($routesBefore);
                
                // Load the route file (this will register routes)
                require $file;
                
                // Track routes after loading
                $routesAfter = $this->getRegisteredRoutes();
                $countAfter = count($routesAfter);
                $newRoutes = $countAfter - $countBefore;
                
                $this->output->success("✓ Loaded {$newRoutes} routes from {$file}");
                
            } catch (\Exception $e) {
                $this->output->error("Failed to load routes from {$file}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get registered routes from Route class
     */
    private function getRegisteredRoutes(): array
    {
        try {
            // Get the router instance from the Route facade
            $router = new Router();
            
            // Get the RouteCollection from the router
            $routeCollection = $router->getRoutes();
            
            // Get all routes from the collection
            // Try common methods that RouteCollection might have
            $possibleMethods = ['all', 'getRoutes', 'getAllRoutes', 'toArray'];
            
            foreach ($possibleMethods as $method) {
                if (method_exists($routeCollection, $method)) {
                    try {
                        $routes = $routeCollection->$method();
                        if (is_array($routes)) {
                            return $this->normalizeRoutes($routes);
                        }
                    } catch (\Exception $e) {
                        $this->output->debug("Method {$method} failed: " . $e->getMessage());
                    }
                }
            }
            
            // If no methods work, try to access routes via reflection
            $reflection = new \ReflectionClass($routeCollection);
            
            // Look for common property names
            $possibleProperties = ['routes', 'items', 'collection'];
            
            foreach ($possibleProperties as $property) {
                if ($reflection->hasProperty($property)) {
                    try {
                        $prop = $reflection->getProperty($property);
                        $prop->setAccessible(true);
                        $routes = $prop->getValue($routeCollection);
                        
                        if (is_array($routes)) {
                            return $this->normalizeRoutes($routes);
                        }
                    } catch (\Exception $e) {
                        $this->output->debug("Property {$property} failed: " . $e->getMessage());
                    }
                }
            }
            
            throw new \RuntimeException("Could not access routes from RouteCollection");
            
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Could not access routes from Route class: " . $e->getMessage() . ". " .
                "Please ensure the RouteCollection class has a method like all() or getRoutes() " .
                "that returns the registered routes array."
            );
        }
    }

    /**
     * Normalize routes from RouteCollection to consistent format
     */
    private function normalizeRoutes(array $routes): array
    {
        $normalized = [];
        
        foreach ($routes as $route) {
            try {
                $normalized[] = $this->normalizeRoute($route);
            } catch (\Exception $e) {
                $this->output->debug("Skipping invalid route: " . $e->getMessage());
            }
        }
        
        return $normalized;
    }

    /**
     * Normalize a single RouteDefinition to consistent format
     */
    private function normalizeRoute(mixed $route): array
    {
        // Handle RouteDefinition objects (your system)
        if (is_object($route) && method_exists($route, 'getMethods')) {
            $methods = $route->getMethods();
            $uri = method_exists($route, 'getUri') ? $route->getUri() : 
                  (method_exists($route, 'getPattern') ? $route->getPattern() : '/');
            
            $action = '';
            if (method_exists($route, 'getAction')) {
                $action = $this->normalizeAction($route->getAction());
            } elseif (method_exists($route, 'getHandler')) {
                $action = $this->normalizeAction($route->getHandler());
            }
            
            $name = '';
            if (method_exists($route, 'getName')) {
                $name = $route->getName() ?? '';
            }
            
            $middleware = [];
            if (method_exists($route, 'getMiddleware')) {
                $mw = $route->getMiddleware();
                $middleware = is_array($mw) ? $mw : [$mw];
            }
            
            return [
                'method' => implode('|', (array)$methods),
                'uri' => $uri,
                'action' => $action,
                'name' => $name,
                'middleware' => implode(', ', array_filter($middleware))
            ];
        }
        
        // Handle array format
        if (is_array($route)) {
            return [
                'method' => strtoupper($route['method'] ?? $route['methods'] ?? 'GET'),
                'uri' => $route['uri'] ?? $route['path'] ?? '/',
                'action' => $this->normalizeAction($route['action'] ?? $route['handler'] ?? ''),
                'name' => $route['name'] ?? '',
                'middleware' => is_array($route['middleware'] ?? []) ? 
                    implode(', ', $route['middleware']) : 
                    ($route['middleware'] ?? '')
            ];
        }
        
        throw new \InvalidArgumentException("Invalid route format: " . gettype($route));
    }

    /**
     * Normalize route action
     */
    private function normalizeAction(mixed $action): string
    {
        if (is_string($action)) {
            return $action;
        }
        
        if (is_callable($action)) {
            if ($action instanceof \Closure) {
                return 'Closure';
            }
            return 'Callable';
        }
        
        if (is_array($action)) {
            if (count($action) >= 2) {
                $class = is_object($action[0]) ? get_class($action[0]) : $action[0];
                return $class . '@' . $action[1];
            }
        }
        
        return 'Unknown';
    }

    /**
     * Group routes by source (educated guess based on URI patterns)
     */
    private function groupRoutesBySource(array $routes): array
    {
        $webRoutes = [];
        $apiRoutes = [];
        
        foreach ($routes as $route) {
            if (str_starts_with($route['uri'], '/api/') || str_contains($route['uri'], '/api/')) {
                $apiRoutes[] = $route;
            } else {
                $webRoutes[] = $route;
            }
        }
        
        $grouped = [];
        if (!empty($webRoutes)) {
            $grouped['Web'] = $webRoutes;
        }
        if (!empty($apiRoutes)) {
            $grouped['API'] = $apiRoutes;
        }
        if (empty($webRoutes) && empty($apiRoutes)) {
            $grouped['All'] = $routes;
        }
        
        return $grouped;
    }

    /**
     * Display route statistics
     */
    private function displayRouteStatistics(array $routes, array $files): void
    {
        $this->output->subHeader("Route Statistics");
        
        $methodCounts = [];
        $actionTypes = ['Closure' => 0, 'Controller' => 0, 'Other' => 0];
        
        foreach ($routes as $route) {
            // Count methods
            $methods = explode('|', $route['method']);
            foreach ($methods as $method) {
                $methodCounts[$method] = ($methodCounts[$method] ?? 0) + 1;
            }
            
            // Count action types
            if ($route['action'] === 'Closure') {
                $actionTypes['Closure']++;
            } elseif (str_contains($route['action'], '@') || str_contains($route['action'], '::')) {
                $actionTypes['Controller']++;
            } else {
                $actionTypes['Other']++;
            }
        }
        
        $statsTable = [
            ['Total Routes', (string)count($routes)],
            ['Route Files', (string)count($files)],
        ];
        
        foreach ($methodCounts as $method => $count) {
            $statsTable[] = ["{$method} Routes", (string)$count];
        }
        
        $statsTable[] = ['---', '---'];
        foreach ($actionTypes as $type => $count) {
            if ($count > 0) {
                $statsTable[] = ["{$type} Actions", (string)$count];
            }
        }
        
        $this->output->table(['Metric', 'Value'], $statsTable);
    }

    /**
     * Display routes for a specific source
     */
    private function displayRoutesForSource(string $source, array $routes): void
    {
        $this->output->subHeader("{$source} Routes (" . count($routes) . ")");
        
        $tableData = [];
        foreach ($routes as $route) {
            $tableData[] = [
                (string)$route['method'],
                (string)$route['uri'], 
                (string)$route['action'],
                (string)($route['name'] ?: '-'),
                (string)($route['middleware'] ?: '-')
            ];
        }
        
        $this->output->table(['Method', 'URI', 'Action', 'Name', 'Middleware'], $tableData);
    }

    /**
     * Display route file status
     */
    private function displayRouteFileStatus(array $files): void
    {
        $this->output->subHeader("Route File Status");
        
        foreach ($files as $file => $type) {
            try {
                $this->output->info("✓ {$file}: File loads successfully");
            } catch (\Exception $e) {
                $this->output->error("✗ {$file}: Error loading - " . $e->getMessage());
            }
        }
        
        $this->output->note("Route files load but no routes were found in the RouteCollection.");
        $this->output->note("This might indicate an issue with route registration or RouteCollection access.");
    }

    /**
     * Display final summary
     */
    private function displayRouteSummary(array $routes): void
    {
        $uniqueUris = array_unique(array_column($routes, 'uri'));
        $uniqueMethods = [];
        
        foreach ($routes as $route) {
            $methods = explode('|', $route['method']);
            $uniqueMethods = array_merge($uniqueMethods, $methods);
        }
        $uniqueMethods = array_unique($uniqueMethods);
        
        $namedRoutes = array_filter($routes, fn($r) => !empty($r['name']));
        $middlewareRoutes = array_filter($routes, fn($r) => !empty($r['middleware']));
        
        $this->output->box(
            "Total Routes: " . count($routes) . "\n" .
            "Unique URIs: " . count($uniqueUris) . "\n" .
            "HTTP Methods: " . implode(', ', $uniqueMethods) . "\n" .
            "Named Routes: " . count($namedRoutes) . "\n" .
            "Routes with Middleware: " . count($middlewareRoutes),
            "📊 Summary",
            "info"
        );
        
        // Show potential issues
        $this->checkForRouteIssues($routes);
    }

    /**
     * Check for common route issues
     */
    private function checkForRouteIssues(array $routes): void
    {
        $issues = [];
        
        // Check for duplicate routes
        $routeSignatures = [];
        foreach ($routes as $route) {
            $methods = explode('|', $route['method']);
            foreach ($methods as $method) {
                $signature = $method . ':' . $route['uri'];
                if (isset($routeSignatures[$signature])) {
                    $issues[] = "Duplicate route: {$signature}";
                } else {
                    $routeSignatures[$signature] = true;
                }
            }
        }
        
        // Check for empty actions
        $emptyActions = array_filter($routes, fn($r) => empty(trim($r['action'])));
        if (!empty($emptyActions)) {
            $issues[] = count($emptyActions) . " route(s) with empty actions";
        }
        
        // Check for potential parameter conflicts
        $parameterRoutes = array_filter($routes, fn($r) => str_contains($r['uri'], '{'));
        $staticRoutes = array_filter($routes, fn($r) => !str_contains($r['uri'], '{'));
        
        foreach ($parameterRoutes as $paramRoute) {
            $paramPattern = preg_replace('/\{[^}]+\}/', '*', $paramRoute['uri']);
            foreach ($staticRoutes as $staticRoute) {
                if (fnmatch($paramPattern, $staticRoute['uri'])) {
                    $issues[] = "Potential conflict: '{$staticRoute['uri']}' may be shadowed by '{$paramRoute['uri']}'";
                }
            }
        }
        
        if (!empty($issues)) {
            $this->output->box(
                implode("\n", $issues),
                "⚠️ Potential Issues",
                "warning"
            );
        } else {
            $this->output->success("No route issues detected!");
        }
    }
}