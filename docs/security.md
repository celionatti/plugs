# Enhanced Security Defaults

Plugs provides zero-config security features that protect your application out of the box.

## Security Headers

The `SecurityHeadersMiddleware` applies sensible defaults automatically:

| Header | Default Value |
|--------|---------------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |
| `Content-Security-Policy` | Self-only defaults (see below) |

### Default CSP

```
default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; 
img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self'
```

Override via `config/security.php` or `.env`.

## Mass Assignment Protection

Use the `MassAssignmentGuard` trait in your models:

```php
use Plugs\Security\MassAssignmentGuard;

class User {
    use MassAssignmentGuard;

    protected array $fillable = ['name', 'email'];
    protected array $guarded = ['is_admin', 'password'];
}

$user = new User();
$user->fill($request->all()); // Throws if guarded attributes present
```

## Dynamic Threat Detection

The `ThreatDetector` service scans requests for suspicious patterns (XSS, SQL injection, path traversal) and tracks threat scores per IP.

```php
use Plugs\Security\ThreatDetector;

if (ThreatDetector::isSuspicious($request)) {
    // Log, block, or challenge
}

// Record failed auth (adds to threat score)
ThreatDetector::recordFailedAuth($request);
```

### Automatic Middleware

Add `ThreatDetectionMiddleware` to your middleware stack for automatic scanning:

```php
// In your middleware configuration
$middlewareStack = [
    ThreatDetectionMiddleware::class, // Auto-scan all requests
    // ... other middleware
];
```

Options:
- `new ThreatDetectionMiddleware(blockSuspicious: true, blockThreshold: 10)`
- Logs all threats, blocks requests with score >= threshold

## Behavior-Based Rate Limiting

`RateLimitMiddleware` now adjusts limits based on threat scores:

| Threat Score | Effective Limit |
|--------------|-----------------|
| 0-4 | 100% of configured limit |
| 5-9 | 50% of limit |
| 10-14 | 25% of limit |
| 15+ | 10% of limit |

Suspicious clients are automatically throttled more aggressively.
