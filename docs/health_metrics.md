# Health Checks & Metrics

Plugs provides standardized health checks and metrics for monitoring.

## Health Endpoints

### Basic Health Check

```php
// routes/api.php
$router->get('/health', [HealthController::class, 'index']);
```

**Response:**
```json
{
    "status": "healthy",
    "timestamp": "2024-01-15T10:30:00+00:00",
    "checks": {
        "database": {"status": "ok", "latency_ms": 2.5},
        "cache": {"status": "ok", "latency_ms": 0.3},
        "disk": {"status": "ok", "used_percent": 45.2},
        "memory": {"status": "ok", "used_percent": 32.1}
    }
}
```

### Kubernetes Probes

```php
$router->get('/health/liveness', [HealthController::class, 'liveness']);
$router->get('/health/readiness', [HealthController::class, 'readiness']);
$router->get('/health/detailed', [HealthController::class, 'detailed']);
```

## Metrics

### Enable Metrics Middleware

```php
// Middleware stack
$middlewareStack = [
    MetricsMiddleware::class,
    // ...
];
```

### Prometheus Endpoint

```php
$router->get('/metrics', [MetricsController::class, 'prometheus']);
```

**Output:**
```
# TYPE http_requests_total counter
http_requests_total{route="/api/users",method="GET",status="200"} 1523
# TYPE http_request_duration_seconds histogram
http_request_duration_seconds_count{route="/api/users"} 1523
http_request_duration_seconds_sum{route="/api/users"} 45.67
```

## OpenAPI Documentation

### Auto-Serve Docs

```php
$router->get('/api/docs', [OpenApiController::class, 'ui']);
$router->get('/api/docs/spec', [OpenApiController::class, 'spec']);
```

Docs auto-update when routes change.
