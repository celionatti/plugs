# CSRF Protection

Cross-Site Request Forgery (CSRF) is a type of malicious exploit where unauthorized commands are transmitted from a user that the web application trusts.

The Plugs framework provides a robust CSRF protection system out of the box.

## How it Works

CSRF protection works by generating a unique "token" for each user session. This token is used to verify that the authenticated user is the one actually making the requests to the application.

### Protected HTTP Methods

The following HTTP methods are automatically protected by the `CsrfMiddleware`:
- `POST`
- `PUT`
- `PATCH`
- `DELETE`

`GET`, `HEAD`, and `OPTIONS` requests are considered safe and do not require CSRF tokens.

## Usage in Forms

To include a CSRF token in your HTML forms, use the `@csrf` helper (if using the template engine) or the `Csrf::field()` method:

```html
<form method="POST" action="/profile">
    <?= Plugs\Security\Csrf::field() ?>
    <!-- ... -->
</form>
```

This will generate a hidden input field like this:
```html
<input type="hidden" name="_token" value="abc123xyz...">
```

## Usage with AJAX/SPAs (XSRF)

For modern web applications using Axios, Angular, or Vue, the framework implements the "Cookie-to-Header" pattern, often referred to as **XSRF** protection.

### The XSRF-TOKEN Cookie

On every "safe" request (GET), the framework sets a `XSRF-TOKEN` cookie. This cookie is *not* `HttpOnly`, meaning JavaScript can read it.

### Automatic Header Inclusion

When making AJAX requests, your JavaScript library should read the value of the `XSRF-TOKEN` cookie and send it back in the `X-XSRF-TOKEN` header.

#### Axios Example
Axios handles this automatically by default. It looks for a cookie named `XSRF-TOKEN` and sets the `X-XSRF-TOKEN` header.

```javascript
// Axios default configuration (usually pre-configured)
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
```

#### Vanilla JavaScript
```javascript
function getCookie(name) {
    let value = "; " + document.cookie;
    let parts = value.split("; " + name + "=");
    if (parts.length == 2) return parts.pop().split(";").shift();
}

fetch('/api/data', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': getCookie('XSRF-TOKEN')
    },
    body: JSON.stringify({ data: 'example' })
});
```

## Advanced Configuration

You can configure CSRF settings in `config/security.php` (or via `Csrf::configure()`):

| Option | Default | Description |
|--------|---------|-------------|
| `token_lifetime` | `7200` | Token expiration in seconds (2 hours). |
| `use_per_request_tokens` | `false` | Generate a new token for every request (High security). |
| `regenerate_on_verify` | `false` | Automatically rotate token after successful validation. |
| `strict_mode` | `true` | Enforce expiration checks. |
| `use_masking` | `true` | Protect tokens from BREACH attacks using XOR masking. |

## Troubleshooting

If CSRF validation fails, the application will return a `419 CSRF Token Mismatch` response. Common reasons include:
1. **Missing Token**: The `_token` field or `X-XSRF-TOKEN` header is missing.
2. **Expired Token**: The user has been inactive for too long.
3. **Context Mismatch**: The session or browser environment changed suspiciously.
