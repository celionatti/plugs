<?php

declare(strict_types=1);

namespace Plugs\AI;

use Plugs\Container\Container;
use Plugs\AI\Contracts\AIDriverInterface;

/**
 * Class Agent
 * 
 * A high-level AI component that manages state, context, and task decomposition.
 */
class Agent
{
    protected AIDriverInterface $driver;
    protected array $history = [];
    protected array $context = [];
    protected string $systemPrompt = "You are an autonomous development agent for the Plugs PHP Framework.";

    public function __construct(AIDriverInterface $driver)
    {
        $this->driver = $driver;
        $this->initializeContext();
    }

    /**
     * Set the system instructions for the agent.
     */
    public function setInstructions(string $instructions): self
    {
        $this->systemPrompt = $instructions;
        return $this;
    }

    /**
     * Process a user request and return a structured response with possible "actions".
     */
    public function think(string $request): string
    {
        $this->history[] = ['role' => 'user', 'content' => $request];

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt]],
            $this->history
        );

        $response = $this->driver->chat($messages);

        $this->history[] = ['role' => 'assistant', 'content' => $response];

        return $response;
    }

    /**
     * Break down a complex task into actionable steps.
     */
    public function decompose(string $task): array
    {
        $prompt = <<<PROMPT
Decompose the following complex task into a sequence of logical developer steps.
For each step, identify if it's a CLI command or a manual code edit.

Task: "{$task}"

Return a JSON array of steps:
[
  {"step": "Create a migration", "type": "command", "value": "theplugs make:migration ..."},
  {"step": "Define model relationships", "type": "edit", "value": "Modify App\Models\User.php"}
]
PROMPT;

        $response = $this->think($prompt);

        // Basic JSON extraction
        if (preg_match('/\[[\s\S]*\]/', $response, $matches)) {
            return json_decode($matches[0], true) ?: [];
        }

        return [];
    }

    /**
     * Get the current conversation history.
     */
    public function getHistory(): array
    {
        return $this->history;
    }

    /**
     * Clear history and context.
     */
    public function reset(): void
    {
        $this->history = [];
        $this->initializeContext();
    }

    protected function initializeContext(): void
    {
        // Inject framework awareness
        $this->context = [
            'framework' => 'Plugs PHP',
            'base_path' => base_path(),
            'php_version' => PHP_VERSION,
        ];
    }
}
