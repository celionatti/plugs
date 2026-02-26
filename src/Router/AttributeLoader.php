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

        // Check for class-level Route attribute (prefix, domain, middleware, where)
        $classRouteAttr = $reflection->getAttributes(RouteAttribute::class)[0] ?? null;
        $classPrefix = '';
        $classMiddleware = [];
        $classDomain = null;
        $classWhere = [];

        if ($classRouteAttr) {
            /** @var RouteAttribute $instance */
            $instance = $classRouteAttr->newInstance();
            $classPrefix = trim($instance->path, '/');
            $classMiddleware = $instance->middleware;
            $classDomain = $instance->domain;
            $classWhere = $instance->where;
        }

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(RouteAttribute::class);

            foreach ($attributes as $attribute) {
                /** @var RouteAttribute $routeAttr */
                $routeAttr = $attribute->newInstance();

                $methods = (array) $routeAttr->methods;
                $path = '/' . trim($routeAttr->path, '/');

                if ($classPrefix) {
                    $path = '/' . $classPrefix . $path;
                }

                $handler = [$className, $method->getName()];
                $routeMiddleware = array_merge($classMiddleware, $routeAttr->middleware);

                $route = $this->router->match($methods, $path, $handler, $routeMiddleware);

                // Apply domain (method level overrides class level if set)
                $domain = $routeAttr->domain ?? $classDomain;
                if ($domain) {
                    $route->domain($domain);
                }

                if ($routeAttr->name) {
                    $route->name($routeAttr->name);
                }

                // Apply where constraints (merge method level into class level)
                $where = array_merge($classWhere, $routeAttr->where);
                if (!empty($where)) {
                    $route->where($where);
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
