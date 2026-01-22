# Service Providers

Service providers are the central place of ALL application bootstrapping. Your own application, as well as all of Plugs's core services, are bootstrapped via providers.

## Introduction

In a service provider, you can register bindings in the service container, adding event listeners, middleware, and even routes.

## Writing Service Providers

All service providers extend the `Plugs\Support\ServiceProvider` class. This abstract class requires you to define two methods: `register` and `boot`.

### The Register Method

In the `register` method, you should **only bind services into the service container**. You should never attempt to register any event listeners, routes, or any other piece of functionality within the `register` method.

```php
<?php

namespace App\Providers;

use Plugs\Support\ServiceProvider;

class RiakServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Connection::class, function ($app) {
            return new Connection(config('riak'));
        });
    }
}
```

### The Boot Method

This method is called after all other service providers have been registered, meaning you have access to all other services that have been registered by the framework.

```php
public function boot()
{
    //
}
```

## Registering Providers

All service providers are registered in the `config/app.php` configuration file. Just add your class to the `providers` array:

```php
'providers' => [
    App\Providers\AppServiceProvider::class,
],
```

## Generating Providers

You can generate a new provider using the `make:provider` command:

```bash
php theplugs make:provider PaymentServiceProvider
```
