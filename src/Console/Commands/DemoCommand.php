<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class DemoCommand extends Command
{
    protected string $description = 'Showcase advanced CLI UI components';

    public function handle(): int
    {
        $this->advancedHeader('CLI UI Showcase', 'Premium component library for Plugs');

        $this->section('Progress Indicators');

        $this->info('Standard Progress Bar:');
        for ($i = 1; $i <= 5; $i++) {
            $this->progress($i, 5, "Processing item $i...");
            usleep(200000);
        }
        $this->newLine();

        $this->info('Gradient Progress Bar:');
        for ($i = 1; $i <= 10; $i++) {
            $this->gradientProgressBar($i, 10, "Optimizing assets step $i...");
            usleep(150000);
        }
        $this->newLine();

        $this->section('Information Display');

        $this->statusCard('System Status', [
            'Environment' => 'Production',
            'PHP Version' => PHP_VERSION,
            'Framework' => 'Plugs v1.0.0',
            'Memory' => '12.4 MB',
            'Status' => 'Healthy ✅'
        ], 'success');

        $this->section('JSON Colorizer');
        $this->json([
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'active' => true,
                'settings' => null,
                'roles' => ['admin', 'editor']
            ],
            'stats' => [
                'logins' => 42,
                'last_seen' => date('Y-m-d H:i:s')
            ]
        ]);

        $this->section('Layouts & Highlighting');

        $this->sideBySide(
            "This is the old configuration that was used in the previous version of the framework. It contains some legacy settings that are no longer supported.",
            "This is the new configuration which is optimized for peak performance and follows the latest security standards recommended by the community.",
            "Old Configuration",
            "New Configuration"
        );

        $this->highlight(
            "The quick brown fox jumps over the lazy dog. This is a simple test of the highlighting feature within the Plugs framework.",
            ['fox', 'dog', 'Plugs'],
            "\033[92m" // Bright Green
        );

        $this->newLine();
        $this->success('Demo completed successfully!');

        return 0;
    }
}
