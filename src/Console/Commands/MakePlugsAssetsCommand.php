<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\Minifier;

class MakePlugsAssetsCommand extends Command
{
    protected string $description = 'Publish core framework assets to the public/plugs directory';

    public function handle(): int
    {
        $this->title('Plugs Framework Asset Publisher');

        $targetDirectory = public_path('plugs');
        $force = $this->isForce();
        $shouldMinify = $this->hasOption('min');

        // Ensure target directory exists
        if (!$this->ensureDirectory($targetDirectory)) {
            $this->error("Failed to create directory: {$targetDirectory}");
            return 1;
        }

        $assets = [
            'js' => [
                'plugs-editor.js',
                'plugs-lazy.js',
            ],
            'css' => [
                'plugs-editor.css',
            ],
        ];

        $this->section('Publishing Assets to ' . $targetDirectory);

        foreach ($assets as $type => $files) {
            foreach ($files as $filename) {
                $source = dirname(__DIR__, 2) . "/Resources/assets/{$type}/{$filename}";
                $destination = $targetDirectory . '/' . $filename;

                if (!file_exists($source)) {
                    $this->warning("Source file not found: {$source}");
                    continue;
                }

                $content = file_get_contents($source);
                $this->publishAsset($destination, $filename, $content, $force, $shouldMinify, $type);
            }
        }

        $this->newLine();
        $this->info("Framework assets published successfully to /public/plugs/");
        $this->newLine();

        return 0;
    }

    protected function publishAsset(string $path, string $filename, string $content, bool $force, bool $shouldMinify, string $type): void
    {
        if (file_exists($path) && !$force) {
            if (!$this->confirm("File {$filename} already exists in target. Overwrite?", false)) {
                $this->warning("Skipped: {$filename}");
                return;
            }
        }

        if (file_put_contents($path, $content) !== false) {
            $this->success("Published: {$filename}");
        } else {
            $this->error("Failed to publish: {$filename}");
        }

        if ($shouldMinify) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $minFilename = str_replace(".{$ext}", ".min.{$ext}", $filename);
            $minPath = dirname($path) . '/' . $minFilename;

            $minified = $type === 'js' ? Minifier::js($content) : Minifier::css($content);

            if (file_put_contents($minPath, $minified) !== false) {
                $this->success("Minified: {$minFilename}");
            } else {
                $this->error("Failed to minify: {$minFilename}");
            }
        }
    }

    protected function defineOptions(): array
    {
        return [
            '--min' => 'Create minified versions (.min.js, .min.css)',
            '--force' => 'Overwrite existing files without asking',
        ];
    }
}
