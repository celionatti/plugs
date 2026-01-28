<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Support\HealthCheck;

class HealthCommand extends Command
{
    /**
     * The command name
     */
    protected string $name = 'health';

    /**
     * The command description
     */
    protected string $description = 'Verify the server environment and framework health';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('System Health Check');

        $this->info("Verifying server environment and framework configuration...");

        $healthCheck = new HealthCheck();
        $results = $healthCheck->runAll();

        $allOk = true;

        $this->checkpoint('checks_completed');

        $this->newLine();
        $this->section('Detailed Analysis');

        foreach ($results as $check) {
            $isOk = $check['status'];
            $statusIcon = $isOk ? "✓" : "✗";
            $statusLabel = $isOk ? "OK" : "FAIL";

            if ($isOk) {
                $this->success(sprintf("  %s %-25s [%s]", $statusIcon, $check['name'], $statusLabel));
            } else {
                $this->error(sprintf("  %s %-25s [%s]", $statusIcon, $check['name'], $statusLabel));
            }

            $this->line("    ↪ " . $check['message']);
            $this->newLine();

            if (!$isOk && $check['required']) {
                $allOk = false;
            }
        }

        $this->checkpoint('finished');

        $this->newLine();
        if ($allOk) {
            $this->box(
                "Framework is healthy and ready for development!\n\n" .
                "Total Checks: " . count($results) . "\n" .
                "Time: {$this->formatTime($this->elapsed())}",
                "✅ System Healthy",
                "success"
            );
        } else {
            $this->box(
                "Action Required: One or more critical checks failed.\n\n" .
                "Please address the issues listed above to ensure correct behavior.",
                "❌ System Unhealthy",
                "error"
            );
        }

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return $allOk ? 0 : 1;
    }
}
