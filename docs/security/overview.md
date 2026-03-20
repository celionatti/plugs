# Security Overview

Plugs is built with a "Security-First" philosophy, providing multiple layers of protection out of the box to keep your application and users safe.

---

## 1. Security Shield (DDoS & Bot Protection)

The **Security Shield** is a proactive defense layer that analyzes request patterns to block malicious bots and mitigate DDoS attacks.

### How it Works
It assigns a risk score to every IP. If the score exceeds the threshold, the request is either challenged (CAPTCHA) or denied.

```php
// config/security.php
'security_shield' => [
    'enabled' => true,
    'risk_thresholds' => [
        'deny' => 0.85,           // Immediately block
        'challenge_high' => 0.70, // High friction challenge
    ],
],
```

### Manual Threat Detection
You can manually flag suspicious activity from your controllers:
```php
use Plugs\Security\ThreatDetector;

ThreatDetector::logSuspiciousActivity($ip, score: 20);
```

---

## 2. CSRF Protection

Cross-Site Request Forgery protection is enabled by default for all `POST`, `PUT`, `PATCH`, and `DELETE` requests.

### Usage in Forms
```html
<form method="POST" action="/update">
    @csrf
    <button type="submit">Update</button>
</form>
```

### AJAX & SPAs
Plugs uses the "Cookie-to-Header" pattern. It sets an `XSRF-TOKEN` cookie that your JavaScript should send back in the `X-XSRF-TOKEN` header.

---

## 3. Security Headers

The `SecurityHeadersMiddleware` automatically injects industry-standard headers to prevent common attacks:

- **X-Frame-Options**: `SAMEORIGIN` (Prevents Clickjacking)
- **X-Content-Type-Options**: `nosniff` (Prevents MIME sniffing)
- **X-XSS-Protection**: `1; mode=block` (Legacy XSS protection)
- **Content-Security-Policy**: Customizable CSP to prevent XSS and data injection.

---

## 4. Production Hardening

When deploying to production (`APP_ENV=production`), Plugs enforces stricter security rules:
- **Secure Cookies**: Cookies are only sent over HTTPS.
- **Strict Transport Security (HSTS)**: Forces browsers to use HTTPS.
- **Error Obfuscation**: Detailed stack traces are disabled and replaced with generic error pages.

---

## Next Steps
Manage user access using [Authentication & Authorization](./authentication-authorization.md).
