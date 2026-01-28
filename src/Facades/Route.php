<?php

declare(strict_types=1);

namespace Plugs\Facades;

/*
|--------------------------------------------------------------------------
| Route Facade
|--------------------------------------------------------------------------
|
| Provides static access to the Router instance.
|
| Usage:
|   use Plugs\Facades\Route;
|
|   Route::get('/path', $handler);
|   Route::post('/path', $handler)->name('route.name');
|   Route::group(['prefix' => '/api'], function() { ... });
*/

use Plugs\Facade;

/**
 * @method static \Plugs\Router\Route get(string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route post(string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route put(string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route delete(string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route patch(string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route match(array $methods, string $path, $handler, array $middleware = [])
 * @method static \Plugs\Router\Route any(string $path, $handler, array $middleware = [])
 * @method static void resource(string $name, string $controller, array $options = [])
 * @method static void apiResource(string $name, string $controller, array $options = [])
 * @method static void group(array $attributes, callable $callback)
 * @method static string route(string $name, array $parameters = [])
 * @method static \Plugs\Router\Route|null getRouteByName(string $name)
 * @method static array getRoutes()
 *
 * @see \Plugs\Router\Router
 */
class Route extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
