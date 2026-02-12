<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\AI\AIManager;
use Plugs\Container\Container;

class AiFixCommand extends Command
{
    protected string $signature = 'ai:fix {path : The path to the file to fix} {instruction? : What the AI should fix or improve}';
    protected string $description = 'Improve or fix code in a specific file using AI';

    public function handle(): int
    {
        $this->title('AI Code Fixer');

        $path = $this->argument('path');
        $instruction = $this->argument('instruction');

        if (!file_exists($path)) {
            $this->error("File [{$path}] not found.");
            return 1;
        }

        if (!$instruction) {
            $instruction = $this->ask('What should I fix or improve in this file?', 'refactor for better readability and PHP 8.2 features');
        }

        $code = file_get_contents($path);

        $this->info("Consulting AI to improve {$path}...");

        $prompt = <<<PROMPT
You are a senior PHP developer for the Plugs Framework.
Improve the following code based on this instruction: "{$instruction}".

Guidelines:
1. Maintain the existing logic unless the instruction says otherwise.
2. Use modern PHP features (readonly, match, constructor property promotion, etc.)
3. Ensure PSR-12 compliance.
4. The response MUST be ONLY the full improved PHP code for the file.

Original Code:
{$code}
PROMPT;

        try {
            $ai = Container::getInstance()->make(AIManager::class);
            $improvedCode = $ai->prompt($prompt);

            // Clean Markdown wrappers
            $improvedCode = str_replace(['```php', '```'], '', $improvedCode);
            $improvedCode = trim($improvedCode);

            $this->newLine();
            $this->info("AI suggested improvements. Preview of changes (first 10 lines):");
            $this->newLine();
            $lines = explode("\n", $improvedCode);
            foreach (array_slice($lines, 0, 10) as $line) {
                $this->line("  {$line}");
            }

            if ($this->confirm("Apply these changes to [{$path}]?", false)) {
                $this->task('Updating file', function () use ($path, $improvedCode) {
                    Filesystem::put($path, $improvedCode);
                });
                $this->success("File updated successfully!");
            } else {
                $this->warning("Changes discarded.");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }
    }
}
