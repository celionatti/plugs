# Passwordless Authentication (Passkeys/Identity)

Plugs provides a state-of-the-art passwordless authentication system built on the **WebAuthn/FIDO2** standards. This system allows users to log in using biometric sensors (TouchID, FaceID), security keys (YubiKey), or browser-stored passkeys without ever needing a traditional password.

---

## The Unified Auth Experience

One of the most powerful features of Plugs is that **passwordless authentication uses the exact same `Auth` facade as traditional authentication.** This means your application logic doesn't need to change significantly to support both.

### Unified Usage

Whether a user logged in with a password or a cryptographic key, the way you interact with them is identical:

```php
use Plugs\Facades\Auth;

// Check if authenticated
if (Auth::check()) {
    $user = Auth::user();
    $id = Auth::id();
}

// Log out (works for both session types)
Auth::logout();
```

---

## Setup & Installation

To enable passwordless features, you must install the identity scaffolding which adds the necessary columns to your `users` table and creates the tracking tables for device trust and cryptographic challenges.

### 1. Run Installation

```bash
php theplugs identity:install
php theplugs migrate
```

This command creates/updates:

- `users`: Adds `public_key` and `prompt_ids` columns.
- `identity_challenges`: Stores one-time nonces for secure login.
- `device_tokens`: Tracks trusted browsers/devices.
- `sessions`: Enables database-backed session hardening.

### 2. Configure the User Model

Ensure your `User` model implements the `Authenticatable` interface.

```php
namespace App\Models;

use Plugs\Security\Auth\Authenticatable;

class User implements Authenticatable
{
    // ... standard implementation ...
}
```

---

## Authentication Flow

Passwordless authentication in Plugs typically follows a **two-step verification** process if the device is unrecognized.

### Step 1: Identity Check

The user enters their email. The system checks if the current device is "trusted".

```php
public function check(Request $request) {
    $user = User::where('email', $request->email)->first();

    if (Auth::deviceTrust()->isTrusted($user)) {
        // Instant login if device is recognized!
        Auth::login($user);
        return redirect('/dashboard');
    }

    // Unknown device: Generate a challenge
    $nonce = Identity::challenge($user->email);
    return redirect('/challenge');
}
```

### Step 2: Cryptographic Verification

If the device is new, the user must provide their identity key (Passphrase or Hardware signature).

```php
public function verify(Request $request) {
    // Verify the challenge signature
    $user = Identity::verify($email, $request->passphrase, $nonce);

    if ($user) {
        Auth::login($user);
        Auth::deviceTrust()->trust($user); // Mark this device as trusted
        return redirect('/dashboard');
    }
}
```

---

## Security Hardening

The passwordless system is automatically protected by several framework-level security layers:

### 1. Device Trust

When you call `Auth::deviceTrust()->trust($user)`, the framework generates a unique cryptographic token for that browser. On subsequent visits, the `Auth` guard verifies this token. If a session cookie is stolen but the device token is missing, the user is logged out.

### 2. Single Active Session

By default, the `DeviceTrustManager` enforces a single active session. If a user logs in on a New Device, the framework can automatically invalidate all other sessions to prevent "Evil Twin" attacks.

### 3. Hybrid Session Storage

To keep your database clean:

- **Guests:** Sessions are stored in encrypted cookies (preserving CSRF protection).
- **Users:** Sessions are promoted to the database immediately upon `Auth::login()`.

---

## Rate Limiting & Protection

Always protect your passwordless endpoints using the `SecurityShield`.

```bash
php theplugs security:install
php theplugs migrate
```

This activates the `SecurityShieldMiddleware` which detects brute-force attempts on your identity challenges and automatically blacklists suspicious IPs.
