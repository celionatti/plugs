<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use Plugs\Router\Router;
use Plugs\FeatureModule\FeatureModuleManager;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

$container = \Plugs\Container\Container::getInstance();
$router = new Router($container);

// BIND THE ROUTER SO THE FACADE WORKS
$container->instance('router', $router);

// Manually load module routes as the Kernel would
$manager = FeatureModuleManager::getInstance();
$manager->boot($app); // Ensure modules are booted

foreach ($manager->getWebRouteEntries() as $entry) {
    $module = $entry['module'];
    $file = $entry['file'];

    $attributes = [
        'namespace' => "Modules\\" . $module->getName() . "\\Controllers",
        'as' => strtolower($module->getName()) . '.',
        'prefix' => strtolower($module->getName()),
    ];

    $router->group($attributes, function () use ($file) {
        require $file;
    });
}

// Load main web routes
$router->group(['namespace' => 'App\Http\Controllers', 'middleware' => ['web']], function () use ($router) {
    require __DIR__ . '/routes/web.php';
});

$routes = $router->getRoutes();

foreach ($routes as $method => $routeArray) {
    foreach ($routeArray as $route) {
        $handler = $route->getHandler();
        if (is_array($handler)) {
            $handler = implode('@', $handler);
        } elseif ($handler instanceof Closure) {
            $handler = 'Closure';
        }
        echo sprintf("[%s] %s -> %s\n", $method, $route->getPath(), $handler);
    }
}
