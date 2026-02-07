# Concurrency & Fibers

Plugs includes a powerful concurrency module built on top of PHP 8.1 Fibers and Guzzle Promises. This allows you to perform non-blocking I/O operations (like HTTP requests) in parallel, significantly improving the performance of your application.

## Introduction

In traditional PHP applications, I/O operations are synchronous and blocking. If you need to make three API calls, you have to wait for the first one to finish before starting the second.

With Plugs Concurrency, you can start multiple operations at once and wait for them all to complete together.

## Basic Usage

The `parallel` helper function is the easiest way to run concurrent tasks:

```php
use Plugs\Http\HTTPClient;

$client = new HTTPClient();

$results = parallel([
    'users' => fn() => $client->getAsync('https://api.example.com/users'),
    'posts' => fn() => $client->getAsync('https://api.example.com/posts'),
]);

// Access results immediately
$users = $results['users'];
$posts = $results['posts'];
```

### The Async Helper

You can also use the `Async` facade or global `async` helper to manage fibers manually.

```php
use Plugs\Concurrency\Async;

// Start a fiber
async(function () {
    // ... logic running in fiber
});
```

## Integration with HTTP Client

The built-in `HTTPClient` is pre-configured to work with the concurrency system. Methods like `getAsync`, `postAsync`, etc., return a `PromiseInterface` that `parallel` understands.

## Under the Hood

The `Plugs\Concurrency\FiberManager` handles the scheduling of fibers. when a fiber suspends (e.g., waiting for a promise), the manager switches execution to another fiber. Once the promise settles, the original fiber is resumed.
