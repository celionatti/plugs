# Query Middleware Pipeline

The Query Middleware Pipeline allows you to intercept and modify database queries before they are executed, and transform their results after execution. This is useful for cross-cutting concerns like multi-tenancy, security, and auditing.

## Interface

Middleware must implement the `Plugs\Database\Contracts\QueryMiddleware` interface:

```php
namespace App\Database\Middleware;

use Plugs\Database\Contracts\QueryMiddleware;
use Plugs\Database\QueryBuilder;
use Closure;

class MyMiddleware implements QueryMiddleware
{
    public function handle(QueryBuilder $builder, Closure $next)
    {
        // 1. Modify the builder (e.g., apply a scope)
        $builder->where('tenant_id', 1);

        // 2. Pass to the next middleware/destination
        $result = $next($builder);

        // 3. (Optional) Transform the results
        return $result;
    }
}
```

## Usage

### Applying to a Model Query

You can apply middleware to any model query using the `through()` method:

```php
use App\Models\User;
use App\Database\Middleware\MyMiddleware;

$users = User::through(MyMiddleware::class)->get();

// You can also pass multiple middleware
$users = User::through(MiddlewareA::class, MiddlewareB::class)->get();

// Or as an array
$users = User::through([MiddlewareA::class, MiddlewareB::class])->get();
```

### Closure Middleware

For quick modifications, you can use a Closure:

```php
$users = User::through(function($builder, $next) {
    $builder->where('active', 1);
    return $next($builder);
})->get();
```

## Execution Methods

The pipeline is triggered when calling any of the following execution methods on the `QueryBuilder`:

- `get()`
- `first()`
- `count()`
- `update()`
- `delete()`
- `toSql()` (useful for debugging middleware effects)

## Built-in Examples

### Multi-Tenancy

```php
$posts = Post::through(new TenantMiddleware($tenantId))->get();
```

### Soft Deletes

```php
$users = User::through(SoftDeleteMiddleware::class)->get();
```
