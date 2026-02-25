# Rate Limiting

The Plugs framework includes a robust, zero-configuration rate limiting system to protect your application from brute-force attacks and API abuse.

## Introduction

Rate limiting is applied via the `throttle` middleware. By default, it uses a fixed-window algorithm to track request attempts per IP address or a custom identifier (like an email address).

## Named Rate Limiters

You should define your rate limiters in the `boot` method of your `App\Providers\AppServiceProvider`. Named limiters allow you to define complex logic once and apply it to multiple routes.

```php
use Plugs\Security\RateLimiter;

public function boot()
{
    RateLimiter::for('login', fn($request) => [
        RateLimiter::perMinute(5)->by('login_email:' . strtolower($request->input('email', ''))),
        RateLimiter::perMinute(15)->by('login_ip:' . $request->ip()),
    ]);
}
```

### Defining Multiple Rules

As shown above, a single named limiter can return an array of configurations. Plugs will check **all** rules, and if any of them are exceeded, the request will be throttled.

## Applying Middleware

You can apply the `throttle` middleware to your routes using the alias `throttle` followed by the name of the limiter:

```php
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:login');
```

For simple, IP-based limiting without a named limiter, you can specify the number of requests and minutes:

```php
// 10 requests per 1 minute
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:10,1');
```

## How It Works

1.  **Counter Tracking**: Plugs uses the configured cache driver to store attempt counts.
2.  **Expiration**: Each counter has its own "reset time" based on the decay period (e.g., 60 seconds for `perMinute`).
3.  **Exception Handling**: When a limit is exceeded, the framework throws a `RateLimitException`.
4.  **Response**:
    - **Web**: By default, shows a `429 Too Many Requests` error page.
    - **API (JSON)**: Returns a JSON response with the error message and a `Retry-After` header.

## Clearing Limits

You can manually clear the rate limit for a specific key using the `RateLimiter` facade. This is useful when a user successfully authenticates:

```php
use Plugs\Facades\RateLimiter;

// Clear the email-based limit
RateLimiter::clear('throttle:login_email:' . strtolower($email));
```

## Available Configurations

| Method                     | Duration       |
| :------------------------- | :------------- |
| `perMinute(int $attempts)` | 60 seconds     |
| `perHour(int $attempts)`   | 3,600 seconds  |
| `perDay(int $attempts)`    | 86,400 seconds |
