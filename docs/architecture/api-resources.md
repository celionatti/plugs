# API Resources (Transformation Layer)

API Resources act as a **Transformation Layer** between your Eloquent models and the JSON responses your application returns. They allow you to decouple your database schema from your public API interface, ensuring that changes to the database don't necessarily break your API.

## Why Use Resources?
- **Data Encapsulation**: Expose only the fields you want.
- **Naming Consistency**: Keep JSON keys in `camelCase` even if the database uses `snake_case`.
- **Relationship Control**: Avoid deep nesting or N+1 issues by conditionally loading related data.
- **Formatting**: Centralize logic for formatting dates, currency, or user names.

---

## 🏗️ Creating a Resource

Generate a resource using the CLI:

```bash
php theplugs make:resource UserResource --model=User
```

This creates `app/Http/Resources/UserResource.php`.

---

## 🛠️ Resource Implementation

The `toArray()` method defines how the model should be transformed into JSON.

```php
namespace App\Http\Resources;

use Plugs\Http\Resources\PlugResource;

class UserResource extends PlugResource
{
    public function toArray(): array
    {
        return [
            'id'    => $this->resource->id,
            'name'  => $this->resource->first_name . ' ' . $this->resource->last_name,
            'email' => $this->resource->email,
            
            // Conditional data: only if eager loaded
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            
            // Metadata
            'createdAt' => $this->resource->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
```

---

## 📦 Using in Controllers

You can return a resource or a collection of resources directly from your controller.

```php
namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function show($id)
    {
        $user = User::with('posts')->findOrFail($id);
        
        return UserResource::make($user)->toResponse();
    }

    public function index()
    {
        $users = User::paginate(15);
        
        return UserResource::collection($users)->toResponse();
    }
}
```

---

## 🛡️ Conditional Loading

One of the most powerful features of Resources is the ability to include data only when certain conditions are met:

- **`whenLoaded('relation')`**: Only includes the relation if it was already eager-loaded.
- **`when($condition, $value)`**: Includes the value only if the condition is true.
- **`mergeWhen($condition, $array)`**: Merges multiple fields into the response conditionally.

```php
'is_admin' => $this->when(auth()->user()->isAdmin(), true),
```

---

## 💡 Best Practices

1. **Keep it declarative**: Avoid complex logic in `toArray()`.
2. **Standardize collections**: Use `Resource::collection($data)` for lists to maintain consistency.
3. **Handle nulls**: Use `whenNotNull()` for optional fields.
