# Microservices & Event-Driven Architecture

Plugs is designed for distributed systems. It provides first-class support for Event Sourcing and Worker Orchestration.

## 1. Event Bus (Redis Streams)

For production environments, Plugs uses Redis Streams via the `redis` driver to ensure message persistence and delivery guarantees.

```bash
# .env
EVENT_BUS_DRIVER=redis
REDIS_HOST=127.0.0.1
```

### Publishing & Subscribing

```php
// Dispatch to the cloud
EventBus::publish('user.signed_up', ['id' => 1]);

// Listen in a long-running process
EventBus::subscribe('user.signed_up', function($data) {
    Mail::to($data['email'])->send(new WelcomeMail());
});
```

## 2. Distributed Workers

Plugs workers are lightweight Fiber-based processes. You can run them via the CLI:

```bash
# Run a specific queue with 4 concurrent fibers
php theplugs worker:run --queue=emails --concurrency=4
```

## 3. Worker Orchestrator (Auto-Scaling)

The Orchestrator manages worker pools and scales them based on queue pressure.

```bash
# Start orchestrator with auto-scaling enabled
php theplugs orchestrator:run --auto-scale --min-workers=2 --max-workers=20
```

### Scaling Metrics
- **Throughput**: Jobs processed per second.
- **Latency**: Average job execution time.
- **Backpressure**: Queue length vs. Worker capacity.

## 4. Health Monitoring

Monitor your cluster's health via the built-in collector:

```bash
php theplugs health:check
```
