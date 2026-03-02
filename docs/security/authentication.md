# Authentication

Plugs provides a powerful, flexible, multi-guard authentication system for managing user sessions, API tokens, and modern passwordless security. It is highly extensible, allowing you to seamlessly register custom authentication drivers and manage events at every step of the authentication lifecycle.

---

## Architecture Overview

The authentication system revolves around **Guards** and **Providers**:

- **Guards** define _how_ users are authenticated for each request (e.g., sessions with cookies, JWT tokens in headers, passwordless cryptographic keys).
- **Providers** define _how_ users are retrieved from your persistent storage (e.g., an Eloquent database provider or an LDAP server).

Plugs comes with four built-in guards:

1. `session`: Traditional cookie-based stateful authentication.
2. `token`: Personal Access Tokens loaded via database lookups.
3. `jwt`: JSON Web Tokens for stateless API interactions.
4. `key`: Passwordless cryptography using Ed25519 digital signatures (Challenge-Response).

---

## Database Scaffolding

Before using the authentication features, you must generate the necessary database migrations using the Plugs CLI.

### Personal Access Tokens

If you plan to use API tokens (`token` guard), publish the `personal_access_tokens` table schema:

```bash
php theplugs auth:install
```

### Key-Based Identity

If you intend to use passwordless authentication (`key` guard), publish the modifier migration to add `public_key` and schema fields to your `users` table:

```bash
php theplugs identity:install
```

After generating the migrations, run them:

```bash
php theplugs migrate
```

---

## Setup

First, ensure your user model implements the `Plugs\Security\Auth\Authenticatable` interface.

```php
namespace App\Models;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Traits\HasApiTokens;

class User implements Authenticatable
{
    // Required if you plan to use API Tokens
    use HasApiTokens;

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPassword(): string { return $this->password; }
    public function getRememberToken(): string { return $this->remember_token ?? ''; }
    public function setRememberToken(string $value): void { $this->remember_token = $value; }
    public function getRememberTokenName(): string { return 'remember_token'; }
}
```

---

## Routing & Middleware

You can protect your routes using the `auth` middleware. You may optionally specify which guard to use.

```php
use Plugs\Facades\Route;
use Plugs\Http\Middleware\AuthenticateMiddleware;

// Uses the default guard (usually 'session')
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Specifying a guard
Route::get('/api/data', [ApiController::class, 'index'])
     ->middleware(new AuthenticateMiddleware('token'));

// Multi-guard (fallbacks)
Route::get('/mixed', [ApiController::class, 'mixed'])
     ->middleware(new AuthenticateMiddleware('jwt', 'session'));
```

Unauthenticated users will be redirected to `/login` or receive a `401 Unauthorized` response for JSON requests.

---

## The Auth Facade

The `Auth` facade provides a simple interface to interact with the authentication manager. By default, it uses the guard specified in your `config('auth.defaults.guard')` configuration.

### Logging In

```php
use Plugs\Facades\Auth;

$credentials = ['email' => 'user@example.com', 'password' => 'secret'];

if (Auth::attempt($credentials)) {
    // Authentication passed...
    return redirect('/dashboard');
}

// Log in a specific user instance manually
Auth::login($user);

// Log in via User ID
Auth::loginUsingId(1);
```

### Checking Authentication

```php
if (Auth::check()) {
    $user = Auth::user(); // Get the Authenticatable instance
    $id = Auth::id();     // Get the User ID
}
```

### Logging Out

```php
Auth::logout();
```

### Specifying a Guard on the Fly

```php
$user = Auth::guard('jwt')->user();
```

---

## Unified Auth Experience

Plugs is designed with a "Unified Auth" philosophy. Whether you are using traditional **Session-based** login or modern **Passwordless (Key-based)** identity, you use the same `Auth` class.

This allows you to write components (like a `NavBar` or `ProfileController`) once, and they will work regardless of how the user authenticated.

- `Auth::check()`: Returns true if the user is authenticated via any enabled guard.
- `Auth::user()`: Returns the `Authenticatable` user instance.
- `Auth::logout()`: Clears the session and invalidates the authentication state for the current guard.

### Passwordless Authentication

For detailed information on setting up and using the cryptographic identity system, see the [Passwordless Authentication Guide](./passwordless-auth.md).

---

## Defining Custom Guards

You can implement entirely custom authentication mechanisms by registering them in a Service Provider using `Auth::extend()`.

```php
use Plugs\Facades\Auth;

public function boot()
{
    Auth::extend('ldap', function ($container, $name, $config) {
        $provider = Auth::createUserProvider($config['provider'] ?? null);

        return new \App\Security\LdapGuard($name, $provider);
    });
}
```

_Note: Your custom guard must implement `Plugs\Security\Auth\GuardInterface`, `StatefulGuardInterface`, or `StatelessGuardInterface` depending on its behavior._

---

## Events

The authentication lifecycle broadcasts several events via the `Plugs\Event\Dispatcher`. You can define listeners to hook into these actions for auditing, logging, or business logic.

### Standard Events

- `Plugs\Security\Auth\Events\AuthAttempting`: Triggered before credentials are verified.
- `Plugs\Security\Auth\Events\AuthSucceeded`: Triggered upon successful login.
- `Plugs\Security\Auth\Events\AuthFailed`: Triggered when login fails.
- `Plugs\Security\Auth\Events\LogoutOccurred`: Triggered when a user logs out.

### Identity Events (Key-Based)

If you're using the passwordless identity system:

- `Plugs\Security\Auth\Events\IdentityRegistered`: Triggered when a new physical key or passphrase is bound to an account.
- `Plugs\Security\Auth\Events\IdentityAuthenticated`: Triggered upon successful cryptographic challenge completion.
- `Plugs\Security\Auth\Events\IdentityRecovered`: Triggered when an account is recovered and the master keys are rotated.

### Example Listener Registration

```php
use Plugs\Facades\Event;
use Plugs\Security\Auth\Events\AuthFailed;

Event::listen(AuthFailed::class, function (AuthFailed $event) {
    Log::warning("Login failed for guard: {$event->guard}");
});
```
