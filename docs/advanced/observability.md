# Observability & Health

Plugs provides built-in tools for monitoring your application's health and debugging issues in real-time.

---

## 1. Health Checks

Automatically monitor your application's vital signs (Database, Redis, Storage) using the Health Check system.

### Accessing the Status
```bash
php theplugs health:check
```

Responses can also be exposed via an API endpoint for external monitoring services.

---

## 2. Debugging Tools

### The Debug Bar
When `APP_DEBUG=true`, Plugs can inject a performance bar into your views, showing:
- Executed SQL queries.
- Memory usage.
- Request lifecycle timing.
- Session and Auth state.

### Diagnostics CLI
Use the CLI to identify configuration issues or environment mismatches:
```bash
php theplugs db:diagnose
```

---

## 3. Real-Time Metrics
Monitor request throughput and error rates through the `monitoring` module (if enabled).

---

## Next Steps
Secure your application with [Security Overview](../security/overview.md).
