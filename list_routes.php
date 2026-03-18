<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

// The router might be bound to 'router' or Plugs\Router\Router::class
$container = \Plugs\Container\Container::getInstance();
$router = null;

if ($container->has('router')) {
    $router = $container->make('router');
} elseif ($container->has(\Plugs\Router\Router::class)) {
    $router = $container->make(\Plugs\Router\Router::class);
}

if (!$router) {
    echo "Router not found in container.\n";
    exit(1);
}

$routes = $router->getRoutes();

foreach ($routes as $method => $routeArray) {
    foreach ($routeArray as $path => $route) {
        echo "[$method] $path\n";
    }
}
