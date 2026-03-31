<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Output;

class DemoCommand extends Command
{
    protected string $description = 'Showcase the premium CLI UI components';

    public function handle(): int
    {
        $this->checkpoint('start');
        
        $this->output->advancedHeader('Plugs CLI UI', 'Premium component library showcase');

        // Basic Info Methods
        $this->output->section('Standard Messages');
        $this->output->info('This is an info message using the new glyph');
        $this->output->success('This represents a successful operation');
        $this->output->warning('A warning that needs your attention');
        $this->output->error('An error occurred during execution');
        $this->output->note('A muted note for secondary information');
        $this->output->critical('A critical error that stops execution');

        // Progress methods
        $this->output->section('Progress Indicators');
        $this->output->info('Standard Progress Bar:');
        $this->output->progressBar(10, function($i) {
            usleep(100000);
        }, 'Processing assets');
        
        $this->output->newLine();
        $this->output->info('Gradient Scanner (Task):');
        $this->output->taskWithBox('Compiling assets', function() {
            usleep(1500000); // 1.5 seconds
            return true;
        });

        // Timeline
        $this->output->section('Timeline Progress');
        $this->timeline(1, 4, 'Analyzing dependencies...');
        usleep(300000);
        $this->timeline(2, 4, 'Downloading packages...');
        usleep(300000);
        $this->timeline(3, 4, 'Linking binaries...');
        usleep(300000);
        $this->timeline(4, 4, 'Generating autoloader...');
        usleep(300000);
        $this->timeline(5, 4, 'Installation complete');

        // Layouts
        $this->output->section('Layouts & Tables');
        $this->output->table(
            ['Method', 'URI', 'Name', 'Action'],
            [
                ['GET', '/', 'home', 'HomeController@index'],
                ['GET', '/users', 'users.index', 'UserController@index'],
                ['POST', '/users', 'users.store', 'UserController@store'],
                ['GET', '/api/very/long/path/for/this/example', 'api.long', 'ApiController@veryLongMethodName'],
            ]
        );

        $this->output->section('Multi-Column Grid');
        $this->columns([
            'App\Models\User', 'App\Models\Post', 'App\Models\Comment',
            'App\Models\Tag', 'App\Models\Category', 'App\Models\Role',
            'App\Models\Permission', 'App\Models\Session', 'App\Models\Team'
        ], 3);

        // Boxes and Status
        $this->output->section('Display Cards');
        $this->commandSummary('System Health', [
            'Environment' => 'Production',
            'PHP Version' => PHP_VERSION,
            'Framework' => \Plugs\Plugs::version(),
            'Memory' => memory_get_usage(true) / 1024 / 1024 . ' MB',
            'Status' => $this->badge('Healthy', 'success')
        ]);

        $this->output->box(
            "The new bounding boxes support custom accents, bold titles, and multi-line content wrapping cleanly.\nThey draw focus to important segments of text.", 
            "Information Box", 
            "info"
        );

        // Badges and Result Summary
        $this->output->section('Badges & Inline Results');
        $this->output->line("HTTP Status: " . $this->badge('200 OK', 'success') . " " . $this->badge('404 Not Found', 'warning') . " " . $this->badge('500 Error', 'error'));
        $this->output->newLine();

        $this->output->info('File Operations:');
        $this->fileCreated('app/Http/Controllers/DemoController.php');
        $this->fileModified('routes/web.php');
        $this->fileDeleted('app/Http/Controllers/OldController.php');
        $this->fileSkipped('config/app.php (Already exists)');

        $this->output->newLine();
        $this->resultSummary([
            'Files' => 4,
            'Classes' => 2,
            'Routes' => 6
        ], 0.45, 1024 * 1024 * 4);

        $this->checkpoint('finished');
        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
