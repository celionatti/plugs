# Security Headers

Plugs includes a built-in middleware to manage critical security headers, protecting your application against common vulnerabilities like XSS, Clickjacking, and MIME-sniffing.

## Overview

The `SecurityHeadersMiddleware` is responsible for adding these headers to every response. It is configured via the `config/security.php` file.

### Default Headers

| Header | Default Value | Purpose |
|--------|---------------|---------|
| `X-Frame-Options` | `SAMEORIGIN` | Prevents Clickjacking. |
| `X-Content-Type-Options` | `nosniff` | Prevents MIME-sniffing. |
| `X-XSS-Protection` | `1; mode=block` | Enables browser XSS filters. |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Controls referrer information. |
| `Strict-Transport-Security` | `max-age=31536000...` | Enforces HTTPS (HSTS). |

## Content Security Policy (CSP)

CSP is a powerful security layer that helps detect and mitigate certain types of attacks, including Cross-Site Scripting (XSS) and data injection attacks.

### Configuration

In `config/security.php`, you can define your CSP policy:

```php
'csp' => [
    'enabled' => true,
    'default-src' => ["'self'"],
    'script-src' => ["'self'", "'unsafe-inline'", 'cdn.tailwindcss.com'],
    'style-src' => ["'self'", "'unsafe-inline'", 'cdn.jsdelivr.net'],
    'img-src' => ["'self'", 'data:', 'https:'],
    'font-src' => ["'self'", 'data:'],
],
```

The middleware will automatically transform this array into a valid `Content-Security-Policy` header.

## HSTS (HTTP Strict Transport Security)

HSTS tells the browser that it should only interact with your site using HTTPS. Plugs adds the following header by default:

`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`

> [!WARNING]
> Only enable HSTS if your site is fully served over HTTPS, as it will block all HTTP access.

## Disabling Headers

If you need to disable a specific header (e.g., if you are managing it at the Nginx/Cloudflare level), you can set its value to `null` in `config/security.php`:

```php
'headers' => [
    'X-Frame-Options' => null, // This header will not be sent
],
```
