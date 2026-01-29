# Health Checks

Plugs includes a built-in health check system that allows you to monitor the status of your application and its core dependencies.

## The Health Check Endpoint

By default, the framework provides a `HealthController` that can be mapped to a route like `/up` or `/health`.

```php
// routes/web.php

$router->get('/up', \Plugs\Http\Controllers\HealthController::class);
```

### JSON Response

When you visit the health check endpoint, you will receive a JSON response indicating the status of various components:

```json
{
    "status": "up",
    "timestamp": "2026-01-29T12:00:00+00:00",
    "environment": "production",
    "checks": {
        "database": {
            "status": "ok"
        },
        "cache": {
            "status": "ok"
        },
        "storage": {
            "status": "ok"
        }
    }
}
```

### HTTP Status Codes

- **200 OK**: All systems are operational.
- **503 Service Unavailable**: One or more critical systems (like the database) are down.

## Monitored Services

| Service | Check Performed |
|---------|-----------------|
| **Database** | Executes a simple `SELECT 1` query to verify connection. |
| **Cache** | Verifies read/write capability to the configured cache driver. |
| **Storage** | Checks if the `storage` directory is writable. |

This endpoint is perfect for use with monitoring tools like **Pingdom**, **Uptime Robot**, or **Kubernetes** liveness/readiness probes.
