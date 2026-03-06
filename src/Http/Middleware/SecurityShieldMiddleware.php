<?php

declare(strict_types=1);

namespace Plugs\Http\Middleware;

/*
|--------------------------------------------------------------------------
| SecurityShieldMiddleware Class
|--------------------------------------------------------------------------
|
| This middleware provides comprehensive security protection including:
| - Rate limiting (IP, user, endpoint-based)
| - Bot detection and validation
| - Behavioral analysis and anomaly detection
| - Email validation and disposable email blocking
| - Real-time threat scoring
|
| Usage:
| $app->pipe(new SecurityShieldMiddleware());
*/

use Plugs\Database\Connection;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Plugs\Http\Middleware\MiddlewareLayer;
use Plugs\Http\Middleware\Middleware;

#[Middleware(layer: MiddlewareLayer::SECURITY, priority: 20)]
class SecurityShieldMiddleware implements MiddlewareInterface
{
    private Connection $db;
    private array $config;
    private array $rules;
    private static ?array $cachedWhitelistedIps = null;
    private static ?array $cachedBlacklistedIps = null;
    private static int $lastListLoadTime = 0;
    private bool $listsLoaded = false;
    private \Plugs\Security\BotDetector $botDetector;
    private \Plugs\Security\EmailSecurityValidator $emailValidator;

    /**
     * Initialize SecurityShield Middleware
     *
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        // Connection is lazy, so this doesn't connect yet
        $this->db = Connection::getInstance();
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initializeRules();

        $this->botDetector = new \Plugs\Security\BotDetector($this->config);
        $this->emailValidator = new \Plugs\Security\EmailSecurityValidator();
    }

    private function ensureListsLoaded(): void
    {
        if ($this->listsLoaded) {
            return;
        }

        $this->loadWhitelistBlacklist();
        $this->listsLoaded = true;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Extract request data
        $requestData = $this->extractRequestData($request);

        // Run security checks
        $isLocal = in_array($requestData['ip'], ['127.0.0.1', '::1', 'localhost', 'plugs.local']);
        $decision = $isLocal ? ['allowed' => true, 'risk_score' => 0, 'challenge_required' => false] : $this->protect($requestData);

        // Handle security decision
        if (!$decision['allowed']) {
            return $this->createSecurityResponse($decision);
        }

        // If challenge required, return challenge response
        if ($decision['challenge_required']) {
            return $this->createChallengeResponse($decision);
        }

        // Add security attributes to request for downstream use
        $request = $request
            ->withAttribute('security_score', $decision['risk_score'])
            ->withAttribute('security_decision', $decision);

        // Continue to next middleware
        $response = $handler->handle($request);

        // Add security headers to response
        return $this->addSecurityHeaders($response, $decision);
    }

    /**
     * Main protection method - analyzes request and returns decision
     */
    private function protect(array $requestData): array
    {
        $decision = [
            'allowed' => true,
            'reason' => 'OK',
            'challenge_required' => false,
            'risk_score' => 0,
            'checks_passed' => [],
            'checks_failed' => [],
            'timestamp' => time(),
        ];

        $this->ensureListsLoaded();

        // Check whitelist first
        if ($this->isWhitelisted($requestData['ip'])) {
            $decision['reason'] = 'Whitelisted IP';

            return $decision;
        }

        // Check blacklist
        if ($this->isBlacklisted($requestData['ip'])) {
            return $this->deny('Blacklisted IP', 1.0);
        }

        // Basic validation
        if (!$this->basicRequestValidation($requestData)) {
            return $this->deny('Invalid request format', 1.0);
        }

        // Security checks pipeline with weights
        $checks = [
            'rate_limit' => ['weight' => 0.30, 'method' => 'checkRateLimit'],
            'bot_detection' => ['weight' => 0.25, 'method' => 'detectBots'],
            'behavior' => ['weight' => 0.20, 'method' => 'analyzeBehavior'],
            'email' => ['weight' => 0.15, 'method' => 'validateEmail'],
            'fingerprint' => ['weight' => 0.10, 'method' => 'checkFingerprint'],
        ];

        foreach ($checks as $checkName => $check) {
            if (!$this->rules[$checkName]) {
                continue; // Skip disabled rules
            }

            $result = call_user_func([$this, $check['method']], $requestData);

            if (!$result['allowed']) {
                $decision['checks_failed'][] = $checkName;

                return $result; // Immediate deny
            }

            $decision['checks_passed'][] = $checkName;
            $decision['risk_score'] += $result['risk_score'] * $check['weight'];

            if ($result['challenge_required'] && !$decision['challenge_required']) {
                $decision['challenge_required'] = true;
                $decision['challenge_type'] = $result['challenge_type'] ?? 'captcha';
            }
        }

        // Final risk assessment
        if ($decision['risk_score'] > 0.85) {
            return $this->deny('Critical risk threshold exceeded', $decision['risk_score']);
        } elseif ($decision['risk_score'] > 0.70) {
            $decision['challenge_required'] = true;
            $decision['challenge_type'] = 'multi_factor';
        } elseif ($decision['risk_score'] > 0.50) {
            $decision['challenge_required'] = true;
            $decision['challenge_type'] = 'captcha';
        }

        // Log the decision
        $this->logSecurityEvent($requestData, $decision);

        return $decision;
    }

