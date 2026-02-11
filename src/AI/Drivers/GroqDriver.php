<?php

declare(strict_types=1);

namespace Plugs\AI\Drivers;

use GuzzleHttp\Client;
use RuntimeException;

class GroqDriver extends AIBaseDriver
{
    protected Client $client;
    protected string $baseUrl = 'https://api.groq.com/openai/v1/';

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getConfig('api_key'),
                'Content-Type' => 'application/json',
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
            throw new RuntimeException("Groq API Error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
