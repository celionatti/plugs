# API Resources

API Resources provide a transformation layer between your models and the JSON responses returned by your API. They allow you to control exactly what data is exposed, format values consistently, and include conditional attributes.

> [!TIP]
> Resources are especially useful for APIs where you need to hide sensitive data, format dates, or include related data only when loaded.

## Creating Resources

Use the `make:resource` command to generate a new resource:

```bash
php theplugs make:resource UserResource --model=User
```

### Subdirectories & Namespaces
You can organize your resources into subdirectories. Plugs will automatically create the folders and adjust the namespace:

```bash
php theplugs make:resource V1/UserResource
```
This creates `app/Http/Resources/V1/UserResource.php` with the namespace `App\Http\Resources\V1`.

### Collection Detection
The command is smart enough to detect if you want a collection based on the name:
- `php theplugs make:resource UserResource` -> Standard resource.
- `php theplugs make:resource UserCollection` -> Resource collection.
- `php theplugs make:resource User --collection` -> Generates both `UserResource` and `UserCollection`.

## Data Formatting

By default, Plugs auto-converts your `snake_case` array keys to `camelCase` for JSON responses, matching modern API standards.

### Toggling camelCase
You can disable this globally for a resource class:

```php
class UserResource extends PlugResource
{
    public static bool $camelCase = false;
}
```

### Preserving Specific Keys
Use `$preserveKeys` to prevent conversion for a specific instance:

```php
public function toArray(): array
{
    $this->preserveKeys = true;
    
    return [
        'user_id' => $this->resource->id,
    ];
}
```

## Basic Usage

### Single Resource

```php
use App\Http\Resources\UserResource;

// Create a resource
$resource = UserResource::make($user);

// Return as StandardResponse
return UserResource::make($user)->toResponse();
```

### Resource Collections

```php
// Transform multiple models
return UserResource::collection($users)->toResponse();

// With pagination
return UserResource::collection($users)
    ->withPagination($total, $perPage, $currentPage, '/api/users')
    ->toResponse();
```

### From Model/Collection

```php
// Direct model conversion
return $user->resource(UserResource::class)->toResponse();

// Direct collection conversion
return $users->toResource(UserResource::class)->toResponse();
```

## Conditional Attributes

### `when()` - Include Based on Condition

Only include an attribute when a condition is true:

```php
public function toArray(): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        
        // Only include if user is admin
        'is_admin' => $this->when($this->resource->is_admin, true),
        
        // With default value
        'role' => $this->when($this->resource->role, $this->resource->role, 'guest'),
    ];
}
```

### `whenNotNull()` - Include When Value Exists

```php
'avatar' => $this->whenNotNull($this->resource->avatar_url),
'phone' => $this->whenNotNull($this->resource->phone),
```

### `whenLoaded()` - Include Relationships

Only include relationships when they've been loaded on the model:

```php
public function toArray(): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        
        // Only included when relationship is eager loaded
        'posts' => $this->whenLoaded('posts', function($posts) {
            return PostResource::collection($posts);
        }),
        
        'profile' => $this->whenLoaded('profile'),
    ];
}
```

### `mergeWhen()` - Conditionally Merge Arrays

```php
public function toArray(): array
{
    return [
        'id' => $this->resource->id,
        
        // Merge admin-only fields conditionally
        $this->mergeWhen(auth()->user()->isAdmin(), [
            'internal_notes' => $this->resource->internal_notes,
            'audit_log' => $this->resource->audit_log,
        ]),
    ];
}
```

## Additional Data

Append extra data to the response:

```php
return UserResource::make($user)
    ->additional([
        'permissions' => $user->permissions,
        'token' => $token,
    ])
    ->toResponse();
```

## Collections with Pagination

```php
$users = User::paginate(15);

return UserResource::collection($users->items())
    ->withPagination($users->total(), $users->perPage(), $users->currentPage(), '/api/users')
    ->toResponse();
```

Response format:

```json
{
    "success": true,
    "status": 200,
    "data": [...],
    "meta": {
        "pagination": {
            "total": 100,
            "per_page": 15,
            "current_page": 1,
            "last_page": 7,
            "from": 1,
            "to": 15
        }
    },
    "links": {
        "first": "/api/users?page=1",
        "last": "/api/users?page=7",
        "next": "/api/users?page=2",
        "prev": null
    }
}
```

## Resource Collections Class

For complex collections, create a dedicated collection class:

```bash
php theplugs make:resource UserResource --collection
```

This creates `UserCollection.php`:

```php
namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResourceCollection;

class UserCollection extends PlugResourceCollection
{
    protected string $collects = UserResource::class;
}
```

## Controller Example

```php
namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('profile')->paginate(15);
        
        return UserResource::collection($users->items())
            ->withPagination($users->total(), 15, $users->currentPage(), '/api/users')
            ->toResponse(200, 'Users retrieved');
    }

    public function show($id)
    {
        $user = User::with(['posts', 'profile'])->findOrFail($id);
        
        return UserResource::make($user)
            ->additional(['token' => auth()->user()->createToken()])
            ->toResponse();
    }
}
```

## Customizing Response

Use `withResponse()` to modify the response before it's sent:

```php
return UserResource::make($user)
    ->withResponse(function($response) {
        $response->withHeader('X-Resource-Type', 'User');
    })
    ->toResponse();
```

## API Reference

### PlugResource Methods

| Method | Description |
|--------|-------------|
| `make($resource)` | Create a new resource instance |
| `collection($items)` | Create a collection of resources |
| `toArray()` | Transform the resource (must be implemented) |
| `when($condition, $value, $default)` | Conditional attribute |
| `whenLoaded($relationship, $value)` | Include only when loaded |
| `whenNotNull($value)` | Include only when not null |
| `mergeWhen($condition, $values)` | Conditionally merge array |
| `additional($data)` | Append extra data |
| `toResponse($status, $message)` | Convert to StandardResponse |
| `toJson()` | Convert to JSON string |

### PlugResourceCollection Methods

| Method | Description |
|--------|-------------|
| `make($items)` | Create a new collection |
| `withPagination($total, $perPage, $page, $path)` | Add pagination |
| `withMeta($meta)` | Add meta data |
| `withLinks($links)` | Add HATEOAS links |
| `toResponse($status, $message)` | Convert to StandardResponse |
