<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Routes File - Default Routes
|--------------------------------------------------------------------------
|
| This file is where you can define default framework routes such as
| debug tools, health checks, and other system endpoints.
*/

use Plugs\Facades\Route;
use Plugs\Http\Controllers\Debug\PerformanceController;


Route::group(['prefix' => 'debug'], function ($router) {
    $router->get('/performance', [PerformanceController::class, 'index']);
    $router->get('/performance/latest', [PerformanceController::class, 'latest']);
    $router->get('/performance/{id}', [PerformanceController::class, 'show']);
});

Route::post('/plugs/component/action', [\Plugs\View\ReactiveController::class, 'handle']);

Route::get('/reactive-test', function () {
    return view('reactive_test');
});
