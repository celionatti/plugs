# Batch Handling

// Simple GET request
$response = http_get('<https://api.example.com/users>');

// POST with authentication
$response = http('<https://api.example.com>')
    ->bearerToken('your-token')
    ->post('/users', ['name' => 'John']);

// Batch requests
$responses = batch()
    ->get('<https://api.example.com/users>', [], 'users')
    ->get('<https://api.example.com/posts>', [], 'posts')
    ->send();

$users = $responses->get('users')->json();
$posts = $responses->get('posts')->json();
