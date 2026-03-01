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
    private const LIFETIME_MINUTES = 129600; // 90 days in minutes

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
     * Only enforces trust for users utilizing the Identity system (with a public_key).
     */
    public function isTrusted(Authenticatable $user, ?string $ip = null): bool
    {
        // 1. If user is not an Identity user (no public key), they don't use device trust
        // We allow standard password-based sessions to pass through.
        if (method_exists($user, 'getPublicKey') && !$user->getPublicKey()) {
            return true;
        }

        $rawToken = \Plugs\Facades\Cookie::get(self::COOKIE_NAME);
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
        \Plugs\Facades\Cookie::set(self::COOKIE_NAME, $rawToken, self::LIFETIME_MINUTES);
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
