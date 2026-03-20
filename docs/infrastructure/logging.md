# Logging

Plugs includes a robust logging system based on the PSR-3 standard, allowing you to record errors, warnings, and general application activity.

---

## 1. Usage

Use the `Log` facade to write messages to your log files.

```php
use Plugs\Facades\Log;

Log::info('User logged in', ['user_id' => $user->id]);
Log::error('Payment failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
```

### Log Levels
Supports all RFC 5424 levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, and `debug`.

---

## 2. Channels

By default, logs are written to `storage/logs/plugs.log`. You can configure multiple channels in `config/logging.php`:

- **`single`**: A single file updated daily.
- **`daily`**: Rotated log files for each day.
- **`syslog`**: System logger.
- **`errorlog`**: PHP's error log.

---

## 3. Contextual Information

Providing context helps in debugging:
```php
Log::debug('API Request sent', [
    'endpoint' => $url,
    'response_time' => $ms,
    'status' => $code
]);
```

---

## Next Steps
Process heavy tasks in the background with [Queues & Scheduling](./queues-scheduling.md).
