# Advanced Database Connection Management

The framework includes enterprise-grade database features designed for high-performance applications, including connection pooling, load balancing, and read/write splitting.

## Table of Contents

- [Connection Pooling](#connection-pooling)
- [Load Balancing](#load-balancing)
- [Read/Write Splitting](#readwrite-splitting)
- [Environment Configuration](#environment-configuration)
- [Diagnostics & Monitoring](#diagnostics--monitoring)

---

## Connection Pooling

Database connections are expensive to establish. The built-in connection pool significantly reduces overhead by maintaining a set of active connections ready for use.

### Features

- **Auto-Persistent Connections**: Automatically uses `PDO::ATTR_PERSISTENT` to reuse connections across PHP-FPM requests.
- **Pre-Warming**: Eagerly establishes `min_connections` when the application boots.
- **Exponential Backoff**: Efficiently waits for available slots when the pool is exhausted.
- **Thread Safety**: Includes Swoole lock support for async environments (Swoole, RoadRunner).

### Configuration

Polling is configured in `config/database.php` under your connection settings:

```php
'mysql' => [
    // ...
    'pool' => [
        'enabled' => env('DB_POOL_ENABLED', false),
        'min_connections' => 2,    // Always keep 2 ready
        'max_connections' => 10,   // Cap at 10 to protect DB
        'idle_timeout' => 300,     // Close after 5 mins idle
        'connection_timeout' => 30, // Wait 30s before failing
    ],
],
```

Enable via `.env`:

```env
DB_POOL_ENABLED=true
```

---

## Load Balancing

Distribute database traffic across multiple servers using strategies like Random, Round-Robin, or Weighted selection.

### Supported Strategies

- **`random`**: (Default) Picks a random host. Good for identical servers.
- **`round-robin`**: Cycles sequentially (Server A -> B -> C -> A). Ensures fair distribution.
- **`weighted`**: Routes more traffic to powerful servers based on assigned weights.

### Failover & Health Tracking

If a database server goes down:

1. The load balancer detects the failure.
2. It automatically retries with the next available host.
3. The failed host is marked as "down" for a cooldown period (default 30s).
4. After the cooldown, it is tentatively retried.

### Configuration

Add the `load_balancing` block to your database config:

```php
'load_balancing' => [
    'strategy' => 'round-robin',    // 'random', 'round-robin', 'weighted'
    'health_check_cooldown' => 30,  // Seconds to avoid dead hosts
    'max_failures' => 3,            // Failures before marking down
],
```

For weighted load balancing, structure your hosts array like this:

```php
'host' => [
    ['host' => 'primary-db.local', 'weight' => 10],
    ['host' => 'secondary-db.local', 'weight' => 5],
],
```

---

## Read/Write Splitting

Optimize performance by routing `SELECT` queries to read replicas and `INSERT/UPDATE/DELETE` to the primary.

### Setup

Define `read` and `write` arrays in your connection config:

```php
'mysql' => [
    'read' => [
        'host' => ['replica1.db','replica2.db'], // Auto-load balanced!
    ],
    'write' => [
        'host' => 'primary.db',
    ],
    'sticky' => true,
    'sticky_window' => 0.5,
],
```

### Sticky Reads

To prevent "replication lag" (where a user writes data but can't see it immediately because the replica hasn't updated), "Sticky Reads" are enabled by default.

- **How it works**: After a write, the current request will route subsequent reads to the _write_ connection for a short window (default 0.5s).
- **Config**:
  - `'sticky' => true`
  - `'sticky_window' => 2.0` (increase if replicas are slow)

---

## Environment Configuration

The framework automatically tunes pool settings based on your environment (`production`, `development`, `testing`) to balance performance and resource usage.

You can customize this in your `AppServiceProvider` or bootstrap code:

```php
use Plugs\Database\Connection;

Connection::configurePoolForEnvironment('production');
```

| Environment | Min | Max | Backoff | Validation |
| ----------- | --- | --- | ------- | ---------- |
| Production  | 5   | 20  | 10s     | Strict     |
| Development | 1   | 5   | 30s     | Lax        |
| Testing     | 1   | 3   | 5s      | None       |

---

## Diagnostics & Monitoring

### Health Checks

The framework performs "smart health checks":

- **Write Connection**: Pings if idle for > 1 hour.
- **Read Replicas**: Independently checked. If a replica dies, it reconnects to another one automatically.

### Connection Stats

You can inspect pool usage at runtime:

```php
use Plugs\Database\Connection;

$stats = Connection::getPoolStats('mysql');
// Returns:
// [
//    'total_connections' => 5,
//    'available_connections' => 3,
//    'in_use_connections' => 2,
//    'runtime' => 'swoole',
//    'persistent_enabled' => true
// ]
```
