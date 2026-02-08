<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Internal Framework Routes
|--------------------------------------------------------------------------
|
| This file contains default framework routes such as debug tools,
| health checks, and other system endpoints. These are kept in the
| src directory to protect them from accidental user modification.
*/

/** @var \Plugs\Router\Router $router */

/*
|--------------------------------------------------------------------------
| Profiler Dashboard Routes (Development Only)
|--------------------------------------------------------------------------
|
*/
if (config('security.profiler.enabled', false)) {
    $router->group(['prefix' => 'plugs/profiler'], function () use ($router) {
        $router->get('/', [\Plugs\Debug\ProfilerController::class, 'index'])->name('profiler.index');
        $router->get('/latest', [\Plugs\Debug\ProfilerController::class, 'latest'])->name('profiler.latest');
        $router->post('/clear', [\Plugs\Debug\ProfilerController::class, 'clear'])->name('profiler.clear');
        $router->get('/{id}', [\Plugs\Debug\ProfilerController::class, 'show'])->name('profiler.show');
        $router->delete('/{id}', [\Plugs\Debug\ProfilerController::class, 'destroy'])->name('profiler.destroy');
    });
}

$router->post('/plugs/component/action', [\Plugs\View\ReactiveController::class, 'handle']);

$router->get('/plugs/up', [\Plugs\Http\Controllers\HealthController::class]);

$router->get('/reactive-test', function () {
    return view('reactive_test');
});
