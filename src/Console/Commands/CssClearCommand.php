<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;

class CssClearCommand extends Command
{
    protected string $description = 'Remove the compiled Plugs CSS file';

    public function handle(): int
    {
        $this->title('Plugs CSS Clear');

        $basePath = defined('BASE_PATH') ? BASE_PATH : (defined('ROOT_PATH') ? ROOT_PATH : getcwd());

        $config = [];
        if (function_exists('config')) {
            $config = config('css', []);
        } else {
            $configPath = $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'css.php';
            if (file_exists($configPath)) {
                $config = require $configPath;
            }
        }

        $outputPath = $config['output'] ?? 'public/build/plugs.css';
        $fullPath = $basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputPath);

        if (!file_exists($fullPath)) {
            $this->info('No compiled CSS file found. Nothing to clear.');
            return self::SUCCESS;
        }

        $size = filesize($fullPath);
        unlink($fullPath);

        $sizeStr = $size >= 1024
            ? number_format($size / 1024, 1) . ' KB'
            : $size . ' B';

        $this->success("Removed: {$outputPath} ({$sizeStr})");
        return self::SUCCESS;
    }
}
