<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\SecurityShieldManager;

class ShieldCommand extends Command
{
    protected string $description = 'Manage Security Shield (blacklist, whitelist, and rate limits)';

    protected array $signatures = [
        'shield:list' => 'List all blocked IPs and device fingerprints',
        'shield:unblock' => 'Unblock an IP or device fingerprint',
        'shield:block' => 'Manually block an IP or device fingerprint',
        'shield:clear' => 'Clear all blocks and security attempts',
        'shield:stats' => 'Display security shield statistics'
    ];

    public function handle(): int
    {
        $manager = new SecurityShieldManager();

        return match ($this->name) {
            'shield:list' => $this->listBlocks($manager),
            'shield:unblock' => $this->unblock($manager),
            'shield:block' => $this->block($manager),
            'shield:clear' => $this->clearShield($manager),
            'shield:stats' => $this->stats($manager),
            default => $this->listBlocks($manager),
        };
    }

    protected function listBlocks(SecurityShieldManager $manager): int
    {
        $this->title('Security Shield: Blocked Entities');

        $blacklist = $manager->getBlacklistedIps();
        $this->section('Blacklisted IPs');
        if (empty($blacklist)) {
            $this->info('No IPs are currently blacklisted.');
        } else {
            $this->table(['IP', 'Reason', 'Expires At', 'Blocked At'], array_map(function ($item) {
                return [
                    $item['ip'],
                    $item['reason'] ?? 'N/A',
                    $item['expires_at'] ?? 'Permanent',
                    $item['created_at']
                ];
            }, $blacklist));
        }

        $this->newLine();

        $this->section('Blocked Fingerprints');
        // We'll need a way to get blocked fingerprints in Manager if not already there
        // For now let's use what we have or add it.
        try {
            $fingerprints = \Plugs\Database\Connection::getInstance()->fetchAll("SELECT * FROM blocked_fingerprints");
            if (empty($fingerprints)) {
                $this->info('No device fingerprints are currently blocked.');
            } else {
                $this->table(['Fingerprint', 'Reason', 'Blocked At'], array_map(function ($item) {
                    return [
                        $item['fingerprint'],
                        $item['reason'] ?? 'N/A',
                        $item['created_at']
                    ];
                }, $fingerprints));
            }
        } catch (\Exception $e) {
            $this->warning('Could not load blocked fingerprints: ' . $e->getMessage());
        }

        return 0;
    }

    protected function unblock(SecurityShieldManager $manager): int
    {
        $args = array_values($this->arguments());
        $target = $args[0] ?? null;

        if (empty($target)) {
            $target = $this->ask('Enter the IP or Fingerprint to unblock');
        }

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            if ($manager->removeFromBlacklist($target)) {
                $manager->clearRateLimit($target);
                // Also clear the cache for IP lists
                \Plugs\Facades\Cache::delete('shield_ip_lists');
                $this->success("IP [{$target}] has been successfully unblocked.");
                return 0;
            }
        } else {
            if ($manager->unblockFingerprint($target)) {
                $this->success("Fingerprint [{$target}] has been successfully unblocked.");
                return 0;
            }
        }

        $this->error("Failed to unblock [{$target}]. It might not be blocked.");
        return 1;
    }

    protected function block(SecurityShieldManager $manager): int
    {
        $args = array_values($this->arguments());
        $target = $args[0] ?? null;
        $reason = (string) ($this->option('reason') ?? 'Manual block');

        if (empty($target)) {
            $target = $this->ask('Enter the IP or Fingerprint to block');
        }

        if (filter_var($target, FILTER_VALIDATE_IP)) {
            if ($manager->addToBlacklist($target, $reason)) {
                \Plugs\Facades\Cache::delete('shield_ip_lists');
                $this->success("IP [{$target}] has been blocked.");
                return 0;
            }
        } else {
            if ($manager->blockFingerprint($target, $reason)) {
                $this->success("Fingerprint [{$target}] has been blocked.");
                return 0;
            }
        }

        $this->error("Failed to block [{$target}].");
        return 1;
    }

    protected function clearShield(SecurityShieldManager $manager): int
    {
        if (!$this->confirm('Are you sure you want to clear ALL security blocks and logs?', false)) {
            $this->warning('Operation cancelled.');
            return 0;
        }

        try {
            $db = \Plugs\Database\Connection::getInstance();
            $db->execute("DELETE FROM blacklisted_ips");
            $db->execute("DELETE FROM blocked_fingerprints");
            $db->execute("DELETE FROM security_attempts");
            $db->execute("DELETE FROM security_logs");

            \Plugs\Facades\Cache::delete('shield_ip_lists');

            $this->success('Security Shield state has been fully cleared.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear security state: ' . $e->getMessage());
            return 1;
        }
    }

    protected function stats(SecurityShieldManager $manager): int
    {
        $days = (int) ($this->option('days') ?? 7);
        $stats = $manager->getStatistics($days);

        $this->title("Security Shield Statistics (Last {$days} Days)");

        $this->table(['Metric', 'Value'], [
            ['Total Requests Tracked', $stats['total_requests']],
            ['Blocked Requests', $stats['blocked_requests']],
            ['Allowed Requests', $stats['allowed_requests']],
            ['Security Challenges', $stats['challenge_issued'] ?? 0],
        ]);

        if (!empty($stats['top_blocked_ips'])) {
            $this->section('Top Blocked IPs');
            $this->table(['IP', 'Attempts'], $stats['top_blocked_ips']);
        }

        if (!empty($stats['risk_distribution'])) {
            $this->section('Risk Level Distribution');
            $this->table(['Level', 'Count'], $stats['risk_distribution']);
        }

        return 0;
    }
}
