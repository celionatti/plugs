<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Env: Sync Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class EnvSyncCommand extends Command
{
    protected string $description = 'Sync .env with .env.example by adding missing keys';

    public function handle(): int
    {
        $envFile = BASE_PATH . '.env';
        $exampleFile = BASE_PATH . '.env.example';

        if (!Filesystem::exists($exampleFile)) {
            $this->error(".env.example file not found!");
            return self::FAILURE;
        }

        if (!Filesystem::exists($envFile)) {
            if ($this->confirm(".env file not found. Create from .env.example?", true)) {
                Filesystem::copy($exampleFile, $envFile);
                $this->success(".env file created from .env.example.");
                return self::SUCCESS;
            }
            return self::FAILURE;
        }

        $this->advancedHeader('Environment Sync', 'Synchronizing .env with .env.example');

        $exampleKeys = $this->parseEnvKeys($exampleFile);
        $envKeys = $this->parseEnvKeys($envFile);

        $missingKeys = array_diff(array_keys($exampleKeys), array_keys($envKeys));

        if (empty($missingKeys)) {
            $this->info("Your .env file is already in sync with .env.example.");
            return self::SUCCESS;
        }

        $this->warning("Found " . count($missingKeys) . " missing keys in your .env file.");
        $this->newLine();

        $addedCount = 0;
        foreach ($missingKeys as $key) {
            $defaultValue = $exampleKeys[$key];
            $value = $this->ask("Enter value for {$key}", $defaultValue);

            $this->appendToFile($envFile, "{$key}={$value}");
            $addedCount++;
        }

        $this->newLine();
        $this->success("Successfully added {$addedCount} keys to your .env file.");

        return self::SUCCESS;
    }

    protected function parseEnvKeys(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $keys = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $keys[trim($key)] = trim($value, '"\' ');
            }
        }

        return $keys;
    }

    protected function appendToFile(string $path, string $line): void
    {
        $content = Filesystem::get($path);
        
        // Ensure there's a newline at the end if not present
        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $content .= $line . "\n";
        Filesystem::put($path, $content);
    }
}
