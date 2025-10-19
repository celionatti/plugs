<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Enhanced Demo Command
|--------------------------------------------------------------------------
| Comprehensive showcase of ALL console features with interactive menu
*/

use Plugs\Console\Command;

class DemoCommand extends Command
{
    protected string $description = 'Interactive showcase of all console features and capabilities';
    protected $checkpoints;

    public function handle(): int
    {
        $this->clear();
        $this->showWelcomeScreen();
        
        do {
            $choice = $this->showMainMenu();
            
            match ($choice) {
                '🎨 Output Styles' => $this->demoOutputStyles(),
                '💬 Interactive Input' => $this->demoInteractiveInput(),
                '⏳ Progress & Loading' => $this->demoProgress(),
                '📊 Tables & Data Display' => $this->demoTablesAndBoxes(),
                '🎭 Advanced Features' => $this->demoAdvancedFeatures(),
                '📝 Text Formatting' => $this->demoTextFormatting(),
                '🎯 Complete Showcase' => $this->runCompleteDemo(),
                '❌ Exit' => $this->goodbye(),
                default => null
            };
            
            if ($choice === '❌ Exit') {
                break;
            }
            
            $this->newLine(2);
            
        } while ($this->confirm('Return to main menu?', true));
        
        $this->goodbye();
        return 0;
    }

    private function showWelcomeScreen(): void
    {
        $this->banner('THE PLUGS CONSOLE DEMO');
        
        $this->panel(
            "Welcome to ThePlugs Console Framework!\n\n" .
            "This interactive demo will showcase:\n" .
            "  • Beautiful styled output\n" .
            "  • Interactive user prompts\n" .
            "  • Progress indicators & animations\n" .
            "  • Tables, boxes, and data display\n" .
            "  • Advanced formatting features\n" .
            "  • And much more!\n\n" .
            "Explore each section or run the complete showcase.",
            "🎉 Welcome"
        );
        
        $this->newLine();
    }

    private function showMainMenu(): string
    {
        $this->title('DEMO MENU');
        
        return $this->choice(
            'What would you like to explore?',
            [
                '🎨 Output Styles',
                '💬 Interactive Input',
                '⏳ Progress & Loading',
                '📊 Tables & Data Display',
                '🎭 Advanced Features',
                '📝 Text Formatting',
                '🎯 Complete Showcase',
                '❌ Exit'
            ],
            '🎯 Complete Showcase'
        );
    }

    // ========================================================================
    // OUTPUT STYLES DEMO
    // ========================================================================
    
    private function demoOutputStyles(): void
    {
        $this->clear();
        $this->title('Output Styles');
        
        $this->section('Basic Message Types');
        $this->info('This is an informational message ℹ️');
        $this->success('This is a success message ✓');
        $this->warning('This is a warning message ⚠️');
        $this->error('This is an error message ✗');
        $this->note('This is a helpful note 📝');
        $this->critical('This is a critical message! 🚨');
        $this->debug('This is debug output 🐛');
        
        $this->newLine(2);
        $this->section('Special Effects');
        
        $this->gradient('✨ This text has a beautiful gradient effect! ✨');
        $this->newLine();
        
        $this->header('Beautiful Centered Header');
        
        $this->banner('BANNER TEXT');
        
        $this->newLine();
        $this->section('Quotes');
        $this->quote(
            'The best way to predict the future is to invent it.',
            'Alan Kay'
        );
        
        $this->newLine();
        $this->section('Dividers & Lines');
        $this->divider('=');
        $this->divider('-');
        $this->divider('·');
        
        $this->newLine();
        $this->alert('This is an alert message!', 'info');
        $this->alert('Success alert!', 'success');
        $this->alert('Warning alert!', 'warning');
        
        $this->waitForContinue();
    }

    // ========================================================================
    // INTERACTIVE INPUT DEMO
    // ========================================================================
    
