<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Config\DefaultConfig;

class ConfigPublishCommand extends Command
{
    protected string $description = 'Publish framework configuration files to the project config directory';

    protected function defineArguments(): array
    {
        return [
            ['file', 'Optional name of the configuration file to publish'],
        ];
    }

    protected function defineOptions(): array
    {
        return [
            ['all', 'a', 'Publish all available configuration files'],
            ['force', 'f', 'Overwrite existing configuration files'],
        ];
    }

    public function handle(): int
    {
        $this->title('Configuration Publisher');

        $file = $this->argument('0');
        $publishAll = $this->hasOption('all');
        $force = $this->hasOption('force');

        $availableConfigs = [
            'app',
            'auth',
            'assets',
            'cache',
            'database',
            'filesystems',
            'hash',
            'logging',
            'mail',
            'middleware',
            'queue',
            'security',
            'services',
            'ai',
            'seo',
            'opcache',
            'view',
            'billing'
        ];

        if (!$file && !$publishAll) {
            $file = $this->choice('Which configuration file would you like to publish?', array_merge(['all'], $availableConfigs), 'all');
            if ($file === 'all') {
                $publishAll = true;
            }
        }

        $toPublish = $publishAll ? $availableConfigs : [$file];

        foreach ($toPublish as $config) {
            if (!in_array($config, $availableConfigs)) {
                $this->error("Configuration file [{$config}] not found.");
                continue;
            }

            $this->publish($config, $force);
        }

        $this->newLine();
        $this->success('Configuration publishing completed.');

        return 0;
    }

    private function publish(string $name, bool $force): void
    {
        $targetPath = base_path("config/{$name}.php");

        if (file_exists($targetPath) && !$force) {
            if (!$this->confirm("Configuration file [{$name}.php] already exists. Overwrite?", false)) {
                $this->warning("Skipped [{$name}.php]");
                return;
            }
        }

        $this->task("Publishing [{$name}.php]", function () use ($name, $targetPath) {
            $config = DefaultConfig::get($name);
            $content = $this->formatConfig($config);

            if (!is_dir(dirname($targetPath))) {
                mkdir(dirname($targetPath), 0755, true);
            }

            file_put_contents($targetPath, $content);
            return true;
        });
    }

    private function formatConfig(array $config): string
    {
        $exported = var_export($config, true);

        // Convert array() to []
        $exported = str_replace(['array (', ')'], ['[', ']'], $exported);

        // Fix indentation and spacing
        $exported = preg_replace('/=>\s+\[/', '=> [', $exported);

        return "<?php\n\nreturn {$exported};\n";
    }
}
