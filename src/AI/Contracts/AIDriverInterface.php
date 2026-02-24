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
     * Send a prompt to the AI model asynchronously.
     * Returns a promise-like object or a resolution callback.
     *
     * @param string $prompt
     * @param array $options
     */
    public function promptAsync(string $prompt, array $options = []);

    /**
     * Send a chat-style message history to the AI model.
     *
     * @param array $messages
     * @param array $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * Send a chat history to the AI model asynchronously.
     *
     * @param array $messages
     * @param array $options
     */
    public function chatAsync(array $messages, array $options = []);


    /**
     * Set the model to be used.
     *
     * @param string $model
     * @return self
     */
    public function withModel(string $model): self;

    /**
     * Generate an embedding for the given text.
     *
     * @param string $text
     * @return array
     */
    public function embed(string $text): array;
}
