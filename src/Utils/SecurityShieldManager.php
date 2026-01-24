<?php

declare(strict_types=1);

namespace Plugs\Utils;

/*
|--------------------------------------------------------------------------
| SecurityShieldManager Class
|--------------------------------------------------------------------------
|
| Utility class for managing SecurityShield operations such as:
| - Viewing statistics
| - Managing whitelist/blacklist
| - Cleaning up old logs
| - Blocking/unblocking IPs and fingerprints
|
| Usage:
| $manager = new SecurityShieldManager();
| $stats = $manager->getStatistics(7);
*/

use Plugs\Database\Connection;

class SecurityShieldManager
{
    private Connection $db;

    public function __construct(?Connection $db = null)
    {
        $this->db = $db ?? Connection::getInstance();
    }

    /**
     * Get security statistics
     *
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getStatistics(int $days = 7): array
    {
        $stats = [
            'total_requests' => 0,
            'blocked_requests' => 0,
            'allowed_requests' => 0,
            'challenge_issued' => 0,
            'top_blocked_ips' => [],
            'top_endpoints' => [],
            'blocked_by_reason' => [],
            'risk_distribution' => [],
        ];

        try {
            // Total requests
            $result = $this->db->fetch(
                "SELECT COUNT(*) as total FROM security_logs 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            $stats['total_requests'] = (int)($result['total'] ?? 0);

            // Blocked requests
            $result = $this->db->fetch(
                "SELECT COUNT(*) as blocked FROM security_logs 
                 WHERE decision = 'denied' AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            $stats['blocked_requests'] = (int)($result['blocked'] ?? 0);

            // Allowed requests
            $result = $this->db->fetch(
                "SELECT COUNT(*) as allowed FROM security_logs 
                 WHERE decision = 'allowed' AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$days]
            );
            $stats['allowed_requests'] = (int)($result['allowed'] ?? 0);

            // Top blocked IPs
            $stats['top_blocked_ips'] = $this->db->fetchAll(
                "SELECT ip, COUNT(*) as count FROM security_logs 
                 WHERE decision = 'denied' AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY ip ORDER BY count DESC LIMIT 10",
                [$days]
            );

            // Top endpoints
            $stats['top_endpoints'] = $this->db->fetchAll(
                "SELECT endpoint, COUNT(*) as count FROM security_logs 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY endpoint ORDER BY count DESC LIMIT 10",
                [$days]
            );

            // Risk score distribution
            $stats['risk_distribution'] = $this->db->fetchAll(
                "SELECT 
                    CASE 
                        WHEN risk_score < 0.3 THEN 'Low'
                        WHEN risk_score < 0.6 THEN 'Medium'
                        WHEN risk_score < 0.85 THEN 'High'
                        ELSE 'Critical'
                    END as risk_level,
                    COUNT(*) as count
                 FROM security_logs 
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY risk_level
                 ORDER BY FIELD(risk_level, 'Low', 'Medium', 'High', 'Critical')",
                [$days]
            );

        } catch (\Exception $e) {
            // Return empty stats on error
        }

        return $stats;
    }

    /**
     * Get recent blocked requests
     *
     * @param int $limit
     * @return array
     */
    public function getRecentBlocked(int $limit = 50): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM security_logs 
                 WHERE decision = 'denied' 
                 ORDER BY created_at DESC 
                 LIMIT ?",
                [$limit]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get whitelisted IPs
     *
     * @return array
     */
    public function getWhitelistedIps(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM whitelisted_ips WHERE active = 1 ORDER BY created_at DESC"
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get blacklisted IPs
     *
     * @return array
     */
    public function getBlacklistedIps(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM blacklisted_ips 
                 WHERE active = 1 AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY created_at DESC"
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Add IP to whitelist
     *
     * @param string $ip
     * @return bool
     */
    public function addToWhitelist(string $ip): bool
    {
        try {
            $this->db->query(
                "INSERT INTO whitelisted_ips (ip, created_at) VALUES (?, NOW()) 
                 ON DUPLICATE KEY UPDATE active = 1",
                [$ip]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove IP from whitelist
     *
     * @param string $ip
     * @return bool
     */
    public function removeFromWhitelist(string $ip): bool
    {
        try {
            $this->db->execute(
                "UPDATE whitelisted_ips SET active = 0 WHERE ip = ?",
                [$ip]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Add IP to blacklist
     *
     * @param string $ip
     * @param string $reason
     * @param int|null $duration Duration in seconds, null for permanent
     * @return bool
     */
    public function addToBlacklist(string $ip, string $reason = 'Manual block', ?int $duration = null): bool
    {
        try {
            if ($duration) {
                $this->db->query(
                    "INSERT INTO blacklisted_ips (ip, reason, expires_at, created_at) 
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())",
                    [$ip, $reason, $duration]
                );
            } else {
                $this->db->query(
                    "INSERT INTO blacklisted_ips (ip, reason, created_at) 
                     VALUES (?, ?, NOW())",
                    [$ip, $reason]
                );
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remove IP from blacklist
     *
     * @param string $ip
     * @return bool
     */
    public function removeFromBlacklist(string $ip): bool
    {
        try {
            $this->db->execute(
                "UPDATE blacklisted_ips SET active = 0 WHERE ip = ?",
                [$ip]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Block a device fingerprint
     *
     * @param string $fingerprint
     * @param string $reason
     * @return bool
     */
    public function blockFingerprint(string $fingerprint, string $reason = 'Manual block'): bool
    {
        try {
            $this->db->query(
                "INSERT INTO blocked_fingerprints (fingerprint, reason, created_at) 
                 VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE reason = ?",
                [$fingerprint, $reason, $reason]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Unblock a device fingerprint
     *
     * @param string $fingerprint
     * @return bool
     */
    public function unblockFingerprint(string $fingerprint): bool
    {
        try {
            $this->db->execute(
                "DELETE FROM blocked_fingerprints WHERE fingerprint = ?",
                [$fingerprint]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear rate limit for an IP
     *
     * @param string $ip
     * @return bool
     */
    public function clearRateLimit(string $ip): bool
    {
        try {
            $this->db->execute(
                "DELETE FROM security_attempts WHERE identifier = ? AND type = 'ip'",
                [$ip]
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if IP is rate limited
     *
     * @param string $ip
     * @return bool
     */
    public function isRateLimited(string $ip): bool
    {
        try {
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM security_attempts 
                 WHERE identifier = ? AND type = 'ip' 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
                [$ip]
            );

            return (int)($result['count'] ?? 0) >= 5;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up old logs and attempts
     *
     * @param int $daysToKeep
     * @return array Number of rows deleted
     */
    public function cleanup(int $daysToKeep = 30): array
    {
        $deleted = [
            'attempts' => 0,
            'logs' => 0,
            'expired_blacklist' => 0,
        ];

        try {
            // Delete old attempts
            $stmt = $this->db->query(
                "DELETE FROM security_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            $deleted['attempts'] = $stmt->rowCount();

            // Delete old logs
            $stmt = $this->db->query(
                "DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            $deleted['logs'] = $stmt->rowCount();

            // Delete expired blacklist entries
            $stmt = $this->db->query(
                "DELETE FROM blacklisted_ips WHERE expires_at IS NOT NULL AND expires_at < NOW()"
            );
            $deleted['expired_blacklist'] = $stmt->rowCount();

        } catch (\Exception $e) {
            // Silent fail
        }

        return $deleted;
    }

    /**
     * Get IP details including history
     *
     * @param string $ip
     * @return array
     */
    public function getIpDetails(string $ip): array
    {
        $details = [
            'ip' => $ip,
            'is_whitelisted' => false,
            'is_blacklisted' => false,
            'total_requests' => 0,
            'blocked_requests' => 0,
            'recent_activity' => [],
        ];

        try {
            // Check whitelist
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM whitelisted_ips WHERE ip = ? AND active = 1",
                [$ip]
            );
            $details['is_whitelisted'] = (int)($result['count'] ?? 0) > 0;

            // Check blacklist
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM blacklisted_ips 
                 WHERE ip = ? AND active = 1 AND (expires_at IS NULL OR expires_at > NOW())",
                [$ip]
            );
            $details['is_blacklisted'] = (int)($result['count'] ?? 0) > 0;

            // Total requests
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM security_logs WHERE ip = ?",
                [$ip]
            );
            $details['total_requests'] = (int)($result['count'] ?? 0);

            // Blocked requests
            $result = $this->db->fetch(
                "SELECT COUNT(*) as count FROM security_logs WHERE ip = ? AND decision = 'denied'",
                [$ip]
            );
            $details['blocked_requests'] = (int)($result['count'] ?? 0);

            // Recent activity
            $details['recent_activity'] = $this->db->fetchAll(
                "SELECT * FROM security_logs WHERE ip = ? ORDER BY created_at DESC LIMIT 20",
                [$ip]
            );

        } catch (\Exception $e) {
            // Return partial details
        }

        return $details;
    }

    /**
     * Export statistics to array
     *
     * @param int $days
     * @return array
     */
    public function exportStatistics(int $days = 7): array
    {
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'period_days' => $days,
            'statistics' => $this->getStatistics($days),
            'whitelisted_ips' => $this->getWhitelistedIps(),
            'blacklisted_ips' => $this->getBlacklistedIps(),
            'recent_blocked' => $this->getRecentBlocked(20),
        ];
    }
}
