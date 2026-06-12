<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class KeyGenerateCommand extends Command
{
    protected string $description = 'Generate a secure application encryption key';

    protected function defineOptions(): array
    {
        return [
            '--show' => 'Only display the key instead of modifying the files',
            '--force' => 'Force the operation to run when in production',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Application Key Generator');

        $key = 'base64:' . base64_encode(random_bytes(32));

        if ($this->hasOption('show')) {
            $this->info("Generated key: " . $key);
            return self::SUCCESS;
        }

        $envFile = BASE_PATH . '.env';

        if (!Filesystem::exists($envFile)) {
            $exampleFile = BASE_PATH . '.env.example';
            if (Filesystem::exists($exampleFile)) {
                if ($this->confirm(".env file not found. Copy from .env.example?", true)) {
                    Filesystem::copy($exampleFile, $envFile);
                    $this->info(".env file created from .env.example.");
                } else {
                    $this->error(".env file does not exist. Cannot write APP_KEY.");
                    return self::FAILURE;
                }
            } else {
                $this->error(".env file does not exist. Cannot write APP_KEY.");
                return self::FAILURE;
            }
        }

        $content = Filesystem::get($envFile);

        // Check if APP_KEY already exists and is not empty
        if (preg_match('/^APP_KEY=.+$/m', $content) && !$this->hasOption('force')) {
            if ($this->isProduction()) {
                if (!$this->confirm('Application is in production. Generate a new key? This will break existing encrypted data!', false)) {
                    $this->error('Operation cancelled.');
                    return self::FAILURE;
                }
            } else {
                if (!$this->confirm('Application key is already set. Do you want to generate a new one?', false)) {
                    $this->error('Operation cancelled.');
                    return self::FAILURE;
                }
            }
        }

        // Replace or append the APP_KEY
        if (preg_match('/^APP_KEY=.*$/m', $content)) {
            $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content);
        } else {
            // Ensure content ends with a newline
            if (!str_ends_with($content, "\n")) {
                $content .= "\n";
            }
            $content .= 'APP_KEY=' . $key . "\n";
        }

        Filesystem::put($envFile, $content);

        $this->success("Application key set successfully.");
        $this->note("Key: " . $key);

        $this->checkpoint('finished');
        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return self::SUCCESS;
    }
}
