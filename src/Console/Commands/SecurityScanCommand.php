<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Security\Scanner\SecurityScanner;

class SecurityScanCommand extends Command
{
    protected string $description = 'Scan project for security vulnerabilities and unsafe coding patterns';

    public function handle(): int
    {
        $this->advancedHeader('Security Scanner', 'Enforcing secure coding practices across the codebase');

        $this->info("Scanning project files...");
        $this->newLine();

        $scanner = new SecurityScanner();
        $results = $scanner->scanProject(getcwd());

        $issues = $results['issues'];
        $filesScanned = $results['files_scanned'];

        if (empty($issues)) {
            $this->success("No security vulnerabilities identified in {$filesScanned} files. Good job!");
            return 0;
        }

        $this->warning("Identified " . count($issues) . " potential security issues across " . count(array_unique(array_column($issues, 'path'))) . " files.");
        $this->newLine();

        $this->section('Security Audit Report');

        $severityWeights = [
            'CRITICAL' => 4,
            'HIGH' => 3,
            'MEDIUM' => 2,
            'LOW' => 1
        ];

        // Sort issues by severity
        usort($issues, function ($a, $b) use ($severityWeights) {
            return ($severityWeights[$b['severity']] ?? 0) <=> ($severityWeights[$a['severity']] ?? 0);
        });

        foreach ($issues as $index => $issue) {
            $this->displayIssue($index + 1, $issue);
        }

        $this->calculateAndDisplaySecurityScore($issues, $filesScanned);

        return 1; // Exit with error code if issues were found
    }

    protected function displayIssue(int $num, array $issue): void
    {
        $colors = [
            'CRITICAL' => 'red',
            'HIGH' => 'red',
            'MEDIUM' => 'yellow',
            'LOW' => 'yellow'
        ];

        $color = $colors[$issue['severity']] ?? 'white';
        $path = str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $issue['path']);

        $this->line("<options=bold>Issue #{$num}</> [<fg={$color}>{$issue['severity']}</>] - <fg=cyan>{$issue['scanner']}</>");
        $this->line("  <fg=gray>Location: {$path}:{$issue['line']}</>");
        $this->line("  <fg=white>Message: {$issue['message']}</>");
        $this->line("  <fg=green>Suggestion: {$issue['suggestion']}</>");
        $this->newLine();
    }

    protected function calculateAndDisplaySecurityScore(array $issues, int $filesScanned): void
    {
        $deductions = [
            'CRITICAL' => 15,
            'HIGH' => 10,
            'MEDIUM' => 5,
            'LOW' => 2
        ];

        $score = 100;
        foreach ($issues as $issue) {
            $score -= $deductions[$issue['severity']] ?? 0;
        }

        $score = max(0, $score);
        $color = $score > 80 ? 'green' : ($score > 50 ? 'yellow' : 'red');

        $this->divider();
        $this->line(sprintf(
            "Final Security Score: <fg=%s;options=bold>%d/100</>",
            $color,
            $score
        ));
        $this->line("Files Scanned: {$filesScanned}");
        $this->newLine();
    }
}
