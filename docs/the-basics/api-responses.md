# API Responses

The Plugs framework provides a powerful and consistent way to return JSON data from your application. Using standardized responses ensures that your frontend developers or API consumers always receive data in a predictable format.

## Standard Response Structure

All standardized responses follow this JSON structure:

```json
{
    "success": true,
    "status": 200,
    "message": "Success",
    "timestamp": "2026-01-25T21:00:00Z",
    "data": { ... },
    "meta": { ... },
    "links": { ... }
}
```

- **success**: Boolean indicating if the operation was successful.
- **status**: The HTTP status code.
- **message**: A human-readable message.
- **timestamp**: ISO 8601 timestamp of the response.
- **data**: The main payload (object or array).
- **meta**: Metadata, such as pagination information.
- **links**: Hypermedia links for navigation.

## Usage

### Using the Helper

The `api_response()` helper is the easiest way to create a standardized response.

```php
return api_response(['user' => $user], 200, 'User retrieved successfully');
```

In production mode (`APP_ENV=production`), sensitive debug information is automatically stripped from the response.

### Adding Meta and Links

You can chain methods to add extra information to your response:

```php
return api_response($users)
    ->withMeta(['total_count' => 100])
    ->withLinks(['next' => '/api/users?page=2']);
```

### Custom Headers

You can also add custom headers to your response:

```php
return api_response($data)
    ->withHeader('X-API-Version', '2.0');
```

## Model Integration

Models and Collections come with built-in support for standardized responses.

### Single Model

```php
$user = User::find(1);
return $user->toResponse();
```

### Collections

```php
$users = User::all();
return $users->toResponse();
```

## Convenience Query Methods

You can fetch and respond in a single line using convenience methods on your Models:

| Method | Description |
|--------|-------------|
| `findResponse($id)` | Finds a record and returns a standardized response (handles 404). |
| `allResponse()` | Returns all records as a standardized response. |
| `getResponse()` | Returns the results of a query builder as a standardized response. |
| `firstResponse()` | Returns the first matching record as a standardized response (handles 404). |

### Example

```php
// Automatically returns 404 if user doesn't exist
return User::findResponse($id);

// Get filtered results as API response
return User::where('active', 1)->getResponse();
```

## Practical Controller Example

Here is how you might use these features in a typical API controller:

```php
namespace App\Http\Controllers;

use App\Models\User;
use Plugs\Http\StandardResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // One-liner for simple indices
        return User::where('status', 'active')->getResponse();
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Efficiently fetch and respond with 404 handling
        return User::findResponse($id, ['id', 'name', 'email'], 200, 'User profile found');
    }

    /**
     * Create a new resource with a custom success message.
     */
    public function store()
    {
        $user = User::create(request()->all());

        return $user->toResponse(201, 'Account created successfully')
            ->withHeader('X-Registration-Source', 'Web');
    }
}
```

## Advanced Customization

### Conditional Metadata

You might want to add metadata based on certain conditions:

```php
$response = User::getResponse();

if (auth()->isAdmin()) {
    $response->withMeta(['admin_stats' => $stats]);
}

return $response;
```

### Error Handling

Standardized errors can be generated manually:

```php
if (!$paymentSuccessful) {
    return StandardResponse::error('Transaction failed', 402)
        ->withMeta(['reason' => 'insufficient_funds']);
}
```

## Production-Ready Pagination

The `paginate()` method and its variants are designed for production security and usability.

### Security: Per-Page Limits

To prevent Denial of Service (DoS) attacks where a user requests a massive number of records, Plugs enforces a `MAX_PER_PAGE` limit (default: 100). You can customize this on a per-model basis:

```php
class Post extends PlugModel
{
    public const MAX_PER_PAGE = 50;
}
```

### HATEOAS: Absolute Links

Standardized pagination responses include absolute URLs for easy navigation by frontend clients:

```json
"links": {
    "first": "https://api.example.com/posts?page=1",
    "last": "https://api.example.com/posts?page=10",
    "next": "https://api.example.com/posts?page=3",
    "prev": "https://api.example.com/posts?page=1"
}
```

### Pagination Response Helper

Use `paginateResponse()` to get a fully formatted API response in one call:

```php
// In a Controller
public function index()
{
    return Post::paginateResponse(perPage: 15);
}
```
