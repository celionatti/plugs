<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Services Configuration File
|--------------------------------------------------------------------------
|
| This file is used to define and bind services into the application's
| service container. You can register services such as database connections,
| view engines, and other shared resources here.
*/

use Plugs\Router\Router;
use Plugs\View\ViewEngine;
use Plugs\Container\Container;
use Plugs\Database\Connection;


return function (Container $container) {

    // Bind ViewEngine as singleton
    $container->singleton(ViewEngine::class, function ($container) {
        $viewPath = BASE_PATH . 'resources/views';
        $cachePath = BASE_PATH . 'storage/views';

        // Ensure cache directory exists
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        return new ViewEngine(
            $viewPath,
            $cachePath,
            true // Temporarily force true for debugging
        );
    });

    // Bind Database Connection as singleton
    $container->singleton(Connection::class, function ($container) {
        $defaultConnection = config('database.default');
        $dbConfig = config("database.connections.{$defaultConnection}");

        return Connection::getInstance($dbConfig);
    });

    // Bind Router as singleton
    $container->singleton(Router::class, function ($container) {
        return new Router();
    });

    // Bind Mail as singleton
    $container->singleton('mail', function () {
        $config = config('mail');
        return new \Plugs\Mail\MailService($config);
    });

    // Optional: Create aliases for easier access
    $container->alias(ViewEngine::class, 'view');
    $container->alias(Connection::class, 'db');
    $container->alias(Router::class, 'router');
};
