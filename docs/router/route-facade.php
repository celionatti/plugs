<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Plugs\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Using the Route facade for clean, static route definitions.
| No need to receive $router parameter anymore!
*/

// Basic closure route
Route::get('/', function() {
    return '<h1>Welcome to Plugs Framework!</h1>';
});

// Controller routes
Route::get('/home', [HomeController::class, 'home']);

Route::get('/user', function() {
    return '<h1>User Page</h1>';
})->name('user');

Route::get('/contact', [HomeController::class, 'contact'])->name('contact');

// String syntax for controller
Route::get('/about', 'App\Controllers\HomeController@about');

// Routes with parameters
Route::get('/show/{id}', [HomeController::class, 'show']);
Route::post('/show/{id}', [HomeController::class, 'post']);
Route::delete('/show/{id}', [HomeController::class, 'delete']);

// Optional parameters
Route::get('/posts/{id?}', [HomeController::class, 'posts']);

// Parameter constraints
Route::get('/user/{id}', [HomeController::class, 'user'])
    ->where('id', '[0-9]+')
    ->name('user.show');

// Multiple constraints
Route::get('/profile/{username}/{tab?}', [HomeController::class, 'profile'])
    ->where([
        'username' => '[a-zA-Z0-9_]+',
        'tab' => 'posts|followers|following'
    ])
    ->name('profile');

// Route with middleware
Route::get('/dashboard', [HomeController::class, 'dashboard'])
    ->middleware(['auth'])
    ->name('dashboard');

// Multiple HTTP methods
Route::match(['GET', 'POST'], '/form', [HomeController::class, 'form'])
    ->name('form');

// All HTTP methods
Route::any('/webhook', [HomeController::class, 'webhook']);

/*
|--------------------------------------------------------------------------
| API Routes Group
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => '/api'], function() {
    
    // GET /api/users
    Route::get('/users', function() {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ];
    })->name('api.users');

    // POST /api/users
    Route::post('/users', function($request) {
        $data = $request->getParsedBody();
        return [
            'message' => 'User created',
            'user' => $data
        ];
    })->name('api.users.create');

    // GET /api/users/{id}
    Route::get('/users/{id}', function($request) {
        $id = $request->getAttribute('id');
        return [
            'id' => $id,
            'name' => "User {$id}"
        ];
    })->where('id', '[0-9]+')->name('api.users.show');
});

/*
|--------------------------------------------------------------------------
| Admin Routes Group
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function() {
    
    Route::get('/dashboard', [HomeController::class, 'adminDashboard'])
        ->name('admin.dashboard');
    
    Route::get('/users', function() {
        return '<h1>Admin - User Management</h1>';
    })->name('admin.users');
    
    // Nested group
    Route::group(['prefix' => '/reports'], function() {
        Route::get('/sales', function() {
            return ['report' => 'sales'];
        })->name('admin.reports.sales');
    });
});

/*
|--------------------------------------------------------------------------
| Test Named Routes
|--------------------------------------------------------------------------
*/
Route::get('/test-routes', function() {
    return [
        'user' => route('user'),
        'contact' => route('contact'),
        'user.show' => route('user.show', ['id' => 123]),
        'profile' => route('profile', ['username' => 'john']),
    ];
})->name('test.routes');