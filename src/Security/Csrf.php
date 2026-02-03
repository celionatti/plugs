<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| Csrf Class
|--------------------------------------------------------------------------
|
| Provides comprehensive protection against Cross-Site Request Forgery attacks
| with token rotation, per-request tokens, and configurable security levels.
|
| This class is for generating and validating CSRF tokens to protect against
| Cross-Site Request Forgery attacks.
| --------------------------------------------------------------------------
|
| You can configure token lifetime, enable per-request tokens for enhanced
| security, and choose whether to regenerate tokens upon verification.
| --------------------------------------------------------------------------
|
| @package Plugs\Security
*/

class Csrf
{
    /**
     * Session key for storing the master CSRF token
     */
    private const TOKEN_KEY = '_csrf_token';

    /**
     * Session key for storing per-request tokens
     */
    private const REQUEST_TOKENS_KEY = '_csrf_request_tokens';

    /**
     * Session key for token generation timestamp
     */
    private const TOKEN_TIMESTAMP_KEY = '_csrf_token_timestamp';

    /**
     * Default token lifetime in seconds (2 hours)
     */
    private const DEFAULT_TOKEN_LIFETIME = 7200;

    /**
     * Maximum number of per-request tokens to store
     */
    private const MAX_REQUEST_TOKENS = 10;

    /**
     * Token length in bytes (will be hex encoded to 64 characters)
     */
    private const TOKEN_LENGTH = 32;

    /**
     * Validation status constants
     */
    public const STATUS_VALID = 'valid';
    public const STATUS_MISSING = 'missing';
    public const STATUS_MISMATCH = 'mismatch';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONTEXT_MISMATCH = 'context_mismatch';

    /**
     * Last validation error
     */
    private static string $lastError = self::STATUS_VALID;

    /**
     * Configuration options
     */
    private static array $config = [
        'token_lifetime' => self::DEFAULT_TOKEN_LIFETIME,
        'use_per_request_tokens' => false,
        'regenerate_on_verify' => false,
        'strict_mode' => true,
        'use_masking' => true, // New: Enable token masking for BREACH protection
        'context_bound' => true, // New: Bind token to session/UA
    ];

    /**
     * Initialize/configure CSRF protection
     *
     * @param array $options Configuration options
     * @return void
     */
    public static function configure(array $options = []): void
    {
        self::$config = array_merge(self::$config, $options);
    }

    /**
     * Generate or retrieve the master CSRF token
     *
     * @return string The CSRF token
     * @throws \RuntimeException If random bytes generation fails
     */
    public static function generate(): string
    {
        $session = self::getSession();

        // Check if token exists and is still valid
        if (self::hasValidToken()) {
            return $session->get(self::TOKEN_KEY);
        }

        // Generate new token
        return self::regenerate();
    }

    /**
     * Get the current CSRF token (alias for generate)
     *
     * @return string The CSRF token
     * @throws \RuntimeException If random bytes generation fails
     */
    /**
     * Get the current CSRF token (masked if enabled)
     *
     * @return string The CSRF token
     * @throws \RuntimeException If random bytes generation fails
     */
    public static function token(): string
    {
        $token = self::generate();

        if (self::$config['use_masking']) {
            return self::getMaskedToken($token);
        }

        return $token;
    }

    /**
     * Generate a new CSRF token (force regeneration)
     *
     * @return string The new CSRF token
     * @throws \RuntimeException If random bytes generation fails
     */
    public static function regenerate(): string
    {
        $session = self::getSession();

        try {
            $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to generate CSRF token: ' . $e->getMessage(),
                0,
                $e
            );
        }

        $session->set(self::TOKEN_KEY, $token);
        $session->set(self::TOKEN_TIMESTAMP_KEY, time());

        // Clear old per-request tokens when regenerating master token
        $session->remove(self::REQUEST_TOKENS_KEY);

        if (self::$config['context_bound']) {
            $session->set('_csrf_context', self::getContextFingerprint());
        }

