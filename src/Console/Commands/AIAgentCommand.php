<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\AI\AIManager;
use Plugs\AI\Agent;
use Plugs\Container\Container;
use Throwable;

class AIAgentCommand extends Command
{
    protected string $signature = 'ai:agent {goal? : What do you want to achieve?}';
    protected string $description = 'Start an autonomous AI agent session to help build features';

    public function handle(): int
    {
        $this->advancedHeader('Plugs AI Agent', 'Autonomous Development Partner');

        $goal = $this->argument('goal');

        /** @var AIManager $ai */
        $ai = Container::getInstance()->make(AIManager::class);
        $agent = new Agent($ai->driver());

        if ($goal) {
            $this->processGoal($agent, $goal);
        }

        $this->startInteractiveSession($agent);

        return 0;
    }

    protected function processGoal(Agent $agent, string $goal): void
    {
        $this->info("Goal: " . $goal);
        $this->newLine();

        $steps = $this->loading('Decomposing task into steps...', function () use ($agent, $goal) {
            return $agent->decompose($goal);
        });

        if (empty($steps)) {
            $this->warning("Agent couldn't break down the task. Switching to conversational mode.");
            return;
        }

        $this->info("Proposed Plan:");
        $displaySteps = array_map(fn($s) => "[{$s['type']}] {$s['step']}: {$s['value']}", $steps);
        $this->bulletList($displaySteps);

        $this->newLine();
        if ($this->confirm('Should I help you execute these steps?', true)) {
            $this->info("Great! I'll guide you through each step. I can't run the commands for you yet, but I'll provide exactly what you need.");
        }
    }

    protected function startInteractiveSession(Agent $agent): void
    {
        $this->info("Agent is online. Type 'exit' to end or 'reset' to clear context.");
        $this->divider();

        while (true) {
            $input = $this->ask('Agentic You');

            if (in_array(strtolower($input), ['exit', 'quit'])) {
                $this->success('Mission accomplished. Goodbye!');
                break;
            }

            if (strtolower($input) === 'reset') {
                $agent->reset();
                $this->info('Context cleared.');
                continue;
            }

            if (empty(trim($input))) {
                continue;
            }

            $response = $this->loading('Agent is thinking...', function () use ($agent, $input) {
                return $agent->think($input);
            });

            $this->newLine();
            $this->panel($response, 'Agent Response');
            $this->newLine();
        }
    }
}
