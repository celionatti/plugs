<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;
use Plugs\Console\Support\Str;
use Plugs\AI\AIManager;
use Plugs\Container\Container;
use ReflectionClass;

class MakeAiTestCommand extends Command
{
    protected string $signature = 'make:ai-test {class : The full class name or file path} {--type=Unit : The type of test (Unit/Integration)}';
    protected string $description = 'Generate a PHPUnit test for a class using AI';

    public function handle(): int
    {
        $this->title('AI Test Generator');

        $classInput = $this->argument('class');
        $testType = $this->option('type') ?: 'Unit';

        if (!class_exists($classInput) && !file_exists($classInput)) {
            $this->error("Target [{$classInput}] not found as a class or file.");
            return 1;
        }

        $code = "";
        $className = "";

        if (class_exists($classInput)) {
            $reflection = new ReflectionClass($classInput);
            $className = $reflection->getShortName();
            $code = file_get_contents($reflection->getFileName());
        } else {
            $code = file_get_contents($classInput);
            preg_match('/class\s+(\w+)/', $code, $matches);
            $className = $matches[1] ?? 'Target';
        }

        $this->info("Analyzing code and drafting tests for {$className}...");

        $prompt = <<<PROMPT
You are a testing expert for the Plugs PHP Framework.
Generate a PHPUnit {$testType} test for the following PHP code.

Guidelines:
1. Use PHPUnit\Framework\TestCase.
2. Use modern PHP 8.2+ features.
3. Include setup method if needed.
4. Mock dependencies where appropriate using Mockery or PHPUnit mocks.
5. The response MUST be ONLY the PHP code.

Source Code:
{$code}
PROMPT;

        try {
            $ai = Container::getInstance()->make(AIManager::class);
            $content = $ai->prompt($prompt);

            // Clean Markdown wrappers
            $content = str_replace(['```php', '```'], '', $content);
            $content = trim($content);

            $testClassName = "{$className}Test";
            $filename = "{$testClassName}.php";
            $path = base_path("tests/{$testType}/{$filename}");

            $this->task('Writing test file', function () use ($path, $content) {
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                Filesystem::put($path, $content);
            });

            $this->success("Test generated: tests/{$testType}/{$filename}");
            return 0;

        } catch (\Exception $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }
    }
}
