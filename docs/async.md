# Async & Concurrency

Plugs provides first-class support for asynchronous programming using PHP Fibers and Promises. This allows you to write non-blocking code that looks synchronous.

## Core Concepts

### Async & Await

Use the global `async` and `await` helpers to manage non-blocking tasks.

```php
use Plugs\Http\Client\AsyncClient;

async(function () {
    $client = new AsyncClient();
    
    // Non-blocking request
    $promise = $client->getAsync('https://api.example.com/data');
    
    // Suspend execution until resolved
    $response = await($promise);
    
    echo $response->getBody();
});
```

### Promises

The `Plugs\Concurrency\Promise` class wraps Guzzle promises, providing a fluent interface.

```php
use Plugs\Concurrency\Promise;

$promise = new Promise($guzzlePromise);

$promise->then(fn($val) => echo $val)
        ->catch(fn($err) => echo $err);
```

### Parallel Execution

Run multiple tasks concurrently and wait for all of them.

```php
use Plugs\Concurrency\Async;

$results = Async::parallel([
    'users' => fn() => $client->getAsync('/users'),
    'posts' => fn() => $client->getAsync('/posts'),
]);

// $results['users'] and $results['posts'] are resolved values.
```

## HTTP Client

The `Plugs\Http\Client\AsyncClient` provides methods like `getAsync`, `postAsync`, etc., which return `Promise` objects compatible with `await()`.

```php
$client = new AsyncClient();
$promise = $client->postAsync('/users', ['json' => ['name' => 'John']]);
$response = await($promise);
```
