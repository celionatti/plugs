<?php

declare(strict_types=1);

namespace Plugs\Http\Utils;

use Psr\Http\Message\ServerRequestInterface;

/**
 * HttpUtils
 * 
 * Centralized utility for framework-wide HTTP security and state detection.
 */
class HttpUtils
{
    /**
     * Determine if the current connection is secure.
     * 
     * This considers the PSR-7 request scheme (which may be rewritten by TrustedProxyMiddleware),
     * as well as standard PHP server variables as backstops.
     */
    public static function isSecure(?ServerRequestInterface $request = null): bool
    {
        // 1. Check PSR-7 request if provided
        if ($request) {
            if ($request->getUri()->getScheme() === 'https') {
                return true;
            }
        }

        // 2. Fallback to global current request if available
        if (isset($GLOBALS['__current_request']) && $GLOBALS['__current_request'] instanceof ServerRequestInterface) {
            if ($GLOBALS['__current_request']->getUri()->getScheme() === 'https') {
                return true;
            }
        }

        // 3. Fallback to standard Server variables (if not behind proxy or if proxy is not configured)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        // 4. Check standard proxy headers (last resort, should be handled by middleware)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }

    /**
     * Get the real client IP address.
     * 
     * Prioritizes the 'client_ip' attribute which is set by TrustedProxyMiddleware
     * after validating against trusted proxy lists.
     */
    public static function getClientIp(?ServerRequestInterface $request = null): string
    {
        // 1. Check PSR-7 request attribute (Set by TrustedProxyMiddleware)
        if ($request) {
            $ip = $request->getAttribute('client_ip');
            if ($ip) {
                return (string) $ip;
            }

            // Fallback to ServerParams
            $params = $request->getServerParams();
            if (isset($params['REMOTE_ADDR'])) {
                return $params['REMOTE_ADDR'];
            }
        }

        // 2. Fallback to standard REMOTE_ADDR
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
