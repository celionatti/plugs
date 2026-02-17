<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use GuzzleHttp\Client;
use RuntimeException;

class OpenRouterDriver extends AIBaseDriver
{
    protected Client $client;
    protected string $baseUrl = 'https://openrouter.ai/api/v1/';

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url', 'http://plugs.local'),
                'X-Title' => config('app.name', 'Plugs Framework'),
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function prompt(string $prompt, array $options = []): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): string
    {
        try {
            $response = $this->client->post('chat/completions', [
                'json' => array_merge([
                    'model' => $this->model,
                    'messages' => $messages,
                ], $options),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("OpenRouter API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function embed(string $text): array
    {
        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => $this->config['embedding_model'] ?? 'openai/text-embedding-3-small',
                    'input' => $text,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['data'][0]['embedding'] ?? [];
        } catch (\Exception $e) {
            // Fallback to base error or empty array if embeddings endpoint is not supported by model
            return [];
        }
    }
}
