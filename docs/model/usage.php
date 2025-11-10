Here's a comprehensive usage guide showing how to use all the new features while maintaining backward compatibility:

## **Basic Setup & Configuration**

```php
<?php

// 1. BASIC MODEL DEFINITION
class User extends PlugModel
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'email', 'password'];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'meta' => 'array'
    ];
    protected $hidden = ['password'];
    public $timestamps = true;

    // Relationships
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

class Post extends PlugModel
{
    protected $fillable = ['title', 'content', 'user_id'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

## **Connection Management**

```php
<?php

// OLD WAY (still works)
User::setConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'password'
]);

// NEW WAY (using Connection class)
User::connection('default'); // Uses pre-configured connection
```

## **Enhanced Query Building**

```php
<?php

// NESTED WHERE CLAUSES
$users = User::where(function($query) {
    $query->where('age', '>', 18)
          ->orWhere('verified', true);
})->get();

// COMPLEX NESTED QUERIES
$posts = Post::where('published', true)
    ->where(function($query) {
        $query->where('views', '>', 1000)
              ->orWhere(function($q) {
                  $q->where('featured', true)
                    ->where('created_at', '>', '2024-01-01');
              });
    })->get();

// RAW WHERE CLAUSES
$users = User::whereRaw('YEAR(created_at) = ? AND status = ?', [2024, 'active'])->get();

$products = Product::orWhereRaw('price * quantity > ?', [1000])->get();

// ENHANCED WHERE METHODS
$users = User::whereBetween('age', [18, 65])->get();
$products = Product::whereNotBetween('price', [100, 500])->get();

// DATE QUERIES
$posts = Post::whereDate('created_at', '2024-01-15')->get();
$users = User::whereYear('created_at', 2024)->get();
$orders = Order::whereMonth('created_at', 12)->get();

// SEARCH FUNCTIONALITY
// Single column search
$users = User::whereLike('name', 'John')->get();

// Multiple column search
$users = User::searchMultiple(['name', 'email', 'bio'], 'search term')->get();

// Exact search
$products = Product::search('sku', 'EXACT123', true)->get();
```

## **New Utility Methods**

```php
<?php

// SOLE - GET SINGLE RECORD WITH VALIDATION
try {
    $user = User::where('email', 'admin@example.com')->sole();
} catch (Exception $e) {
    // Handle no record or multiple records
}

// VALUE - GET SINGLE COLUMN VALUE
$email = User::where('id', 1)->value('email');
$latestPost = Post::latest()->value('title');

// PLUCK - GET ARRAY OF COLUMN VALUES
$names = User::pluck('name'); // ['John', 'Jane', ...]
$emails = User::pluck('email', 'id'); // [1 => 'john@example.com', 2 => 'jane@example.com']

// ENHANCED PAGINATION
// Regular pagination (existing)
$users = User::paginate(15, 1);

// Simple pagination (new) - faster, no count query
$users = User::simplePaginate(15, 1);

// CURSOR - MEMORY EFFICIENT ITERATION
foreach (User::cursor() as $user) {
    // Process user without loading all into memory
    echo $user->name;
}

// CHUNK BY ID - BETTER PERFORMANCE FOR LARGE DATASETS
User::chunkById(1000, function($users) {
    foreach ($users as $user) {
        // Process users
    }
});

// RANDOM RECORDS
$randomUser = User::inRandomOrder()->first();
$randomPosts = Post::random(5); // Get 5 random posts
```

## **Enhanced CRUD Operations**

```php
<?php

// ENHANCED INCREMENT/DECREMENT WITH EXTRA DATA
$user = User::find(1);
$user->increment('login_count', 1, ['last_login' => now()]);
$product->decrement('stock', 5, ['updated_by' => auth()->id()]);

// BATCH INSERT WITH ID RETURN
$id = User::insertGetId([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// RAW UPDATE
User::updateRaw(
    ['status' => 'inactive', 'updated_at' => now()],
    ['last_login' => '<', '2023-01-01']
);

// ENHANCED BATCH OPERATIONS
// Destroy multiple records efficiently
$deletedCount = User::destroyMany([1, 2, 3, 4, 5]);

// Preload models
$users = User::preload([1, 2, 3, 4, 5]);

// UPSERT WITH ENHANCED SYNTAX
User::upsert(
    [
        ['email' => 'john@example.com', 'name' => 'John Doe'],
        ['email' => 'jane@example.com', 'name' => 'Jane Smith']
    ],
    ['email'], // Unique keys
    ['name']   // Columns to update on duplicate
);
```

## **Relationships & Eager Loading**

```php
<?php

// OPTIMIZED EAGER LOADING
$posts = Post::with('user', 'tags')->get();

// LOAD MISSING RELATIONS
$user = User::find(1);
$user->loadMissing('posts', 'profile');

// ENHANCED RELATIONSHIP METHODS
class User extends PlugModel 
{
    // Has Many Through
    public function postComments()
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    // Polymorphic
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }
}

// MORPH MAP
PlugModel::morphMap([
    'user' => User::class,
    'post' => Post::class,
]);
```

## **Global Scopes**

```php
<?php

class ActiveScope
{
    public function __invoke($query)
    {
        return $query->where('active', true);
    }
}

// ADD GLOBAL SCOPE
User::addGlobalScope('active', new ActiveScope());

// NOW ALL USER QUERIES AUTOMATICALLY FILTER ACTIVE USERS
$activeUsers = User::all(); // Only active users

// REMOVE SCOPE FOR SPECIFIC QUERY
$allUsers = User::withoutGlobalScope('active')->get();

// QUERY WITH CUSTOM SCOPE
class User extends PlugModel
{
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }
}

