<?php

declare(strict_types=1);

namespace Plugs\Middlewares;

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

class SecurityShieldMiddleware implements MiddlewareInterface
{
    private Connection $db;
    private array $config;
    private array $rules;
    private ?array $whitelistedIps = null;
    private ?array $blacklistedIps = null;
    private bool $listsLoaded = false;

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
        $this->whitelistedIps = [];
        $this->blacklistedIps = [];
        $this->initializeRules();
        // Removed eager loadWhitelistBlacklist()
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
        $decision = $this->protect($requestData);

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

        $limits = [
            'ip_attempts' => $this->getAttemptCount($ip, 'ip'),
            'email_attempts' => $email ? $this->getAttemptCount($email, 'email') : 0,
            'ip_daily' => $this->getDailyCount($ip, 'ip'),
            'endpoint_rate' => $this->getEndpointRate($ip, $endpoint),
        ];

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

        // Record this attempt
        $this->recordAttempt($ip, $email, $endpoint);

        // Calculate risk score
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
        $userAgent = strtolower($requestData['user_agent']);
        $headers = $requestData['headers'];

        $botIndicators = 0;
        $maxIndicators = 10;
        $details = [];

        // Check for known bot user agents
        foreach ($this->config['bot_detection']['suspicious_headers'] as $botKeyword) {
            if (strpos($userAgent, $botKeyword) !== false) {
                $botIndicators += 2;
                $details[] = "bot_keyword:{$botKeyword}";
            }
        }

        // Check for empty or suspicious user agent
        if (empty($userAgent) || strlen($userAgent) < 10) {
            $botIndicators += 1.5;
            $details[] = 'suspicious_ua_length';
        }

        // Check for missing standard headers
        $requiredHeaders = ['accept', 'accept-language'];
        foreach ($requiredHeaders as $header) {
            if (!isset($headers[$header])) {
                $botIndicators += 0.5;
                $details[] = "missing_header:{$header}";
            }
        }

        // Check User-Agent consistency
        if (!$this->isUserAgentConsistent($userAgent, $headers)) {
            $botIndicators += 1;
            $details[] = 'inconsistent_ua';
        }

        // Check for headless browser signatures
        if ($this->isHeadlessBrowser($userAgent)) {
            $botIndicators += 1.5;
            $details[] = 'headless_browser';
        }

        $botScore = min($botIndicators / $maxIndicators, 1.0);

        // Block high-confidence bots
        if ($botScore > 0.75 && $this->config['bot_detection']['block_suspicious_bots']) {
            return $this->deny('Automated bot detected', $botScore);
        }

        return [
            'allowed' => true,
            'risk_score' => $botScore * 0.8,
            'challenge_required' => $botScore > 0.4,
            'challenge_type' => $botScore > 0.6 ? 'advanced_captcha' : 'captcha',
            'details' => $details,
        ];
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
        $email = $requestData['email'] ?? '';

