<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Demo Command
|--------------------------------------------------------------------------
| Displays all registered commands with descriptions
*/

use Plugs\Console\Command;

class DemoCommand extends Command
{
    protected string $description = 'Demonstration of all console features and capabilities';

    public function handle(): int
    {
        $this->banner('CONSOLE FEATURES DEMO');
        
        $choice = $this->choice(
            'What would you like to see?',
            [
                'Output Styles',
                'Interactive Input',
                'Progress Indicators',
                'Tables & Boxes',
                'Complete Demo',
                'Exit'
            ],
            'Complete Demo'
        );

        match ($choice) {
            'Output Styles' => $this->demoOutputStyles(),
            'Interactive Input' => $this->demoInteractiveInput(),
            'Progress Indicators' => $this->demoProgress(),
            'Tables & Boxes' => $this->demoTablesAndBoxes(),
            'Complete Demo' => $this->runCompleteDemo(),
            'Exit' => $this->info('👋 Goodbye!'),
        };

        return 0;
    }

    private function demoOutputStyles(): void
    {
        $this->section('Output Styles');
        
        $this->info('This is an info message ℹ️');
        $this->success('This is a success message ✓');
        $this->warning('This is a warning message ⚠');
        $this->error('This is an error message ✗');
        $this->critical('This is a critical message!');
        $this->note('This is a helpful note');
        $this->debug('This is debug output (only with --debug)');
        
        $this->line();
        $this->gradient('This text has a beautiful gradient effect!');
        $this->line();
        
        $this->header('Beautiful Header');
        
        if ($this->confirm('Run again?', false)) {
            $this->demoOutputStyles();
        }
    }

    private function demoInteractiveInput(): void
    {
        $this->section('Interactive Input Demo');
        
        $name = $this->ask('What is your name?', 'Developer');
        $this->success("Hello, {$name}!");
        
        $this->line();
        
        $language = $this->choice(
            'What is your favorite programming language?',
            ['PHP', 'JavaScript', 'Python', 'Go', 'Rust'],
            'PHP'
        );
        $this->info("Great choice! {$language} is awesome!");
        
        $this->line();
        
        $frameworks = $this->multiChoice(
            'Which frameworks do you use?',
            ['Laravel', 'Symfony', 'CodeIgniter', 'CakePHP', 'Yii'],
            ['Laravel']
        );
        
        if (!empty($frameworks)) {
            $this->success('You selected: ' . implode(', ', $frameworks));
        }
        
        $this->line();
        
        if ($this->confirm('Would you like to see more demos?', true)) {
            $this->info('Awesome! Let\'s continue...');
        } else {
            $this->warning('No problem, exiting demo...');
        }
    }

    private function demoProgress(): void
    {
        $this->section('Progress Indicators');
        
        $this->task('Processing data', function() {
            sleep(2);
            return 'Data processed successfully';
        });
        
        $this->line();
        
        $this->withProgressBar(20, function($step) {
            usleep(100000);
        }, 'Downloading Files');
        
        $this->line();
        
        $this->info('Multi-step process:');
        for ($i = 1; $i <= 5; $i++) {
            $this->step($i, 5, "Processing step {$i}");
            usleep(300000);
        }
        
        $this->line();
        
        if ($this->confirm('Show countdown demo?', true)) {
            $this->countdown(3, 'Demo starting in');
        }
    }

    private function demoTablesAndBoxes(): void
    {
        $this->section('Tables and Boxes');
        
        $this->info('Sample User Table:');
        $this->line();
        
        $headers = ['ID', 'Name', 'Email', 'Role', 'Status'];
        $rows = [
            ['1', 'John Doe', 'john@example.com', 'Admin', 'Active'],
            ['2', 'Jane Smith', 'jane@example.com', 'User', 'Active'],
            ['3', 'Bob Wilson', 'bob@example.com', 'Moderator', 'Inactive'],
            ['4', 'Alice Brown', 'alice@example.com', 'User', 'Active'],
        ];
        
        $this->table($headers, $rows);
        
        $this->box(
            "This is an informational box.\nIt can contain multiple lines.\nPerfect for important messages!",
            "📘 Information",
            "info"
        );
        
        $this->box(
            "Operation completed successfully!\nAll files have been processed.",
            "✅ Success",
            "success"
        );
        
        $this->box(
            "Warning: This action cannot be undone.\nPlease review before proceeding.",
            "⚠️ Warning",
            "warning"
        );
    }

    private function runCompleteDemo(): void
    {
        $this->section('Complete Feature Demonstration');
        
        $this->checkpoint('demo_start');
        
        $this->info('Starting complete demonstration...');
        $this->line();
        
        $userName = $this->ask('Enter your name for personalization', 'User');
        $this->success("Welcome to the demo, {$userName}!");
        
        $this->line();
        $this->checkpoint('input_complete');
        
        $result = $this->task('Initializing framework components', function() {
            sleep(2);
            return true;
        });
        
        $this->checkpoint('initialization_complete');
        
        $this->line();
        $this->withProgressBar(15, function($step) {
            usleep(150000);
        }, 'Processing Resources');
        
        $this->checkpoint('processing_complete');
        
        $this->line();
        $this->section('Processing Results');
        
        $this->table(
            ['Component', 'Status', 'Time'],
            [
                ['Core System', '✓ Ready', '0.45ms'],
                ['Database', '✓ Connected', '1.23ms'],
                ['Cache', '✓ Initialized', '0.78ms'],
                ['Routes', '✓ Loaded', '2.15ms'],
                ['Services', '✓ Registered', '1.89ms'],
            ]
        );
        
        $this->box(
            "All components initialized successfully!\n\n" .
            "Framework is ready for development.\n" .
            "Memory usage: 2.4 MB\n" .
            "Configuration: Production mode",
            "🎉 System Status",
            "success"
        );
        
        $this->displayTimings();
        
        $this->line();
        $this->section('Demo Summary');
        
        $totalTime = $this->getExecutionTime();
        $this->info("✨ Demo completed in {$this->formatTime($totalTime)}");
        
        $this->line();
        $this->gradient("▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓");
        
        $this->line();
        $this->note("Thank you for exploring the console features, {$userName}!");
    }
}