# Dependency Injection Container

The Plugs Framework features a powerful, modern Dependency Injection (DI) Container. It supports auto-wiring, scoped services, and attribute-based contextual binding.

## 1. Basic Binding

You can bind interfaces to implementations in your Service Providers.

```php
// Bind a new instance every time
$container->bind(UserRepositoryInterface::class, UserRepository::class);

// Bind a singleton (shared instance)
$container->singleton(Database::class, MySQLDatabase::class);

// Bind an existing instance
$container->instance('config', $configArray);
```

## 2. Scoped Services (Per-Request)

Scoped services are created once per "scope" (e.g., per HTTP Request) and then reused. They are flushed when the scope ends.

```php
// Created once per request
$container->scoped(CurrentContext::class);
```

To use this in your application lifecycle (e.g., in a middleware):
```php
// Clear scoped instances at the end of a request
Container::getInstance()->forgetInstances(); 
// Note: This clears ALL instances. For precise control, use logic to clear only scoped ones if needed, 
// though typically frameworks flush everything between requests in long-running processes.
```

## 3. Contextual Binding with Attributes

You can inject specific implementations using PHP 8 Attributes, without writing configuration in a provider.

```php
use Plugs\Container\Attributes\Inject;

class reportService
{
    public function __construct(
        #[Inject('reports_database')] 
        protected Database $db
    ) {}
}
```

This tells the container: "When resolving `ReportService`, inject the service bound to key `reports_database` into `$db`."

## 4. Visual Debugging

The Container includes an `Inspector` to visualize your dependency graph.

```php
$container = Container::getInstance();
$inspector = new Inspector();
$inspector->enable();
$container->setInspector($inspector);

// ... resolve your application ...

// Get Mermaid JS graph syntax
echo $inspector->generateMermaid();
```

This output can be rendered specifically in tools like Mermaid Live Editor to visualize complexity and cycles.