        if (empty($email)) {
            return ['allowed' => true, 'risk_score' => 0, 'challenge_required' => false];
        }

        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->deny('Invalid email format', 0.8);
        }

        // Check disposable email domains
        if ($this->isDisposableEmail($email)) {
            return $this->deny('Disposable email addresses not allowed', 0.85);
        }

        // Check for typos in popular domains
        $typoScore = $this->detectEmailTypos($email);

        return [
            'allowed' => true,
            'risk_score' => min($typoScore, 0.95),
            'challenge_required' => $typoScore > 0.6,
            'typo_detected' => $typoScore > 0.7,
            'suggested_email' => $typoScore > 0.7 ? $this->suggestEmailCorrection($email) : null,
        ];
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
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        $serverParams = $request->getServerParams();

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

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

    private function getAttemptCount(string $identifier, string $type): int
    {
        if (empty($identifier)) {
            return 0;
        }

        try {
            $window = $this->config['rate_limits']['login_window'];
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM security_attempts 
                 WHERE identifier = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$identifier, $type, $window]
            );

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDailyCount(string $identifier, string $type): int
    {
        if (empty($identifier)) {
            return 0;
        }

        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM security_attempts 
                 WHERE identifier = ? AND type = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$identifier, $type]
            );

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getEndpointRate(string $ip, string $endpoint): int
    {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM security_attempts 
                 WHERE identifier = ? AND endpoint = ? AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)",
                [$ip, $endpoint]
            );

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function recordAttempt(string $ip, string $email, string $endpoint): void
    {
        try {
            $this->db->query(
                "INSERT INTO security_attempts (identifier, type, endpoint, created_at) VALUES (?, ?, ?, NOW())",
                [$ip, 'ip', $endpoint]
            );

            if (!empty($email)) {
                $this->db->query(
                    "INSERT INTO security_attempts (identifier, type, endpoint, created_at) VALUES (?, ?, ?, NOW())",
                    [$email, 'email', $endpoint]
                );
            }
        } catch (\Exception $e) {
            // Silent fail
        }
    }

    private function logSecurityEvent(array $requestData, array $decision): void
    {
        try {
            $this->db->query(
                "INSERT INTO security_logs (ip, email, endpoint, risk_score, decision, details, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $requestData['ip'],
                    $requestData['email'] ?? '',
                    $requestData['endpoint'],
                    $decision['risk_score'],
                    $decision['allowed'] ? 'allowed' : 'denied',
                    json_encode(['reason' => $decision['reason'], 'checks' => $decision]),
                ]
            );
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

    private function isUserAgentConsistent(string $userAgent, array $headers): bool
    {
        $isBrowser = preg_match('/(mozilla|chrome|safari|firefox|edge)/i', $userAgent);
        $hasAccept = isset($headers['accept']);

        return $isBrowser === $hasAccept;
    }

    private function isHeadlessBrowser(string $userAgent): bool
    {
        $headlessSignatures = ['headless', 'phantom', 'selenium', 'puppeteer', 'playwright'];

        foreach ($headlessSignatures as $signature) {
            if (strpos($userAgent, $signature) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(explode('@', $email)[1] ?? '');

        $disposableDomains = [
            'tempmail.com',
            'guerrillamail.com',
            'mailinator.com',
            '10minutemail.com',
            'yopmail.com',
            'throwawaymail.com',
            'temp-mail.org',
            'getnada.com',
            'maildrop.cc',
        ];

        return in_array($domain, $disposableDomains);
    }

    private function detectEmailTypos(string $email): float
    {
        $popularDomains = [
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'aol.com',
            'icloud.com',
        ];

        $userDomain = strtolower(explode('@', $email)[1] ?? '');

        foreach ($popularDomains as $correctDomain) {
            $distance = levenshtein($userDomain, $correctDomain);
            if ($distance > 0 && $distance <= 2 && $userDomain !== $correctDomain) {
                return 0.8;
            }
        }

        return 0;
    }

    private function suggestEmailCorrection(string $email): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        $popularDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        $userDomain = strtolower($parts[1]);

        foreach ($popularDomains as $correctDomain) {
            if (levenshtein($userDomain, $correctDomain) <= 2) {
                return $parts[0] . '@' . $correctDomain;
            }
        }

        return null;
    }

    private function getConcurrentSessions(string $ip): int
    {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(DISTINCT endpoint) FROM security_attempts 
                 WHERE identifier = ? AND type = 'ip' AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                [$ip]
            );

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 1;
        }
    }

    private function getRequestFrequency(string $ip): int
    {
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM security_attempts 
                 WHERE identifier = ? AND type = 'ip' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                [$ip]
            );

            return (int) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
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
        try {
            $whitelist = $this->db->fetchAll("SELECT ip FROM whitelisted_ips WHERE active = 1");
            $this->whitelistedIps = array_column($whitelist, 'ip');

            $blacklist = $this->db->fetchAll(
                "SELECT ip FROM blacklisted_ips WHERE active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
            );
            $this->blacklistedIps = array_column($blacklist, 'ip');
        } catch (\Exception $e) {
            // Tables might not exist yet or connection failed
            if ($this->whitelistedIps === null)
                $this->whitelistedIps = [];
            if ($this->blacklistedIps === null)
                $this->blacklistedIps = [];
        }
    }

    private function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->whitelistedIps);
    }

    private function isBlacklisted(string $ip): bool
    {
        return in_array($ip, $this->blacklistedIps);
    }

    private function addToBlacklist(string $ip, string $reason, int $duration = 3600): void
    {
        try {
            $this->db->query(
                "INSERT INTO blacklisted_ips (ip, reason, expires_at, created_at) 
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
                [$ip, $reason, $duration]
            );
            $this->blacklistedIps[] = $ip;
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
        $this->whitelistedIps[] = $ip;

        try {
            $this->db->query(
                "INSERT INTO whitelisted_ips (ip, created_at) VALUES (?, NOW()) 
                 ON DUPLICATE KEY UPDATE active = 1",
                [$ip]
            );
        } catch (\Exception $e) {
            // Silent fail
        }

        return $this;
    }
}
