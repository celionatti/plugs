# Caching

Plugs features a high-performance, driver-based caching system (PSR-16) to speed up your application by storing frequently accessed data.

## Usage

### The Cache Facade

```php
use Plugs\Facades\Cache;

// Store data
Cache::set('key', 'value', 3600); // TTL in seconds

// Retrieve data
$value = Cache::get('key', 'default');

// Check existence
if (Cache::has('key')) {
    // ...
}

// Remove data
Cache::delete('key');

// Clear all cache
Cache::clear();
```

### The cache() Helper

```php
// Get/Set
cache('key', 'value');
$value = cache('key');

// Multiple operations
cache(['a' => 1, 'b' => 2]);

// Atomic Remember
$value = cache_remember('users_count', function() {
    return User::count();
}, 600);
```

### Utility Helpers

- `cache_has($key)`
- `cache_forget($key)`
- `cache_flush()`

## Drivers

The default driver is `file`, which stores cache entries in `storage/cache/`.
