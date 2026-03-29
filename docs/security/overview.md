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

## 2. XSS Protection (Cross-Site Scripting)

Plugs provides automatic protection against XSS through its context-aware view engine and a robust HTML sanitizer.

### Auto-Escaping
All data echoed in templates using `{{ $var }}` is automatically escaped for the HTML context using `Plugs\View\Escaper`.

### HTML Sanitization
For cases where you need to allow some HTML, use the `@sanitize` directive or `Plugs\Security\Sanitizer`.

- **Attribute Scrubbing**: Automatically strips dangerous attributes like `onclick`, `onmouseover`, etc.
- **Protocol Filtering**: Rejects dangerous URI schemes like `javascript:`, `vbscript:`, and `data:` (except for safe image types).
- **Tag Blacklisting**: Neutralizes high-risk tags such as `<script>`, `<iframe>`, and `<object>`.

```blade
@sanitize($userBio)
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

### High-Performance PSR-7 Integration
The CSRF protection layer is optimized for PSR-7 compatibility, including robust handling of large request body streams without memory overhead or runtime exceptions on non-seekable streams.

---

## 3. Security Headers

The `SecurityHeadersMiddleware` automatically injects industry-standard headers to prevent common attacks:

- **X-Frame-Options**: `SAMEORIGIN` (Prevents Clickjacking)
- **X-Content-Type-Options**: `nosniff` (Prevents MIME sniffing)
- **X-XSS-Protection**: `1; mode=block` (Legacy XSS protection)
- **Content-Security-Policy**: Customizable CSP to prevent XSS and data injection.

### CSS Security (Inline Styles)
Inline styles bound via `:style="..."` are automatically sanitized using `Plugs\View\Escaper::css()`. This blocks dangerous properties like `expression()`, `behavior`, and unsafe `url()` protocols that could lead to CSS-based XSS.

---

## 4. Data Encryption

Plugs provides a secure, easy-to-use encryption system for sensitive data stored in your database.

### Model Encryption
By casting an attribute as `encrypted` in your model, the data is automatically encrypted on save and decrypted on retrieval using **Encrypt-then-MAC** (AES-256-CBC + HMAC-SHA256).

```php
protected $casts = [
    'ssn' => 'encrypted',
];
```

### Key Management
Encryption uses the `APP_KEY` defined in your `.env` file. Ensure this key is protected and backed up. If the key is lost, encrypted data cannot be recovered.

---

## 5. Production Hardening

When deploying to production (`APP_ENV=production`), Plugs enforces stricter security rules:
- **Secure Cookies**: Cookies are only sent over HTTPS.
- **Strict Transport Security (HSTS)**: Forces browsers to use HTTPS.
- **Error Obfuscation**: Detailed stack traces are disabled and replaced with generic error pages.

---

## Next Steps
Manage user access using [Authentication & Authorization](./authentication-authorization.md).
