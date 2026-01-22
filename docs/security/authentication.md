# Authentication

Plugs provides a flexible, multi-guard authentication system for managing user sessions and security.

## Setup

Your User model should implement the `Plugs\Security\Auth\Authenticatable` interface:

```php
use Plugs\Security\Auth\Authenticatable;
use Plugs\Database\Traits\HasQueryBuilder;

class User implements Authenticatable
{
    use HasQueryBuilder;

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword(): string { return $this->password; }
}
```

## Usage

### The Auth Facade

```php
use Plugs\Facades\Auth;

// Attempt login
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    // Authentication passed...
    return redirect('/dashboard');
}

// Check if authenticated
if (Auth::check()) {
    $user = Auth::user();
}

// Logout
Auth::logout();
```

### Helpers

```php
$user = user(); // Current authenticated user
$id = auth()->id(); // Current user ID
```

## Middleware

Protect your routes using the `AuthenticateMiddleware`:

```php
$router->get('/dashboard', [DashboardController::class, 'index'])
       ->middleware(\Plugs\Http\Middleware\AuthenticateMiddleware::class);
```

Unauthenticated users will be redirected to `/login` or receive a 401 response for JSON requests.
