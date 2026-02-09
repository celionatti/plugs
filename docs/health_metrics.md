# Health, Metrics & Observability

Understand exactly how your application is performing with built-in monitoring tools.

## 1. Health Checks

Plugs provides a standard endpoint for load balancers and monitoring tools.

- **Endpoint**: `/health` (configurable)
- **Checks**: Database connection, Redis availability, Disk space, and custom logic.

```php
// Register a custom check in a Provider
Health::addCheck('api_external', fn() => Http::get('...')->ok());
```

## 2. Metrics Collector (Prometheus)

Expose application metrics (RAM, CPU, Request Latency) in a format Prometheus understands.

- **Endpoint**: `/metrics`
- **Dashboards**: Ships with a pre-built Grafana template.

## 3. OpenAPI & Swagger UI

Keep your API documentation alive and interactive.

- **Docs**: `/api/docs`
- **JSON**: `/api/openapi.json`

## 4. Profiler

In `local` mode, a floating profiler bar provides deep insights into:
- Executed SQL queries
- Registered Routes
- Memory usage per middleware
- Event Bus activity