        return $token;
    }

    /**
     * Generate a per-request CSRF token (one-time use)
     *
     * @return string The per-request token
     * @throws \RuntimeException If random bytes generation fails
     */
    public static function generateRequestToken(): string
    {
        $session = self::getSession();

        // Ensure master token exists
        self::generate();

        try {
            $requestToken = bin2hex(random_bytes(self::TOKEN_LENGTH));
        } catch (\Exception $e) {
            throw new \RuntimeException(
                'Failed to generate request token: ' . $e->getMessage(),
                0,
                $e
            );
        }

        // Initialize request tokens array if needed
        $tokens = $session->get(self::REQUEST_TOKENS_KEY, []);
        $tokens[$requestToken] = time();
        $session->set(self::REQUEST_TOKENS_KEY, $tokens);

        // Cleanup old tokens if we exceed maximum
        self::cleanupRequestTokens();

        return $requestToken;
    }

    /**
     * Verify a CSRF token
     *
     * @param string|null $token The token to verify (null to auto-detect)
     * @param bool $consumeRequestToken Whether to consume per-request tokens
     * @return bool True if valid, false otherwise
     */
    public static function verify(?string $token = null, bool $consumeRequestToken = true): bool
    {
        self::$lastError = self::STATUS_VALID;

        // Auto-detect token if not provided
        if ($token === null) {
            $token = self::getTokenFromRequest();
        }

        if ($token === null || $token === '') {
            self::$lastError = self::STATUS_MISSING;
            return false;
        }

        // Unmask if it looks like a masked token
        if (self::$config['use_masking']) {
            $unmasked = self::unmaskToken($token);
            if ($unmasked !== null) {
                $token = $unmasked;
            }
        }

        // Check master token first
        $session = self::getSession();
        $sessionToken = $session->get(self::TOKEN_KEY);

        if ($sessionToken !== null && self::constantTimeCompare($sessionToken, $token)) {
            // Check context if enabled
            if (self::$config['context_bound'] && $session->has('_csrf_context')) {
                if (!self::constantTimeCompare($session->get('_csrf_context'), self::getContextFingerprint())) {
                    self::$lastError = self::STATUS_CONTEXT_MISMATCH;
                    return false;
                }
            }

            // Check if token has expired
            if (self::$config['strict_mode'] && self::isTokenExpired()) {
                self::$lastError = self::STATUS_EXPIRED;
                self::regenerate();

                return false;
            }

            // Optionally regenerate after verification
            if (self::$config['regenerate_on_verify']) {
                self::regenerate();
            }

            return true;
        }

        // Check per-request tokens if enabled
        if (self::$config['use_per_request_tokens'] && $session->has(self::REQUEST_TOKENS_KEY)) {
            $requestTokens = $session->get(self::REQUEST_TOKENS_KEY);

            foreach ($requestTokens as $storedToken => $timestamp) {
                if (self::constantTimeCompare($storedToken, $token)) {
                    // Check token age
                    if (
                        self::$config['strict_mode'] &&
                        (time() - $timestamp) > self::$config['token_lifetime']
                    ) {
                        unset($requestTokens[$storedToken]);
                        $session->set(self::REQUEST_TOKENS_KEY, $requestTokens);

                        return false;
                    }

                    // Consume the token (one-time use)
                    if ($consumeRequestToken) {
                        unset($requestTokens[$storedToken]);
                        $session->set(self::REQUEST_TOKENS_KEY, $requestTokens);
                    }

                    return true;
                }
            }
        }

        self::$lastError = self::STATUS_MISMATCH;
        return false;
    }

    /**
     * Get the last validation error
     *
     * @return string One of the STATUS_* constants
     */
    public static function getLastError(): string
    {
        return self::$lastError;
    }

    /**
     * Generate an HTML hidden input field with CSRF token
     *
     * @param bool $useRequestToken Whether to use per-request token
     * @return string HTML input field
     * @throws \RuntimeException If token generation fails
     */
    public static function field(bool $useRequestToken = false): string
    {
        $token = $useRequestToken ? self::generateRequestToken() : self::token();

        return sprintf(
            '<input type="hidden" name="_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generate a meta tag with CSRF token (for JavaScript use)
     *
     * @return string HTML meta tag
     * @throws \RuntimeException If token generation fails
     */
    public static function metaTag(): string
    {
        $token = self::token();

        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get CSRF token as JSON (for AJAX requests)
     *
     * @param bool $useRequestToken Whether to use per-request token
     * @return string JSON string
     * @throws \RuntimeException If token generation fails
     */
    public static function json(bool $useRequestToken = false): string
    {
        $token = $useRequestToken ? self::generateRequestToken() : self::token();

        return json_encode([
            'csrf_token' => $token,
            'token_name' => '_token',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Clear/invalidate all CSRF tokens
     *
     * @return void
     */
    public static function clear(): void
    {
        $session = self::getSession();

        $session->remove(self::TOKEN_KEY);
        $session->remove(self::TOKEN_TIMESTAMP_KEY);
        $session->remove(self::REQUEST_TOKENS_KEY);
    }

    /**
     * Check if current request has valid CSRF token
     *
     * @return bool True if valid, false otherwise
     */
    public static function validateRequest(): bool
    {
        $token = self::getTokenFromRequest();

        return self::verify($token);
    }

    /**
     * Get CSRF token from current request
     *
     * @return string|null The token or null if not found
     */
    private static function getTokenFromRequest(): ?string
    {
        // Check POST data
        if (isset($_POST['_token']) && is_string($_POST['_token'])) {
            return $_POST['_token'];
        }

        // Check JSON body
        if (
            isset($_SERVER['CONTENT_TYPE']) &&
            stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) {
            $input = file_get_contents('php://input');
            if ($input !== false && $input !== '') {
                $data = json_decode($input, true);
                if (isset($data['_token']) && is_string($data['_token'])) {
                    return $data['_token'];
                }
            }
        }

        // Check headers
        $headers = [
            'HTTP_X_CSRF_TOKEN',
            'HTTP_X_XSRF_TOKEN',
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && is_string($_SERVER[$header])) {
                return $_SERVER[$header];
            }
        }

        return null;
    }

    /**
     * Mask a CSRF token to prevent BREACH attacks
     *
     * @param string $token The raw token
     * @return string The masked token
     */
    public static function getMaskedToken(string $token): string
    {
        // Generate random XOR mask
        $mask = random_bytes(self::TOKEN_LENGTH);

        // XOR the token with the mask
        $masked = $token ^ $mask;

        // Return mask + masked token, hex encoded
        return bin2hex($mask . $masked);
    }

    /**
     * Unmask a CSRF token
     *
     * @param string $maskedToken The masked token
     * @return string|null The raw token or null if invalid
     */
    public static function unmaskToken(string $maskedToken): ?string
    {
        // Masked token is hex(mask[32] + masked[32]) = 128 chars
        if (strlen($maskedToken) !== self::TOKEN_LENGTH * 4) {
            return null;
        }

        $decoded = hex2bin($maskedToken);
        if (!$decoded) {
            return null;
        }

        $maskSize = self::TOKEN_LENGTH;
        $mask = substr($decoded, 0, $maskSize);
        $masked = substr($decoded, $maskSize);

        return $masked ^ $mask;
    }

    /**
     * Set the XSRF-TOKEN cookie for AJAX applications
     *
     * @return void
     */
    public static function setXsrftokenCookie(): void
    {
        self::ensureSessionStarted();

        $token = self::generate(); // Use raw token for the cookie

        setcookie('XSRF-TOKEN', $token, [
            'expires' => time() + self::$config['token_lifetime'],
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Get the session instance
     */
    private static function getSession(): \Plugs\Session\Session
    {
        $container = \Plugs\Container\Container::getInstance();
        if ($container->bound('session')) {
            return $container->make('session');
        }

        return new \Plugs\Session\Session();
    }

    /**
     * Ensure session is started (Deprecated: Use getSession() instead)
     *
     * @return void
     */
    private static function ensureSessionStarted(): void
    {
        self::getSession();
    }

    private static function hasValidToken(): bool
    {
        $session = self::getSession();

        if (!$session->has(self::TOKEN_KEY)) {
            return false;
        }

        // If strict mode is disabled, any existing token is valid
        if (!self::$config['strict_mode']) {
            return true;
        }

        // Check expiration
        return !self::isTokenExpired();
    }

    private static function isTokenExpired(): bool
    {
        $session = self::getSession();

        if (!$session->has(self::TOKEN_TIMESTAMP_KEY)) {
            return true;
        }

        $age = time() - $session->get(self::TOKEN_TIMESTAMP_KEY);

        return $age > self::$config['token_lifetime'];
    }

    private static function getContextFingerprint(): string
    {
        $session = self::getSession();
        $sessionId = $session->getId();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        return hash('sha256', $sessionId . '|' . $userAgent);
    }

    private static function cleanupRequestTokens(): void
    {
        $session = self::getSession();

        if (!$session->has(self::REQUEST_TOKENS_KEY)) {
            return;
        }

        $tokens = $session->get(self::REQUEST_TOKENS_KEY);

        // Remove expired tokens
        $currentTime = time();
        foreach ($tokens as $token => $timestamp) {
            if (($currentTime - $timestamp) > self::$config['token_lifetime']) {
                unset($tokens[$token]);
            }
        }

        // If still too many, remove oldest
        if (count($tokens) > self::MAX_REQUEST_TOKENS) {
            asort($tokens);
            $tokens = array_slice($tokens, -self::MAX_REQUEST_TOKENS, null, true);
        }

        $session->set(self::REQUEST_TOKENS_KEY, $tokens);
    }

    /**
     * Constant-time string comparison to prevent timing attacks
     *
     * @param string $known The known string
     * @param string $user The user-supplied string
     * @return bool True if strings match
     */
    private static function constantTimeCompare(string $known, string $user): bool
    {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $user);
        }

        // Fallback for older PHP versions (though declare(strict_types=1) requires 7.0+)
        if (strlen($known) !== strlen($user)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($known); $i++) {
            $result |= ord($known[$i]) ^ ord($user[$i]);
        }

        return $result === 0;
    }

    /**
     * Get current configuration
     *
     * @return array Current configuration
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    public static function isConfigured(): bool
    {
        return self::getSession()->has(self::TOKEN_KEY);
    }

    /**
     * Check if a URI should be excluded from CSRF verification
     *
     * @param string $uri The URI to check
     * @param array $except Array of patterns to exclude
     * @return bool True if should be excluded
     */
    public static function shouldExclude(string $uri, array $except = []): bool
    {
        foreach ($except as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify CSRF token from PSR-7 ServerRequest
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request object
     * @param bool $consumeRequestToken Whether to consume per-request tokens
     * @return bool True if valid
     */
    public static function verifyRequest($request, bool $consumeRequestToken = true): bool
    {
        self::ensureSessionStarted();
        self::$lastError = self::STATUS_VALID;

        $token = null;

        // Check parsed body (POST/PUT/PATCH)
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['_token'])) {
            $token = $parsedBody['_token'];
        }

        // Check JSON body if not found
        if (
            $token === null &&
            stripos($request->getHeaderLine('Content-Type'), 'application/json') !== false
        ) {
            $body = (string) $request->getBody();
            $request->getBody()->rewind(); // Reset stream for next middleware

            if (!empty($body)) {
                $data = json_decode($body, true);
                if (isset($data['_token'])) {
                    $token = $data['_token'];
                }
            }
        }

        // Check headers
        if ($token === null) {
            $headerToken = $request->getHeaderLine('X-CSRF-TOKEN');
            if ($headerToken !== '') {
                $token = $headerToken;
            }
        }

        if ($token === null) {
            $headerToken = $request->getHeaderLine('X-XSRF-TOKEN');
            if ($headerToken !== '') {
                $token = $headerToken;
            }
        }

        return $token !== null && self::verify($token, $consumeRequestToken);
    }
}
