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

### Protecting Routes

Protect your routes using the `auth` middleware alias:

```php
use Plugs\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/settings', [SettingsController::class, 'index']);
});
```

Or apply it to a single route:

```php
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth');
```

Unauthenticated users will be redirected to `/login` or receive a 401 response for JSON requests.

### Guest Middleware

Use the `guest` middleware to restrict routes to unauthenticated users only (e.g., login and registration pages):

```php
Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/register', [RegisterController::class, 'index'])->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
});
```

Authenticated users hitting these routes will be redirected to `/dashboard`.

### Middleware Aliases

Middleware aliases are defined in `config/middleware.php`:

| Alias   | Class                                          |
| :------ | :--------------------------------------------- |
| `auth`  | `Plugs\Http\Middleware\AuthenticateMiddleware` |
| `guest` | `App\Http\Middleware\GuestMiddleware`          |
| `csrf`  | `Plugs\Http\Middleware\CsrfMiddleware`         |

## API Token Authentication

For lightweight API authentication (similar to Laravel Sanctum), you can use the `HasApiTokens` trait in your User model.

### Setup

```php
use Plugs\Security\Auth\Traits\HasApiTokens;

class User implements Authenticatable
{
    use HasApiTokens;
}
```

### Issuing Tokens

You can issue a token for a user using the `createToken` method:

```php
public function login(Request $request)
{
    $user = User::where('email', $request->email)->first();

    if ($user && Hash::check($request->password, $user->password)) {
        $token = $user->createToken('my-app-token');

        return response()->json(['token' => $token]);
    }
}
```

### Authenticating API Requests

The framework automatically looks for a `Bearer` token in the `Authorization` header. You can then access the authenticated user via `Auth::user()` as usual.
