# Database: Security & Advanced Features

The Plugs framework provides enterprise-grade security features for your database connections, including read/write splitting, automated auditing, and a safety layer for raw queries.

## Read/Write Splitting

Plugs allows you to separate your Read and Write database traffic. This is useful for scaling applications that use a primary/replica database architecture.

### Configuration

In your `config/database.php` file, you can define separate `read` and `write` host configurations:

```php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST', '192.168.1.10'),
            env('DB_READ_HOST_2', '192.168.1.11'),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_HOST', '127.0.0.1'),
        ],
    ],
    'sticky' => true,
    'driver' => 'mysql',
    // ...
],
```

- **Load Balancing**: Providing an array of hosts will cause Plugs to randomly select a host for each connection attempt.
- **Sticky Connections**: When `sticky` is enabled, if a write operation occurs during a request, Plugs will continue to use the "Write" connection for subsequent "Read" operations in the same request. This prevents "read-after-write" consistency issues.

## Connection Auditing

All critical connection events, including failures and potential security risks, are logged to `storage/logs/security_audit.log`.

The audit log includes:
- **Connection Failures**: CRITICAL logs when the framework cannot reach the database.
- **Query Errors**: WARNING logs for SQL syntax errors or constraint violations.
- **Dangerous Queries**: ALERT logs when the [Query Guard](#query-guard) detects unsafe operations.

## Query Guard

The **Query Guard** is a framework-level defense mechanism that protects your database from accidental or malicious mass updates and deletes.

### Safety Checks

The Query Guard automatically detects and alerts on `UPDATE` or `DELETE` statements that lack a `WHERE` clause. These operations are often unintentional and can lead to catastrophic data loss.

### Transient Failure Handling

Plugs automatically retries database connections if a transient error occurs (like a temporary network hiccup). It will attempt to reconnect up to 3 times with an exponential backoff before throwing a `RuntimeException`.

## SSL Support

To enable encrypted connections to your database, you can provide the SSL CA certificate path in your `.env` file:

```dotenv
DB_SSL_CA=/path/to/ca-cert.pem
```

The framework will automatically pass the `PDO::MYSQL_ATTR_SSL_CA` option to the connection.
