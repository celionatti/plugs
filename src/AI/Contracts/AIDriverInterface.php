<?php

declare(strict_types=1);

namespace Plugs\AI\Contracts;

interface AIDriverInterface
{
    /**
     * Send a prompt to the AI model and get a response.
     *
     * @param string $prompt
     * @param array $options
     * @return string
     */
    public function prompt(string $prompt, array $options = []): string;

    /**
     * Send a chat-style message history to the AI model.
     *
     * @param array $messages
     * @param array $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Set the model to be used.
     *
     * @param string $model
     * @return self
     */
    public function withModel(string $model): self;
}
