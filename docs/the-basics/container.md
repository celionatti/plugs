# Service Container (The Brain)

The Plugs Service Container is the heart of the framework, responsible for managing object instantiation and dependency injection. It supports advanced features like auto-wiring, different lifetimes, contextual binding, and lazy loading.

## Registration

You can bind services to the container using several methods:

### Basic Binding

```php
$container->bind(LoggerInterface::class, FileLogger::class);
```

### Singletons

Singletons are instantiated once and shared across the entire application lifecycle (persistent in long-running environments like Swoole).

```php
$container->singleton(DatabaseConnection::class, function($container) {
    return new DatabaseConnection(config('database'));
});
```

### Scoped Services

Scoped services are persistent within a single request but are flushed after the request terminates. This is critical for request-specific state in long-running servers.

```php
$container->scoped(Breadcrumbs::class);
```

### Lazy Loading

Lazy services are only instantiated when a method on the service is actually called. This is great for heavy services that might not be used in every request.

```php
$container->lazy(HeavyReportGenerator::class);
```

## Contextual Binding

Sometimes you may have two classes that utilize the same interface, but you wish to inject different implementations into each class.

```php
$container->when(PhotoController::class)
          ->needs(FilesystemInterface::class)
          ->give(LocalFilesystem::class);

$container->when(VideoController::class)
          ->needs(FilesystemInterface::class)
          ->give(S3Filesystem::class);
```

You can also bind primitive values:

```php
$container->when(DatabaseLogger::class)
          ->needs('$tableName')
          ->give('user_logs');
```

## Auto-wiring

The container automatically resolves dependencies by inspecting the constructor's type-hints.

```php
class UserController {
    public function __construct(UserRepository $users) {
        $this->users = $users;
    }
}

// No binding required if UserRepository can be auto-instantiated!
$controller = app(UserController::class);
```

## Method Injection

You can also invoke methods and have the container inject their dependencies:

```php
app()->call([$controller, 'update'], ['id' => 1]);
```

## Lifecycle Management

In long-running environments, the `AbstractKernel::terminate()` method automatically calls `$container->forgetScoped()`, ensuring a clean slate for the next request.
