# Logging

Plugs provides a robust, PSR-3 compliant logging system that allows you to record information about your application's behavior. It features a multi-channel manager that can route logs to various destinations.

## Usage

### The Log Facade

The `Log` facade provides static methods for all PSR-3 log levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, and `debug`.

```php
use Plugs\Facades\Log;

Log::info('User {id} logged in.', ['id' => $user->id]);
Log::error('Something went wrong!');
```

### The logger() Helper

You can also use the `logger()` helper function:

```php
logger('An informational message.');
logger('An error occurred.', 'error', ['context' => 'data']);

// Get the logger instance
$logger = logger();
```

## Multi-Channel Logging

By default, Plugs uses the `stack` channel which can log to multiple destinations simultaneously. You can log to specific channels using the `channel` method:

```php
Log::channel('daily')->info('Something happened today.');
Log::channel('stderr')->error('This goes to the error log.');
```

### Available Drivers

| Driver   | Description                                                  |
| -------- | ------------------------------------------------------------ |
| `single` | A single file-based logger.                                  |
| `daily`  | A daily rotating log file with automatic pruning.            |
| `stderr` | Logs output directly to the PHP `stderr` stream.             |
| `stack`  | A special channel that delegates to multiple other channels. |

## Context Interpolation

The logger automatically replaces placeholders in the message with values from the context array:

```php
Log::info('Hello {name}', ['name' => 'John']);
// Result: [timestamp] INFO: Hello John {"name":"John"}
```

## Configuration

Logging is configured in `config/logging.php`. You can set the default channel and define multiple channels:

```php
'default' => env('LOG_CHANNEL', 'stack'),

'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily'],
    ],
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/plugs.log'),
        'max_files' => 14,
    ],
],
```

### Daily Rotation

The `daily` driver creates a new log file for each day (e.g., `plugs-2026-02-23.log`) and automatically deletes files older than the `max_files` setting.

### Structured JSON Logging

When the log format is set to `json` (if supported by the driver), the framework will output logs as single-line JSON objects, ideal for centralized logging systems.

```json
{
  "timestamp": "2026-01-29 12:00:00",
  "level": "INFO",
  "message": "User 123 logged in",
  "context": { "user_id": 123 }
}
```

## Modular Nature

The logging system is provided by the `Log` module. If you wish to disable logging entirely (e.g., for maximum performance in a microservice), you can do so before the framework boots:

```php
use Plugs\Framework;

Framework::disableModule('Log');
```

---

> [!NOTE]
> When the `Log` module is disabled, the `Log` facade and `logger()` helper will remain available but will silently discard all log entries.
