<?php

/*
|--------------------------------------------------------------------------
| Plugs Framework Debug Tools - Usage Examples
|--------------------------------------------------------------------------
|
| This file demonstrates how to use the enhanced debugging tools
| in your Plugs Framework application.
|--------------------------------------------------------------------------
*/

// ============================================================================
// BASIC DEBUGGING
// ============================================================================

// Dump and die (stops execution)
$users = User::all();
dd($users);

// Dump without dying (continues execution)
$user = User::find(1);
d($user);
d($user->posts);

// Multiple variables
dd($users, $posts, $comments);

// ============================================================================
// QUERY DEBUGGING
// ============================================================================

// Enable query logging before your code
User::enableQueryLog();

// Your database operations
$users = User::with('posts')->get();
foreach ($users as $user) {
    echo $user->name;
}

// Dump all queries
dq(); // Dies after showing queries

// Or without dying
dq(false);

// Get query statistics programmatically
$queries = User::getQueryLog();
d($queries);

// ============================================================================
// MODEL DEBUGGING (with Debuggable trait)
// ============================================================================

// Add trait to your model:
/*
class User extends PlugModel
{
    use Debuggable;
    
    // ... your model code
}
*/

// Enable debug mode
User::enableDebug();

// Execute queries
$users = User::where('active', 1)->get();

// Dump the model with queries
dm($users);

// Get performance analysis
$analysis = User::analyzePerformance();
dd($analysis);

// Get debug statistics
$stats = User::getDebugStats();
d($stats);

// ============================================================================
// PERFORMANCE PROFILING
// ============================================================================

// Profile a specific operation
$result = User::profile(function() {
    return User::with('posts', 'comments')->get();
});

dd($result);
/*
Output includes:
- result: The actual query results
- execution_time: How long it took
- memory_used: Memory consumed
- queries: All queries executed
- query_count: Number of queries
- query_time: Total query time
*/

// ============================================================================
// N+1 DETECTION
// ============================================================================

User::enableDebug();

// This will trigger N+1 warning
$users = User::all();
foreach ($users as $user) {
    // This executes a query for each user!
    echo $user->posts->count();
}

dq(); // Will show N+1 detection alert

// ============================================================================
// SLOW QUERY DETECTION
// ============================================================================

// Set slow query threshold (100ms)
User::setSlowQueryThreshold(0.1);

User::enableDebug();

// Execute some queries
$users = User::with('posts.comments.author')->get();

// Check for slow queries
$stats = User::getDebugStats();
if ($stats['slow_queries'] > 0) {
    echo "Found {$stats['slow_queries']} slow queries!";
    dd($stats);
}

// ============================================================================
// DEBUGGING IN CONTROLLERS
// ============================================================================

class UserController
{
    public function index()
    {
        // Enable debugging for development
        if (env('APP_DEBUG')) {
            User::enableDebug();
        }
        
        $users = User::with('posts')->paginate(15);
        
        // Check performance before rendering
        if (env('APP_DEBUG')) {
            $analysis = User::analyzePerformance();
            
            if ($analysis['status'] === 'critical') {
                // Log or alert about performance issues
                User::log('Performance issues detected', $analysis);
            }
        }
        
        return view('users.index', compact('users'));
    }
    
    public function show($id)
    {
        $user = User::with('posts', 'comments')->findOrFail($id);
        
        // Quick debug during development
        // d($user); // Uncomment to debug
        
        return view('users.show', compact('user'));
    }
}

// ============================================================================
// DEBUGGING RELATIONSHIPS
// ============================================================================

User::enableDebug();

// Without eager loading (causes N+1)
$users = User::all();
foreach ($users as $user) {
    d($user->posts); // N+1 problem!
}

// Check the queries
dq();

// With eager loading (optimal)
User::flushQueryLog(); // Clear previous queries
$users = User::with('posts')->get();
foreach ($users as $user) {
    d($user->posts); // No extra queries!
}

dq(); // Will show only 2 queries instead of N+1

// ============================================================================
// DEBUGGING COMPLEX QUERIES
// ============================================================================

User::enableQueryLog();

$query = User::where('status', 'active')
    ->whereHas('posts', function($q) {
        $q->where('published', true);
    })
    ->with(['posts' => function($q) {
        $q->latest()->limit(5);
    }])
    ->latest()
    ->take(10);

// Debug the query before executing
d($query->toSql());
d($query->getBindingsArray());

$users = $query->get();

// See actual execution
dq();

// ============================================================================
// MEMORY DEBUGGING
// ============================================================================

// Check memory before operation
$memoryBefore = memory_get_usage(true);

// Your operation
$users = User::with('posts', 'comments', 'likes')->get();

// Check memory after
$memoryAfter = memory_get_usage(true);
$memoryUsed = $memoryAfter - $memoryBefore;

echo "Memory used: " . number_format($memoryUsed / 1024 / 1024, 2) . " MB";

// Get detailed memory stats
$memStats = User::getMemoryStats();
dd($memStats);

// ============================================================================
// DEBUGGING IN PRODUCTION (CAREFUL!)
// ============================================================================

// Only enable for specific users or conditions
if (auth()->check() && auth()->user()->is_admin) {
    User::enableDebug();
}

// Or use environment check
if (env('APP_ENV') === 'local') {
    User::enableDebug();
}

// Log queries to file instead of displaying
User::enableQueryLog();
$users = User::all();
$queries = User::getQueryLog();

// Write to log file
file_put_contents(
    storage_path('logs/queries.log'),
    json_encode($queries, JSON_PRETTY_PRINT),
    FILE_APPEND
);

// ============================================================================
// DEBUGGING TIPS
// ============================================================================

/*
1. Always use eager loading for relationships:
   ✓ User::with('posts')->get()
   ✗ User::all() then $user->posts in loop

2. Enable query logging only when needed:
   User::enableQueryLog();
   // ... your code
   User::disableQueryLog();

3. Use dq() to check for N+1 problems

4. Profile expensive operations:
   $result = User::profile(function() {
       // expensive operation
   });

5. Set slow query thresholds:
   User::setSlowQueryThreshold(0.1); // 100ms

6. Check analysis before production:
   $analysis = User::analyzePerformance();
   if ($analysis['status'] !== 'good') {
       // Fix issues
   }

7. Clear query log between tests:
   User::flushQueryLog();

8. Use dm() for model-specific debugging:
   dm($user); // Shows model + queries

9. Monitor memory for large datasets:
   User::getMemoryStats();

10. Use profiling for optimization:
    User::profile(function() {
        return User::with('posts')->get();
    });
*/