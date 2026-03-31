<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| AI Scaffold Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;
use Throwable;

class AiScaffoldCommand extends Command
{
    protected string $description = 'Generate application structure using AI';

    protected function defineArguments(): array
    {
        return [
            'prompt' => 'What would you like to build? (e.g., "A blog with categories")',
        ];
    }

    public function handle(): int
    {
        $prompt = $this->argument('prompt');

        if (!$prompt) {
            $prompt = $this->ask('What would you like to build?');
        }

        $this->advancedHeader('AI Scaffolder', "Planning: {$prompt}");

        $this->info("Analyzing your request and planning the architecture...");

        try {
            $planJson = $this->generatePlan($prompt);
            $plan = json_decode($planJson, true);

            if (!$plan || !isset($plan['commands'])) {
                $this->error("Failed to generate a valid plan. Response was: " . $planJson);
                return self::FAILURE;
            }

            $this->section('Proposed Plan');
            
            $cmdList = [];
            foreach ($plan['commands'] as $cmd) {
                $argsStr = implode(' ', $cmd['arguments']);
                $cmdList[] = $this->badge($cmd['command'], 'accent') . " " . \Plugs\Console\Support\Output::SKY . $argsStr . \Plugs\Console\Support\Output::RESET;
            }
            $this->output->numberedList($cmdList);
            $this->newLine();

            if ($this->confirm("Would you like to execute this plan?", true)) {
                $this->executePlan($plan['commands']);
                $this->newLine();
                $this->resultSummary([
                    'Tasks Executed' => count($plan['commands'])
                ], $this->elapsed());
            } else {
                $this->info("Plan aborted.");
            }

        } catch (Throwable $e) {
            $this->error("AI Error: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function generatePlan(string $userPrompt): string
    {
        $systemPrompt = <<<EOD
You are an expert architect for the Plugs PHP Framework.
The user wants to: "{$userPrompt}"

Generate a list of framework commands to execute to bootstrap this feature.
Available commands:
- make:model {Name} [-m]
- make:controller {Name}Controller [--resource]
- make:crud {Name}
- make:migration {Name}
- make:request {Name}Request
- make:service {Name}Service
- make:middleware {Name}

Return ONLY a JSON object with a "commands" key containing an array of objects.
Each object must have "command" (string) and "arguments" (array of strings).

Example:
{
  "commands": [
    { "command": "make:crud", "arguments": ["Post"] },
    { "command": "make:model", "arguments": ["Category", "-m"] }
  ]
}
EOD;

        return ai()->prompt($systemPrompt);
    }

    protected function executePlan(array $commands): void
    {
        $total = count($commands);
        foreach ($commands as $index => $cmd) {
            $step = $index + 1;
            $this->step($step, $total, "Running: {$cmd['command']} " . implode(' ', $cmd['arguments']));
            
            try {
                // Determine if we need to split arguments
                $this->call($cmd['command'], $cmd['arguments']);
            } catch (Throwable $e) {
                $this->error("  FAILED: " . $e->getMessage());
            }
        }
    }
}
