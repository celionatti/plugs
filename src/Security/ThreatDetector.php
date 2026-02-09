<?php

declare(strict_types=1);

namespace Plugs\Security;

use Psr\Http\Message\ServerRequestInterface;

/**
 * ThreatDetector service for dynamic threat detection.
 * Tracks suspicious patterns and provides threat scoring.
 */
class ThreatDetector
{
    private static array $suspiciousPatterns = [
        // XSS patterns
        '/<script\b[^>]*>/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        // SQL injection patterns
        '/(\bunion\b.*\bselect\b|\bselect\b.*\bfrom\b|\binsert\b.*\binto\b|\bdelete\b.*\bfrom\b)/i',
        '/(\bor\b|\band\b)\s+[\'"]\d+[\'"]?\s*=\s*[\'"]\d+/i',
        "/(--|#|;)/",
        // Path traversal
        '/\.\.\//',
        '/\.\.\\\\/',
    ];

    private static array $threatScores = [];

    private const SCORE_THRESHOLD = 10;
    private const DECAY_SECONDS = 300; // 5 minutes

    /**
     * Analyze a request for suspicious patterns.
     */
    public static function analyze(ServerRequestInterface $request): int
    {
        $ip = self::getClientIp($request);
        $score = 0;

        // Check query params
        foreach ($request->getQueryParams() as $value) {
            $score += self::scanValue($value);
        }

        // Check body params
        $body = $request->getParsedBody();
        if (is_array($body)) {
            foreach ($body as $value) {
                $score += self::scanValue($value);
            }
        }

        // Check headers for common attack vectors
        foreach ($request->getHeaders() as $header) {
            foreach ($header as $value) {
                $score += self::scanValue($value);
            }
        }

        // Accumulate score for IP
        self::addScore($ip, $score);

        return self::getScore($ip);
    }

    /**
     * Check if a request is suspicious.
     */
    public static function isSuspicious(ServerRequestInterface $request): bool
    {
        return self::analyze($request) >= self::SCORE_THRESHOLD;
    }

    /**
     * Record a failed authentication attempt.
     */
    public static function recordFailedAuth(ServerRequestInterface $request): void
    {
        $ip = self::getClientIp($request);
        self::addScore($ip, 3); // Each failed auth adds 3 points
    }

    /**
     * Get the current threat score for a request/IP.
     */
    public static function getScore(string $ip): int
    {
        self::decayScores();
        return self::$threatScores[$ip]['score'] ?? 0;
    }

    private static function addScore(string $ip, int $points): void
    {
        if (!isset(self::$threatScores[$ip])) {
            self::$threatScores[$ip] = ['score' => 0, 'updated_at' => time()];
        }
        self::$threatScores[$ip]['score'] += $points;
        self::$threatScores[$ip]['updated_at'] = time();
    }

    private static function decayScores(): void
    {
        $now = time();
        foreach (self::$threatScores as $ip => &$data) {
            if ($now - $data['updated_at'] > self::DECAY_SECONDS) {
                unset(self::$threatScores[$ip]);
            }
        }
    }

    private static function scanValue(mixed $value): int
    {
        if (!is_string($value)) {
            return 0;
        }

        $score = 0;
        foreach (self::$suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $score += 5; // Each pattern match adds 5 points
            }
        }
        return $score;
    }

    private static function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR']
            ?? $serverParams['REMOTE_ADDR']
            ?? 'unknown';
    }

    /**
     * Reset threat data (for testing).
     */
    public static function reset(): void
    {
        self::$threatScores = [];
    }
}
