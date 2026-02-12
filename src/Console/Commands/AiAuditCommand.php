<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\AI\AIManager;
use Plugs\Container\Container;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AiAuditCommand extends Command
{
    protected string $signature = 'ai:audit {dir? : The directory to audit}';
    protected string $description = 'Audit the project for security and performance issues using AI';

    public function handle(): int
    {
        $this->title('AI Security & Performance Auditor');

        $dir = $this->argument('dir') ?: base_path('src');

        if (!is_dir($dir)) {
            $this->error("Directory [{$dir}] not found.");
            return 1;
        }

        $this->info("Scanning files in [{$dir}]...");

        $files = $this->getFiles($dir);
        $fileCount = count($files);

        if ($fileCount === 0) {
            $this->warning("No PHP files found in {$dir}.");
            return 0;
        }

        $this->info("Analyzing {$fileCount} files...");

        // For a broad audit, we send the file list and structure first
        $fileList = implode("\n", array_map(fn($f) => str_replace(base_path(), '', $f), $files));

        $prompt = <<<PROMPT
You are a security and performance auditor for the Plugs PHP Framework.
Below is a list of files in a project directory. 

Based on the file names and structure, identify common risk areas or performance bottlenecks.
Then, I will provide content for suspicious files for deeper analysis.

File List:
{$fileList}

Return a summary of recommended audit focus areas.
PROMPT;

        try {
            $ai = Container::getInstance()->make(AIManager::class);
            $response = $ai->prompt($prompt);

            $this->newLine();
            $this->panel($response, 'Initial Audit Recommendations');
            $this->newLine();

            $target = $this->ask('Enter a specific file path from the list for deep analysis (or skip)', 'none');

            if ($target !== 'none') {
                $fullPath = base_path(ltrim($target, DIRECTORY_SEPARATOR));
                if (file_exists($fullPath)) {
                    $this->deepAudit($fullPath);
                } else {
                    $this->error("File not found: {$fullPath}");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }
    }

    protected function deepAudit(string $path): void
    {
        $code = file_get_contents($path);

        $prompt = <<<PROMPT
Perform a deep security and performance audit on this PHP code.
Identify:
1. SQL Injection risks.
2. XSS risks.
3. Insecure configurations.
4. N+1 query problems.
5. Inefficient algorithm complexity.

Code:
{$code}

Format the response as a bulleted list of findings.
PROMPT;

        $this->info("Performing deep audit on " . basename($path) . "...");

        $ai = Container::getInstance()->make(AIManager::class);
        $response = $ai->prompt($prompt);

        $this->newLine();
        $this->panel($response, 'Deep Audit Findings');
    }

    protected function getFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
