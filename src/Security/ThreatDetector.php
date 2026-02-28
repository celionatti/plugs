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
        '/\bexpression\s*\(/i',
        // SQL injection patterns
        '/(\bunion\b.*\bselect\b|\bselect\b.*\bfrom\b|\binsert\b.*\binto\b|\bdelete\b.*\bfrom\b)/i',
        '/(\bor\b|\band\b)\s+[\'"]\d+[\'"]?\s*=\s*[\'"]\d+/i',
        // Instead of matching any `;`, check for known malicious chaining ` ; ` or SQL comment sequence `-- `
        "/(?:\s--\s|\s#\s|;\s*(?:DROP|ALTER|CREATE|TRUNCATE))/i",
        '/\bwaitfor\b\s+\bdelay\b/i',
        '/\bbenchmark\s*\(/i',
        // Path traversal
        '/\.\.\//',
        '/\.\.\\\\/',
        // Command injection
        '/;\s*(ls|cat|rm|wget|curl|bash|sh|nc|netcat|python|perl|php)\b/i',
        '/\|\s*(ls|cat|rm|wget|curl|bash|sh)\b/i',
        '/`[^`]+`/',
        '/\$\([^)]+\)/',
        // Null byte injection
        '/\x00/',
        '/\\\\0/',
        // LDAP injection: avoid matching normal parentheses and MIME asterisks
        '/(?:\x00|(?<=\=)\s*\()|(?<=\=)\s*\*/i',
    ];

    private const SCORE_THRESHOLD = 10;
    private const CACHE_PREFIX = 'security:threat:';
    private const CACHE_TTL = 3600; // 1 hour

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

        // Check uploaded file names for traversal
        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles)) {
            $score += self::scanUploadedFiles($uploadedFiles);
        }

        // Accumulate score for IP
        if ($score > 0) {
            self::addScore($ip, $score);
        }

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
        return (int) \Plugs\Facades\Cache::get(self::CACHE_PREFIX . $ip, 0);
    }

    private static function addScore(string $ip, int $points): void
    {
        $key = self::CACHE_PREFIX . $ip;
        $current = (int) \Plugs\Facades\Cache::get($key, 0);
        \Plugs\Facades\Cache::set($key, $current + $points, self::CACHE_TTL);
    }

    private static function scanValue(mixed $value): int
    {
        if (!is_string($value)) {
            return 0;
        }

        $score = 0;
        foreach (self::$suspiciousPatterns as $pattern) {
            if (@preg_match($pattern, $value)) {
                $score += 5; // Each pattern match adds 5 points
            }
        }
        return $score;
    }

    /**
     * Scan uploaded file names for path traversal and dangerous extensions.
     */
    private static function scanUploadedFiles(array $files): int
    {
        $score = 0;
        $dangerousExtensions = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'cgi'];

        foreach ($files as $file) {
            if (is_array($file)) {
                $score += self::scanUploadedFiles($file);
                continue;
            }

            if (method_exists($file, 'getClientFilename')) {
                $name = $file->getClientFilename() ?? '';

                // Check for path traversal in filename
                if (preg_match('/\.\.[\\/\\\\]/', $name) || str_contains($name, "\0")) {
                    $score += 10;
                }

                // Check for dangerous extensions (double extension attacks)
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, $dangerousExtensions, true)) {
                    $score += 8;
                }

                // Double extension: file.php.jpg
                if (preg_match('/\.(' . implode('|', $dangerousExtensions) . ')\.\w+$/i', $name)) {
                    $score += 8;
                }
            }
        }

        return $score;
    }

    private static function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['HTTP_X_FORWARDED_FOR']
            ?? $serverParams['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Reset threat data (for testing).
     */
    public static function reset(): void
    {
        // This is tricky with globally shared cache, but we can't easily clear by prefix 
        // without knowing the driver capabilities. For now, leave as is or clear specific keys if known.
    }
}

