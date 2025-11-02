# Complete PlugModel Usage Examples - Every Feature

## 📚 Table of Contents

1. [Setup & Configuration](#setup)
2. [Basic CRUD Operations](#crud)
3. [Query Builder - All Methods](#query-builder)
4. [Relationships - All Types](#relationships)
5. [Validation](#validation)
6. [Observers & Events](#observers)
7. [Caching](#caching)
8. [Transactions](#transactions)
9. [Raw Queries](#raw-queries)
10. [Batch Operations](#batch-operations)
11. [Soft Deletes](#soft-deletes)
12. [Scopes](#scopes)
13. [Casting & Mutators](#casting)
14. [Advanced Features](#advanced)

---

## 🔧 <a name="setup"></a>1. Setup & Configuration

```php
<?php
require 'vendor/autoload.php';

use Plugs\Base\Model\PlugModel;

// ==================== DATABASE CONNECTION ====================

// MySQL
PlugModel::setConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
    'charset' => 'utf8mb4'
]);

// PostgreSQL
PlugModel::setConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'myapp',
    'username' => 'postgres',
    'password' => 'secret'
]);

// SQLite
PlugModel::setConnection([
    'driver' => 'sqlite',
    'database' => '/path/to/database.sqlite'
]);

// SQL Server
PlugModel::setConnection([
    'driver' => 'sqlsrv',
    'host' => 'localhost',
    'port' => 1433,
    'database' => 'myapp',
    'username' => 'sa',
    'password' => 'secret'
]);

// ==================== ENABLE FEATURES ====================

// Enable query logging
PlugModel::enableQueryLog();

// Enable caching (TTL in seconds)
PlugModel::enableCache(3600);

// ==================== DEFINE MODELS ====================

class User extends PlugModel
{
    protected $table = 'users'; // Optional, auto-detects as 'users'
    protected $primaryKey = 'id'; // Default
    
    // Mass assignment protection
    protected $fillable = ['name', 'email', 'password', 'age', 'role'];
    // OR
    protected $guarded = ['id', 'admin_only_field'];
    
    // Hide from array/JSON output
    protected $hidden = ['password', 'api_token'];
    
    // Auto-cast attributes
    protected $casts = [
        'age' => 'integer',
        'is_active' => 'boolean',
        'preferences' => 'json',
        'created_at' => 'datetime',
        'api_token' => 'encrypted'
    ];
    
    // Append computed attributes
    protected $appends = ['full_name'];
    
    // Enable timestamps (created_at, updated_at)
    protected $timestamps = true;
    
    // Enable soft deletes
    protected $softDelete = true;
    protected $deletedAtColumn = 'deleted_at';
    
    // Validation rules
    protected $rules = [
        'name' => 'required|min:3',
        'email' => 'required|email|unique:users,email',
        'age' => 'numeric|min:18'
    ];
    
    // Computed attribute
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // Mutator (set)
    public function setPasswordAttribute($value)
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }
    
    // Accessor (get)
    public function getEmailAttribute($value)
    {
        return strtolower($value);
    }
}

class Post extends PlugModel
{
    protected $fillable = ['title', 'content', 'user_id', 'published'];
    protected $casts = ['published' => 'boolean'];
}

class Comment extends PlugModel
{
    protected $fillable = ['post_id', 'user_id', 'content'];
}

class Role extends PlugModel
{
    protected $fillable = ['name'];
}

class Profile extends PlugModel
{
    protected $fillable = ['user_id', 'bio', 'avatar'];
}
```

---

## 📝 <a name="crud"></a>2. Basic CRUD Operations

```php
// ==================== CREATE ====================

// Method 1: New instance then save
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->password = 'secret123';
$user->save();

// Method 2: Mass assignment with create
$user = User::create([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
    'password' => 'secret123',
    'age' => 25
]);

// Method 3: Fill then save
$user = new User();
$user->fill([
    'name' => 'Bob Wilson',
    'email' => 'bob@example.com'
]);
$user->save();

// Method 4: Force fill (bypass guards)
$user = new User();
$user->forceFill([
    'id' => 999,
    'name' => 'Admin User',
    'admin_only_field' => 'secret'
]);
$user->save();

// Method 5: From JSON
$json = '{"name":"Alice","email":"alice@example.com"}';
$user = User::createFromJson($json);

// ==================== READ ====================

// Find by primary key
$user = User::find(1);
$user = User::find(999); // Returns null if not found

// Find or fail (throws exception)
$user = User::findOrFail(1);

// Find multiple by IDs
$users = User::findMany([1, 2, 3, 4, 5]);

// Get all records
$users = User::all();

// First record
$user = User::first();

// First or fail
$user = User::where('email', 'test@example.com')->firstOrFail();

// Get specific columns
$users = User::all();
foreach ($users as $user) {
    echo $user->name;
}

// ==================== UPDATE ====================

// Method 1: Find then update
$user = User::find(1);
$user->name = 'Updated Name';
$user->email = 'updated@example.com';
$user->save();

// Method 2: Mass update
$user = User::find(1);
$user->update([
    'name' => 'New Name',
    'age' => 30
]);

// Method 3: Update or create
$user = User::updateOrCreate(
    ['email' => 'john@example.com'], // Search criteria
    ['name' => 'John Updated', 'age' => 35] // Values to update/create
);

// Method 4: First or create
$user = User::firstOrCreate(
    ['email' => 'new@example.com'],
    ['name' => 'New User', 'age' => 25]
);

// Method 5: First or new (doesn't save)
$user = User::firstOrNew(
    ['email' => 'maybe@example.com'],
    ['name' => 'Maybe User']
);
// Check if exists
if ($user->exists) {
    echo "User exists";
} else {
    echo "User is new, not saved yet";
    $user->save();
}

// ==================== DELETE ====================

// Method 1: Find then delete
$user = User::find(1);
$user->delete(); // Soft delete if enabled

// Method 2: Force delete (permanent)
$user = User::find(1);
$user->forceDelete();

// Method 3: Delete without retrieving
User::where('inactive', 1)->get()->each(function($user) {
    $user->delete();
});

// ==================== CHECK STATE ====================

// Check if model exists in database
if ($user->exists) {
    echo "User exists in database";
}

// Check if model has been modified
if ($user->isDirty()) {
    echo "User has unsaved changes";
}

// Check specific attributes
if ($user->isDirty('email')) {
    echo "Email has changed";
}

// Check if clean
if ($user->isClean()) {
    echo "No changes";
}

// Get changes
$changes = $user->getChanges();
print_r($changes);

// Refresh from database
$user->refresh();
```

---

## 🔍 <a name="query-builder"></a>3. Query Builder - All Methods

```php
// ==================== WHERE CLAUSES ====================

// Basic where
$users = User::where('active', 1)->get();
$users = User::where('age', '>', 18)->get();
$users = User::where('status', '=', 'approved')->get();

// Multiple where (AND)
$users = User::where('active', 1)
    ->where('age', '>', 18)
    ->where('role', 'user')
    ->get();

// OR where
$users = User::where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where IN
$users = User::whereIn('id', [1, 2, 3, 4, 5])->get();
$users = User::whereIn('role', ['admin', 'editor'])->get();

// Where NOT IN
$users = User::whereNotIn('status', ['banned', 'suspended'])->get();

// Where NULL
$users = User::whereNull('deleted_at')->get();
$users = User::whereNull('email_verified_at')->get();

// Where NOT NULL
$users = User::whereNotNull('email_verified_at')->get();

// Where BETWEEN
$users = User::whereBetween('age', [18, 65])->get();
$users = User::whereBetween('salary', [30000, 80000])->get();

// Where NOT BETWEEN
$users = User::whereNotBetween('age', [0, 17])->get();

// Where DATE
$orders = Order::whereDate('created_at', '2025-01-15')->get();
$orders = Order::whereDate('created_at', date('Y-m-d'))->get();

// Where YEAR
$posts = Post::whereYear('published_at', 2025)->get();

// Where MONTH
$sales = Sale::whereMonth('created_at', 12)->get(); // December
$sales = Sale::whereMonth('created_at', date('m'))->get(); // Current month

// Where DAY
$birthdays = User::whereDay('birthdate', 25)->get();

// Where LIKE
$users = User::whereLike('name', '%john%')->get();
$users = User::whereLike('email', '%@gmail.com')->get();

// OR Where LIKE
$users = User::whereLike('name', '%john%')
    ->orWhereLike('name', '%jane%')
    ->get();

// Simple search (auto adds %)
$users = User::search('name', 'john')->get(); // LIKE '%john%'

// Exact search
$users = User::search('email', 'exact@example.com', true)->get();

// Multi-column search
$users = User::searchMultiple(['name', 'email', 'bio'], 'keyword')->get();

// ==================== SELECT ====================

// Select all columns (default)
$users = User::get();

// Select specific columns
$users = User::select('id', 'name', 'email')->get();

// Add more columns
$users = User::select('id', 'name')
    ->addSelect('email', 'created_at')
    ->get();

// Distinct
$roles = User::select('role')->distinct()->get();

// ==================== ORDERING ====================

// Order by ascending
$users = User::orderBy('name', 'ASC')->get();
$users = User::orderBy('created_at')->get(); // ASC default

// Order by descending
$users = User::orderBy('created_at', 'DESC')->get();
$users = User::orderByDesc('created_at')->get(); // Shorthand

// Latest (order by DESC)
$users = User::latest()->get(); // created_at DESC
$users = User::latest('updated_at')->get();

// Oldest (order by ASC)
$users = User::oldest()->get(); // created_at ASC

// Multiple order by
$users = User::orderBy('role', 'ASC')
    ->orderBy('name', 'ASC')
    ->get();

// Random order
$users = User::inRandomOrder()->get();

// Random records
$winners = User::random(5)->get(); // 5 random users
$featured = Post::where('published', 1)->random(3)->get();

// ==================== LIMITING & OFFSETTING ====================

// Limit
$users = User::limit(10)->get();
$users = User::take(10)->get(); // Alias

// Offset
$users = User::offset(20)->get();
$users = User::skip(20)->get(); // Alias

// Pagination
$users = User::limit(10)->offset(0)->get(); // Page 1
$users = User::limit(10)->offset(10)->get(); // Page 2

// ==================== AGGREGATES ====================

// Count
$count = User::count();
$activeCount = User::where('active', 1)->count();

// Sum
$total = Order::sum('amount');
$userTotal = Order::where('user_id', 1)->sum('amount');

// Average
$avgAge = User::avg('age');
$avgSalary = Employee::where('department', 'IT')->avg('salary');

// Maximum
$maxPrice = Product::max('price');
$topScore = Score::where('game_id', 5)->max('points');

// Minimum
$minPrice = Product::min('price');
$lowestScore = Score::min('points');

// ==================== EXISTS ====================

// Check if records exist
if (User::where('email', 'test@example.com')->exists()) {
    echo "User exists";
}

// Check if doesn't exist
if (User::where('username', 'taken')->doesntExist()) {
    echo "Username available";
}

// ==================== GROUPING ====================

// Group by
$stats = User::select('role', 'COUNT(*) as count')
    ->groupBy('role')
    ->get();

// Group by multiple columns
$stats = Order::select('user_id', 'status', 'COUNT(*) as count')
    ->groupBy('user_id', 'status')
    ->get();

// Having
$stats = User::select('role', 'COUNT(*) as count')
    ->groupBy('role')
    ->having('count', '>', 10)
    ->get();

// ==================== PAGINATION ====================

$result = User::where('active', 1)->paginate(15, 1); // 15 per page, page 1

echo "Total: " . $result['total'];
echo "Current Page: " . $result['current_page'];
echo "Total Pages: " . $result['total_pages'];

foreach ($result['data'] as $user) {
    echo $user->name;
}

// Page 2
$result = User::paginate(15, 2);

// ==================== CONDITIONAL QUERIES ====================

// When (if condition is true)
$role = 'admin';
$users = User::query()
    ->when($role, function($query) use ($role) {
        return $query->where('role', $role);
    })
    ->get();

// When with else
$users = User::query()
    ->when($role === 'admin', function($query) {
        return $query->where('is_admin', 1);
    }, function($query) {
        return $query->where('is_admin', 0);
    })
    ->get();

// Unless (if condition is false)
$showAll = false;
$users = User::query()
    ->unless($showAll, function($query) {
        return $query->limit(10);
    })
    ->get();

// Tap (debug/log without affecting query)
$users = User::where('active', 1)
    ->tap(function($query) {
        error_log("Building user query...");
    })
    ->get();

// ==================== CHUNKING ====================

// Process in chunks (memory efficient)
User::chunk(100, function($users, $page) {
    echo "Processing page {$page}\n";
    foreach ($users as $user) {
        // Process user
        echo $user->name . "\n";
    }
});

// Stop chunking
User::chunk(100, function($users) {
    foreach ($users as $user) {
        if ($user->id === 500) {
            return false; // Stop
        }
    }
});

// Each (process individually)
User::each(function($user) {
    $user->update(['processed' => true]);
}, 100); // Chunk size
```

---

## 🔗 <a name="relationships"></a>4. Relationships - All Types

```php
// ==================== ONE-TO-ONE (hasOne) ====================

class User extends PlugModel
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
        // return $this->hasOne(Profile::class, 'user_id', 'id'); // Custom keys
    }
}

class Profile extends PlugModel
{
    protected $fillable = ['user_id', 'bio', 'avatar'];
}

// Usage
$user = User::find(1);
$profile = $user->profile(); // Returns Profile or null

if ($profile) {
    echo $profile->bio;
}

// ==================== ONE-TO-ONE INVERSE (belongsTo) ====================

class Profile extends PlugModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
        // return $this->belongsTo(User::class, 'user_id', 'id'); // Custom keys
    }
}

// Usage
$profile = Profile::find(1);
$user = $profile->user();
echo $user->name;

// ==================== ONE-TO-MANY (hasMany) ====================

class User extends PlugModel
{
    public function posts()
    {
        return $this->hasMany(Post::class);
        // return $this->hasMany(Post::class, 'user_id', 'id'); // Custom keys
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts(); // Returns Collection

foreach ($posts as $post) {
    echo $post->title;
}

// Count
$postCount = $user->posts()->count();

// Filter relationship
$publishedPosts = $user->posts()->where('published', 1)->get();

// ==================== MANY-TO-ONE INVERSE (belongsTo) ====================

class Post extends PlugModel
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function author() // Alias
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Usage
$post = Post::find(1);
$author = $post->user();
echo $author->name;

// ==================== MANY-TO-MANY (belongsToMany) ====================

// Tables: users, roles, role_user (pivot table)
class User extends PlugModel
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
        // return $this->belongsToMany(
        //     Role::class,
        //     'role_user',           // Pivot table
        //     'user_id',             // Foreign key on pivot
        //     'role_id',             // Related key on pivot
        //     'id',                  // Local key
        //     'id'                   // Related key
        // );
    }
}

class Role extends PlugModel
{
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles(); // Collection of roles

foreach ($roles as $role) {
    echo $role->name;
}

// ==================== POLYMORPHIC ONE-TO-ONE (morphOne) ====================

// Setup morph map
PlugModel::morphMap([
    'post' => Post::class,
    'video' => Video::class
]);

class Post extends PlugModel
{
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

class Video extends PlugModel
{
    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

class Image extends PlugModel
{
    // Columns: id, url, imageable_id, imageable_type
    protected $fillable = ['url', 'imageable_id', 'imageable_type'];
}

// Usage
$post = Post::find(1);
$image = $post->image();
echo $image->url;

// ==================== POLYMORPHIC ONE-TO-MANY (morphMany) ====================

class Post extends PlugModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Video extends PlugModel
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Comment extends PlugModel
{
    // Columns: id, content, commentable_id, commentable_type
    protected $fillable = ['content', 'commentable_id', 'commentable_type'];
}

// Usage
$post = Post::find(1);
$comments = $post->comments(); // Collection

foreach ($comments as $comment) {
    echo $comment->content;
}

// ==================== POLYMORPHIC INVERSE (morphTo) ====================

class Comment extends PlugModel
{
    public function commentable()
    {
        return $this->morphTo();
    }
}

// Usage
$comment = Comment::find(1);
$parent = $comment->commentable(); // Returns Post or Video

if ($parent instanceof Post) {
    echo "Comment on post: " . $parent->title;
} else if ($parent instanceof Video) {
    echo "Comment on video: " . $parent->title;
}

// ==================== EAGER LOADING ====================

// N+1 Problem (BAD)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user()->name; // Queries user for each post!
}

// Eager loading (GOOD)
$posts = Post::with('user')->get();
foreach ($posts as $post) {
    echo $post->user()->name; // No additional query!
}

// Multiple relationships
$posts = Post::with('user', 'comments')->get();

// Nested relationships
$posts = Post::with('comments.user')->get();

// Load after retrieval
$posts = Post::all();
foreach ($posts as $post) {
    $post->load('user');
}
```

---

## ✅ <a name="validation"></a>5. Validation

```php
class User extends PlugModel
{
    protected $rules = [
        'name' => 'required|min:3|max:50',
        'email' => 'required|email|unique:users,email',
        'age' => 'required|numeric|min:18|max:120',
        'password' => 'required|min:8',
        'role' => 'required|in:user,admin,moderator',
        'phone' => 'numeric',
        'website' => 'required',
        'birthdate' => 'date',
        'username' => 'required|unique:users,username'
    ];
    
    protected $messages = [
        'name.required' => 'Please enter your name',
        'name.min' => 'Name must be at least 3 characters',
        'email.unique' => 'This email is already registered',
        'age.min' => 'You must be at least 18 years old',
        'role.in' => 'Invalid role selected'
    ];
}

// ==================== VALIDATE BEFORE SAVE ====================

$user = new User([
    'name' => 'Jo', // Too short
    'email' => 'invalid-email',
    'age' => 16, // Too young
    'password' => '123', // Too short
    'role' => 'superadmin', // Invalid
    'phone' => 'abc123', // Not numeric
    'birthdate' => 'invalid-date'
]);

if ($user->validate()) {
    $user->save();
    echo "User created successfully!";
} else {
    $errors = $user->getErrors();
    foreach ($errors as $field => $messages) {
        echo "$field: " . implode(', ', $messages) . "\n";
    }
}

// Output:
// name: The name must be at least 3.
// email: The email must be a valid email.
// age: You must be at least 18 years old
// password: The password must be at least 8.
// role: Invalid role selected
// phone: The phone must be numeric.
// birthdate: The birthdate must be a valid date.

// ==================== CHECK FOR ERRORS ====================

if ($user->hasErrors()) {
    $errors = $user->getErrors();
    print_r($errors);
}

// ==================== CUSTOM VALIDATION ====================

$user = new User($data);

if ($user->validate()) {
    // Additional custom validation
    if ($user->age < 21 && $user->role === 'admin') {
        echo "Admins must be at least 21";
    } else {
        $user->save();
    }
}

// ==================== RUNTIME VALIDATION ====================

$user = User::find(1);
$user->fill($_POST);

$customRules = [
    'email' => 'required|email',
    'name' => 'required|min:5'
];

$customMessages = [
    'email.required' => 'Email is mandatory',
    'name.min' => 'Name too short'
];

if ($user->validate($customRules, $customMessages)) {
    $user->save();
}
```

---

## 👀 <a name="observers"></a>6. Observers & Events

```php
// ==================== CREATE OBSERVER CLASS ====================

class UserObserver
{
    public function creating($user)
    {
        // Before insert
        echo "User is being created\n";
        $user->setAttribute('uuid', uniqid());
        $user->setAttribute('status', 'pending');
    }
    
    public function created($user)
    {
        // After insert
        echo "User created with ID: {$user->id}\n";
        
        // Send welcome email
        mail($user->email, 'Welcome!', 'Thanks for joining!');
        
        // Create default profile
        Profile::create(['user_id' => $user->id]);
        
        // Log activity
        error_log("New user registered: {$user->email}");
    }
    
    public function updating($user)
    {
        // Before update
        if ($user->isDirty('email')) {
            // Email is changing
            $old = $user->original['email'];
            $new = $user->email;
            echo "Email changing from {$old} to {$new}\n";
        }
    }
    
    public function updated($user)
    {
        // After update
        echo "User {$user->id} updated\n";
        
        // Clear cache
        PlugModel::flushCache();
    }
    
    public function saving($user)
    {
        // Before insert or update
        echo "User is being saved\n";
    }
    
    public function saved($user)
    {
        // After insert or update
        echo "User saved successfully\n";
    }
    
    public function deleting($user)
    {
        // Before delete
        echo "User {$user->id} is being deleted\n";
        
        // Prevent deletion
        if ($user->role === 'admin') {
            echo "Cannot delete admin user!\n";
            return false; // Stops deletion
        }
    }
    
    public function deleted($user)
    {
        // After delete
        echo "User {$user->id} deleted\n";
        
        // Clean up related data
        $user->posts()->delete();
        $user->comments()->delete();
    }
    
    public function restoring($user)
    {
        // Before restore (soft delete)
        echo "Restoring user {$user->id}\n";
    }
    
    public function restored($user)
    {
        // After restore
        echo "User {$user->id} restored\n";
    }
}

// ==================== REGISTER OBSERVER ====================

User::observe(new UserObserver());

// ==================== NOW ALL EVENTS FIRE AUTOMATICALLY ====================

$user = User::create(['name' => 'John', 'email' => 'john@example.com']);
// Fires: creating -> created -> saving -> saved

$user->update(['name' => 'John Updated']);
// Fires: updating -> updated -> saving -> saved

$user->delete();
// Fires: deleting -> deleted

// ==================== MODEL EVENTS (Alternative) ====================

class User extends PlugModel
{
    protected function creating()
    {
        echo "Creating user\n";
    }
    
    protected function created()
    {
        echo "User created\n";
    }
    
    protected function updating()
    {
        if ($this->isDirty('password')) {
            // Hash password
            $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        }
    }
    
    protected function deleting()
    {
        // Prevent deletion of admin
        if ($this->role === 'admin') {
            return false;
        }
    }
}
```

---

## 💾 <a name="caching"></a>7. Caching

```php
// ==================== ENABLE CACHING ====================

// Enable globally with TTL (seconds)
PlugModel::enableCache(3600); // 1 hour

// ==================== CACHE QUERIES ====================

// Cache with default TTL
$users = User::where('active', 1)->remember()->get();

// Cache with custom TTL
$users = User::where('active', 1)->remember(600)->get(); // 10 minutes

// Expensive aggregates
$stats = User::select('role', 'COUNT(*) as count')
    ->groupBy('role')
    ->remember(1800)
    ->get();

// ==================== REAL-WORLD EXAMPLES ====================

// Dashboard statistics (cache for 5 minutes)
$todayOrders = Order::whereDate('created_at', date('Y-m-d'))
    ->remember(300)
    ->count();

$revenue = Order::where('status', 'completed')
    ->remember(300)
    ->sum('amount');

// Product listings (cache for 1 hour)
$products = Product::where('active', 1)
    ->orderBy('featured', 'DESC')
    ->remember(3600)
    ->get();

// User settings (cache for 24 hours)
$settings = Setting::remember(86400)->get();

// ==================== CLEAR CACHE ====================

// Clear all cache
PlugModel::flushCache();

// Clear cache after changes
