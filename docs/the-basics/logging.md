# Logging

Plugs provides a robust, PSR-3 compliant logging system that allows you to record information about your application's behavior.

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

## Context Interpolation

The logger automatically replaces placeholders in the message with values from the context array:

```php
Log::info('Hello {name}', ['name' => 'John']);
// Result: [timestamp] INFO: Hello John {"name":"John"}
```

## Configuration

The logger stores files in `storage/logs/plugs.log`. You can configure the log format in your `.env` file or environment:

```env
LOG_FORMAT=json
```

### Structured JSON Logging

When the log format is set to `json`, the framework will output logs as single-line JSON objects, which are ideal for centralized logging systems like ELK, Datadog, or AWS CloudWatch.

**Example Output:**
```json
{"timestamp":"2026-01-29 12:00:00","level":"INFO","message":"User 123 logged in","context":{"user_id":123}}
```

This format makes it much easier to query and aggregate logs across multiple distributed servers.
