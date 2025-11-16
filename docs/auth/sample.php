<?php

/*
|--------------------------------------------------------------------------
| Auth Configuration Example
|--------------------------------------------------------------------------
| Path: config/auth.php
*/

return [
    // User Model (optional - if not provided, Auth will work with any table)
    'user_model' => App\Models\User::class,
    
    // Database table
    'table' => 'users',
    'primary_key' => 'id',
    
    // Authentication columns (auto-detected if null)
    // The Auth class will scan your table and find these automatically
    'email_column' => null, // Will auto-detect: email, user_email, username, login
    'password_column' => null, // Will auto-detect: password, user_password, pass
    'remember_token_column' => null,
    'last_login_column' => null, // Will auto-detect: last_login, last_login_at
    
    // Password hashing
    'password_algo' => PASSWORD_BCRYPT,
    'password_cost' => 12,
    
    // Session
    'session_key' => 'auth_user_id',
    'remember_token_name' => 'remember_token',
    'remember_days' => 30,
    
    // OAuth Providers
    'oauth' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        ],
        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
        ],
        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        ],
        'discord' => [
            'client_id' => env('DISCORD_CLIENT_ID'),
            'client_secret' => env('DISCORD_CLIENT_SECRET'),
        ],
    ],
    
    // OAuth tables
    'oauth_table' => 'oauth_accounts',
    'remember_tokens_table' => 'remember_tokens',
    
    // Timestamps
    'use_timestamps' => true,
    'created_at_column' => 'created_at',
    'updated_at_column' => 'updated_at',
];

/*
|--------------------------------------------------------------------------
| User Model Example
|--------------------------------------------------------------------------
| Path: app/Models/User.php
*/

namespace App\Models;

use Plugs\Base\Model\PlugModel;

class User extends PlugModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $timestamps = true;
    
    // Mass assignment protection
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'bio',
        'status',
    ];
    
    // Hide sensitive fields
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    // Type casting
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'is_active' => 'boolean',
        'preferences' => 'json',
    ];
    
    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }
    
    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id', 'id');
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
    
    // Accessors
    public function getFullNameAttribute()
    {
        return $this->getAttribute('first_name') . ' ' . $this->getAttribute('last_name');
    }
    
    // Mutators
    public function setEmailAttribute($value)
    {
        return strtolower(trim($value));
    }
}

/*
|--------------------------------------------------------------------------
| Database Migrations
|--------------------------------------------------------------------------
*/

// Users table (flexible - works with any column structure)
/*
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    avatar VARCHAR(255) NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
*/

// OAuth accounts table
/*
CREATE TABLE oauth_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_id VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider_account (provider, provider_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
*/

// Remember tokens table
/*
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_token (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
*/

/*
|--------------------------------------------------------------------------
| Usage Examples
|--------------------------------------------------------------------------
*/

// Example 1: Basic Authentication
$auth = Auth::make();

// Register new user
$registered = $auth->register(
    'user@example.com',
    'password123',
    ['name' => 'John Doe']
);

// Login
if ($auth->login('user@example.com', 'password123', $remember = true)) {
    echo "Login successful!";
    $user = $auth->user(); // Returns PlugModel instance
    echo "Welcome, " . $user->name;
}

// Check authentication
if ($auth->check()) {
    $userId = $auth->id();
    $user = $auth->user();
    $userArray = $auth->userArray();
}

// Logout
$auth->logout();

// Example 2: Using with Custom User Model
$auth = Auth::make([
    'user_model' => User::class,
    'table' => 'users',
]);

// Now auth returns User model instances
$user = $auth->user(); // Instance of User model
$posts = $user->posts()->get(); // Access relationships

// Example 3: OAuth Authentication
$auth = Auth::make();

// Step 1: Redirect to OAuth provider
$redirectUrl = 'https://yoursite.com/auth/callback/google';
$oauthUrl = $auth->getOAuthUrl('google', $redirectUrl, ['email', 'profile']);
header("Location: $oauthUrl");

// Step 2: Handle callback
if (isset($_GET['code'], $_GET['state'])) {
    $success = $auth->handleOAuthCallback(
        $_GET['code'],
        $_GET['state'],
        $redirectUrl
    );
    
    if ($success) {
        echo "OAuth login successful!";
        $user = $auth->user();
    }
}

