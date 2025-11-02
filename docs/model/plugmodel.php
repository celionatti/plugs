<?php

/**
 * =====================================================
 * PLUGMODEL - COMPLETE USAGE GUIDE
 * Laravel Eloquent-like ORM for Plugs Framework
 * =====================================================
 */

// ============================================
// 1. SETUP & CONFIGURATION
// ============================================

use Plugs\Base\Model\PlugModel;

// Set database connection (do this once in your bootstrap)
PlugModel::setConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'your_database',
    'username' => 'root',
    'password' => 'password',
    'charset' => 'utf8mb4',
    'options' => [
        // Additional PDO options (optional)
    ]
]);

// ============================================
// 2. DEFINING MODELS
// ============================================

class User extends PlugModel
{
    protected $table = 'users'; // Optional, auto-detected as 'users'
    protected $primaryKey = 'id'; // Default is 'id'
    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = [
        'id' => 'int',
        'is_active' => 'boolean',
        'settings' => 'json',
        'created_at' => 'datetime'
    ];
    protected $timestamps = true; // Auto manage created_at & updated_at
    protected $softDelete = false; // Enable soft deletes

    // Mutators (setters)
    protected function setPasswordAttribute($value)
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    // Accessors (getters)
    protected function getFullNameAttribute($value)
    {
        return ucfirst($this->name);
    }

    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    // Model Events
    protected function creating()
    {
        // Runs before creating new record
        $this->setAttribute('uuid', uniqid());
    }

    protected function created()
    {
        // Runs after record is created
        // Send welcome email, etc.
    }
}

class Post extends PlugModel
{
    protected $fillable = ['title', 'content', 'user_id', 'status'];
    protected $softDelete = true; // Enable soft deletes

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}

