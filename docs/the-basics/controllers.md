# Controllers

Instead of defining all of your request handling logic as closures in your route files, you may wish to organize this behavior using "controller" classes.

## Basic Controllers

Controllers live in the `app/Http/Controllers` directory. A basic controller should extend the `Plugs\Base\Controller\Controller` class.

```php
<?php

namespace App\Http\Controllers;

use Plugs\Base\Controller\Controller;
use Psr\Http\Message\ResponseInterface;

class UserController extends Controller
{
    /**
     * Show the profile for a given user.
     */
    public function show(string $id): ResponseInterface
    {
        $user = User::find($id);

        return $this->view('user.profile', ['user' => $user]);
    }
}
```

---

## 🛠️ Base Controller Features

The `Plugs\Base\Controller\Controller` class provides several helpful methods:

### Rendering Views
```php
return $this->view('home.index', ['data' => $data]);
```

### Fluent Redirects
```php
// Redirect to URL
return $this->redirect('/home');

// Redirect back with old input and errors
return $this->back()->withInput()->withErrors($errors);

// Success/Error shortcuts with optional titles
return $this->redirectWithSuccess('/posts', 'Post saved!', 'System');
return $this->redirectWithError('/login', 'Invalid password', 'Auth');
```

### Validation
Pass a `FormRequest` class name or an array of rules:

```php
// Using FormRequest class (Returns sanitized data)
$data = $this->validate(CreatePostRequest::class);

// Using rules array
$data = $this->validate($request, [
    'title' => 'required|max:255',
]);
```

### Response Helpers
```php
return $this->json(['status' => 'ok']);
return $this->file($path);
return $this->download($path, 'report.pdf');
```

---

## 🏗️ Dependency Injection

The Plugs container is used to resolve all controllers. As a result, you may type-hint any dependencies your controller may need in its constructor.

```php
namespace App\Http\Controllers;

use App\Services\UserRepository;
use Plugs\Base\Controller\Controller;

class UserController extends Controller
{
    public function __construct(
        protected UserRepository $users
    ) {}

    public function index()
    {
        return $this->users->all();
    }
}
```
