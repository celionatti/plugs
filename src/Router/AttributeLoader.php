<?php

declare(strict_types=1);

namespace Plugs\Router;

use Plugs\Router\Attributes\Route as RouteAttribute;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;

class AttributeLoader
{
    protected Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Scan directories for attributes and register routes.
     *
     * @param array|string $directories
     * @return void
     */
    public function load($directories): void
    {
        $directories = (array) $directories;

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory)
            );

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $className = $this->getClassNameFromFile($file->getPathname());

                if ($className && class_exists($className)) {
                    $this->registerRoutesFromClass($className);
                }
            }
        }
    }

    protected function registerRoutesFromClass(string $className): void
    {
        $reflection = new ReflectionClass($className);

        // Check for class-level Route attribute (prefix)
        $classRoute = $reflection->getAttributes(RouteAttribute::class)[0] ?? null;
        $prefix = '';
        $middleware = [];
        $domain = null;

        if ($classRoute) {
            /** @var RouteAttribute $instance */
            $instance = $classRoute->newInstance();
            $prefix = trim($instance->path, '/');
            $middleware = $instance->middleware;
            // Class level route usually implies a group, but our Attribute might need extending for full group support
            // For now, we'll just treat path as prefix if it exists on class
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);

            foreach ($attributes as $attribute) {
                /** @var RouteAttribute $routeAttr */
                $routeAttr = $attribute->newInstance();

                $methods = (array) $routeAttr->methods;
                $path = '/' . trim($routeAttr->path, '/');

                if ($prefix) {
                    $path = '/' . $prefix . $path;
                }

                $handler = [$className, $method->getName()];
                $routeMiddleware = array_merge($middleware, $routeAttr->middleware);

                $route = $this->router->match($methods, $path, $handler, $routeMiddleware);

                if ($routeAttr->name) {
                    $route->name($routeAttr->name);
                }

                if (!empty($routeAttr->where)) {
                    $route->where($routeAttr->where);
                }
            }
        }
    }

    /**
     * Extract class name from file
     */
    protected function getClassNameFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return $class;
    }
}
