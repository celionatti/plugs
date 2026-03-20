# Container & Service Providers

The **Dependency Injection (DI) Container** is the primary tool for managing class dependencies and performing dependency injection. **Service Providers** are the central place to configure and register these bindings.

---

## 1. The Container

Plugs uses an advanced, reflection-based container that can automatically resolve your class dependencies.

### Automatic Injection
```php
class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    public function show($id)
    {
        // $this->service is automatically injected
    }
}
```

### Manual Binding
```php
$container->bind(HttpClientInterface::class, GuzzleClient::class);
```

---

## 2. Service Providers

Service Providers are the "bootstrapper" for your application. They are stored in `app/Providers/`.

### The `register` Method
Use this method to bind things into the container. Do not perform any other logic here.

```php
public function register()
{
    $this->container->singleton(PaymentGateway::class, function ($app) {
        return new StripeGateway(config('services.stripe.key'));
    });
}
```

### The `boot` Method
This method is called after all other service providers have been registered. You may use it to register event listeners, routes, or middleware.

```php
public function boot()
{
    // ...
}
```

---

## Next Steps
Understand the [Request Lifecycle](./lifecycle.md).
