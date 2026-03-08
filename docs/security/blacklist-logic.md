# Framework Blacklisting Logic Analysis

The Plugs framework uses the `SecurityShieldMiddleware` and `ThreatDetector` to protect the application by blacklisting IP addresses that exhibit suspicious or malicious behavior.

## Primary Reasons for Blacklisting

### 1. Brute Force Protection (Rate Limiting)

The most common reason for an IP to be automatically saved in the `blacklisted_ips` table is exceeding login attempt thresholds.

- **Threshold**: 5 failed login attempts (default).
- **Time Window**: 15 minutes.
- **Action**: The IP is saved to the database with a `rate_limit` reason for **1 hour**.
- **Source**: `SecurityShieldMiddleware::checkRateLimit()`

### 2. Malicious Payloads (Threat Detection)

The `ThreatDetector` scans all incoming requests (query parameters, body data, and headers) for common attack patterns:

- **SQL Injection**: Matches for `UNION SELECT`, `DROP TABLE`, etc.
- **XSS (Cross-Site Scripting)**: Matches for `<script>` tags, `javascript:` protocol, etc.
- **Path Traversal**: Matches for `../` or `..\` in URLs or filenames.
- **Command Injection**: Matches for shell commands like `ls`, `cat`, `rm`, `wget`.
- **Action**: These increase the IP's **Risk Score**. If the score exceeds **0.85**, the IP is blocked.

### 3. Behavioral Anomaly Detection

The framework tracks how an IP interacts with the server:

- **High Frequency**: Making more than 10 requests per minute from a single IP.
- **Concurrent Sessions**: Accessing more than 5 different endpoints within 5 minutes.
- **Action**: These behaviors contribute significantly to the Risk Score, which can lead to a challenge (CAPTCHA) or a full block.

### 4. Bot & Crawler Identification

The `BotDetector` analyzes the `User-Agent` header for:

- Known scrapers, spiders, or crawlers.
- Automated tools like `curl`, `wget`, or `python-requests`.
- **Action**: Requests from identified malicious bots are denied immediately.

### 5. Persistent Risk Scores

Unlike simple rate limiters, Plugs persists threat scores in the **Cache** (prefixed with `security:threat:`).

- Scores decay over time if no new suspicious activity is detected.
- If an IP consistently triggers security rules, its cumulative score will eventually exceed the "Deny" threshold.

## Safeguards for Legitimate Users

To prevent "disturbing" real users in production, the framework includes several layers of protection:

### 1. The Challenge System (Don't Block, Just Verify)

The framework uses a progressive risk system. Instead of a hard block, users who exhibit slightly unusual behavior are "challenged":

- **Moderate Risk (0.50 - 0.70)**: User is shown a **CAPTCHA**. If they solve it, they can continue.
- **High Risk (0.70 - 0.85)**: User may be required to perform **Multi-Factor Authentication**.
- **Critical Risk (>0.85)**: Only then is the IP denied.

### 2. Trusted Proxy Support

If your website is behind a load balancer, CDN (like Cloudflare), or Nginx proxy, the framework can be configured to "trust" these proxies.

- This ensures the security shield sees the **real user's IP** rather than the proxy's IP.
- This prevents the framework from accidentally blocking your entire user base if it thinks all traffic is coming from a single "malicious" proxy IP.

### 3. Temporary Blocks (Decay)

Automatic blocks are not permanent.

- **Rate Limit Blocks**: Typically expire after **1 hour**.
- **Threat Scores**: Naturally decay in the Cache over time. If a user stops acting "suspicious," their score returns to zero.

### 4. Local & Whitelist Bypasses

- **Localhost** (`127.0.0.1`, `::1`) is always allowed.
- **Whitelisted IPs** bypass all security checks entirely, ensuring your office or integration servers are never blocked.

## How to Manage Blacklisted IPs

You can manage these blocks using the database or the framework's console commands:

- **Check Logs**: `SELECT * FROM security_logs WHERE decision = 'denied';`
- **Manual Unblock**: Delete the entry from the `blacklisted_ips` table or set `active = 0`.
- **CLI**: Use the command we recently implemented, e.g., `php plugs security:unblock <ip>`.
