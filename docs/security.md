# Security Configuration Guide

This document provides a comprehensive guide to the security features implemented in the Plugs framework.

## Overview

The framework includes multiple layers of security protection:
- **DDoS Protection** via `SecurityShieldMiddleware`
- **CSRF Protection** via `CsrfMiddleware`
- **Input Sanitization** via `SanitizationMiddleware`
- **Secure Headers** via `SecurityHeadersMiddleware`
- **SQL Injection Prevention** via prepared statements

## Security Shield (DDoS & Bot Protection)

### Configuration

Located in `config/security.php`:

```php
'security_shield' => [
    'enabled' => true,
    'whitelisted_ips' => ['127.0.0.1', '::1'],
    'risk_thresholds' => [
        'deny' => 0.85,
        'challenge_high' => 0.70,
        'challenge_low' => 0.50,
    ],
]
```

### Features

- **Rate Limiting**: IP-based, user-based, and endpoint-based limits
- **Bot Detection**: Identifies suspicious user agents and automated tools
- **Behavioral Analysis**: Tracks concurrent sessions and request frequency
- **Email Validation**: Blocks disposable email domains
- **Device Fingerprinting**: Blocks known malicious devices

### Database Tables

Run the migration to create required tables:
```bash
php theplugs migrate
```

Tables created:
- `security_attempts` - tracks login/access attempts
- `security_logs` - logs security events
- `whitelisted_ips` - trusted IP addresses
- `blacklisted_ips` - blocked IP addresses
- `blocked_fingerprints` - blocked device fingerprints

### Response Headers

Successful requests include:
- `X-Security-Score`: Risk score (0.0 to 1.0)
- `X-Security-Decision`: `allowed`

Denied requests return 403 with:
- `X-Security-Decision`: `denied`
- `X-Risk-Score`: Calculated risk score

## CSRF Protection

### Middleware

`CsrfMiddleware` protects POST, PUT, PATCH, DELETE requests.

### Configuration

```php
new CsrfMiddleware([
    'except' => ['/api/*', '/webhooks/*'],
    'consume_request_tokens' => true,
    'log_failures' => true,
])
```

### Usage in Forms

```php
<form method="POST">
    <?php csrf_field(); ?>
    <!-- form fields -->
</form>
```

### AJAX Requests

```javascript
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-Token': '<?= csrf_token(); ?>',
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data)
});
```

## Input Sanitization

### Middleware

`SanitizationMiddleware` automatically cleans request inputs.

### Registration

Add to middleware pipeline in `bootstrap/boot.php`:

```php
$app->pipe(new \Plugs\Middlewares\SanitizationMiddleware([
    'except' => ['/admin/raw-input']
]));
```

### Manual Sanitization

```php
use Plugs\Security\Sanitizer;

$clean = Sanitizer::string($input);
$email = Sanitizer::email($emailInput);
$int = Sanitizer::int($numberInput);
```

## Secure Headers

### SecurityHeadersMiddleware

Located at `src/Http/Middleware/SecurityHeadersMiddleware.php`

### Default Headers

- `X-Frame-Options`: SAMEORIGIN
- `X-Content-Type-Options`: nosniff
- `X-XSS-Protection`: 1; mode=block
- `Referrer-Policy`: strict-origin-when-cross-origin
- `Permissions-Policy`: geolocation=(), microphone=(), camera=()

### Custom Configuration

```php
new SecurityHeadersMiddleware([
    'X-Frame-Options' => 'DENY',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
])
```

## SQL Injection Prevention

### Prepared Statements

The framework uses PDO prepared statements with emulated prepares **disabled**:

```php
$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
```

### Usage Examples

```php
// Safe - uses prepared statements
$db->query("SELECT * FROM users WHERE email = ?", [$email]);

// Safe - query builder
User::where('email', $email)->first();
```

## Security Best Practices

### Production Checklist

1. ✅ Enable `SecurityShieldMiddleware` in production
2. ✅ Configure `whitelisted_ips` for admin panels
3. ✅ Set `HTTPS` and enable `Strict-Transport-Security` header
4. ✅ Configure CSP (Content Security Policy) headers
5. ✅ Enable error logging for security events
6. ✅ Regularly review `security_logs` table
7. ✅ Keep framework and dependencies updated
8. ✅ Use environment variables for sensitive data

### Monitoring

Check security logs:
```sql
SELECT * FROM security_logs 
WHERE decision = 'denied' 
ORDER BY created_at DESC 
LIMIT 100;
```

View blocked IPs:
```sql
SELECT ip, reason, COUNT(*) as attempts 
FROM blacklisted_ips 
WHERE active = 1 
GROUP BY ip, reason;
```

## Troubleshooting

### Issue: Missing Headers

**Solution**: Ensure middleware order is correct. `SecurityShieldMiddleware` should run after `SecurityHeadersMiddleware`.

### Issue: CSRF Token Mismatch

**Symptoms**: 419 errors on form submissions

**Solutions**:
- Clear browser cache
- Check session configuration
- Verify CSRF middleware is registered
- Ensure `csrf_field()` is in forms

### Issue: Legitimate Users Blocked

**Solution**: Add IP to whitelist:
```sql
INSERT INTO whitelisted_ips (ip, created_at) 
VALUES ('192.168.1.100', NOW());
```

Or configure in `config/security.php`:
```php
'whitelisted_ips' => ['127.0.0.1', '::1', '192.168.1.100'],
```
