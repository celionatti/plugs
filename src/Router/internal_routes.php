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

        // AI Profiler Enhancements
        $router->post('/analyze-request', [\Plugs\Debug\AIProfilerController::class, 'analyzeRequest'])->name('profiler.ai.request');
        $router->post('/analyze-sql', [\Plugs\Debug\AIProfilerController::class, 'analyzeSql'])->name('profiler.ai.sql');
    });
}

$router->post('/plugs/component/action', [\Plugs\View\ReactiveController::class, 'handle']);

/*
|--------------------------------------------------------------------------
| Framework Health & Metrics Routes
|--------------------------------------------------------------------------
|
| These routes use the `/_plugs/` prefix (with leading underscore) to avoid
| conflicts with user-defined routes in production projects.
|
*/
$router->group(['prefix' => '_plugs'], function () use ($router) {
    // Health checks (Kubernetes-compatible)
    $router->get('/health', [\Plugs\Http\Controllers\HealthController::class, 'index'])->name('plugs.health');
    $router->get('/health/detailed', [\Plugs\Http\Controllers\HealthController::class, 'detailed'])->name('plugs.health.detailed');
    $router->get('/health/liveness', [\Plugs\Http\Controllers\HealthController::class, 'liveness'])->name('plugs.health.liveness');
    $router->get('/health/readiness', [\Plugs\Http\Controllers\HealthController::class, 'readiness'])->name('plugs.health.readiness');
    $router->get('/health/dashboard', [\Plugs\Http\Controllers\HealthController::class, 'dashboard'])->name('plugs.health.dashboard');

    // Metrics (Prometheus-compatible)
    $router->get('/metrics', [\Plugs\Http\Controllers\MetricsController::class, 'prometheus'])->name('plugs.metrics');
    $router->get('/metrics/json', [\Plugs\Http\Controllers\MetricsController::class, 'json'])->name('plugs.metrics.json');

    // API Documentation (OpenAPI/Swagger)
    $router->get('/docs', [\Plugs\Http\Controllers\OpenApiController::class, 'ui'])->name('plugs.docs');
    $router->get('/docs/spec', [\Plugs\Http\Controllers\OpenApiController::class, 'spec'])->name('plugs.docs.spec');

    // Lazy Component Rendering
    $router->post('/component/render', [\Plugs\Http\Controllers\ComponentController::class, 'render'])->name('plugs.component.render');
});

// Media Routes
$router->post('/plugs/media/upload', [\Plugs\Http\Controllers\MediaController::class, 'upload'])->name('plugs.media.upload');

$router->get('/reactive-test', function () {
    return view('reactive_test');
});
