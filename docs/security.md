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

## 3. Mass Assignment Guard

Standard in all Models to prevent illicit data modification.

```php
class User extends Model {
    protected array $fillable = ['name', 'email'];
}
```

## 4. Zero-Config Security Headers

Plugs automatically injects hardened HTTP headers:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Content-Security-Policy` (configurable)
- `Strict-Transport-Security`
