# Production Security Guide: Avoiding False Positives

To ensure your users have a smooth experience in production without compromising security, follow these best practices.

## 1. Configure Trusted Proxies

If you are using Cloudflare, AWS Load Balancer, or Nginx as a reverse proxy, you **must** configure trusted proxies in your `.env` file. Otherwise, the framework might see the proxy's IP as the client and block everyone.

Add this to your `.env`:

```env
# Trust specific proxies (Cloudflare IPs or your Load Balancer)
TRUSTED_PROXIES=127.0.0.1,10.0.0.1

# OR trust all (use with caution in controlled environments)
# TRUSTED_PROXIES=*
```

## 2. Whitelist Essential IPs

Ensure your company office, CI/CD runners, and monitoring services are whitelisted.

```bash
# Example CLI commands (if implemented)
php plugs security:whitelist 1.2.3.4 --reason="Office IP"
```

## 3. Tune Risk Thresholds

If you find that legitimate users are frequently seeing CAPTCHAs, you can increase the challenge thresholds in `config/security.php` (or `src/Config/DefaultConfig.php`):

```php
'risk_thresholds' => [
    'deny' => 0.90,           // Be more lenient before blocking
    'challenge_high' => 0.75, // Allow more activity before high-friction challenges
    'challenge_low' => 0.60,  // Allow more activity before low-friction challenges
],
```

## 4. Monitoring Strategy

Monitor the `security_logs` table for "denied" decisions where the `risk_score` is just barely over the threshold. These are candidates for tuning.

```sql
SELECT ip, endpoint, risk_score, details
FROM security_logs
WHERE decision = 'denied'
AND created_at > NOW() - INTERVAL 24 HOUR;
```

## 5. Enable High-Performance Cache

Security shield relies heavily on the Cache. In production, use **Redis** or **Memcached** instead of the `file` driver to ensure multi-threaded requests are tracked accurately without race conditions.