    private function demoInteractiveInput(): void
    {
        $this->clear();
        $this->title('Interactive Input');
        
        // Simple Question
        $this->section('1. Simple Question (ask)');
        $name = $this->ask('What is your name?', 'Developer');
        $this->success("Hello, {$name}! 👋");
        
        $this->newLine(2);
        
        // Confirmation
        $this->section('2. Confirmation (confirm)');
        $likes = $this->confirm('Do you like PHP?', true);
        $this->info($likes ? 'Great! PHP is awesome! 🐘' : 'That\'s okay, everyone has preferences!');
        
        $this->newLine(2);
        
        // Single Choice
        $this->section('3. Single Choice (choice)');
        $language = $this->choice(
            'What is your favorite programming language?',
            ['PHP', 'JavaScript', 'Python', 'Go', 'Rust', 'TypeScript', 'Java', 'C#'],
            'PHP'
        );
        $this->success("Excellent choice! {$language} is powerful! 🚀");
        
        $this->newLine(2);
        
        // Multiple Choice
        $this->section('4. Multiple Choice (multiChoice)');
        $frameworks = $this->multiChoice(
            'Which PHP frameworks have you used?',
            ['Laravel', 'Symfony', 'CodeIgniter', 'Slim', 'Yii', 'CakePHP', 'Phalcon'],
            ['Laravel']
        );
        
        if (!empty($frameworks)) {
            $this->box(
                "You have experience with:\n\n" . 
                implode("\n", array_map(fn($f) => "  ✓ {$f}", $frameworks)),
                "📚 Your Experience",
                "success"
            );
        }
        
        $this->newLine(2);
        
        // Autocomplete
        $this->section('5. Autocomplete (anticipate)');
        $city = $this->anticipate(
            'Which city are you from?',
            ['Lagos', 'Abuja', 'Port Harcourt', 'Kano', 'Ibadan', 'Kaduna', 'Benin City'],
            'Lagos'
        );
        $this->info("Nice to meet someone from {$city}! 🌆");
        
        $this->newLine(2);
        
        // Secret Input
        $this->section('6. Secret Input (secret)');
        if ($this->confirm('Try secret input demo?', false)) {
            $secret = $this->secret('Enter a secret password');
            $this->success('Secret received! (hidden from display) 🔒');
        } else {
            $this->note('Skipped secret input demo');
        }
        
        $this->waitForContinue();
    }

    // ========================================================================
    // PROGRESS & LOADING DEMO
    // ========================================================================
    
    private function demoProgress(): void
    {
        $this->clear();
        $this->title('Progress & Loading Indicators');
        
        // Task with Spinner
        $this->section('1. Task with Spinner (task)');
        $result = $this->task('Processing important data', function() {
            sleep(2);
            return 'Data processed successfully!';
        });
        $this->success($result);
        
        $this->newLine(2);
        
        // Progress Bar
        $this->section('2. Progress Bar (withProgressBar)');
        $this->withProgressBar(30, function($step) {
            usleep(50000);
        }, 'Downloading files');
        
        $this->newLine(2);
        
        // Step Progress
        $this->section('3. Multi-Step Process (step)');
        $steps = [
            'Initializing application',
            'Loading configuration',
            'Connecting to database',
            'Registering services',
            'Booting framework',
            'Ready!'
        ];
        
        foreach ($steps as $index => $stepName) {
            $this->step($index + 1, count($steps), $stepName);
            usleep(400000);
        }
        
        $this->newLine(2);
        
        // Loading Animation
        $this->section('4. Loading Animation (loading)');
        $this->loading('Fetching remote data', function() {
            sleep(2);
            return true;
        });
        
        $this->newLine(2);
        
        // Countdown
        $this->section('5. Countdown (countdown)');
        if ($this->confirm('Show countdown demo?', true)) {
            $this->countdown(3, 'Next section in');
        }
        
        $this->waitForContinue();
    }

    // ========================================================================
    // TABLES & DATA DISPLAY DEMO
    // ========================================================================
    
    private function demoTablesAndBoxes(): void
    {
        $this->clear();
        $this->title('Tables & Data Display');
        
        // Table
        $this->section('1. Data Table (table)');
        $headers = ['ID', 'Name', 'Email', 'Role', 'Status'];
        $rows = [
            ['1', 'John Doe', 'john@example.com', 'Admin', '✓ Active'],
            ['2', 'Jane Smith', 'jane@example.com', 'Editor', '✓ Active'],
            ['3', 'Bob Wilson', 'bob@example.com', 'Moderator', '✗ Inactive'],
            ['4', 'Alice Brown', 'alice@example.com', 'User', '✓ Active'],
            ['5', 'Charlie Davis', 'charlie@example.com', 'User', '✓ Active'],
        ];
        $this->table($headers, $rows);
        
        // Boxes
        $this->section('2. Information Boxes (box)');
        
        $this->box(
            "This is an informational box.\nIt can contain multiple lines.\nPerfect for displaying important messages!",
            "📘 Information",
            "info"
        );
        
        $this->box(
            "Operation completed successfully!\nAll files have been processed.\nNo errors detected.",
            "✅ Success",
            "success"
        );
        
        $this->box(
            "Warning: This action cannot be undone.\nPlease review before proceeding.\nMake sure you have backups!",
            "⚠️ Warning",
            "warning"
        );
        
        $this->box(
            "Critical error detected!\nSystem shutdown initiated.\nPlease contact support immediately!",
            "❌ Error",
            "error"
        );
        
        // Panel
        $this->section('3. Panel (panel)');
        $this->panel(
            "This is a panel with a border.\nGreat for displaying structured information.\nCan be used for help text, summaries, etc.",
            "Panel Title"
        );
        
        // Key-Value Pairs
        $this->section('4. Key-Value Display (keyValue)');
        $this->keyValue('Application Name', 'ThePlugs Framework');
        $this->keyValue('Version', '1.0.0');
        $this->keyValue('PHP Version', PHP_VERSION);
        $this->keyValue('Environment', 'Development');
        $this->keyValue('Debug Mode', 'Enabled');
        
        $this->waitForContinue();
    }

