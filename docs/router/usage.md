# Plugs Framework Routing

## Basic routes

$router->get('/', 'HomeController@index');
$router->post('/login', 'AuthController@login');

## METHOD SPOOFING EXAMPLE

### In your HTML form

<form method="POST" action="/users/1">
     @csrf
    @method('DELETE')
     <button>Delete User</button>
</form>

$router->delete('/users/{id}', 'UserController@destroy')->name('users.destroy');

// The form will be sent as POST, but router will recognize it as DELETE
// via the _method field

## Route with constraints

$router->get('/users/{id}', 'UserController@show')
    ->whereNumber('id')
    ->name('users.show');

// Optional parameters
$router->get('/posts/{slug}/{page?}', 'PostController@show')
    ->whereSlug('slug')
    ->whereNumber('page')
    ->defaults(['page' => 1]);

// Route groups
$router->group(['prefix' => 'api', 'middleware' => 'api'], function ($router) {
    $router->get('/users', 'ApiController@users');
    $router->post('/users', 'ApiController@createUser');
});

// Resource routes
$router->resource('posts', 'PostController');
// Creates: index, create, store, show, edit, update, destroy

// API resource (without create/edit)
$router->apiResource('comments', 'CommentController');

// Named routes with URL generation
$router->get('/profile/{username}', 'ProfileController@show')
    ->name('profile.show');

// Generate URL: $router->route('profile.show', ['username' => 'john'])
// Result: /profile/john

// Middleware
$router->get('/dashboard', 'DashboardController@index')
    ->middleware(['auth', 'verified']);

// Domain routing
$router->group(['domain' => 'api.example.com'], function ($router) {
    $router->get('/', 'ApiController@index');
});

// HTTPS only
$router->get('/secure', 'SecureController@index')->secure();

// Redirect routes
$router->redirect('/old-page', '/new-page', 301);
$router->permanentRedirect('/another-old', '/another-new');

// View routes (no controller needed)
$router->view('/terms', 'legal.terms');
$router->view('/privacy', 'legal.privacy', ['updated' => '2024']);

// Rate limiting
$router->get('/api/data', 'ApiController@data')
    ->throttle(60, 1); // 60 requests per minute

// Caching
$router->get('/expensive', 'DataController@expensive')
    ->cache(3600); // Cache for 1 hour

// Proxy routes
$router->get('/proxy/{path}', function () {})->proxy('https://api.external.com/{path}');

// Fallback route (404)
$router->fallback(function () {
    return view('errors.404');
});

// Global middleware
$router->middleware(['web', 'csrf']);

// Custom patterns
$router->pattern('id', '[0-9]+');
$router->pattern('username', '[a-zA-Z0-9_-]+');

// Dispatch
$response = $router->dispatch($request);

if ($response === null) {
    // No route matched - handle 404
    $response = ResponseFactory::html('404 Not Found', 404);
}

// Send response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

## File 4: Method Spoofing Guide

```markdown
# Method Spoofing in Forms

## Overview
Laravel-style method spoofing allows you to use PUT, PATCH, and DELETE methods in HTML forms, which natively only support GET and POST.

## How It Works

### 1. Using @method directive in views
```php
<form method="POST" action="/users/1">
    @csrf
    @method('PUT')
    <input type="text" name="name" value="John">
    <button type="submit">Update User</button>
</form>
```

Outputs:

```html
<form method="POST" action="/users/1">
    <input type="hidden" name="_token" value="csrf_token_here">
    <input type="hidden" name="_method" value="PUT">
    <input type="text" name="name" value="John">
    <button type="submit">Update User</button>
</form>
```

### 2. Manual hidden field

```html
<form method="POST" action="/users/1">
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit">Delete User</button>
</form>
```

### 3. Via query string

```html
<form method="POST" action="/users/1?_method=PUT">
    <button type="submit">Update</button>
</form>
```

### 4. Via HTTP header (AJAX)

```javascript
fetch('/users/1', {
    method: 'POST',
    headers: {
        'X-HTTP-Method-Override': 'DELETE',
        'Content-Type': 'application/json'
    }
})
```

## Supported Methods

Only these methods can be spoofed (security feature):

- PUT
- PATCH
- DELETE

## Route Definition

```php
// Define routes normally
$router->put('/users/{id}', 'UserController@update');
$router->patch('/users/{id}', 'UserController@update');
$router->delete('/users/{id}', 'UserController@destroy');

// Or use resource routes
$router->resource('users', 'UserController');
```

## Complete Example

### routes.php

```php
$router->resource('articles', 'ArticleController');
```

### article_form.plug.php

```html
<form method="POST" action="/articles/{{ $article->id }}">
    @csrf
    @method('PUT')
    
    <input type="text" name="title" value="{{ $article->title }}">
    <textarea name="content">{{ $article->content }}</textarea>
    
    <button type="submit">Update Article</button>
</form>

<form method="POST" action="/articles/{{ $article->id }}">
    @csrf
    @method('DELETE')
    
    <button type="submit" onclick="return confirm('Delete?')">
        Delete Article
    </button>
</form>
```

### ArticleController.php

```php
class ArticleController
{
    public function update(ServerRequestInterface $request, int $id)
    {
        // Router automatically recognizes this as PUT request
        // even though the form sent POST
        
        $data = $request->getParsedBody();
        // Update article...
        
        return redirect()->route('articles.show', ['id' => $id]);
    }
    
    public function destroy(ServerRequestInterface $request, int $id)
    {
        // Recognized as DELETE request
        
        // Delete article...
        
        return redirect()->route('articles.index');
    }
}
```

## Security Notes

1. **CSRF Protection**: Always use @csrf with forms
2. **Limited Methods**: Only PUT, PATCH, DELETE can be spoofed
3. **POST Only**: Method spoofing only works with POST requests
4. **Validation**: The _method field is validated before use

## Testing Method Spoofing

```php
// Test with cURL
curl -X POST http://example.com/users/1 \
  -d "_method=PUT" \
  -d "name=John Doe"

// Test with Postman
// Method: POST
// URL: http://example.com/users/1
// Body (form-data):
//   _method: DELETE

## Summary of Improvements

### Key Features Added:

1. ✅ **Method Spoofing Support** - Full Laravel-style _method field support
2. ✅ **Enhanced Route Groups** - Added domain and where constraints to groups
3. ✅ **Global Middleware** - Apply middleware to all routes
4. ✅ **Better Resource Routes** - Customizable parameters
5. ✅ **Redirect Helpers** - Quick redirect route creation
6. ✅ **View Routes** - Direct view rendering without controllers
7. ✅ **Fallback Routes** - Clean 404 handling
8. ✅ **Route Macros** - Extend router with custom methods
9. ✅ **Pattern Registration** - Reusable regex patterns
10. ✅ **Improved Security** - Better HTTPS detection, domain validation
11. ✅ **Production Ready** - Proper error handling, caching, optimization
12. ✅ **Better DX** - More helper methods, debugging tools

The router now fully supports method spoofing!
