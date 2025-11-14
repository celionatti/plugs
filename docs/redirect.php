<?php

/*
|--------------------------------------------------------------------------
| Redirect Usage Examples
|--------------------------------------------------------------------------
|
| Comprehensive examples of how to use the chainable redirect methods
*/

// ============================================================
// BASIC REDIRECTS
// ============================================================

// Simple redirect
redirect('/dashboard');

// Redirect with custom status code
redirect('/login', 301);

// Redirect to named route
redirectTo('user.profile', ['id' => 123]);

// Redirect back to previous URL
redirectBack();
redirectBack('/home'); // with fallback

// ============================================================
// CHAINABLE REDIRECTS WITH FLASH DATA
// ============================================================

// Redirect with success message
redirect('/dashboard')->withSuccess('Profile updated successfully!');

// Redirect with error message
redirect('/profile')->withError('Failed to update profile');

// Redirect with custom flash data
redirect('/dashboard')
    ->with('message', 'Welcome back!')
    ->with('user_name', 'John Doe');

// Multiple flash values at once
redirect('/dashboard')->with([
    'message' => 'Data saved!',
    'timestamp' => time(),
    'status' => 'success'
]);

// ============================================================
// FORM HANDLING
// ============================================================

// Redirect with validation errors
redirect('/register')
    ->withErrors([
        'email' => 'Email is required',
        'password' => 'Password must be at least 8 characters'
    ])
    ->withInput(); // Preserve form input

// Redirect with old input (without errors)
redirect('/profile/edit')
    ->withInput($_POST)
    ->withInfo('Please review your changes');

// Named error bags
redirect('/checkout')
    ->withErrors(['card' => 'Invalid card number'], 'payment')
    ->withErrors(['address' => 'Address required'], 'shipping');

// ============================================================
// ADVANCED CHAINING
// ============================================================

// Combine multiple features
redirect('/dashboard')
    ->withSuccess('Order placed successfully!')
    ->with('order_id', 12345)
    ->with('total', 99.99)
    ->withFragment('order-details')
    ->withQuery(['ref' => 'email']);

// Add custom headers
redirect('/api/callback')
    ->withHeader('X-Custom-Header', 'value')
    ->withHeaders([
        'X-API-Key' => 'abc123',
        'X-Request-ID' => uniqid()
    ]);

// Set cookies with redirect
redirect('/welcome')
    ->withCookie('visited', 'true', time() + 3600)
    ->withSuccess('Thanks for visiting!');

// URL with query params and fragment
redirect('/search')
    ->withQuery(['q' => 'php', 'type' => 'tutorial'])
    ->withFragment('results');

// ============================================================
// CONTROLLER EXAMPLES
// ============================================================

class UserController
{
    public function store()
    {
        // Validate input
        $errors = $this->validate($_POST);
        
        if (!empty($errors)) {
            return redirect('/users/create')
                ->withErrors($errors)
                ->withInput();
        }
        
        // Create user
        $user = User::create($_POST);
        
        // Redirect with success
        return redirectTo('users.show', ['id' => $user->id])
            ->withSuccess('User created successfully!');
    }
    
    public function update($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return redirect('/users')
                ->withError('User not found');
        }
        
        $user->update($_POST);
        
        return redirectBack()
            ->withSuccess('Profile updated!')
            ->with('updated_at', date('Y-m-d H:i:s'));
    }
    
    public function destroy($id)
    {
        User::destroy($id);
        
        return redirect('/users')
            ->withWarning('User has been deleted')
            ->with('deleted_id', $id);
    }
    
    public function login()
    {
        $credentials = ['email' => $_POST['email'], 'password' => $_POST['password']];
        
        if (!Auth::attempt($credentials)) {
            return redirectBack()
                ->withError('Invalid credentials')
                ->withInput(['email' => $_POST['email']]); // Don't flash password
        }
        
        return redirect('/dashboard')
            ->withSuccess('Welcome back!')
            ->with('last_login', time());
    }
}

// ============================================================
// VIEW USAGE (Displaying Flash Data)
// ============================================================
?>

<!-- Display success message -->
<?php if (hasFlash('success')): ?>
    <div class="alert alert-success">
        <?= flash('success') ?>
    </div>
<?php endif; ?>

<!-- Display error message -->
<?php if (hasFlash('error')): ?>
    <div class="alert alert-error">
        <?= flash('error') ?>
    </div>
<?php endif; ?>

<!-- Display validation errors -->
<?php if (hasErrors()): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach (errors() as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Display first error only -->
<?php if (hasErrors()): ?>
    <div class="alert alert-danger">
        <?= firstError() ?>
    </div>
<?php endif; ?>

<!-- Form with old input -->
<form method="POST" action="/register">
    <input type="email" 
           name="email" 
           value="<?= old('email') ?>" 
           placeholder="Email">
    
    <input type="text" 
           name="profile[name]" 
           value="<?= old('profile.name') ?>" 
           placeholder="Name">
    
    <button type="submit">Register</button>
</form>

<!-- Named error bags -->
<?php if (hasErrors('payment')): ?>
    <div class="payment-errors">
        <?php foreach (errors('payment') as $error): ?>
            <p><?= $error ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Custom flash data -->
<?php if (hasFlash('order_id')): ?>
    <p>Order #<?= flash('order_id') ?> confirmed!</p>
<?php endif; ?>

<?php
// ============================================================
// SPECIAL CASES
// ============================================================

// Keep flash data for another request
if (someCondition()) {
    keepFlash(['success', 'order_id']);
    // or reflash all
    reflash();
}

// Conditional redirect in middleware
function checkAuth($request, $next) {
    if (!isLoggedIn()) {
        return redirect('/login')
            ->withError('Please login to continue')
            ->with('intended', currentUrl());
    }
    
    return $next($request);
}

// API-style redirect (return PSR-7 response)
function apiRedirect() {
    return redirect('/api/callback')
        ->with('token', generateToken())
        ->toResponse(); // Returns PSR-7 ResponseInterface
}

// Manual send (if not using auto-destruct)
$redirect = redirect('/dashboard')->withSuccess('Done!');
// ... do other stuff ...
$redirect->send(); // Manually trigger redirect

// ============================================================
// ROUTE HELPERS
// ============================================================

// Check current route
if (routeIs('user.*')) {
    // Current route name starts with 'user.'
}

if (routeIs(['home', 'dashboard'])) {
    // Current route is either 'home' or 'dashboard'
}

// Get route params
$userId = routeParams('id'); // Get single param
$allParams = routeParams(); // Get all params

// Method checking
if (isPost()) {
    // Handle POST request
}

if (isMethod(['PUT', 'PATCH'])) {
    // Handle PUT or PATCH
}

// Request type checking
if (isAjax()) {
    return jsonResponse(['success' => true]);
}

if (wantsJson()) {
    return jsonResponse($data);
} else {
    return redirect('/dashboard')->withSuccess('Saved!');
}
?>