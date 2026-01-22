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
        $this->info("Running Environment Health Check...");
        $this->line("--------------------------------------------------");

        $healthCheck = new HealthCheck();
        $results = $healthCheck->runAll();

        $allOk = true;

        foreach ($results as $check) {
            $status = $check['status'] ? "[OK]" : "[FAIL]";
            $color = $check['status'] ? "info" : "error";

            $this->{$color}(sprintf("%-25s %s", $check['name'], $status));
            $this->line("   " . $check['message']);
            $this->line("");

            if (!$check['status'] && $check['required']) {
                $allOk = false;
            }
        }

        $this->line("--------------------------------------------------");

        if ($allOk) {
            $this->info("Overall Status: HEALTHY");
        } else {
            $this->error("Overall Status: UNHEALTHY");
            $this->note("Please address the issues listed above to ensure the framework functions correctly.");
        }

        return $allOk ? 0 : 1;
    }
}
