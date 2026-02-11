<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\AI\AIManager;
use Throwable;

class AIChatCommand extends Command
{
    protected string $description = 'Interact with the configured AI driver';

    protected function defineArguments(): array
    {
        return [
            'prompt' => 'The initial prompt to send to the AI',
        ];
    }

    protected function defineOptions(): array
    {
        return [
            'driver' => 'Specify the AI driver to use (openai, anthropic, gemini)',
            'model' => 'Specify the model to use',
            'interactive' => 'Start an interactive chat session',
        ];
    }

    public function handle(): int
    {
        $this->banner('Plugs AI Chat');

        $driverName = $this->option('driver');
        $model = $this->option('model');
        $prompt = $this->argument('prompt');
        $interactive = $this->hasOption('interactive');

        /** @var AIManager $ai */
        $ai = app('ai');

        try {
            $driver = $ai->driver($driverName);
            if ($model) {
                $driver->withModel($model);
            }

            if ($prompt) {
                $this->info("User: " . $prompt);
                $this->newLine();

                $response = $this->task('Thinking...', function () use ($driver, $prompt) {
                    return $driver->prompt($prompt);
                });

                $this->newLine();
                $this->success("AI: " . $response);
                $this->newLine();

                if (!$interactive && !$this->confirm('Continue in interactive mode?', false)) {
                    return 0;
                }
                $interactive = true;
            }

            if ($interactive || !$prompt) {
                $this->startInteractiveSession($driver);
            }

        } catch (Throwable $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    protected function startInteractiveSession($driver): void
    {
        $this->info("Starting interactive session. Type 'exit' or 'quit' to end.");
        $this->divider();

        $messages = [];

        while (true) {
            $input = $this->ask('You');

            if (in_array(strtolower($input), ['exit', 'quit'])) {
                $this->info('Goodbye!');
                break;
            }

            if (empty(trim($input))) {
                continue;
            }

            $messages[] = ['role' => 'user', 'content' => $input];

            $response = $this->task('AI is thinking...', function () use ($driver, $messages) {
                return $driver->chat($messages);
            });

            $messages[] = ['role' => 'assistant', 'content' => $response];

            $this->newLine();
            $this->success("AI: " . $response);
            $this->newLine();
            $this->divider();
        }
    }
}
