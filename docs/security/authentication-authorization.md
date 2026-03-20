# Authentication & Authorization

Plugs provides a robust, multi-guard system for managing user identity and access control.

---

## 1. Authentication (Guards)

Guards define how users are authenticated for each request.

### Available Guards
- **`session`**: Traditional cookie-based auth.
- **`jwt`**: Stateless JSON Web Tokens.
- **`token`**: Simple API tokens (Personal Access Tokens).
- **`key`**: Modern **Passwordless** identity using Ed25519 digital signatures.

### Usage
Protect routes using the `auth` middleware:
```php
Route::middleware('auth:jwt')->get('/api/user', $callback);
```

Check authentication via the `Auth` facade:
```php
if (Auth::check()) {
    $user = Auth::user();
}
```

---

## 2. Passwordless Identity

Plugs features a high-security, passwordless system. Users bind a physical key or cryptographic device to their account, eliminating the risks of password theft.

### Installation
```bash
php theplugs identity:install
```

For more details, see the deep-dive [Passwordless Guide](./passwordless-auth.md).

---

## 3. Authorization (Gates & Policies)

Authorization determines if a user has permission to perform a specific action.

### Gates (Closure-based)
Ideal for simple, one-off checks:
```php
Gate::define('update-post', function (User $user, Post $post) {
    return $user->id === $post->user_id;
});
```

### Policies (Class-based)
Organize logic around a specific Model:
```php
// In PostPolicy
public function update(User $user, Post $post) { ... }

// Usage in Controller
$this->authorize('update', $post);
```

---

## 4. Rate Limiting

Protect your APIs and sensitive routes from brute-force attacks.

```php
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/login', $callback);
});
```
*Allows 60 requests per minute per IP.*

---

## Next Steps
Automate your workflows with [Console Commands](../infrastructure/console.md).
