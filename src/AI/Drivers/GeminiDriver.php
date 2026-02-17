<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use GuzzleHttp\Client;
use RuntimeException;

class GeminiDriver extends AIBaseDriver
{
    protected Client $client;
    protected string $apiVersion;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->apiVersion = $config['version'] ?? (str_contains($this->model, 'preview') ? 'v1beta' : 'v1');

        $this->client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->getConfig('api_key'),
            ],
        ]);
    }

    /**
     * Get the base URL for the Gemini API.
     */
    protected function getUrl(string $action): string
    {
        return "https://generativelanguage.googleapis.com/{$this->apiVersion}/models/{$this->model}:{$action}";
    }

    /**
     * @inheritDoc
     */
    public function prompt(string $prompt, array $options = []): string
    {
        try {
            $response = $this->client->post($this->getUrl('generateContent'), [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Gemini API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function chat(array $messages, array $options = []): string
    {
        $contents = array_map(function ($message) {
            return [
                'role' => $message['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $message['content']]]
            ];
        }, $messages);

        try {
            $response = $this->client->post($this->getUrl('generateContent'), [
                'json' => [
                    'contents' => $contents
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } catch (\Exception $e) {
            throw new RuntimeException("Gemini API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function embed(string $text): array
    {
        try {
            $response = $this->client->post($this->getUrl('embedContent'), [
                'json' => [
                    'model' => 'models/' . ($this->config['embedding_model'] ?? 'text-embedding-004'),
                    'content' => ['parts' => [['text' => $text]]]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['embedding']['values'] ?? [];
        } catch (\Exception $e) {
            throw new RuntimeException("Gemini Embedding Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
