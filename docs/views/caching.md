# View Caching

Optimize rendering performance with view compilation caching and block-level content caching.

## Compilation Caching

### Enabling Cache

```php
$viewEngine = new ViewEngine(
    viewPath: __DIR__ . '/views',
    cachePath: __DIR__ . '/cache/views',
    cacheEnabled: true  // Enable compilation caching
);
```

### How It Works

1. First render: Template is compiled and cached as PHP
2. Subsequent renders: Cached PHP file is used directly
3. Cache is invalidated when source file is modified

### Cache Location

Compiled views are stored in the cache path:

```
cache/views/
├── 5d41402abc4b2a76b9719d911017c592.php
├── 7f021a4d5b1a8c2e3f4a5b6c7d8e9f0a.php
└── ...
```

### Fast Cache Mode

For production environments, enable fast cache to skip modification time checks:

```php
$viewEngine->setFastCache(true);
```

> **Warning:** With fast cache enabled, you must clear the cache manually when deploying new views.

### Clearing the Cache

```php
// Clear all cached views
$viewEngine->clearCache();

// Garbage collect old cache files (older than 30 days)
$count = $viewEngine->gc(720); // hours
```

---

## Block Caching

Cache expensive sections of your views using `@cache`:

### Basic Usage

```blade
{{-- Cache for 1 hour (3600 seconds) --}}
@cache('sidebar-menu', 3600)
    <nav class="sidebar">
        @foreach($menuItems as $item)
            <a href="{{ $item->url }}">{{ $item->label }}</a>
        @endforeach
    </nav>
@endcache
```

### Dynamic Cache Keys

Include variables in cache keys for user-specific content:

```blade
@cache('user-dashboard-' . $user->id, 1800)
    <div class="dashboard">
        {{-- User-specific content --}}
    </div>
@endcache
```

### Versioning Cache

Invalidate cache when data changes:

```blade
@cache('products-' . $category->id . '-v' . $category->updated_at->timestamp)
    <div class="products">
        @foreach($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    </div>
@endcache
```

---

## ViewCache Class

The `ViewCache` class provides programmatic caching:

### Basic Operations

```php
use Plugs\View\ViewCache;

$cache = new ViewCache(__DIR__ . '/cache');

// Store content
$cache->put('key', $content, 3600);

// Retrieve content
$content = $cache->get('key');
$content = $cache->get('key', 'default');

// Check existence
if ($cache->has('key')) {
    // ...
}

// Delete
$cache->forget('key');

// Clear all
$cache->flush();
```

### Remember Pattern

```php
// Cache the result of a callback
$content = $cache->remember('expensive-content', function() {
    return $this->generateExpensiveContent();
}, 3600);
```

### Cache Forever

```php
$cache->forever('static-content', $content);
```

### Tagged Caching

Group cache items with tags:

```php
// Store with tags
$cache->tags(['products', 'homepage'])->put('featured', $content);

// Retrieve
$content = $cache->tags(['products'])->get('featured');

// Clear by tag
$cache->tags(['products'])->flush();
```

### Statistics

Monitor cache performance:

```php
$stats = $cache->getStats();
// [
//     'hits' => 150,
//     'misses' => 23,
//     'writes' => 45,
// ]
```

---

## PSR-16 Integration

Use any PSR-16 compatible cache:

```php
use Psr\SimpleCache\CacheInterface;

$cache = new ViewCache(
    cacheDir: __DIR__ . '/cache',
    cache: $redisCache  // Your PSR-16 implementation
);

$viewEngine->setViewCache($cache);
```

---

## Cache Warming

Precompile views during deployment:

```php
// Warm all views
$count = $viewEngine->warmCache();
echo "Warmed $count views";

// Warm views matching a pattern
$count = $viewEngine->warmCache('admin');
echo "Warmed $count admin views";
```

### Console Command Example

```php
// In a CLI command
public function handle()
{
    $this->info('Warming view cache...');
    
    $engine = app(ViewEngine::class);
    $count = $engine->warmCache();
    
    $this->info("Warmed $count views.");
}
```

---

## Preloading Views

Preload frequently used views into memory:

```php
$viewEngine->preload([
    'layouts.app',
    'partials.header',
    'partials.footer',
    'components.button',
]);
```

---

## Cache Best Practices

### 1. Cache Expensive Computations

```blade
@cache('stats-' . date('Y-m-d-H'), 3600)
    {{-- Heavy database queries --}}
    @foreach($this->getComplexStats() as $stat)
        ...
    @endforeach
@endcache
```

### 2. Don't Cache Dynamic Content

```blade
{{-- DON'T cache CSRF tokens or user-specific data without key --}}
{{-- Bad: --}}
@cache('form')
    <form>
        @csrf  {{-- TOKEN WILL BE CACHED! --}}
    </form>
@endcache

{{-- Good: Cache around the token --}}
<form>
    @csrf
    @cache('form-fields')
        {{-- Static form fields --}}
    @endcache
</form>
```

### 3. Use Short Keys

```blade
{{-- Use hashes for long keys --}}
@cache('menu-' . md5(json_encode($menuConfig)))
```

### 4. Set Appropriate TTLs

| Content Type | Recommended TTL |
|--------------|-----------------|
| Static content | 86400 (24h) |
| Navigation menus | 3600 (1h) |
| User dashboards | 300 (5m) |
| Real-time data | Don't cache |

### 5. Clear Cache on Deploy

```bash
# deployment script
php artisan view:clear
php artisan view:cache
```

---

## Debugging Cache

Check if content is cached:

```php
$cache = $viewEngine->getViewCache();

if ($cache->has('sidebar')) {
    echo "Sidebar is cached";
} else {
    echo "Sidebar will be generated";
}
```

---

## Performance Optimizations

### Fast Hashing

The View system uses xxHash (PHP 8.1+) for ~3-4x faster hash generation:

```php
// Automatically uses xxh128 on PHP 8.1+, md5 otherwise
$hash = ViewEngine::fastHash($content);
```

### File Existence Caching

Reduce I/O operations by caching `file_exists()` results:

```php
// Uses cache instead of disk check
$exists = $viewEngine->fileExistsCached($path);

// Clear cache after creating/deleting files
$viewEngine->clearFileExistsCache($path);
$viewEngine->clearFileExistsCache(); // Clear all
```

### OPcache Integration

Automatically compile views to OPcache:

```php
// Enable OPcache hints
$viewEngine->setOpcacheEnabled(true);

// Compile with OPcache
$viewEngine->compileWithOpcache($viewFile, $compiledPath);

// Warm cache with OPcache
$results = $viewEngine->warmCacheWithOpcache();
// ['views_compiled' => 50, 'opcache_compiled' => 50]
```

### Lazy Component Loading

Defer component parsing until needed:

```php
$viewEngine->registerLazyComponent('heavy-chart', function() {
    return file_get_contents('/path/to/chart.plug.php');
});
```

### Production Optimization

One-command production setup:

```php
$results = $viewEngine->optimizeForProduction();
// Enables fast cache
// Warms all views
// Compiles to OPcache
// Returns statistics
```

### Performance Statistics

Monitor cache performance:

```php
$stats = $viewEngine->getPerformanceStats();
// [
//     'path_cache_size' => 25,
//     'file_exists_cache_size' => 100,
//     'preloaded_views' => 5,
//     'component_cache_size' => 10,
//     'opcache_enabled' => true,
//     'opcache_hit_rate' => 98.5,
// ]
```