    // ==================== SECURITY CHECKS ====================

    private function checkRateLimit(array $requestData): array
    {
        $ip = $requestData['ip'];
        $email = $requestData['email'] ?? '';
        $endpoint = $requestData['endpoint'];

        $limits = $this->getConsolidatedLimits($ip, $email, $endpoint);

        // Check endpoint-specific rate limit
        if ($limits['endpoint_rate'] >= $this->config['rate_limits']['endpoint_limit']) {
            return $this->deny('Endpoint rate limit exceeded', 0.95);
        }

        // Check IP-based rate limits
        if ($limits['ip_attempts'] >= $this->config['rate_limits']['login_attempts']) {
            $this->addToBlacklist($ip, 'rate_limit', 3600);

            return $this->deny('Too many attempts from this IP', 0.9);
        }

        if ($limits['ip_daily'] >= $this->config['rate_limits']['ip_daily_limit']) {
            return $this->deny('Daily limit exceeded for this IP', 0.85);
        }

        // Check email-based rate limits
        if (!empty($email) && $limits['email_attempts'] >= $this->config['rate_limits']['user_daily_limit']) {
            return $this->deny('Too many attempts for this account', 0.8);
        }

        // Optimizing: Record this attempt first, then we can fetch all stats in one or fewer queries
        $this->recordAttempt($ip, $email, $endpoint);

        // Calculate risk score - we can't easily merge these complex "COUNT WHERE" across different tables/logics 
        // without a more complex SQL, but we can at least avoid some if weights are low or disabled.
        $riskScore = min($limits['ip_attempts'] / $this->config['rate_limits']['login_attempts'], 0.9);

        return [
            'allowed' => true,
            'risk_score' => min($riskScore, 0.95),
            'challenge_required' => $riskScore > 0.5,
            'limits' => $limits,
        ];
    }

    private function detectBots(array $requestData): array
    {
        return $this->botDetector->detectBots($requestData);
    }

    private function analyzeBehavior(array $requestData): array
    {
        $ip = $requestData['ip'];
        $behaviorScore = 0;
        $details = [];

        // Check concurrent sessions
        $concurrentSessions = $this->getConcurrentSessions($ip);
        if ($concurrentSessions > 5) {
            $behaviorScore += 0.4;
            $details[] = "concurrent_sessions:{$concurrentSessions}";
        }

        // Check request frequency
        $requestFrequency = $this->getRequestFrequency($ip);
        if ($requestFrequency > 10) {
            $behaviorScore += 0.5;
            $details[] = "high_frequency:{$requestFrequency}";
        }

        return [
            'allowed' => true,
            'risk_score' => min($behaviorScore, 0.95),
            'challenge_required' => $behaviorScore > 0.5,
            'details' => $details,
        ];
    }

    private function validateEmail(array $requestData): array
    {
        return $this->emailValidator->validateEmail($requestData);
    }

