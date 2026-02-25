# Security & Protection

Plugs is "Secure by Default." The framework includes an advanced **Security Shield** that monitors and blocks malicious activity.

## 1. The Security Shield

The Shield is a high-performance middleware that analyzes every request for:

- SQL Injection patterns
- XSS Payloads
- Path Traversal attempts
- Suspicious User Agents

```php
// .env
SECURITY_SHIELD=true
```

## 2. Threat Detector

Logs and tracks potential attackers. If a client hits too many security rules, they can be automatically rate-limited or blocked.

## 3. Rate Limiting

Plugs includes a built-in rate limiting system to protect your application from brute-force attacks and API abuse. You can define custom limits for routes using the `throttle` middleware.

Learn more in the [Rate Limiting guide](rate-limiting.md).

## 4. Mass Assignment Guard

Standard in all Models to prevent illicit data modification.

```php
class User extends Model {
    protected array $fillable = ['name', 'email'];
}
```

## 5. View Security (Context-Aware)

Plugs V5 includes a context-aware escaping engine. It automatically detects the output context and sanitizes it accordingly:

- **Body**: Standard HTML escaping (`e()`).
- **Attributes**: Quote and special character escaping (`attr()`).
- **Scripts**: Safe JSON encoding (`js()`).
- **URLs**: Protocol-aware sanitization (`safeUrl()`).

## 6. Zero-Config Security Headers

Plugs automatically injects hardened HTTP headers:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Content-Security-Policy` (configurable)
- `Strict-Transport-Security`
