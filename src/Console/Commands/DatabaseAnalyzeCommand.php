<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Database\Connection;
use Plugs\Database\Optimization\OptimizationManager;

class DatabaseAnalyzeCommand extends Command
{
    protected string $description = 'Analyze database queries and provide optimization recommendations';

    protected function defineOptions(): array
    {
        return [
            'clear' => 'Clear the slow query logs'
        ];
    }

    public function handle(): int
    {
        $this->advancedHeader('Database Query Analyzer', 'Identifying bottlenecks and index opportunities');

        $logger = new \Plugs\Database\Optimization\SlowQueryLogger();

        if ($this->hasOption('clear')) {
            $logger->clear();
            $this->success("Slow query logs have been cleared.");
            return 0;
        }

        $logs = $logger->readLogs();

        if (empty($logs)) {
            $this->info("No slow queries recorded yet. Try running some complex operations first.");
            return 0;
        }

        $this->info("Analyzed " . count($logs) . " slow queries from logs.");
        $this->newLine();

        $this->section('Optimization Report');

        foreach ($logs as $index => $log) {
            $this->displayLogAnalysis($index + 1, $log);
        }

        $this->newLine();
        $this->info("Run 'php framework:analyze-db --clear' to reset logs.");

        return 0;
    }

    protected function displayLogAnalysis(int $num, array $log): void
    {
        $sql = $log['sql'];
        $time = number_format($log['time'], 4);
        $score = $log['analysis']['score'] ?? 0;

        $status = $score > 80 ? 'success' : ($score > 50 ? 'warning' : 'error');
        $color = $score > 80 ? 'green' : ($score > 50 ? 'yellow' : 'red');

        $this->line("<options=bold>Query #{$num}</> [<fg={$color}>Score: {$score}/100</>] - Took <fg=cyan>{$time}s</>");
        $this->line("<fg=gray>{$sql}</>");

        if (!empty($log['analysis']['issues'])) {
            $this->line("  <options=bold>Issues:</>");
            foreach ($log['analysis']['issues'] as $issue) {
                $severityColor = $issue['severity'] === 'CRITICAL' ? 'red' : 'yellow';
                $this->line("    • [<fg={$severityColor}>{$issue['severity']}</>] {$issue['message']}");
                $this->line("      <fg=gray>Suggestion: {$issue['suggestion']}</>");
            }
        }

        if (!empty($log['suggestions'])) {
            $this->line("  <options=bold>Recommended Indexes:</>");
            foreach ($log['suggestions'] as $suggestion) {
                $this->line("    • <fg=green>{$suggestion['sql']}</>");
            }
        }

        $this->newLine();
    }
}
