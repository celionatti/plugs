# Production Optimization Guide

To ensure your Plugs application runs at peak performance in production, follow this guide for caching and system optimization.

## 1. Environment Configuration

Modern PHP applications rely heavily on environment variables. In production, ensure these are optimized:

-   **`APP_ENV=production`**: Enables production-level optimizations across the framework.
-   **`APP_DEBUG=false`**: Disables the debug bar, detailed error pages, and logging overhead.
-   **`PROFILER_ENABLED=false`**: Disables the performance profiler (crucial for speed).
-   **`DB_MONITORING=false`**: Disables the `RelationMonitor` and N+1 detection backtraces.

## 2. Command Caching

Plugs provides several caching commands that significantly reduce I/O and CPU usage by pre-compiling configuration, routes, and views.

### Configuration Caching
Combines all configuration files into a single PHP file.
```bash
php theplugs config:cache
```
*   **When:** Run this **after** every deployment when your `.env` or `config/` files have changed.
*   **Clear:** `php theplugs config:clear`

### Route Caching
Pre-compiles all your route definitions into a fast-lookup array.
```bash
php theplugs route:cache
```
*   **When:** Run this **after** every deployment if you have added or modified routes.
*   **Note:** For applications with hundreds of routes, this can save up to 10-20ms per request.
*   **Clear:** `php theplugs route:clear`

### Router Reflection Caching
The router utilizes an internal reflection cache to store metadata about controller methods. This significantly reduces the overhead of dependency injection by avoiding expensive PHP Reflection calls on every request. This optimization is automatic and works in conjunction with the route cache to ensure high-velocity request handling.

### View Caching
Compiles all your PlugView templates into optimized PHP code.
```bash
php theplugs view:cache
```
*   **When:** Run this **before** or **during** deployment to avoid the "first-hit" slowness for your users.
*   **Clear:** `php theplugs view:clear`

## 3. Composer Optimization

Ensure your autoloader is optimized to avoid expensive class-map lookups.

```bash
composer install --optimize-autoloader --no-dev
```
*   **When:** During your build or deployment process.

## 4. PHP & OpCache

Plugs is highly optimized for PHP 8.2+ with OpCache.

-   **Enable OpCache**: Ensure `opcache.enable=1` in your `php.ini`.
-   **Enable JIT**: If your app is CPU-bound (complex logic), enable PHP 8 JIT.
-   **OpCache Preloading**: For high-traffic apps, use Plugs' preloading capabilities to load the entire framework into memory on start.

## 5. Deployment Workflow (Best Practice)

A typical deployment script should look like this:

```bash
# 1. Pull latest code
git pull origin main

# 2. Install dependencies (Optimized)
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php theplugs migrate --force

# 4. Warm up caches (Do this AFTER code is pulled)
php theplugs config:cache
php theplugs route:cache
php theplugs view:cache

# 5. Restart queue workers (if using Redis/Database queue)
php theplugs queue:restart
```

> [!TIP]
> **Order Matters**: Always run `config:cache` *before* `route:cache` because the router might depend on configuration values.
