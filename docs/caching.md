# Smarter Caching & Performance Intelligence

Plugs provides intelligent caching with tiered storage, tagging, and warm-up capabilities.

## Tiered Cache

Automatically use the fastest available storage with fallback.

```php
use Plugs\Cache\TieredCache;

$cache = new TieredCache([
    'memory' => new MemoryCache(),   // Fastest (per-request)
    'file' => new FileCacheDriver(), // Fast (disk)
    'redis' => new RedisCache(),     // Persistent
]);

// Get promotes value to faster tiers
$value = $cache->get('key');
```

### Automatic Promotion

When a value is found in a slower tier, it's automatically promoted to faster tiers for subsequent requests.

## Cache Tagging

Invalidate related cache entries by tag.

```php
use Plugs\Cache\CacheTagManager;

$cache = new CacheTagManager($driver);

// Set with tags
$cache->tags(['users', 'profile'])->set('user:123', $userData);
$cache->tags(['users', 'list'])->set('users:all', $allUsers);

// Flush all 'users' tagged entries
$cache->flushTags(['users']); // Clears user:123 and users:all
```

## Cache Warming

Pre-populate caches on deploy for zero cold-start latency.

### CLI Usage

```bash
# Warm all caches
php plg cache:warm

# Warm specific warmer
php plg cache:warm config
```

### Custom Warmers

```php
// config/cache.php
return [
    'warmers' => [
        'products' => function () {
            return [
                'products:featured' => ['value' => Product::featured()->get()],
                'products:categories' => ['value' => Category::all()],
            ];
        },
    ],
];
```

### Programmatic Usage

```php
use Plugs\Cache\CacheWarmer;

$warmer = CacheWarmer::withDefaults()
    ->register('custom', fn() => ['key' => 'value']);

$warmer->warmAll();
```

## Performance Tips

| Tier | Speed | Persistence | Use Case |
|------|-------|-------------|----------|
| Memory | ~0.001ms | No | Hot data, same request |
| File | ~1ms | Yes | Compiled views, config |
| Redis | ~5ms | Yes | Shared state, sessions |
