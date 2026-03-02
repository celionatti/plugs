<?php

declare(strict_types=1);

namespace Plugs\Security\Jobs;

use Plugs\Queue\Job;
use Plugs\Database\Connection;

class SecurityLogJob implements Job
{
    /**
     * Execute the security logging job.
     *
     * @param array $data
     * @return void
     */
    public function handle($data)
    {
        $db = Connection::getInstance();
        $type = $data['job_type'] ?? 'log';

        try {
            if ($type === 'attempt') {
                $db->query(
                    "INSERT INTO security_attempts (identifier, type, endpoint, created_at) VALUES (?, ?, ?, NOW())",
                    [$data['identifier'], $data['type'], $data['endpoint']]
                );
            } else {
                $db->query(
                    "INSERT INTO security_logs (ip, email, endpoint, risk_score, decision, details, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $data['ip'],
                        $data['email'] ?? '',
                        $data['endpoint'],
                        $data['risk_score'],
                        $data['decision'],
                        $data['details'],
                    ]
                );
            }
        } catch (\Exception $e) {
            // Log error to system log if database write fails in background
            error_log("[SecurityLogJob] Failed to write to DB: " . $e->getMessage());
        }
    }
}
