# Dependency Injection Container

Plugs features a PSR-11 compliant container with advanced features for high-performance applications.

## 1. Scoped Bindings (Per-Request singletons)

Scoped services are instances that are shared within a single request cycle but are fresh for every new request. This is perfect for State managers or Request-specific contexts.

```php
// In a Service Provider
$this->container->scoped(PaymentProcessor::class);
```

## 2. Contextual Binding with Attributes

Inject specific implementations directly via PHP 8 attributes. This reduces boilerplate in your providers.

```php
use Plugs\Container\Attributes\Inject;

class OrderService
{
    public function __construct(
        #[Inject('fast_cache')] 
        protected CacheInterface $cache
    ) {}
}
```

## 3. Visual Dependency Inspector

Debug complex dependency chains by generating visual graphs.

```php
use Plugs\Container\Inspector;

$inspector = new Inspector();
$inspector->trace(function() {
    return app(ComplexService::class);
});

// Outputs a Mermaid.js diagram
echo $inspector->toMermaid();
```

## 4. Auto-Wiring

The container uses reflection to automatically resolve dependencies for any class it instantiates.

```php
class UserController
{
    // UserRepository and ValidationService are auto-injected
    public function __construct(
        protected UserRepository $users,
        protected ValidationService $val
    ) {}
}
```
