# Security Shield

The Security Shield is a robust protection layer for your Plugs application, designed to mitigate DDoS attacks, block malicious bots, and handle rate limiting.

## Overview

Security Shield works by analyzing incoming request patterns and assigning a risk score to each IP address/fingerprint. High-risk requests can be challenged (CAPTCHA) or denied outright.

## Configuration

Settings are located in `config/security.php`:

```php
return [
    'security_shield' => [
        'enabled' => true,
        'whitelisted_ips' => ['127.0.0.1'],
        'risk_thresholds' => [
            'deny' => 0.85,           // Immediately block
            'challenge_high' => 0.70, // High friction challenge
            'challenge_low' => 0.50,  // Low friction challenge
        ],
    ],
];
```

## Middleware Registration

To enable the shield, add the `SecurityShieldMiddleware` to your global middleware stack or specific route groups:

```php
$app->pipe(new \Plugs\Middlewares\SecurityShieldMiddleware());
```

## Key Protections

### Rate Limiting

Automatically limits the number of requests a single IP can make within a designated timeframe.

### Bot Detection

Identifies and blocks common scraper bots and malicious crawlers based on user-agent analysis and behavioral patterns.

### Behavioral Analysis

Tracks:

- Request frequency
- Concurrent sessions
- Access to sensitive endpoints (e.g., login, register)
- Use of disposable email domains

### Persistent Threat Detection

The `ThreatDetector` component maintains a risk score for suspicious IPs. Unlike traditional systems that lose this state between requests, Plugs persists threat scores using the `Cache` driver.

- **Storage**: Risk scores are cached with a prefix `security:threat:`.
- **Auto-Decay**: Scores automatically decay over time if no new suspicious activity is detected, preventing permanent blocks for temporary behavior.
- **Manual Control**:

  ```php
  use Plugs\Security\ThreatDetector;

  // Manually increase threat score
  ThreatDetector::logSuspiciousActivity($ip, score: 20);

  // Check if IP is currently suspicious
  $isSuspicious = ThreatDetector::isSuspicious($ip);
  ```

## Security Headers

The `SecurityHeadersMiddleware` automatically adds essential security headers to every response:

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `X-XSS-Protection: 1; mode=block`
- `Content-Security-Policy`: (Customizable)

## Monitoring

Security events are logged to the `security_logs` database table. You can monitor blocked attempts and risk scores here.

```sql
SELECT * FROM security_logs WHERE decision = 'denied' ORDER BY created_at DESC;
```
