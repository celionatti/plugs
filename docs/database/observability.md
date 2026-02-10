# Database Observability

Plugs provides built-in observability for your database layer, allowing you to track performance, detect slow queries, and monitor model lifecycle events.

## Declarative Observability

Use the `#[Observable]` attribute to enable monitoring on your models.

```php
use Plugs\Database\Attributes\Observable;

#[Observable(
    trackMetrics: true,
    slowQueryThreshold: 0.5, // 500ms
    alertOnSlow: true
)]
class Order extends PlugModel {
    // ...
}
```

### Configuration Options
- **trackMetrics**: Toggle query and lifecycle metric tracking.
- **slowQueryThreshold**: Set the threshold (in seconds) for what constitutes a slow query for this model.
- **alertOnSlow**: Whether to emit security audit alerts when slow queries are detected.

## Metrics Manager

The `MetricsManager` singleton aggregates all database metrics collected during a request.

```php
use Plugs\Database\Observability\MetricsManager;

$metrics = MetricsManager::getInstance();

// Get total query count
$count = $metrics->getQueryCount();

// Get total query time
$time = $metrics->getTotalTime();

// Get slow query details
$slow = $metrics->getSlowQueries();

// Get report
$report = $metrics->getReport();
```

## Per-Model Metrics

Models using the `HasObservability` trait automatically track:
- **Load Count**: How many times the model was retrieved.
- **Save Count**: How many times the model was saved.
- **Delete Count**: How many times the model was deleted.
- **Average Performance**: Average time for operations.

```php
$stats = Order::getMetricsSummary();
echo "Total Orders Loaded: " . $stats['operations']['retrieved']['count'];
```

## Slow Query Alerts

When a slow query is detected:
1. It is recorded in the `MetricsManager`.
2. An `ALERT` entry is written to `storage/logs/security_audit.log`.
3. (Optional) Custom event listeners can be triggered.
