<?php

declare(strict_types=1);

namespace Plugs\Security\Auth;

use Plugs\Security\Auth\Authenticatable;
use Plugs\Security\Auth\Models\DeviceToken;
use Plugs\Database\Connection;

/**
 * DeviceTrustManager
 *
 * Centralized service for handling device trust in the passwordless system.
 */
class DeviceTrustManager
{
    private const COOKIE_NAME = 'device_trust_token';
    private const LIFETIME = 90 * 24 * 60 * 60; // 90 days

    /**
     * Trust current device for the given user.
     * Invalidates other sessions to preserve the one-active-trust policy.
     */
    public function trust(Authenticatable $user, ?string $userAgent = null, ?string $ip = null): void
    {
        $userId = (int) $user->getAuthIdentifier();
        $userAgent = $userAgent ?: ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        // 1. Invalidate other database sessions for this user
        $this->invalidateOtherSessions($userId);

        // 2. Create/Update the single device token for this user
        $deviceName = $this->parseDevice($userAgent);
        $result = DeviceToken::createForUser($userId, $deviceName, $ip);

        // 3. Set the cookie
        $this->setTrustCookie($result['raw_token']);
    }

    /**
     * Check if the current device is trusted for the user.
     */
    public function isTrusted(Authenticatable $user, ?string $ip = null): bool
    {
        $rawToken = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!$rawToken) {
            return false;
        }

        $tokenHash = hash('sha256', $rawToken);
        $deviceToken = DeviceToken::findValidToken($tokenHash);

        if (!$deviceToken || (int) $deviceToken->user_id !== (int) $user->getAuthIdentifier()) {
            return false;
        }

        // Update last used timestamp and IP
        $deviceToken->touchLastUsed($ip ?: ($_SERVER['REMOTE_ADDR'] ?? null));

        return true;
    }

    /**
     * Invalidate all user sessions except the current one.
     */
    public function invalidateOtherSessions(int $userId): void
    {
        Connection::getInstance()->execute(
            "DELETE FROM sessions WHERE user_id = ? AND id != ?",
            [$userId, session_id()]
        );
    }

    /**
     * Set the secure device trust cookie.
     */
    private function setTrustCookie(string $rawToken): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::COOKIE_NAME, $rawToken, [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Granular device detail parsing from User-Agent.
     */
    public function parseDevice(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        $browser = 'Browser';
        if (preg_match('/(Edge|Edg)\/([\d\.]+)/', $userAgent, $matches)) {
            $browser = "Edge {$matches[2]}";
        } elseif (preg_match('/(Chrome|CriOS)\/([\d\.]+)/', $userAgent, $matches)) {
            $browser = "Chrome {$matches[2]}";
        } elseif (preg_match('/(Firefox|FxiOS)\/([\d\.]+)/', $userAgent, $matches)) {
            $browser = "Firefox {$matches[2]}";
        } elseif (preg_match('/Safari\/([\d\.]+)/', $userAgent, $matches)) {
            $browser = "Safari {$matches[1]}";
        }

        $os = 'Unknown OS';
        if (preg_match('/Windows NT ([\d\.]+)/', $userAgent, $matches)) {
            $osMap = ['10.0' => 'Windows 10/11', '6.3' => 'Windows 8.1', '6.2' => 'Windows 8', '6.1' => 'Windows 7'];
            $os = $osMap[$matches[1]] ?? "Windows (NT {$matches[1]})";
        } elseif (preg_match('/Mac OS X ([\d_]+)/', $userAgent, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Android ([\d\.]+)/', $userAgent, $matches)) {
            $os = "Android {$matches[1]}";
        } elseif (preg_match('/iPhone OS ([\d_]+)/', $userAgent, $matches)) {
            $os = 'iOS ' . str_replace('_', '.', $matches[1]);
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        return "{$browser} on {$os}";
    }
}
