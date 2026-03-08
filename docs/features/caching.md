# Caching & Performance

Plugs implements a high-performance **Multi-Layer Caching** system that ensures your application stays snappy even under heavy load.

## 1. Tiered Application Cache (L1/L2)

The framework can use multiple cache layers simultaneously for your application data:

- **L1 (Local)**: Fast in-memory/APC cache for hot data.
- **L2 (Distributed)**: Redis or Memcached for shared data across nodes.

```php
// Automatically handles tiered lookups
$data = Cache::remember('users.count', 3600, fn() => User::count());

// Store indefinitely
Cache::rememberForever('settings', fn() => Setting::all());
```

## 2. Redis Cache Setup

Plugs supports Redis through two primary methods: the native **phpredis** extension (fastest) and the **Predis** Composer package (easiest to install).

### Option A: Native Extension (phpredis)

Recommended for production environments.

1. **Install phpredis**:
   ```bash
   sudo apt-get install php-redis
   ```
2. **Configure `.env`**:
   ```env
   CACHE_DRIVER=redis
   ```
3. **Registration** (if manual):
   ```php
   use Plugs\Cache\Drivers\RedisCacheDriver;
   Cache::extend('redis', fn() => new RedisCacheDriver());
   ```

### Option B: Composer Package (Predis)

Ideal for environments where you cannot install PHP extensions.

1. **Install Predis**:
   ```bash
   composer require predis/predis
   ```
2. **Configure `.env`**:
   ```env
   CACHE_DRIVER=predis
   ```
3. **Registration**:
   ```php
   use Plugs\Cache\Drivers\PredisCacheDriver;
   Cache::extend('predis', fn() => new PredisCacheDriver());
   ```

### Common Redis Configuration

Both drivers use the same environment variables for connection:

```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_CACHE_DB=1
CACHE_PREFIX=plugs_cache:
```

## 3. Tagged Caching

Group related cache entries and invalidate them all at once.

```php
Cache::tags(['people', 'authors'])->put('John', $user, $seconds);

// Flush all 'people' entries
Cache::tags('people')->flush();
```

## 3. Framework Optimization Caches

Eliminate bootstrap overhead in production by freezing your application state. These caches are critical for achieving sub-100ms render times.

### Route Caching

Serializes all your routes into a single fast-loading file, enabling **O(1)** route matching.

```bash
php theplugs route:cache
```

### Container Caching

Caches the dependency injection container's reflection data and aliases.

```bash
php theplugs container:cache
```

### Configuration Caching

Compiles all configuration files and `.env` variables into a single array for instant loading.

```bash
php theplugs config:cache
```

### All-in-One Optimization

```bash
# Compile everything for maximum speed
php theplugs optimize
```

## 4. Cache Warmer

Proactively warm your cache before users hit the application.

```bash
# Invoke your custom warmers
php theplugs cache:warm
```

---

## 🛠️ Debugging Cache

You can monitor your cache performance in real-time using the **Profiler Bar** at the bottom of your browser.

- **Hits (H)**: Shown in green. Indicates data was successfully retrieved from the cache.
- **Misses (M)**: Shown in red. Indicates data was not found in the cache and had to be generated/fetched.

Example floating bar display: `Cache: 5H / 2M`

For a full breakdown of every cache operation during the current request, open the **Profiler** and check the **Application** tab.
