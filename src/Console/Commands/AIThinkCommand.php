<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\AI\AIManager;
use Plugs\Container\Container;
use Throwable;

class AIThinkCommand extends Command
{
    protected string $signature = 'ai:think {problem : The problem or feature request to analyze}';
    protected string $description = 'Analyze a complex problem using Chain of Thought reasoning';

    public function handle(): int
    {
        $this->title('AI Reasoning Engine');

        $problem = $this->argument('problem');

        /** @var AIManager $ai */
        $ai = Container::getInstance()->make(AIManager::class);

        $prompt = <<<PROMPT
You are a lead architect for the Plugs PHP Framework.
Perform a "Chain of Thought" analysis on the following problem.

Problem: "{$problem}"

Structure your analysis as follows:
1. Analysis of the current state.
2. Identification of core challenges.
3. Multi-step logic for the solution.
4. Final technical recommendation.

Think step-by-step.
PROMPT;

        try {
            $response = $this->loading('Reasoning...', function () use ($ai, $prompt) {
                return $ai->prompt($prompt);
            });

            $this->newLine();
            $this->panel($response, 'Architectural Analysis');
            $this->newLine();

            return 0;

        } catch (Throwable $e) {
            $this->error("AI Error: " . $e->getMessage());
            return 1;
        }
    }
}