// Example 4: Using with any table structure
// Even if your table has different columns, Auth auto-detects them
$auth = Auth::make([
    'table' => 'members', // Table name: 'members'
    'primary_key' => 'member_id', // Primary key: 'member_id'
    // No need to specify email_column, password_column
    // Auth will auto-detect: email, user_email, username, etc.
]);

// Works automatically!
$auth->login('user@example.com', 'password');

// Example 5: Manual column mapping for non-standard tables
$auth = Auth::make([
    'table' => 'accounts',
    'primary_key' => 'account_id',
    'email_column' => 'account_email',
    'password_column' => 'account_password',
    'last_login_column' => 'logged_in_at',
]);

// Example 6: Without timestamps
$auth = Auth::make([
    'table' => 'legacy_users',
    'use_timestamps' => false,
]);

// Example 7: Remember Me functionality
if ($auth->login($email, $password, $remember = true)) {
    // User will be remembered for 30 days (configurable)
}

// Check remember token on subsequent visits
if ($auth->guest() && $auth->loginFromRemember()) {
    echo "Logged in from remember token";
}

// Example 8: Validation without login
if ($auth->validate($email, $password)) {
    echo "Credentials are valid";
    // But user is not logged in yet
}

// Example 9: Working with PlugModel features
$auth = Auth::make(['user_model' => User::class]);

if ($auth->check()) {
    $user = $auth->user();
    
    // Use PlugModel features
    $posts = $user->posts()->latest()->paginate(10);
    $profile = $user->profile()->first();
    
    // Update user
    $user->update(['last_seen' => date('Y-m-d H:i:s')]);
    
    // Access relationships with eager loading
    $userWithPosts = User::with('posts', 'profile')->find($auth->id());
}

// Example 10: Session Management
$session = $auth->getSession();
$session->flash('success', 'Login successful!');
$csrfToken = $session->token();

// Example 11: Middleware-like usage
function requireAuth() {
    $auth = Auth::make();
    
    if ($auth->guest()) {
        header('Location: /login');
        exit;
    }
    
    return $auth->user();
}

// Example 12: Schema inspection
$auth = Auth::make();
$schema = $auth->getTableSchema();
print_r($schema);
/*
Array (
    [all_columns] => Array (
        [0] => id
        [1] => name
        [2] => email
        [3] => password
        [4] => created_at
        [5] => updated_at
    )
    [has_timestamps] => true
    [email_column] => email
    [password_column] => password
)
*/

// Example 13: Multiple OAuth providers
$providers = ['google', 'github', 'facebook', 'discord'];

foreach ($providers as $provider) {
    $url = $auth->getOAuthUrl($provider, "https://yoursite.com/auth/{$provider}/callback");
    echo "<a href='{$url}'>Login with " . ucfirst($provider) . "</a><br>";
}

// Example 14: Advanced User Model with Auth
class User extends PlugModel
{
    protected $table = 'users';
    
    // Custom authentication check
    public function isAdmin(): bool
    {
        return $this->getAttribute('role') === 'admin';
    }
    
    // Check if user has permission
    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions()->pluck('name')->toArray());
    }
    
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }
}

// Usage
$auth = Auth::make(['user_model' => User::class]);
if ($auth->check()) {
    $user = $auth->user();
    
    if ($user->isAdmin()) {
        // Admin actions
    }
    
    if ($user->can('edit_posts')) {
        // Allow editing
    }
}

/*
|--------------------------------------------------------------------------
| API Usage Example
|--------------------------------------------------------------------------
*/

// RESTful API endpoint
header('Content-Type: application/json');

$auth = Auth::make();

// POST /api/register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/api/register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $success = $auth->register(
        $data['email'],
        $data['password'],
        ['name' => $data['name']]
    );
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'User registered']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}

// POST /api/login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/api/login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($auth->login($data['email'], $data['password'])) {
        echo json_encode([
            'success' => true,
            'user' => $auth->userArray(),
            'token' => $auth->getSession()->token()
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

// GET /api/user (requires authentication)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/api/user') {
    if ($auth->check()) {
        echo json_encode([
            'success' => true,
            'user' => $auth->userArray()
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    }
}