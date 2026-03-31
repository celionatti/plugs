<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\AssetManager;

/**
 * Class AssetsCompressCommand
 * 
 * Pre-compress production assets with Gzip and Brotli for better performance.
 */
class AssetsCompressCommand extends Command
{
    protected string $signature = 'assets:compress {--dir=public/build : The directory to compress}';
    protected string $description = 'Pre-compress production assets (Gzip & Brotli)';

    public function handle(): int
    {
        $dir = $this->option('dir');
        $basePath = defined('BASE_PATH') ? BASE_PATH : getcwd();
        $targetDir = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . ltrim($dir, '/\\');

        if (!is_dir($targetDir)) {
            $this->error("Directory not found: $dir");
            return 1;
        }

        $this->info("⚡ Compressing assets in $dir...");

        $manager = new AssetManager();
        $stats = $manager->precompress($targetDir);

        if ($stats['files'] === 0) {
            $this->warn("No compressible assets found.");
            return 0;
        }

        $savedMb = round($stats['saved'] / 1024 / 1024, 2);
        
        $this->success("Successfully compressed {$stats['files']} asset variants.");
        $this->info("Total space saved: {$savedMb} MB");
        
        $this->info("\nFiles compressed with:");
        $this->line("- Gzip (.gz)");
        if (function_exists('brotli_compress')) {
            $this->line("- Brotli (.br)");
        } else {
            $this->warn("- Brotli (Extension not installed)");
        }

        return 0;
    }
}
