# Advanced View Features

This guide covers the high-performance features of the Plug View Engine designed for complex, data-heavy applications.

---

## 1. View Streaming

For pages with large datasets or long-running processes, **Streaming** sends the response in chunks to the browser, significantly improving the Time To First Byte (TTFB).

### Enabling Streaming
Enable it globally via `.env` or programmatically in your controller:

```env
VIEW_STREAMING=true
VIEW_AUTO_FLUSH=50
```

```php
return view('reports.large', $data)->stream();
```

### Automatic Flush
When streaming is enabled, Plugs automatically flushes the buffer during `@foreach` loops (every 50 iterations by default), allowing the user to see the page render progressively.

---

## 2. Compilation Caching

To maximize performance, Plugs compiles your templates into plain PHP files.

### Fast Cache (Production)
In production, enable `Fast Cache` to skip expensive file modification checks:

```php
$viewEngine->setFastCache(true);
```

> [!WARNING]
> When Fast Cache is enabled, you must manually clear the cache during deployment:
> `php theplugs view:clear`

---

## 3. Block Caching

Cache expensive parts of your view independently using the `@cache` directive.

```blade
@cache('sidebar-nav', 3600)
    {{-- This complex menu is cached for 1 hour --}}
    @foreach($categories as $cat)
        <li>{{ $cat->name }}</li>
    @endforeach
@endcache
```

### Dynamic Keys
Include variables in keys for specific versions (e.g., per user):
```blade
@cache('user-settings-' . user.id, 1800)
```

---

## 4. Performance Optimizations

### Support for Generators
Loop directives support **PHP Generators**. This allows you to iterate over millions of database rows with virtually zero memory overhead.

```php
// In Controller
$users = function() {
    foreach (DB::cursor('SELECT * FROM users') as $user) { yield $user; }
};

// In View
@foreach($users() as $user) ... @endforeach
```

### OPcache Integration
Plugs can automatically compile views into the PHP **OPcache**, making template execution nearly as fast as static PHP files.

---

## Next Steps
Now that the frontend is optimized, learn about the [Data Layer](../database/orm.md).