class Profile extends PlugModel
{
    protected $fillable = ['user_id', 'bio', 'avatar', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// ============================================
// 3. BASIC CRUD OPERATIONS
// ============================================

// CREATE
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// Alternative create
$user = new User();
$user->name = 'Jane Doe';
$user->email = 'jane@example.com';
$user->save();

// Fill and save
$user = new User();
$user->fill(['name' => 'Bob', 'email' => 'bob@example.com']);
$user->save();

// READ
$user = User::find(1); // Find by ID
$user = User::findOrFail(1); // Find or throw exception
$users = User::findMany([1, 2, 3]); // Find multiple IDs

// Get all records
$allUsers = User::all();

// Get first record
$firstUser = User::first();
$firstUser = User::firstOrFail(); // Or throw exception

// UPDATE
$user = User::find(1);
$user->name = 'Updated Name';
$user->save();

// Alternative update
$user->update(['name' => 'New Name', 'email' => 'new@example.com']);

// Mass update
User::where('status', 'inactive')
    ->update(['status' => 'pending']); // Note: Use raw query for mass updates

// DELETE
$user = User::find(1);
$user->delete();

// Direct delete
User::find(1)->delete();

// ============================================
// 4. QUERY BUILDER (Static & Instance)
// ============================================

// WHERE CLAUSES
User::where('status', 'active')->get();
User::where('age', '>', 18)->get();
User::where('role', '!=', 'admin')->get();

// Multiple conditions
User::where('status', 'active')
    ->where('age', '>', 18)
    ->get();

// OR WHERE
User::where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// WHERE IN
User::whereIn('id', [1, 2, 3, 4])->get();
User::whereNotIn('status', ['banned', 'deleted'])->get();

// WHERE NULL
User::whereNull('deleted_at')->get();
User::whereNotNull('email_verified_at')->get();

// ORDERING
User::orderBy('created_at', 'DESC')->get();
User::orderByDesc('id')->get();
User::latest()->get(); // Order by created_at DESC
User::oldest()->get(); // Order by created_at ASC
User::latest('updated_at')->get(); // Order by custom column

// LIMIT & OFFSET
User::limit(10)->get();
User::take(10)->get(); // Alias for limit
User::offset(20)->get();
User::skip(20)->get(); // Alias for offset

// Pagination
User::limit(10)->offset(0)->get(); // First page
User::limit(10)->offset(10)->get(); // Second page

// COMBINING QUERIES
$users = User::where('status', 'active')
    ->where('age', '>', 18)
    ->orderBy('name')
    ->limit(50)
    ->get();

// COUNT
$count = User::where('status', 'active')->count();
$total = User::count();

// EXISTS
if (User::where('email', 'john@example.com')->exists()) {
    echo "Email already exists";
}

if (User::where('role', 'superadmin')->doesntExist()) {
    echo "No superadmin found";
}

// AGGREGATE FUNCTIONS
$maxAge = User::max('age');
$minAge = User::min('age');
$totalSalary = User::sum('salary');
$avgAge = User::avg('age');

// ============================================
// 5. WORKING WITH COLLECTIONS
// ============================================

$users = User::all();

// Check if empty
if ($users->isEmpty()) {
    echo "No users found";
}

if ($users->isNotEmpty()) {
    echo "Users found: " . $users->count();
}

// Get first/last
$firstUser = $users->first();
$lastUser = $users->last();

// Map over collection
$names = $users->map(function($user) {
    return $user->name;
});

// Filter collection
$activeUsers = $users->filter(function($user) {
    return $user->status === 'active';
});

// Pluck specific values
$emails = $users->pluck('email');
$ids = $users->pluck('id');

// Get only specific attributes
$userData = $users->only(['id', 'name', 'email']);

// Find in collection
$admin = $users->firstWhere('role', 'admin');
$admins = $users->where('role', 'admin');

// Check if contains
if ($users->contains('email', 'john@example.com')) {
    echo "User exists";
}

// Sum, Average
$totalAge = $users->sum('age');
$averageAge = $users->avg('age');

// Group by
$usersByRole = $users->groupBy('role');

// Sort collection
$sortedUsers = $users->sortBy('name');
$sortedDesc = $users->sortBy('created_at', true);

// Take/Skip
$firstTen = $users->take(10);
$skipFive = $users->skip(5);

// Chunk
$chunks = $users->chunk(10); // Split into chunks of 10

// Unique
$uniqueEmails = $users->pluck('email')->unique();

// Each - iterate
$users->each(function($user) {
    echo $user->name . "\n";
});

// Convert to array/JSON
$array = $users->toArray();
$json = $users->toJson();

// ============================================
// 6. PAGINATION
// ============================================

$result = User::where('status', 'active')
    ->latest()
    ->paginate(15, 1); // 15 per page, page 1

// Access pagination data
$users = $result['data']; // Collection of users
$currentPage = $result['current_page'];
$totalPages = $result['total_pages'];
$total = $result['total'];
$perPage = $result['per_page'];
$from = $result['from'];
$to = $result['to'];
$nextPageUrl = $result['next_page_url'];
$prevPageUrl = $result['prev_page_url'];

// Iterate through paginated results
foreach ($result['data'] as $user) {
    echo $user->name;
}

// ============================================
// 7. RELATIONSHIPS
// ============================================

// ONE TO ONE
$user = User::find(1);
$profile = $user->profile(); // Get user's profile

// ONE TO MANY
$posts = $user->posts(); // Get all user's posts

// BELONGS TO
$post = Post::find(1);
$author = $post->author(); // Get post's author

// MANY TO MANY
$roles = $user->roles(); // Get user's roles

// EAGER LOADING
$users = User::with('posts')->get();
$users = User::with(['posts', 'profile'])->get();

foreach ($users as $user) {
    // Posts are already loaded, no extra query
    foreach ($user->posts() as $post) {
        echo $post->title;
    }
}

// LAZY LOADING
$user = User::find(1);
$user->load('posts'); // Load posts after fetching user

// ============================================
// 8. SOFT DELETES
// ============================================

// Enable in model: protected $softDelete = true;

$post = Post::find(1);
$post->delete(); // Soft delete (sets deleted_at)

// Force delete (permanent)
$post->forceDelete();

// Restore soft deleted
$post = Post::withTrashed()->find(1);
$post->restore();

// Query with soft deleted
$allPosts = Post::withTrashed()->get();
$trashedOnly = Post::onlyTrashed()->get();

// Check if trashed
if ($post->trashed()) {
    echo "Post is deleted";
}

// ============================================
// 9. CUSTOM SCOPES
// ============================================

// Static scope calls
$activeUsers = User::active()->get();
$admins = User::admins()->get();
$activeAdmins = User::active()->admins()->get();

// With parameters
class Post extends PlugModel
{
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}

$publishedPosts = Post::status('published')->get();
$draftPosts = Post::status('draft')->get();

// ============================================
// 10. ADVANCED QUERIES
// ============================================

// Update or Create
$user = User::updateOrCreate(
    ['email' => 'john@example.com'], // Search criteria
    ['name' => 'John Doe', 'status' => 'active'] // Values to update/create
);

// First or Create
$user = User::firstOrCreate(
    ['email' => 'jane@example.com'],
    ['name' => 'Jane Doe']
);

// First or New (doesn't save)
$user = User::firstOrNew(
    ['email' => 'bob@example.com'],
    ['name' => 'Bob Smith']
);
// Manually save if needed
$user->save();

// Refresh from database
$user->refresh(); // Reload from DB

// Check if dirty (has unsaved changes)
$user->name = 'New Name';
if ($user->isDirty()) {
    echo "User has unsaved changes";
}

if ($user->isDirty('name')) {
    echo "Name has changed";
}

if ($user->isClean()) {
    echo "No changes";
}

// Get changes
$changes = $user->getChanges();

// ============================================
// 11. ATTRIBUTE CASTING & MANIPULATION
// ============================================

// JSON casting
class User extends PlugModel
{
    protected $casts = [
        'settings' => 'json',
        'is_admin' => 'boolean'
    ];
}

$user = User::find(1);
$user->settings = ['theme' => 'dark', 'lang' => 'en'];
$user->save();

// Retrieved as array automatically
$theme = $user->settings['theme'];

// Hide attributes
$user = User::find(1);
$publicData = $user->makeHidden('password')->toArray();

// Show hidden attributes
$allData = $user->makeVisible('password')->toArray();

// Get only specific attributes
$basic = $user->only(['id', 'name', 'email']);

// Get all except
$withoutSensitive = $user->except(['password', 'token']);

// ============================================
// 12. PRACTICAL EXAMPLES
// ============================================

// User authentication
function authenticate($email, $password)
{
    $user = User::where('email', $email)->first();
    
    if ($user && password_verify($password, $user->password)) {
        return $user;
    }
    
    return null;
}

// Blog post listing with author
$posts = Post::published()
    ->with('author')
    ->latest()
    ->paginate(10, $_GET['page'] ?? 1);

// Search functionality
function searchUsers($query)
{
    return User::where('name', 'LIKE', "%{$query}%")
        ->orWhere('email', 'LIKE', "%{$query}%")
        ->active()
        ->orderBy('name')
        ->get();
}

// Dashboard statistics
$stats = [
    'total_users' => User::count(),
    'active_users' => User::active()->count(),
    'total_posts' => Post::count(),
    'published_posts' => Post::published()->count(),
    'recent_users' => User::latest()->limit(5)->get(),
    'avg_age' => User::avg('age')
];

// Bulk operations
$userIds = [1, 2, 3, 4, 5];
$users = User::findMany($userIds);

$users->each(function($user) {
    $user->update(['last_login' => date('Y-m-d H:i:s')]);
});

// Complex query
$results = User::where('status', 'active')
    ->where('age', '>', 18)
    ->whereNotIn('role', ['banned', 'suspended'])
    ->whereNotNull('email_verified_at')
    ->orderBy('created_at', 'DESC')
    ->limit(100)
    ->get()
    ->filter(fn($u) => $u->posts()->count() > 5)
    ->map(fn($u) => [
        'name' => $u->name,
        'email' => $u->email,
        'post_count' => $u->posts()->count()
    ]);

// ============================================
// 13. MODEL EVENTS
// ============================================

class User extends PlugModel
{
    protected function creating()
    {
        // Before insert
        $this->setAttribute('api_token', bin2hex(random_bytes(32)));
        return true; // Return false to cancel
    }

    protected function created()
    {
        // After insert
        // Send welcome email
    }

    protected function updating()
    {
        // Before update
        return true;
    }

    protected function updated()
    {
        // After update
    }

    protected function saving()
    {
        // Before insert or update
        return true;
    }

    protected function saved()
    {
        // After insert or update
    }

    protected function deleting()
    {
        // Before delete
        return true;
    }

    protected function deleted()
    {
        // After delete
        // Clean up related data
    }
}

// ============================================
// 14. TIPS & BEST PRACTICES
// ============================================

// Always use fillable or guarded
// protected $fillable = ['name', 'email']; // Whitelist
// protected $guarded = ['id', 'password']; // Blacklist

// Use scopes for reusable queries
// User::active()->verified()->get();

// Eager load relationships to avoid N+1 queries
// User::with('posts')->get(); // Good
// foreach (User::all() as $user) { $user->posts(); } // Bad - N+1

// Use transactions for critical operations
// PDO::beginTransaction();
// try {
//     $user->save();
//     $profile->save();
//     PDO::commit();
// } catch (Exception $e) {
//     PDO::rollBack();
// }

// Cache frequently accessed data
// $popularPosts = Post::published()->orderBy('views', 'DESC')->limit(10)->get();

// Use Collections for data manipulation
// $emails = User::all()->pluck('email')->unique()->filter();

echo "✅ PlugModel Usage Guide Complete!";