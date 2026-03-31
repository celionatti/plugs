<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\Console\Commands\Stubs\IconStubs;

/**
 * Class MakeIconCommand
 * 
 * Create a new SVG icon or initialize the default icon set.
 */
class MakeIconCommand extends Command
{
    protected string $description = 'Create a new SVG icon for the @icon directive';

    protected function defineArguments(): array
    {
        return [
            'name' => 'The name of the icon to create (optional if using --init)',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            '--init' => 'Initialize the default set of icons',
            '--svg=SVG' => 'Optional SVG content to use for the new icon',
            '--force, -f' => 'Overwrite existing icon files',
        ];
    }

    public function handle(): int
    {
        $this->branding();
        $this->advancedHeader('Icon Generator', 'Managing native SVG icons');

        if ($this->hasOption('init')) {
            return $this->initializeDefaults();
        }

        $name = $this->argument('name');

        if (!$name) {
            $this->info('Usage: php plugs make:icon {name} [--svg="..."]');
            $this->info('Or:    php plugs make:icon --init');
            $this->newLine();
            
            if ($this->confirm('Would you like to initialize the default icon set?', true)) {
                return $this->initializeDefaults();
            }

            return self::FAILURE;
        }

        return $this->createIcon($name);
    }

    /**
     * Initialize the default set of icons.
     */
    private function initializeDefaults(): int
    {
        $this->section('Initializing Default Icons');
        
        $icons = IconStubs::getAll();
        $total = count($icons);
        $count = 0;

        $targetDir = getcwd() . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'icons';
        
        if (!is_dir($targetDir)) {
            $this->ensureDirectory($targetDir);
            $this->success("Created directory: {$targetDir}");
        }

        $iconKeys = array_keys($icons);
        $this->withProgressBar($total, function($step) use ($icons, $iconKeys, $targetDir, &$count) {
            $name = $iconKeys[$step - 1];
            $svg = $icons[$name];
            $path = $targetDir . DIRECTORY_SEPARATOR . $name . '.svg';

            if (!file_exists($path) || $this->isForce()) {
                file_put_contents($path, $svg);
                $count++;
            }
        }, 'Populating icons');

        $this->newLine(2);
        $this->success("Successfully initialized {$count} icons in resources/icons/");
        $this->info('You can now use them via @icon(\'name\') in your views.');

        return self::SUCCESS;
    }

    /**
     * Create a single icon file.
     */
    private function createIcon(string $name): int
    {
        $name = Str::kebab($name);
        $targetDir = getcwd() . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'icons';
        $path = $targetDir . DIRECTORY_SEPARATOR . $name . '.svg';

        $this->ensureDirectory($targetDir);

        if (file_exists($path) && !$this->isForce()) {
            $this->error("Icon '{$name}' already exists at resources/icons/{$name}.svg");
            $this->info('Use --force to overwrite.');
            return self::FAILURE;
        }

        $content = $this->option('svg') ?? IconStubs::getSkeleton();

        file_put_contents($path, $content);

        $this->success("Icon created successfully!");
        $this->keyValue('Name', $name);
        $this->keyValue('Path', "resources/icons/{$name}.svg");
        $this->newLine();
        $this->info("Usage: @icon('{$name}')");

        return self::SUCCESS;
    }
}
