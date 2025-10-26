# Using Http Proxy and Fetch

// Proxy route
$router->get('/proxy/users', function() {})
    ->proxy('https://api.example.com/users', [
        'token' => 'your-token',
        'timeout' => 10
    ]);

// Fetch data before handling
$router->get('/dashboard', function($request) {
    $data = $request->getAttribute('user_data');
    return ['dashboard' => $data];
})->fetch('https://api.example.com/user/current', 'user_data');