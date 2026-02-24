# AI Performance & Optimization

The Plugs framework provides advanced features to ensure AI-powered features are lightning-fast and provide a zero-latency experience for end-users.

## 1. AI Result Caching

You can cache AI prompt results to avoid redundant API calls and reduce latency.

### Basic Caching

Pass the `cache` option (integer in seconds or boolean for 1 hour) to `prompt()` or `classify()`.

```php
// Cache for 1 hour (default)
$result = ai()->prompt("Summarize this...", [], ['cache' => true]);

// Cache for 10 minutes
$result = ai()->prompt("Analyze this...", [], ['cache' => 600]);
```

### Explicit Caching with `remember()`

Use the `remember()` method for more control over the caching logic.

```php
$summary = ai()->remember('article_summary_' . $article->id, function() use ($article) {
    return ai()->prompt("Summarize: " . $article->content);
}, 3600);
```

## 2. Stale-While-Revalidate (SWR)

SWR is a powerful pattern that allows you to serve cached content instantly while refreshing it in the background.

```php
// Returns cached value immediately (0ms blocking)
// Schedules a background refresh if cache is stale
$headlines = ai()->prompt("Get trending news", [], ['swr' => true]);
```

When `swr` is enabled:

1. **Instant Response**: If a cached value exists, it is returned immediately.
2. **Background Refresh**: The framework automatically registers a `terminate()` callback to fetch fresh data from the AI provider _after_ the response has been sent to the user.
3. **Cache Update**: The fresh result is saved to the cache for the next request.

## 3. Async/Deferred Execution

If you don't have a cached value and need to perform an AI operation without blocking the main request thread, use `defer()`.

```php
// This is non-blocking and returns a LazyString
$aiResponse = ai()->defer()->prompt("Perform complex analysis...");

// Perform other tasks (DB queries, etc.) in the meantime
$products = Product::all();

// The AI request is only "awaited" when you actually use the string
echo $aiResponse;
```

### How it Works

- `ai()->defer()` returns a `LazyString` object.
- The AI driver (like `OpenAIDriver`) initiates a non-blocking HTTP request (using Guzzle's `postAsync`).
- The framework only waits for the resolution when the `LazyString` is cast to a string or explicitly resolved.

## 4. Background Queuing

For tasks that don't need to return a result to the user at all, dispatch them to the background queue.

```php
ai()->queue('prompt', ["Generate weekly report for Admin", ['model' => 'gpt-4o']]);
```

This uses the framework's Queue system to process the AI task asynchronously.
