<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Plugs\Http\ResponseFactory;

/*
|--------------------------------------------------------------------------
| Route Response Type Examples
|--------------------------------------------------------------------------
|
| Your routes can now return different types of responses:
| - String (HTML)
| - Array (JSON)
| - ResponseInterface (full control)
| - null (204 No Content)
*/

return function($router) {
    // ✅ String response (automatically wrapped in HTML response)
    $router->get('/', function() {
        return '<h1>Welcome to Plugs Framework!</h1>';
    });

    // ✅ Array response (automatically converted to JSON)
    $router->get('/api/status', function() {
        return [
            'status' => 'success',
            'message' => 'API is running',
            'timestamp' => time()
        ];
    });

    // ✅ PSR-7 ResponseInterface (full control)
    $router->get('/custom', function() {
        return ResponseFactory::html('<h1>Custom Response</h1>', 200);
    });

    // ✅ JSON response with custom status
    $router->get('/api/error', function() {
        return ResponseFactory::json([
            'error' => 'Not authorized'
        ], 401);
    });

    // ✅ Redirect response
    $router->get('/old-page', function() {
        return ResponseFactory::redirect('/new-page', 301);
    });

    // ✅ Download response
    $router->get('/download', function() {
        $content = 'File content here';
        return ResponseFactory::download($content, 'file.txt');
    });

    // Named routes
    $router->get('/user', function() {
        return '<h1>User Page</h1>';
    })->name('user');

    $router->get('/contact', [HomeController::class, 'contact'])->name('contact');

    // Routes with parameters
    $router->get('/show/{id}', [HomeController::class, 'show']);
    $router->post('/show/{id}', [HomeController::class, 'post']);
    $router->delete('/show/{id}', [HomeController::class, 'delete']);

    // Parameter constraints
    $router->get('/user/{id}', function($request) {
        $id = $request->getAttribute('id');
        return [
            'user_id' => $id,
            'name' => 'John Doe'
        ];
    })->where('id', '[0-9]+');

    // Route groups
    $router->group(['prefix' => '/api', 'middleware' => []], function($router) {
        // All routes here will have /api prefix
        
        $router->get('/users', function() {
            return [
                ['id' => 1, 'name' => 'User 1'],
                ['id' => 2, 'name' => 'User 2']
            ];
        });

        $router->post('/users', function($request) {
            return [
                'message' => 'User created',
                'user' => ['id' => 3, 'name' => 'New User']
            ];
        });
    });
};