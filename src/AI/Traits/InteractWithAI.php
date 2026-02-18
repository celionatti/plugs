<?php

declare(strict_types=1);

namespace Plugs\AI\Traits;

use Plugs\AI\Agent;

/**
 * Trait InteractWithAI
 * 
 * Provides AI-driven features to controllers.
 */
trait InteractWithAI
{
    /**
     * Send a quick prompt to the AI and get a response.
     */
    protected function aiPrompt(string $prompt, array $options = []): string
    {
        return ai()->prompt($prompt, [], $options);
    }

    /**
     * Get an AI agent instance with optional specific instructions.
     */
    protected function aiAgent(?string $instructions = null): Agent
    {
        $agent = app(Agent::class);

        if ($instructions) {
            $agent->setInstructions($instructions);
        }

        return $agent;
    }

    /**
     * Classify text into one of the given categories.
     */
    protected function aiClassify(string $text, array $categories = [], array $options = []): string
    {
        return ai()->classify($text, $categories, $options);
    }

    /**
     * Generate content for a field based on model context.
     */
    protected function aiGenerate(string $prompt, array $options = []): string
    {
        // Add controller name for context
        $context = "Controller: " . static::class;
        return ai()->prompt("Context: {$context}\n\nTask: {$prompt}", [], $options);
    }
}
