<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\Minifier;

class MakeSpaAssetCommand extends Command
{
    protected string $description = 'Create the plugs-spa.js file in public plugs directory with optional minification';

    public function handle(): int
    {
        $this->title('SPA Asset Generator');

        $directory = getcwd() . '/public/plugs';
        $filename = 'plugs-spa.js';
        $path = $directory . '/' . $filename;
        $shouldMinify = $this->hasOption('min');
        $force = $this->isForce();

        // Ensure directory exists
        if (!$this->ensureDirectory($directory)) {
            $this->error("Failed to create directory: {$directory}");

            return 1;
        }

        // Check if file exists
        if ($this->fileExists($path) && !$force) {
            if (!$this->confirm("File {$filename} already exists. Overwrite?", false)) {
                $this->warning("Operation cancelled.");

                return 0;
            }
        }

        $this->section('Generating Asset');

        $content = $this->getJsContent();

        // Create standard file
        $this->task("Creating {$filename}", function () use ($path, $content) {
            file_put_contents($path, $content);
        });

        $this->success("Created: public/plugs/{$filename}");

        // Create minified file if requested
        if ($shouldMinify) {
            $minFilename = 'plugs-spa.min.js';
            $minPath = $directory . '/' . $minFilename;

            $this->task("Creating {$minFilename}", function () use ($minPath, $content) {
                $minified = $this->minify($content);
                file_put_contents($minPath, $minified);
            });

            $this->success("Created: public/plugs/{$minFilename}");
        }

        $this->newLine();
        $this->info("SPA Bridge installed successfully.");
        $this->info("Add the following script to your layout head:");
        $this->line('<script src="/plugs/' . ($shouldMinify ? 'plugs-spa.min.js' : 'plugs-spa.js') . '"></script>');
        $this->newLine();

        return 0;
    }

    protected function defineOptions(): array
    {
        return [
            '--min' => 'Create a minified version (plugs-spa.min.js)',
            '--force' => 'Overwrite existing files',
        ];
    }

    /**
     * Minify JavaScript content
     *
     * @param string $js
     * @return string
     */
    private function minify(string $js): string
    {
        return Minifier::js($js);
    }

    private function getJsContent(): string
    {
        $templatePath = dirname(__DIR__, 3) . '/public/install/templates/public/plugs/plugs-spa.js.template';

        if (file_exists($templatePath)) {
            return file_get_contents($templatePath);
        }

        // Fallback or warning if template is missing (should not happen in dev)
        $this->warning("SPA template not found at: {$templatePath}");
        return '';
    }
}
