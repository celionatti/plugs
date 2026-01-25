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