    // ========================================================================
    // ADVANCED FEATURES DEMO
    // ========================================================================
    
    private function demoAdvancedFeatures(): void
    {
        $this->clear();
        $this->title('Advanced Features');
        
        // Bullet List
        $this->section('1. Bullet List (bulletList)');
        $this->bulletList([
            'First feature: Beautiful console output',
            'Second feature: Interactive prompts',
            'Third feature: Progress indicators',
            'Fourth feature: Data visualization',
            'Fifth feature: Error handling'
        ]);
        
        $this->newLine(2);
        
        // Numbered List
        $this->section('2. Numbered List (numberedList)');
        $this->numberedList([
            'Install the framework',
            'Configure your environment',
            'Create your first command',
            'Run and test',
            'Deploy to production'
        ]);
        
        $this->newLine(2);
        
        // Tree Structure
        $this->section('3. Tree Structure (tree)');
        $this->tree([
            'app' => [
                'Console' => [
                    'Commands',
                    'Kernel.php'
                ],
                'Controllers' => [
                    'UserController.php',
                    'PostController.php'
                ],
                'Models' => [
                    'User.php',
                    'Post.php'
                ]
            ],
            'config' => [
                'app.php',
                'database.php'
            ],
            'public' => [
                'index.php',
                'assets'
            ]
        ]);
        
        $this->newLine(2);
        
        // Diff Display
        $this->section('4. Diff Display (diff)');
        $this->diff(
            'protected string $oldVariable = "old value";',
            'protected string $newVariable = "new value";'
        );
        
        $this->newLine(2);
        
        // Checkpoints & Timing
        $this->section('5. Performance Tracking (checkpoint)');
        $this->checkpoint('start');
        $this->info('Checkpoint 1: Started');
        sleep(1);
        
        $this->checkpoint('middle');
        $this->info('Checkpoint 2: Middle process');
        sleep(1);
        
        $this->checkpoint('end');
        $this->info('Checkpoint 3: Completed');
        
        $this->newLine();
        $this->displayTimings();
        
        $this->waitForContinue();
    }

    // ========================================================================
    // TEXT FORMATTING DEMO
    // ========================================================================
    
    private function demoTextFormatting(): void
    {
        $this->clear();
        $this->title('Text Formatting Features');
        
        $this->section('1. Headers & Titles');
        $this->header('This is a Header');
        $this->title('This is a Title');
        $this->banner('BANNER');
        
        $this->section('2. Sections & Dividers');
        $this->section('Section Title');
        $this->divider('=');
        $this->divider('-');
        $this->divider('·');
        $this->divider('~');
        
        $this->newLine();
        
        $this->section('3. Gradient Text');
        $this->gradient('This is gradient text - Beautiful colors!');
        $this->gradient('Another gradient with different text length');
        $this->gradient('✨ Gradient with emojis works too! 🌈');
        
        $this->newLine(2);
        
        $this->section('4. Quotes');
        $this->quote('Code is like humor. When you have to explain it, it\'s bad.', 'Cory House');
        $this->quote('First, solve the problem. Then, write the code.', 'John Johnson');
        $this->quote('Simplicity is the soul of efficiency.', 'Austin Freeman');
        
        $this->section('5. Alerts');
        $this->alert('Information alert message', 'info');
        $this->alert('Success alert message', 'success');
        $this->alert('Warning alert message', 'warning');
        $this->alert('Error alert message', 'error');
        
        $this->waitForContinue();
    }

    // ========================================================================
    // COMPLETE SHOWCASE
    // ========================================================================
    
