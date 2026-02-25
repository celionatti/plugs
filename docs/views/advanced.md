# Advanced View Usage

Advanced features for complex applications including streaming, debugging, and performance optimization.

## Streaming Rendering

For large views or long-running processes, Plugs supports various streaming modes to improve Time To First Byte (TTFB) and memory efficiency.

### True Unbuffered Streaming

You can enable true unbuffered streaming, where content is echoed directly to the browser as it is processed. This is ideal for extremely large datasets or dashboards with many components.

**Enabling Globally via `.env`:**

```bash
VIEW_STREAMING=true
VIEW_AUTO_FLUSH=50
```

**Enabling Programmatically:**

```php
$viewEngine->enableStreaming(true);
$viewEngine->enableAutoFlush(50); // Optional: auto-flush every N loop iterations
```

> [!WARNING]
> When `VIEW_STREAMING` is enabled, headers are sent immediately. You cannot use `header()` redirects or set cookies inside your view logic.

### Automatic Loop Flushing

When streaming is enabled, Plugs automatically flushes the output buffer during `@foreach` and `@forelse` loops. This prevents the buffer from becoming too large and ensures the user sees data as it's processed.

```blade
@foreach($largeDataset as $item)
    {{-- Every 50 items, the buffer flushes automatically --}}
    <div>...</div>
@endforeach
```

### Generator-Based Streaming

If you need manual control over chunks, use the `stream` method:

```php
foreach ($viewEngine->stream('large-report', $data) as $chunk) {
    echo $chunk;
    flush();
}
```

---

## Performance & Memory Optimization

### Support for Generators

Loop directives (`@foreach`, `@forelse`) now support non-countable iterables like PHP Generators. This allows you to iterate over millions of rows with virtually zero memory overhead.

```php
// In Controller
$data['users'] = function() {
    foreach (DB::cursor('SELECT * FROM users') as $user) {
        yield $user;
    }
};

// In View
@foreach($users() as $user)
    {{ $user->name }}
@endforeach
```

---

## Parallel Rendering

Render multiple views efficiently:

```php
$results = $viewEngine->renderMany([
    'partials.header' => ['user' => $user],
    'partials.sidebar' => ['menu' => $menu],
    'partials.content' => ['data' => $data],
], ['appName' => 'My App']);

// Returns: ['partials.header' => '...', 'partials.sidebar' => '...', ...]
```

---

## Debugging

### Dump View Data

```php
// Dump data and continue
view('page', $data)->dump();

// Dump data and die
view('page', $data)->dd();
```

### Get Debug Information

```php
$debug = view('page', $data)->debug();
// [
//     'view' => 'page',
//     'data' => [...],
//     'headers' => [...],
//     'status_code' => 200,
//     'excluded_sections' => [],
//     'engine_class' => 'Plugs\View\ViewEngine'
// ]
```

### View Existence Check

```php
if ($viewEngine->exists('emails.welcome')) {
    return view('emails.welcome', $data);
} else {
    return view('emails.generic', $data);
}
```

---

## Response Handling

### Chainable Response Methods

```php
return view('dashboard', $data)
    ->withStatus(200)
    ->withHeaders([
        'Cache-Control' => 'private, max-age=3600',
        'X-Custom-Header' => 'value'
    ])
    ->withHeader('Content-Language', 'en')
    ->send();
```

### JSON Response

Convert view to JSON for AJAX:

```php
return response(view('partials.card', $data)->toJson())
    ->header('Content-Type', 'application/json');
```

Returns:

```json
{
  "html": "<div class=\"card\">...</div>",
  "view": "partials.card"
}
```

### HTMX Response Helper

```php
return view('dashboard', $data)->htmxResponse();
```

This automatically:

- Sends appropriate headers
- Handles teleport content
- Sends the rendered view

---

## Path Resolution Caching

Improve performance by caching file path lookups:

```php
// Use cached path resolution
$path = $viewEngine->getViewPathCached('users.profile');

// Clear path cache (after adding new views)
$viewEngine->clearPathCache();
```

---

## Custom Directives

Register custom directives:

```php
$viewEngine->registerDirective('datetime', function($expression) {
    return "<?php echo date('Y-m-d H:i:s', $expression); ?>";
});
```

Usage:

```blade
@datetime(strtotime($post->created_at))
```

---

## View Composers

Run logic before specific views render:

```php
// Single view
$viewEngine->composer('profile', function($view) {
    $view->with('countries', Country::all());
});

// Multiple views
$viewEngine->composer(['profile', 'settings'], function($view) {
    $view->with('timezones', Timezone::all());
});

// Wildcard
$viewEngine->composer('admin.*', function($view) {
    $view->with('adminMenu', $this->getAdminMenu());
});
```

---

## Conditional Rendering

### Section Exclusion

Exclude sections from output:

```php
return view('page', $data)->without(['ads', 'newsletter']);
```

In template:

```blade
@unless(in_array('ads', $__excludedSections ?? []))
    <div class="ads">...</div>
@endunless
```

### Render Only Specific Section

```php
// Render only the 'content' fragment
$html = view('page', $data)->renderOnly('content');
```

---

## Error Handling

### Debug Mode Errors

When `APP_DEBUG=true`, detailed errors are shown:

```blade
{{-- Displays: view name, error message, file, line, stack trace --}}
```

### Production Mode

When `APP_DEBUG=false`:

- Errors are logged
- Generic error message shown
- No stack traces exposed

### Custom Error Handling

```php
try {
    return view('page', $data)->render();
} catch (RuntimeException $e) {
    Log::error('View error', ['message' => $e->getMessage()]);
    return view('errors.500')->render();
}
```

---

## Performance Tips

### 1. Enable Caching in Production

```php
$viewEngine = new ViewEngine($viewPath, $cachePath, cacheEnabled: true);
$viewEngine->setFastCache(true);
```

### 2. Preload Common Views

```php
$viewEngine->preload(['layouts.app', 'partials.nav']);
```

### 3. Warm Cache on Deploy

```bash
php artisan view:cache
```

### 4. Use Block Caching for Expensive Content

```blade
@cache('navigation', 3600)
    {{-- Database queries here --}}
@endcache
```

### 5. Stream Large Views

```php
$viewEngine->renderToStream('large-report', $data);
```

### 6. Avoid Heavy Logic in Views

```php
// Bad: Heavy logic in view
@foreach(User::where('active', true)->with('posts')->get() as $user)

// Good: Pass data from controller
@foreach($activeUsers as $user)
```

---

## Security

### XSS Protection

Always use escaped echo for user content:

```blade
{{-- Safe --}}
{{ $userInput }}

{{-- Only for trusted content --}}
{!! $trustedHtml !!}
```

### Sanitize User HTML

```blade
@sanitize($userContent, 'basic')
```

### CSP Nonce Support

```php
$viewEngine->setCspNonce($nonce);
```

```blade
<script nonce="{{ $view->getCspNonce() }}">
    // Inline script
</script>
```

---

## Extending the View System

### Custom View Class

```php
class MyView extends View
{
    public function withFlash(): self
    {
        return $this->with('flash', session()->getFlash());
    }
}
```

### Custom Compiler Methods

Extend `ViewCompiler` to add functionality:

```php
class MyViewCompiler extends ViewCompiler
{
    protected function compileMyDirective(string $content): string
    {
        return preg_replace('/@myDirective/', '<?php ...?>', $content);
    }
}
```