    private function checkFingerprint(array $requestData): array
    {
        $fingerprint = $requestData['fingerprint'] ?? '';

        if (empty($fingerprint)) {
            return ['allowed' => true, 'risk_score' => 0.1, 'challenge_required' => false];
        }

        // Check if fingerprint is on blocklist
        if ($this->isFingerprintBlocked($fingerprint)) {
            return $this->deny('Device fingerprint blocked', 0.95);
        }

        return ['allowed' => true, 'risk_score' => 0, 'challenge_required' => false];
    }

    // ==================== HELPER METHODS ====================

    private function extractRequestData(ServerRequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = $values[0] ?? '';
        }

        $parsedBody = $request->getParsedBody();
        $email = '';

        if (is_array($parsedBody)) {
            $email = $parsedBody['email'] ?? $parsedBody['username'] ?? '';
        }

        return [
            'ip' => $this->getClientIP($request),
            'user_agent' => $request->getHeaderLine('user-agent'),
            'headers' => $headers,
            'email' => $email,
            'endpoint' => $request->getUri()->getPath(),
            'method' => $request->getMethod(),
            'timestamp' => time(),
            'fingerprint' => $headers['x-fingerprint'] ?? '',
        ];
    }

    private function getClientIP(ServerRequestInterface $request): string
    {
        // Use the client_ip attribute if set by TrustedProxyMiddleware
        $clientIp = $request->getAttribute('client_ip');
        if ($clientIp && filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }

        // Fallback for non-proxied environments or if attribute is missing
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function createSecurityResponse(array $decision): ResponseInterface
    {
        return ResponseFactory::json([
            'error' => 'Security validation failed',
            'reason' => $decision['reason'],
            'risk_score' => $decision['risk_score'],
            'timestamp' => $decision['timestamp'],
        ], 403, [
            'X-Security-Decision' => 'denied',
            'X-Risk-Score' => (string) $decision['risk_score'],
        ]);
    }

    private function createChallengeResponse(array $decision): ResponseInterface
    {
        $challengeType = $decision['challenge_type'] ?? 'captcha';

        return ResponseFactory::json([
            'challenge_required' => true,
            'challenge_type' => $challengeType,
            'risk_score' => $decision['risk_score'],
            'message' => 'Please complete the security challenge',
        ], 429, [
            'X-Challenge-Type' => $challengeType,
            'Retry-After' => '60',
        ]);
    }

    private function addSecurityHeaders(ResponseInterface $response, array $decision): ResponseInterface
    {
        return $response
            ->withHeader('X-Security-Score', (string) $decision['risk_score'])
            ->withHeader('X-Security-Decision', 'allowed');
    }

    // ==================== DATABASE OPERATIONS ====================

    /**
     * Get consolidated limits using fast Cache counters instead of DB aggregation.
     */
    private function getConsolidatedLimits(string $ip, string $email, string $endpoint): array
    {
        $limits = [
            'ip_attempts' => (int) \Plugs\Facades\Cache::get("shield:attempts:ip:" . md5($ip)),
            'ip_daily' => (int) \Plugs\Facades\Cache::get("shield:daily:ip:" . md5($ip)),
            'endpoint_rate' => (int) \Plugs\Facades\Cache::get("shield:endpoint:" . md5($ip . $endpoint)),
            'email_attempts' => 0,
        ];

        if (!empty($email)) {
            $limits['email_attempts'] = (int) \Plugs\Facades\Cache::get("shield:attempts:email:" . md5($email));
        }

        return $limits;
    }

    private function getAttemptCount(string $identifier, string $type): int
    {
        $limits = $this->getConsolidatedLimits($type === 'ip' ? $identifier : '', $type === 'email' ? $identifier : '', '');
        return $type === 'ip' ? $limits['ip_attempts'] : $limits['email_attempts'];
    }

    private function recordAttempt(string $ip, string $email, string $endpoint): void
    {
        try {
            // Write to DB asynchronously via Queue for historical records
            if (class_exists(\Plugs\Facades\Queue::class)) {
                \Plugs\Facades\Queue::push(\Plugs\Security\Jobs\SecurityLogJob::class, [
                    'job_type' => 'attempt',
                    'identifier' => $ip,
                    'type' => 'ip',
                    'endpoint' => $endpoint
                ]);
            }

            $window = $this->config['rate_limits']['login_window'] ?? 900;

            // Atomic cache increments with TTL
            $this->incrementCache("shield:attempts:ip:" . md5($ip), $window);
            $this->incrementCache("shield:daily:ip:" . md5($ip), 86400);
            $this->incrementCache("shield:endpoint:" . md5($ip . $endpoint), 60);

            if (!empty($email)) {
                if (class_exists(\Plugs\Facades\Queue::class)) {
                    \Plugs\Facades\Queue::push(\Plugs\Security\Jobs\SecurityLogJob::class, [
                        'job_type' => 'attempt',
                        'identifier' => $email,
                        'type' => 'email',
                        'endpoint' => $endpoint
                    ]);
                }

                $this->incrementCache("shield:attempts:email:" . md5($email), $window);
                $this->incrementCache("shield:daily:email:" . md5($email), 86400);
            }

            // Track concurrent sessions / recent endpoints
            $this->trackSessionEndpoints($ip, $endpoint);
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function incrementCache(string $key, int $ttl): void
    {
        if (class_exists(\Plugs\Facades\Cache::class)) {
            $current = (int) \Plugs\Facades\Cache::get($key);
            \Plugs\Facades\Cache::set($key, $current + 1, $ttl);
        }
    }

    private function trackSessionEndpoints(string $ip, string $endpoint): void
    {
        if (!class_exists(\Plugs\Facades\Cache::class)) {
            return;
        }

        $key = "shield:session_endpoints:" . md5($ip);
        $endpoints = \Plugs\Facades\Cache::get($key) ?: [];
        if (is_array($endpoints)) {
            $endpoints[$endpoint] = time();

            // Clean up old endpoints (older than 5 minutes)
            $endpoints = array_filter($endpoints, fn($time) => $time > (time() - 300));
            \Plugs\Facades\Cache::set($key, $endpoints, 300);
        }
    }

    private function logSecurityEvent(array $requestData, array $decision): void
    {
        // Only log failures or 1% of successes to reduce write load
        if ($decision['allowed'] && mt_rand(1, 100) !== 1) {
            return;
        }

        try {
            \Plugs\Facades\Queue::push(\Plugs\Security\Jobs\SecurityLogJob::class, [
                'job_type' => 'log',
                'ip' => $requestData['ip'],
                'email' => $requestData['email'] ?? '',
                'endpoint' => $requestData['endpoint'],
                'risk_score' => $decision['risk_score'],
                'decision' => $decision['allowed'] ? 'allowed' : 'denied',
                'details' => json_encode(['reason' => $decision['reason'], 'checks' => $decision]),
            ]);
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // ==================== VALIDATION & DETECTION ====================

    private function basicRequestValidation(array $requestData): bool
    {
        return !empty($requestData['ip']) &&
            isset($requestData['user_agent']) &&
            isset($requestData['endpoint']);
    }


    private function getConcurrentSessions(string $ip): int
    {
        if (!class_exists(\Plugs\Facades\Cache::class)) {
            return 1;
        }

        $key = "shield:session_endpoints:" . md5($ip);
        $endpoints = \Plugs\Facades\Cache::get($key) ?: [];

        if (!is_array($endpoints)) {
            return 1;
        }

        // Active sessions are endpoints visited in the last 5 minutes (300 seconds)
        $activeCount = 0;
        $now = time();
        foreach ($endpoints as $time) {
            if ($now - $time <= 300) {
                $activeCount++;
            }
        }

        return max(1, $activeCount); // At least 1 (the current request)
    }

    private function getRequestFrequency(string $ip): int
    {
        if (!class_exists(\Plugs\Facades\Cache::class)) {
            return 0; // Optimistic fallback
        }

        // We track a localized 1-minute window using the same cache key populated by recordAttempt
        return (int) \Plugs\Facades\Cache::get("shield:attempts:ip:" . md5($ip));
    }

    private function isFingerprintBlocked(string $fingerprint): bool
    {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM blocked_fingerprints WHERE fingerprint = ?",
                [$fingerprint]
            );

            return (int) $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==================== WHITELIST/BLACKLIST ====================

    private function loadWhitelistBlacklist(): void
    {
        // Try to load from Cache first
        $cachedLists = \Plugs\Facades\Cache::get('shield_ip_lists');

        if ($cachedLists && is_array($cachedLists)) {
            self::$cachedWhitelistedIps = $cachedLists['whitelist'] ?? [];
            self::$cachedBlacklistedIps = $cachedLists['blacklist'] ?? [];
            $this->listsLoaded = true;
            return;
        }

        try {
            $whitelist = $this->db->fetchAll("SELECT ip FROM whitelisted_ips WHERE active = 1");
            self::$cachedWhitelistedIps = array_column($whitelist, 'ip');

            $blacklist = $this->db->fetchAll(
                "SELECT ip FROM blacklisted_ips WHERE active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
            );
            self::$cachedBlacklistedIps = array_column($blacklist, 'ip');

            // Cache the result for 60 seconds
            \Plugs\Facades\Cache::set('shield_ip_lists', [
                'whitelist' => self::$cachedWhitelistedIps,
                'blacklist' => self::$cachedBlacklistedIps
            ], 60);

            $this->listsLoaded = true;
        } catch (\Exception $e) {
            // Tables might not exist yet or connection failed
            if (self::$cachedWhitelistedIps === null) {
                self::$cachedWhitelistedIps = [];
            }
            if (self::$cachedBlacklistedIps === null) {
                self::$cachedBlacklistedIps = [];
            }
        }
    }

    private function isWhitelisted(string $ip): bool
    {
        return in_array($ip, self::$cachedWhitelistedIps ?? []);
    }

    private function isBlacklisted(string $ip): bool
    {
        return in_array($ip, self::$cachedBlacklistedIps ?? []);
    }

    private function addToBlacklist(string $ip, string $reason, int $duration = 3600): void
    {
        try {
            $this->db->query(
                "INSERT INTO blacklisted_ips (ip, reason, expires_at, created_at) 
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
                [$ip, $reason, $duration]
            );
            if (self::$cachedBlacklistedIps !== null) {
                self::$cachedBlacklistedIps[] = $ip;

                // Invalidate cache so next request picks up the new block
                \Plugs\Facades\Cache::delete('shield_ip_lists');
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    // ==================== CONFIGURATION ====================

    private function getDefaultConfig(): array
    {
        return [
            'rate_limits' => [
                'login_attempts' => 5,
                'login_window' => 900,        // 15 minutes
                'ip_daily_limit' => 100,
                'user_daily_limit' => 50,
                'endpoint_limit' => 20,        // Per minute
            ],
            'bot_detection' => [
                'suspicious_headers' => ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget'],
                'block_suspicious_bots' => true,
            ],
        ];
    }

    private function initializeRules(): void
    {
        $this->rules = [
            'rate_limit' => true,
            'bot_detection' => true,
            'email' => true,
            'behavior' => true,
            'fingerprint' => false,
        ];
    }

    private function deny(string $reason, float $riskScore = 1.0): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'risk_score' => $riskScore,
            'challenge_required' => false,
            'timestamp' => time(),
        ];
    }

    // ==================== PUBLIC CONFIGURATION METHODS ====================

    /**
     * Set configuration value
     */
    public function setConfig(string $key, $value): self
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }

        return $this;
    }

    /**
     * Enable a security rule
     */
    public function enableRule(string $rule): self
    {
        if (isset($this->rules[$rule])) {
            $this->rules[$rule] = true;
        }

        return $this;
    }

    /**
     * Disable a security rule
     */
    public function disableRule(string $rule): self
    {
        if (isset($this->rules[$rule])) {
            $this->rules[$rule] = false;
        }

        return $this;
    }

    /**
     * Add IP to whitelist
     */
    public function addToWhitelist(string $ip): self
    {
        if (self::$cachedWhitelistedIps !== null && !in_array($ip, self::$cachedWhitelistedIps)) {
            self::$cachedWhitelistedIps[] = $ip;
        }

        return $this;
    }
}
