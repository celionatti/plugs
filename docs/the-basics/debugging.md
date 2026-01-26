# Debugging & Performance

The Plugs framework includes a suite of premium debugging tools designed to help you build high-performance applications with ease.

## The Debuggable Trait

All Models in Plugs use the `Debuggable` trait, which provides powerful performance monitoring out of the box.

### Performance Marking

Use the `mark` method to track performance between any two points in your code:

```php
User::mark('start');
// ... execution ...
User::mark('end');

$summary = User::getPerformanceSummary('start', 'end');
// Returns ['duration' => 0.05, 'memory_diff' => 1024]
```

### Model Load Tracking

Plugs automatically monitors which models are being loaded during a request. You can access these statistics via:

```php
$stats = User::getLoadedModelStats();
```

This is particularly useful for identifying unexpected database loads or missing eager loading.

## Visual Debugging

### Dump & Die (`dd`)

The `dd()` helper provides a beautiful, searchable, and interactive view of your variables. Sensitive fields like `password` or `secret` are automatically masked.

### Dump Queries (`dq`)

The `dq()` helper opens the **Query Insights Dashboard**, which provides:
- Total query count and time analysis.
- N+1 problem detection.
- A premium, glassmorphic log of every SQL statement executed.

```php
User::where('active', 1)->get();
dq(); // View the query dashboard
```

## API Debugging

When your application is in development mode (`APP_ENV=local`), standardized API responses automatically include a `debug` object containing:
- Peak memory usage.
- Model load statistics.
- Total execution time.

This helps you monitor performance even when working on purely headless APIs.

## Real-world Examples

### 1. Performance Profiling a Service

If you have a complex service method, you can use `mark()` to isolate slow blocks:

```php
public function processExport()
{
    User::mark('fetch_start');
    $data = LargeData::all();
    User::mark('fetch_end');

    User::mark('transform_start');
    $results = $data->map(fn($item) => $this->transform($item));
    User::mark('transform_end');

    $fetchStats = User::getPerformanceSummary('fetch_start', 'fetch_end');
    $transformStats = User::getPerformanceSummary('transform_start', 'transform_end');

    User::log("Fetch took {$fetchStats['duration']}s");
    User::log("Transform took {$transformStats['duration']}s");

    return $results;
}
```

### 2. Identifying N+1 Load Problems

Use `User::getLoadedModelStats()` to find code that triggers too many queries:

```php
// Before optimization
foreach (User::all() as $user) {
    echo $user->profile->bio; // Each loop instantiates Profile
}

dq(); // The dashboard will show: models: { "Profile": 50 } (CRITICAL)
```

### 3. Masking Sensitive Information in Debugs

The `dd()` and `dq()` commands automatically hide sensitive keys. You can customize this by ensuring your model attributes are named accordingly (e.g., `api_token`, `secret_key`):

```php
$user = [
    'name' => 'John',
    'api_secret' => 'sk_test_123456789'
];

dd($user); // Output: [ "name" => "John", "api_secret" => "🔒 [masked secret]" ]
```

## Dump Profile (`dp`)

Profile any code block with automatic query tracking:

```php
dp(function() {
    return User::with('posts', 'comments')->get();
});
```

This displays:
- **Execution time** in milliseconds
- **Memory usage**
- **Query count and time**
- **All executed SQL queries** with timing
- **Performance assessment** (good/warning/critical)
- **The return value** of your callback

### Using Model::profile()

You can also use the static `profile()` method directly:

```php
$result = User::profile(function() {
    return User::where('active', 1)->get();
});

// Returns:
// [
//     'result' => Collection,
//     'execution_time' => 0.05,
//     'execution_time_ms' => 50.0,
//     'memory_used' => 1024,
//     'memory_formatted' => '1 KB',
//     'queries' => [...],
//     'query_count' => 2,
//     'query_time' => 0.01,
//     'query_time_ms' => 10.0,
// ]
```

## Dump Exception (`de`)

Dump exceptions with beautiful stack traces and code context:

```php
try {
    $user = User::findOrFail(999);
} catch (Exception $e) {
    de($e);
}
```

This displays:
- Exception class and message
- File location with code context
- Full stack trace (expandable)
- Previous exceptions chain

## Dump HTTP (`dh`)

Debug HTTP responses with headers, body, and timing:

```php
$response = http_get('https://api.example.com/users');
dh($response);
```

This displays:
- Status code with color-coded indicator
- Request URL
- Response time (if available)
- All response headers
- Parsed JSON body (if applicable)

## Debug Themes (`dt`)

Customize the debug UI with built-in themes:

```php
// Set theme before dumping
dt('light');    // Light mode
dt('dark');     // Dark mode (default)
dt('dracula');  // Dracula color scheme
dt('monokai');  // Monokai color scheme

dd($data);
```

### Available Themes

| Theme | Description |
|-------|-------------|
| `dark` | Default purple/blue dark theme |
| `light` | Clean light mode for bright environments |
| `dracula` | Popular Dracula color palette |
| `monokai` | Classic Monokai editor theme |

## Dump Model (`dm`)

Show model details with related queries:

```php
$user = User::with('posts')->find(1);
dm($user);
```

This displays:
- Model attributes (respects `__debugInfo()`)
- Table name and primary key
- Related queries executed

## Complete Helper Reference

| Function | Description |
|----------|-------------|
| `dd(...$vars)` | Dump and die |
| `d(...$vars)` | Dump without dying |
| `dt($theme)` | Set debug theme |

## Profiler Bar

The **Profiler Bar** is automatically injected into all HTML pages when in `debug` mode. It stays at the bottom of your screen and provides instant performance feedback.

**Features:**
- **Real-time Status:** Shows execution time, memory usage, query count, and HTTP status code.
- **Detailed Modal:** Click **"Profiler"** to open a comprehensive debug dashboard overlay with:
  - **Overview:** High-level stats and performance health check.
  - **Queries:** Full log of executed SQL queries with syntax highlighting and bindings.
  - **Request:** Detailed request parameters, headers, and route info.
  - **Models:** Memory usage and loaded model statistics.

> [!TIP]
> The profiler modal uses the same rich, tabbed interface as `dd()`, giving you a consistent debugging experience across your application.

