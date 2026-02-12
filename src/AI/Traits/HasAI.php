<?php

declare(strict_types=1);

namespace Plugs\AI\Traits;

use Plugs\AI\AIManager;
use Plugs\Container\Container;

/**
 * Trait HasAI
 * 
 * Empowers models with AI-driven capabilities like content generation and summarization.
 */
trait HasAI
{
    /**
     * Generate content for a specific model field based on a prompt.
     * 
     * @param string $attribute The field to populate
     * @param string $prompt The instructions for the AI
     * @param array $options Driver-specific options
     * @return static
     */
    public function generate(string $attribute, string $prompt, array $options = []): self
    {
        $ai = Container::getInstance()->make(AIManager::class);

        // Provide model context to the AI
        $context = $this->getAiContext();
        $fullPrompt = "Model: " . static::class . "\nContext: {$context}\n\nTask: {$prompt}";

        $response = $ai->prompt($fullPrompt, $options);

        $this->setAttribute($attribute, $response);

        return $this;
    }

    /**
     * Generate a summary of the model instance data.
     * 
     * @param int $length Approximate length in words
     * @return string
     */
    public function summarize(int $length = 50): string
    {
        $ai = Container::getInstance()->make(AIManager::class);
        $data = json_encode($this->toArray(), JSON_PRETTY_PRINT);

        $prompt = "Summarize the following model data in about {$length} words:\n\n{$data}";

        return $ai->prompt($prompt);
    }

    /**
     * Predict or suggest values for missing fields.
     * 
     * @param array $fields List of fields to predict
     * @return array
     */
    public function predict(array $fields): array
    {
        $ai = Container::getInstance()->make(AIManager::class);
        $data = json_encode($this->toArray(), JSON_PRETTY_PRINT);
        $targetFields = implode(', ', $fields);

        $prompt = "Based on this model data: {$data}\nSuggest values for these fields: {$targetFields}. Return ONLY a JSON object of field => value mapping.";

        $response = $ai->prompt($prompt);

        // Basic JSON extraction
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return json_decode($matches[0], true) ?: [];
        }

        return [];
    }

    /**
     * Get a string representation of the model's current state for AI context.
     */
    protected function getAiContext(): string
    {
        return json_encode($this->getAttributes());
    }
}