    private function runCompleteDemo(): void
    {
        $this->clear();
        $this->banner('COMPLETE SHOWCASE');
        
        $this->checkpoint('demo_start');
        
        // Introduction
        $this->panel(
            "Welcome to the complete showcase!\n\n" .
            "This will demonstrate all features in sequence.\n" .
            "Sit back and enjoy the show! 🍿",
            "🎬 Starting Demo"
        );
        
        $this->newLine();
        
        // Get user info
        $userName = $this->ask('What should we call you?', 'Developer');
        $this->success("Great to have you here, {$userName}! 👋");
        
        $this->checkpoint('user_input');
        
        $this->newLine(2);
        
        // Initialize
        $this->section('Phase 1: Initialization');
        $this->task('Initializing framework components', function() {
            sleep(2);
            return true;
        });
        
        $this->checkpoint('initialization');
        
        $this->newLine();
        
        // Process
        $this->section('Phase 2: Processing');
        $this->withProgressBar(25, function($step) {
            usleep(100000);
        }, 'Processing resources');
        
        $this->checkpoint('processing');
        
        $this->newLine(2);
        
        // Multi-step
        $this->section('Phase 3: Multi-Step Operation');
        $steps = ['Connecting', 'Authenticating', 'Loading', 'Finalizing'];
        foreach ($steps as $index => $step) {
            $this->step($index + 1, count($steps), $step);
            usleep(500000);
        }
        
        $this->checkpoint('multi_step');
        
        $this->newLine(2);
        
        // Results
        $this->section('Phase 4: Results');
        $this->table(
            ['Component', 'Status', 'Time', 'Memory'],
            [
                ['Core System', '✓ Ready', '0.45ms', '1.2 MB'],
                ['Database', '✓ Connected', '1.23ms', '2.4 MB'],
                ['Cache', '✓ Initialized', '0.78ms', '0.5 MB'],
                ['Routes', '✓ Loaded', '2.15ms', '1.8 MB'],
                ['Services', '✓ Registered', '1.89ms', '3.1 MB'],
                ['Views', '✓ Compiled', '1.45ms', '2.2 MB'],
            ]
        );
        
        $this->checkpoint('results');
        
        $this->newLine();
        
        // Summary
        $this->box(
            "All components initialized successfully!\n\n" .
            "Framework: ThePlugs v1.0.0\n" .
            "PHP Version: " . PHP_VERSION . "\n" .
            "Memory Usage: 11.2 MB\n" .
            "Execution Time: {$this->formatTime($this->elapsed())}\n" .
            "Configuration: Development Mode\n\n" .
            "Everything is ready, {$userName}! 🚀",
            "🎉 System Status",
            "success"
        );
        
        $this->newLine();
        
        // Performance
        $this->section('Performance Metrics');
        $this->displayTimings();
        
        $this->newLine();
        
        // Closing
        $this->section('Demo Summary');
        
        $totalTime = $this->getExecutionTime();
        $this->keyValue('Total Execution Time', $this->formatTime($totalTime));
        $this->keyValue('Checkpoints Created', (string)count($this->checkpoints ?? []));
        $this->keyValue('Features Demonstrated', 'All ✓');
        $this->keyValue('User', $userName);
        
        $this->newLine(2);
        
        $this->gradient("═══════════════════════════════════════════════════════════");
        $this->newLine();
        
        $this->panel(
            "Thank you for exploring ThePlugs Console, {$userName}!\n\n" .
            "You've seen:\n" .
            "  ✓ Output styles and formatting\n" .
            "  ✓ Interactive user input\n" .
            "  ✓ Progress indicators\n" .
            "  ✓ Data visualization\n" .
            "  ✓ Performance tracking\n" .
            "  ✓ And much more!\n\n" .
            "Start building amazing console applications! 🚀",
            "🎊 Thank You!"
        );
        
        $this->newLine();
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    private function waitForContinue(): void
    {
        $this->newLine(2);
        $this->confirm('Press Enter to continue...', true);
    }

    private function goodbye(): void
    {
        $this->clear();
        $this->newLine(2);
        
        $this->gradient("═══════════════════════════════════════════════════════════");
        $this->newLine();
        
        $this->panel(
            "Thank you for exploring ThePlugs Console!\n\n" .
            "We hope you enjoyed the demo.\n" .
            "Happy coding! 💻✨",
            "👋 Goodbye!"
        );
        
        $this->newLine(2);
        $this->quote('The best way to learn is by doing.', 'ThePlugs Team');
        $this->newLine();
    }
}