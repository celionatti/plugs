# Advanced Database Features

For large-scale applications, Plugs offers advanced features for multi-connection management, performance monitoring, and data integrity.

---

## 1. Multi-Connection Management

Plugs supports multiple database connections and read/write splitting (Master-Slave) out of the box.

### Connection Switching
```php
DB::connection('sqlite')->table('logs')->get();
```

### Read/Write Splitting
Configure `read` and `write` hosts in your `.env` or config files. Plugs will automatically route `SELECT` queries to the read node and others to the write node.

---

## 2. Database Observability

Monitor and debug your queries in real-time.

### Query Logging
Enable query logging to see exactly what SQL is being executed:
```php
DB::enableQueryLog();
// ... run queries ...
$log = DB::getQueryLog();
```

For immediate debugging of a single query, you can also use `toSearchSql()` directly on any query builder instance to get the final SQL with all parameters injected:
```php
$sql = User::where('active', 1)->toSearchSql();
```

### Diagnostics CLI
Use the CLI to inspect your database health:
```bash
php theplugs db:status
php theplugs db:diagnose
```

---

## 3. Backup & Restoration

Automate your database backups.

### Creating Backups
```bash
php theplugs db:backup
```

Backups are securely stored in `storage/backups/` and can be restored using:
```bash
php theplugs db:restore path/to/backup.sql
```

---

## 4. Domain Events

Models dispatch lifecycle events that you can listen to for auditing or side effects.

| Event | Description |
| --- | --- |
| `retrieved` | When a model is retrieved from the DB. |
| `creating` / `created` | Before/After record creation. |
| `updating` / `updated` | Before/After record update. |
| `deleting` / `deleted` | Before/After record deletion. |

---

## 5. Query Caching

Improve application performance by caching expensive database results.

### Basic Caching
Enable caching globally or per model:
```php
User::enableCache(ttl: 3600); // Cache for 1 hour
```

### Strategic Caching
Use the `remember()` method on a specific query:
```php
$stats = DB::table('orders')->remember(600)->sum('price');
```

When caching is enabled, Plugs generates a unique cache key based on the SQL and its bindings. Subsequent identical queries will hit the cache instead of the database.

---

## 6. Resource Management

Efficiently manage database connections and memory, especially in long-running processes or high-concurrency environments.

### Connection Termination
Use the `terminate()` method on any database connection instance to explicitly release PDO instances, clear internal statement pools, and nullify schema caches. This is crucial for preventing memory leaks in worker processes or when handling large numbers of dynamic connections.

```php
use Plugs\Facades\DB;

// After processing a large batch or before worker sleep
DB::connection()->terminate();
```

---

## Next Steps
Secure your application with [Security Best Practices](../security/overview.md).
