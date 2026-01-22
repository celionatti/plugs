# Authentication

PLUGS provides a robust authentication system out of the box, supporting traditional login, registration, and Social Login (OAuth).

## Configuration

The authentication configuration is located at `config/auth.php`. By default, it uses the `web` guard with a session driver and the `users` provider.

### Database Migration

Ensure your `users` table exists. A standard schema might look like this:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NULL,
    avatar VARCHAR(255) NULL,
    provider_id VARCHAR(255) NULL,
    provider VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### The User Model

Your User model must implement `Plugs\Security\Auth\Authenticatable`.

```php
namespace App\Models;

use Plugs\Database\Model;
use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\AuthenticatableTrait;

class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected string $table = 'users';
}
```

## Manual Authentication

You can authenticate users manually using the `Auth` facade.

### Attempting Login

```php
use Plugs\Facades\Auth;

$credentials = [
    'email' => $request->input('email'),
    'password' => $request->input('password'),
];

if (Auth::attempt($credentials)) {
    // Authentication passed...
    return redirect('/dashboard');
}

return back()->withError('Invalid credentials');
```

### Checking Auth Status

```php
if (Auth::check()) {
    // The user is logged in...
}

if (Auth::guest()) {
    // The user is not logged in...
}
```

### Retrieving the User

```php
$user = Auth::user();
$id = Auth::id();
```

---

## Social Authentication (Socialite)

PLUGS includes a separate OAuth library for handling "Login with Google", "Login with GitHub", etc.

### Configuration

Add your credentials to `config/services.php`:

```php
return [
    'github' => [
        'client_id' => $_ENV['GITHUB_CLIENT_ID'],
        'client_secret' => $_ENV['GITHUB_CLIENT_SECRET'],
        'redirect' => 'http://your-app.test/auth/github/callback',
    ],
];
```

### Routing

You need two routes: one to redirect the user to the provider, and one to handle the callback.

```php
use Plugs\Facades\Socialite;

// 1. Redirect to Provider
$router->get('/auth/{driver}', function ($driver) {
    return Socialite::driver($driver)->redirect();
});

// 2. Handle Callback
$router->get('/auth/{driver}/callback', function ($driver) {
    try {
        $socialUser = Socialite::driver($driver)->user();
        
        // $socialUser->getId()
        // $socialUser->getName()
        // $socialUser->getEmail()
        // $socialUser->getAvatar()
        
        // Find or Create User
        $user = User::firstOrCreate([
            'email' => $socialUser->getEmail()
        ], [
            'name' => $socialUser->getName(),
            'password' => null, // Social users might not have passwords
            'provider' => $driver,
            'provider_id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
        ]);
        
        Auth::login($user);
        
        return redirect('/dashboard');
        
    } catch (\Exception $e) {
        return redirect('/login')->withError('Login failed: ' . $e->getMessage());
    }
});
```

### Supported Drivers

Currently supported drivers:
- `github`
- `google`

You can extend functionality by adding more drivers to `Plugs\Security\OAuth\SocialiteManager`.
