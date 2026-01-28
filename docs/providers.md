# Service Providers

Service providers are the central place of all Plugs application bootstrapping. Your own application, as well as all of Plugs's core services, are bootstrapped via providers.

But, what do we mean by "bootstrapped"? In general, we mean **registering** things, including registering service container bindings, event listeners, middleware, and even routes. Service providers are the central place to configure your application.

If you open the `config/app.php` file, you will see a `providers` array. These are all of the service provider classes that will be loaded for your application. By default, a set of Plugs core service providers are listed in this array. These providers bootstrap the core Plugs components, such as the mailer, queue, cache, and database. You will add your own providers to this array to bootstrap your own application.

## Writing Service Providers

All service providers extend the `Plugs\Support\ServiceProvider` class. Most service providers contain a `register` and a `boot` method. Within the `register` method, you should **only bind things into the [service container](container.md)**. You should never attempt to register any event listeners, routes, or any other piece of functionality within the `register` method.

The CLI generator can create a new provider using the `make:provider` command:

```bash
php plugs make:provider RiakServiceProvider
```

### The Register Method

As mentioned previously, within the `register` method, you should only bind things into the service container. If you attempt to register event listeners or routes within the `register` method, you might accidentally use a service that is provided by a service provider which has not loaded yet.

Within any of your service provider methods, you always have access to the `$app` property, which provides access to the service container:

```php
<?php

namespace App\Providers;

use Plugs\Support\ServiceProvider;
use App\Services\Riak\Connection;

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

So, what if we need to register a view composer within our service provider? This should be done within the `boot` method. **This method is called after all other service providers have been registered**, meaning you have access to all other services that have been registered by the framework.

```php
<?php

namespace App\Providers;

use Plugs\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
```

## Deferred Providers

If your provider is **only** registering bindings in the [service container](container.md), you may choose to defer its registration until one of the registered bindings is actually needed. Deferring the loading of such a provider will improve the performance of your application, as it is not loaded from the filesystem on every request.

To defer the loading of a provider, set the `defer` property to `true` and define a `provides` method. The `provides` method should return the service container bindings registered by the provider:

```php
<?php

namespace App\Providers;

use App\Services\Riak\Connection;
use Plugs\Support\ServiceProvider;

class RiakServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected bool $defer = true;

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

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [Connection::class];
    }
}
```

## Registering Providers

All service providers are registered in the `config/app.php` configuration file. This file contains a `providers` array where you can list the class names of your service providers. By default, a set of Plugs core service providers are listed in this array. These providers bootstrap the core Plugs components, such as the mailer, queue, cache, and database.

To register your provider, simply add it to the array:

```php
'providers' => [
    // Other Service Providers

    App\Providers\AppServiceProvider::class,
],
```
