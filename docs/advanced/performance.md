# Performance & Concurrency

Plugs is engineered for high-performance, featuring advanced caching mechanisms and support for modern PHP concurrency.

---

## 1. Caching Strategies

Use the `Cache` facade to store expensive data and reduce database load.

```php
use Plugs\Facades\Cache;

$users = Cache::remember('active_users', 3600, function () {
    return User::where('active', 1)->get();
});
```

### Supported Drivers
- **`file`**: Simple, persistent storage (default).
- **`redis`**: High-performance, in-memory storage.
- **`database`**: Reliable shared storage.

---

## 2. Concurrency (Fibers)

Plugs leverages PHP 8.1+ Fibers for non-blocking task execution, allowing you to run multiple heavy operations (like API calls) in parallel.

```php
use Plugs\Support\Concurrency;

[$results] = Concurrency::run([
    fn() => ai()->prompt("Analyze X"),
    fn() => Http::get("https://api.external.com/data"),
]);
```

---

## 3. State Optimization

Running `php theplugs optimize` in production caches:
- **Routes**: Eliminates filesystem hits during route matching.
- **Config**: Consolidates multiple configuration files into one.
- **Container**: Caches reflection data for dependency injection.

---

## Next Steps
Monitor your application with [Observability & Health](./observability.md).
