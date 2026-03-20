# Responses

Plugs provides multiple ways to return data to your users, from standardized JSON for APIs to session-based flash messages for web applications.

---

## 1. API Responses

The `api_response()` helper ensures your JSON outputs follow a consistent, predictable structure.

```php
return api_response(['user' => $user], 200, 'User retrieved successfully');
```

Standard structure:
```json
{
    "success": true,
    "status": 200,
    "message": "...",
    "data": { ... }
}
```

---

## 2. API Resources (Transformation)

Resources provide a layer between your Models and the final JSON response, allowing you to format data and include conditional attributes.

### Creating a Resource
```bash
php theplugs make:resource UserResource
```

### Usage
```php
public function toArray(): array
{
    return [
        'id' => $this->resource->id,
        'name' => $this->resource->name,
        'email' => $this->when(auth()->user()->isAdmin(), $this->resource->email),
        'posts' => PostResource::collection($this->whenLoaded('posts')),
    ];
}
```

---

## 3. Flash Messages

Flash messages are stored in the session for exactly one request, perfect for success notifications.

### Setting Messages
```php
return redirect('/dashboard')->withSuccess('Profile updated!');
```

### Rendering in Views
Add the `<x-flash />` component to your main layout:
```html
<x-flash />
```

---

## Next Steps
Organize your application logic into [Controllers](./controllers.md).
