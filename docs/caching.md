# Caching & Performance

Plugs implements a high-performance **Tiered Caching** system that ensures your application stays snappy even under heavy load.

## 1. Tiered Cache (L1/L2)

The framework can use multiple cache layers simultaneously:
- **L1 (Local)**: Fast in-memory/APC cache for hot data.
- **L2 (Distributed)**: Redis or Memcached for shared data across nodes.

```php
// Automatically handles tiered lookups
$data = Cache::remember('users.count', 3600, fn() => User::count());
```

## 2. Tagged Caching

Group related cache entries and invalidate them all at once.

```php
Cache::tags(['people', 'authors'])->put('John', $user, $seconds);

// Flush all 'people' entries
Cache::tags('people')->flush();
```

## 3. Cache Warmer

Proactively warm your cache before users hit the application.

```bash
# Invoke your custom warmers
php theplugs cache:warm
```

## 4. Route & Config Caching

Eliminate bootstrap overhead in production by freezing your application state.

```bash
# Compile everything for maximum speed
php theplugs optimize
```