// USE SCOPES
$admins = User::admin()->get();
$recentAdmins = User::admin()->recent(7)->get();
```

## **Advanced Features**

```php
<?php

// MODEL REPLICATION
$user = User::find(1);
$newUser = $user->replicate();
$newUser->email = 'new@example.com';
$newUser->save();

// Replicate with exceptions
$newUser = $user->replicate(['created_at', 'updated_at']);

// MODEL COMPARISON
$user1 = User::find(1);
$user2 = User::find(2);

if ($user1->is($user2)) {
    // Same model instance
}

if ($user1->isNot($user2)) {
    // Different model instances
}

// FRESH INSTANCE WITH RELATIONS
$user = User::find(1);
$freshUser = $user->fresh(); // Without relations
$freshUserWithPosts = $user->fresh(['posts', 'profile']); // With relations

// CHANGE TRACKING
$user = User::find(1);
$user->name = 'New Name';
$user->isDirty(); // true
$user->isDirty('name'); // true
$user->getDirty(); // ['name' => 'New Name']

$user->save();
$user->wasChanged(); // true
$user->wasChanged('name'); // true
$user->getChanges(); // ['name' => 'New Name']
```

## **Caching & Performance**

```php
<?php

// QUERY CACHING
User::enableCache(3600); // Enable with 1 hour TTL

// Cached queries
$users = User::where('active', true)->remember()->get();
$users = User::where('active', true)->remember(1800)->get(); // 30 minutes

// FIND WITH CACHING
$user = User::findCached(1); // Cached find
$user = User::findCached(1, 7200); // 2 hour cache

// MANUAL CACHE MANAGEMENT
User::flushCache(); // Clear all cache
User::disableCache(); // Disable caching

// MEMORY STATISTICS
$stats = User::getMemoryStats();
/*
[
    'query_cache_size' => 15,
    'query_log_size' => 100,
    'observers_count' => 2,
    'global_scopes_count' => 1
]
*/

// CLEANUP
User::clearAll(); // Clear all caches and logs
```

## **Validation & Events**

```php
<?php

class User extends PlugModel
{
    protected $rules = [
        'name' => 'required|min:2|max:255',
        'email' => 'required|email|unique:users,email',
        'age' => 'numeric|min:18'
    ];

    protected $messages = [
        'email.unique' => 'This email is already registered.'
    ];

    // MODEL EVENTS
    protected function creating()
    {
        $this->uuid = Str::uuid();
    }

    protected function saving()
    {
        if ($this->isDirty('password')) {
            $this->password = bcrypt($this->password);
        }
    }

    protected function retrieved()
    {
        // After fetching from database
    }
}

// USAGE
$user = new User(['name' => 'John']);
if ($user->validate()) {
    $user->save();
} else {
    $errors = $user->getErrors();
}
```

## **Advanced Usage Patterns**

```php
<?php

// COMPLEX QUERY BUILDING
$query = User::select('name', 'email')
    ->where('active', true)
    ->where(function($q) {
        $q->where('subscription', 'premium')
          ->orWhere('trial_ends_at', '>', now());
    })
    ->whereIn('role', ['admin', 'editor'])
    ->whereNotIn('id', [1, 2, 3])
    ->whereNotNull('email_verified_at')
    ->orderBy('created_at', 'DESC')
    ->groupBy('country')
    ->having('count', '>', 10);

// CONDITIONAL QUERIES
$users = User::when($request->has('search'), function($query) use ($request) {
        return $query->where('name', 'like', "%{$request->search}%");
    })
    ->when($request->has('role'), function($query) use ($request) {
        return $query->where('role', $request->role);
    })
    ->get();

// TRANSACTIONS WITH ENHANCED ERROR HANDLING
User::transaction(function() {
    $user = User::create([...]);
    $user->profile()->create([...]);
    $user->posts()->create([...]);
    
    return $user;
});

// BATCH PROCESSING WITH PROGRESS
$processed = 0;
User::chunkById(100, function($users) use (&$processed) {
    foreach ($users as $user) {
        // Process user
        $user->update(['processed' => true]);
        $processed++;
    }
    
    echo "Processed {$processed} users\n";
});

// LAZY COLLECTION PROCESSING
foreach (User::cursor() as $user) {
    // Process each user without memory overload
    processUser($user);
}
```

## **Backward Compatibility Examples**

```php
<?php

// OLD CODE STILL WORKS PERFECTLY
$user = User::find(1);
$users = User::where('active', 1)->orderBy('name')->get();
$newUser = User::create(['name' => 'John']);
$user->update(['name' => 'Jane']);
$user->delete();

// OLD QUERY BUILDING STILL WORKS
User::where('id', 1)
    ->orWhere('name', 'John')
    ->whereIn('status', ['active', 'pending'])
    ->get();

// OLD TRANSACTIONS STILL WORK
User::beginTransaction();
try {
    // operations
    User::commit();
} catch (Exception $e) {
    User::rollBack();
}


This refactored class gives you all the powerful new features while ensuring that your existing code continues to work without any modifications!
