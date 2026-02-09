# Microservices & Event-Driven Patterns

Plugs provides native support for building microservices and event-driven applications.

## Event Bus

The event bus enables publish-subscribe messaging between services.

### Publishing Events

```php
use Plugs\EventBus\EventBusManager;

// Publish an event
EventBusManager::publish('user.created', [
    'user_id' => 123,
    'email' => 'user@example.com',
]);

// With delay
EventBusManager::publish('reminder.send', $data, ['delay' => 300]);
```

### Subscribing to Events

```php
EventBusManager::subscribe('user.created', function (array $payload, array $meta) {
    // Handle event
    sendWelcomeEmail($payload['email']);
});
```

### Drivers

| Driver | Use Case | Config |
|--------|----------|--------|
| `sync` | Local development | Default |
| `redis` | Production (Redis Streams) | `EVENT_BUS_DRIVER=redis` |

## Workers

Lightweight workers process messages from the event bus.

### CLI Usage

```bash
# Start a worker
php plg worker:run --queue=default --concurrency=4

# With options
php plg worker:run \
    --queue=emails \
    --name=email-worker \
    --timeout=30 \
    --max-jobs=1000
```

### Programmatic Usage

```php
use Plugs\Worker\Worker;

$worker = new Worker('email-processor', concurrency: 4);

$worker->on('email.send', function (array $payload) {
    // Process email
});

$worker->run();
```

## Orchestrator

Manage distributed workers with auto-scaling.

```bash
# Start orchestrator with 3 workers
php plg orchestrator:run --workers=3 --queue=default

# With auto-scaling
php plg orchestrator:run \
    --workers=2 \
    --auto-scale \
    --min-workers=1 \
    --max-workers=10
```

### Scaling Hints

Workers provide metrics for scaling decisions:
- `jobs_per_second` - Throughput
- `avg_process_time_ms` - Latency
- `should_scale_up` / `should_scale_down` - Recommendations
