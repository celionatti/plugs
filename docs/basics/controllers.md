# Controllers

Controllers are the central hubs of your application's logic. They receive requests from the router, interact with your models or services, and return a response to the user.

---

## 1. Basic Usage

A standard controller extends the `Plugs\Base\Controller\Controller` class. This gives you access to several helper methods for common tasks.

```php
namespace App\Http\Controllers;

use Plugs\Base\Controller\Controller;
use Psr\Http\Message\ResponseInterface;

class UserController extends Controller
{
    public function show($id): ResponseInterface
    {
        $user = $this->db->table('users')->find($id);

        return $this->view('user.profile', ['user' => $user]);
    }
}
```

### Generating Controllers
Scaffold a new controller using the CLI:
```bash
php theplugs make:controller UserController
```

---

## 2. Dependency Injection

Plugs uses a powerful **Service Container** to automatically inject dependencies into your controller's constructor.

```php
class PostController extends Controller
{
    public function __construct(
        protected PostService $service
    ) {}

    public function index()
    {
        return $this->view('posts.index', [
            'posts' => $this->service->getAll()
        ]);
    }
}
```

---

## 3. Response Helpers

The base controller provides several convenient methods for returning different types of responses:

| Method | Description |
| --- | --- |
| `view($name, $data)` | Renders a template and returns an HTML response. |
| `json($data, $status)` | Returns a JSON-formatted response. |
| `redirect($url, $status)` | Redirects the user to a new URL. |
| `back($fallback)` | Redirects the user back to their previous location. |
| `file($path, $name)` | Sends a file directly to the browser (inline). |
| `download($path, $name)` | Forces the browser to download a file. |

---

## 4. Input and Validation

### Accessing Input
Use the `input()` or `all()` methods to retrieve data from the request:
```php
$email = $this->input('email');
$data = $this->all();
```

### Inline Validation
The `validate()` method automatically handles errors and redirects the user back with old input if validation fails.

```php
public function store()
{
    $validated = $this->validate([
        'title' => 'required|string|max:200',
        'body'  => 'required',
    ]);

    // Data is already validated here
    Post::create($validated);

    return $this->redirect('/blog')->withSuccess('Post created!');
}
```

---

## 5. Single Action Controllers (Actions)

If a controller only performs one specific task, you can define an `__invoke` method. This allows you to reference the controller class directly in your routes.

```php
// Route
Route::post('/subscribe', SubscribeController::class);

// Controller
class SubscribeController extends Controller
{
    public function __invoke()
    {
        // Handle subscription logic
        return $this->json(['message' => 'Subscribed!']);
    }
}
```

---

## Next Steps
Now that you can handle logic in controllers, learn how to filter requests using [Middleware](./middleware.md).
