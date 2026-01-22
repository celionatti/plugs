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

Log files are stored in `storage/logs/plugs.log`.
