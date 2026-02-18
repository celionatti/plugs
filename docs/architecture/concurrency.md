# Async & Concurrency

Plugs leverages PHP Fibers to provide a non-blocking execution model that scales.

## 1. Async & Await

Use the `async()` and `await()` helpers to execute promises without blocking the main process loop.

```php
$result = async(function() {
    $response = await(Http::getAsync('...'));
    return $response->json();
});
```

## 2. Parallel Processing

Need to run multiple tasks at once? `Async::parallel` handles orchestration and wait logic for you.

```php
use Plugs\Concurrency\Async;

[$users, $posts] = Async::parallel([
    fn() => Http::get('.../users'),
    fn() => Http::get('.../posts')
]);
```

## 3. Async HTTP Client

The built-in client is built on Guzzle with Fiber-aware handlers.

```php
use Plugs\Http\Client\AsyncClient;

$client = new AsyncClient();
$promise = $client->getAsync('/api/data');
```

## 4. Why Fibers?

Unlike ReactPHP or Swoole, Plugs' Fiber-based async allows you to write code that *looks* synchronous but performs asynchronously under the hood, maintaining high readability.
